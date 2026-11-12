<?php

namespace App\Tracing;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Follows each new trace while it is recorded: builds its spans, names the operations, describes what happened in
 * them, and exports the trace to the OpenTelemetry collector when it ends. The results are also written next to the
 * trace, so that a page can show them while they come.
 */
#[AsCommand('app:process-traces', 'Name, describe and export the recorded traces')]
final readonly class ProcessTracesCommand
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
        private OtlpExporter $exporter,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->writeln('Waiting for traces');

        while (true) {
            foreach ($this->store->unprocessedRoots() as $rootId) {
                $io->writeln(\sprintf('Processing %s', $rootId));
                try {
                    $this->process($rootId);
                } catch (\Throwable $e) {
                    $io->error($e->getMessage());
                    $this->store->addNarration($rootId, ['type' => 'failure', 'message' => $e->getMessage()]);
                    $this->store->addNarration($rootId, ['type' => 'end', 'traceId' => null]);
                }
            }
            usleep(100_000);
        }
    }

    private function process(string $rootId): void
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
                foreach ($pending as $step) {
                    if (!isset($described[$step['function']]) && null !== $function = $builder->function($step['function'])) {
                        $code[$step['function']] = $described[$step['function']] = $function['code'];
                    }
                }
                foreach ($this->narrator->narrate($pending, $spans, $builder->requestSpanOf($rootId), $eventsSoFar, $ended, $code) as $event) {
                    $events[] = $event;
                    $this->store->addNarration($rootId, ['type' => 'event'] + $event);
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
            usleep(100_000);
        }

        $names = $this->nameOperations($rootId, $builder, $names);
        $this->exporter->export(array_values(self::withNames($builder->spans(), $names)), $events);
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
     */
    private static function rootSpanId(array $spans, string $rootId): string
    {
        foreach ($spans as $span) {
            if ('server' === $span['kind'] && $span['request'] === $rootId) {
                return $span['id'];
            }
        }

        return '';
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
