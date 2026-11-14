<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Valtzu\DomainTracingBundle\Ai\OperationNamer;
use Valtzu\DomainTracingBundle\Ai\TraceNarrator;
use Valtzu\DomainTracingBundle\Storage\BlobStore;
use Valtzu\DomainTracingBundle\Storage\TraceStore;

/**
 * Follows a trace while it is recorded: builds its spans, names the operations and describes what happened in them.
 * The results are written next to the trace, so that a page can show them while they come. One process describes a
 * trace: the one that holds its lock.
 */
final readonly class TraceDescriber
{
    private const MAX_STEPS_PER_PART = 80;
    private const IDLE_SECONDS_BEFORE_DESCRIBING = 1.0;
    private const TIMEOUT_SECONDS = 30;
    private const SECONDS_TO_WAIT_FOR_THE_FIRST_RECORD = 10;

    public function __construct(
        private TraceStore $store,
        private BlobStore $blobs,
        private OperationNamer $namer,
        private TraceNarrator $narrator,
    ) {
    }

    /**
     * Describes the trace that the given span started, unless another process does it or did it already. The generator
     * gives control back to the caller between the steps of the work, so that the caller can do other work meanwhile.
     */
    public function describe(string $rootId): \Generator
    {
        if (!$this->store->has($rootId) || $this->store->isDescribed($rootId) || null === $lock = $this->store->lockDescription($rootId)) {
            return;
        }

        try {
            $this->store->clearNarration($rootId);
            yield from $this->process($rootId);
        } catch (\Throwable $e) {
            $this->store->addNarration($rootId, ['type' => 'failure', 'message' => $e->getMessage()]);
            $this->store->addNarration($rootId, ['type' => 'end', 'traceId' => null]);
        } finally {
            fclose($lock);
        }
    }

    private function process(string $rootId): \Generator
    {
        $this->store->addNarration($rootId, ['type' => 'start']);
        $follower = new TraceFollower($this->store, $this->blobs, $rootId);
        $builder = new SpanBuilder();
        $names = [];
        $described = [];
        $events = [];
        $pending = [];
        $lastActivity = $startedAt = microtime(true);
        $seenRecords = false;

        while (true) {
            foreach ($follower->poll() as $record) {
                $lastActivity = microtime(true);
                $seenRecords = true;
                $builder->add($record);
                if ('step' === $record['type'] && null !== $span = $builder->currentSpanOf($record['request'])) {
                    $pending[] = ['span' => $span] + $record;
                }
            }

            $ended = $follower->hasEnded();
            $idle = microtime(true) - $lastActivity;
            if ($pending && ($ended || \count($pending) >= self::MAX_STEPS_PER_PART || $idle >= self::IDLE_SECONDS_BEFORE_DESCRIBING)) {
                $names = $this->nameOperations($rootId, $builder, $names);
                $spans = self::withNames($builder->spans(), $names);
                $eventsSoFar = array_map(static fn (array $event) => \sprintf('[%s] %s', $spans[$event['span']]['name'], $event['text']), $events);
                $code = [];
                $functions = array_unique([...array_column($pending, 'function'), ...array_keys($described)]);
                foreach ($functions as $name) {
                    $executedLines = self::executedLines($spans, $name);
                    if (\array_key_exists($name, $described) && ($described[$name] || null === $executedLines)) {
                        continue;
                    }
                    if (null !== $function = $builder->function($name)) {
                        $code[$name] = self::markExecutedLines($function, $executedLines ?? []);
                        $described[$name] = null !== $executedLines;
                    }
                }
                foreach ($this->narrator->narrate($pending, $spans, $builder->requestSpanOf($rootId), $eventsSoFar, $ended, $code) as $event) {
                    $events[] = $event;
                    $this->store->addNarration($rootId, ['type' => 'event'] + $event);
                    yield;
                }
                $pending = [];
                continue;
            }

            if ($ended || $idle > self::TIMEOUT_SECONDS) {
                break;
            }
            if (!$seenRecords && microtime(true) - $startedAt > self::SECONDS_TO_WAIT_FOR_THE_FIRST_RECORD) {
                throw new \RuntimeException('The trace has no records.');
            }
            yield;
        }

        $this->nameOperations($rootId, $builder, $names);
        $this->store->addNarration($rootId, ['type' => 'end', 'traceId' => $follower->traceId()]);
    }

    /**
     * @param array<string, string> $names names by function
     *
     * @return array<string, string>
     */
    private function nameOperations(string $rootId, SpanBuilder $builder, array $names): array
    {
        $operations = [];
        foreach ($builder->spans() as $span) {
            if (null !== $span['function'] && !isset($names[$span['function']]) && null !== $function = $builder->function($span['function'])) {
                $operations[$span['function']] = ['service' => $span['service'], 'function' => $span['function'], 'code' => $function['code']];
            }
        }

        foreach ($operations ? $this->namer->name(array_values($operations)) : [] as $function => $name) {
            $names[$function] = $name;
            $this->store->addNarration($rootId, ['type' => 'name', 'function' => $function, 'name' => $name]);
        }

        return $names;
    }

    /**
     * @param array<string, array> $spans
     *
     * @return list<int>|null the lines of the function that ran, from all its calls, or null when they are not known yet
     */
    private static function executedLines(array $spans, string $function): ?array
    {
        $lines = null;
        foreach ($spans as $span) {
            if ($span['function'] === $function && isset($span['executedLines'])) {
                $lines = array_merge($lines ?? [], $span['executedLines']);
            }
        }

        return null === $lines ? null : array_values(array_unique($lines));
    }

    /**
     * @param list<int> $executedLines
     */
    private static function markExecutedLines(array $function, array $executedLines): string
    {
        $marked = [];
        foreach (explode("\n", $function['code']) as $index => $line) {
            $marked[] = (\in_array($function['line'] + $index, $executedLines, true) ? '▶ ' : '  ').$line;
        }

        return implode("\n", $marked);
    }

    /**
     * @param array<string, array>  $spans
     * @param array<string, string> $names
     *
     * @return array<string, array>
     */
    private static function withNames(array $spans, array $names): array
    {
        foreach ($spans as &$span) {
            $span['name'] = $names[$span['function'] ?? ''] ?? $span['name'];
        }

        return $spans;
    }
}
