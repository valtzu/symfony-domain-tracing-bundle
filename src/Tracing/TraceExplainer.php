<?php

namespace App\Tracing;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Follows a trace while it is recorded and explains it part by part, so the explanation keeps up with a request that
 * runs slowly, for example when someone steps through it with a debugger.
 */
final readonly class TraceExplainer
{
    private const MAX_STEPS_PER_PART = 80;
    private const IDLE_SECONDS_BEFORE_EXPLAINING = 1.0;
    private const POLL_MICROSECONDS = 100_000;

    public function __construct(
        #[Autowire(service: 'ai.agent.trace_explainer')]
        private AgentInterface $agent,
        private TraceStore $store,
    ) {
    }

    /**
     * Follows the trace that the given request started, including the requests it made to other services.
     *
     * @return \Generator<array{0: string, 1: mixed}> pairs of event name and data
     */
    public function follow(string $rootSpanId, float $timeoutSeconds = 600): \Generator
    {
        $offsets = [$rootSpanId => 0];
        $spans = [];
        $ended = [];
        $traceId = null;
        $pending = [];
        $explanation = '';
        $lastActivity = microtime(true);

        while (true) {
            $records = [];
            do {
                foreach (null === $traceId ? [] : $this->store->spansOf($traceId) as $spanId) {
                    $offsets[$spanId] ??= 0;
                }
                $knownSpans = \count($offsets);

                foreach ($offsets as $spanId => $offset) {
                    $read = $this->store->read($spanId, $offset);
                    foreach ($read['records'] ?? [] as $record) {
                        $records[] = ['span' => $spanId] + $record;
                        $traceId ??= 'request' === $record['type'] && $spanId === $rootSpanId ? $record['traceId'] : null;
                    }
                    $offsets[$spanId] = $read['offset'] ?? $offset;
                }
            } while (null !== $traceId && \count($this->store->spansOf($traceId)) > $knownSpans);
            usort($records, static fn (array $a, array $b) => $a['at'] <=> $b['at']);

            foreach ($records as $record) {
                $lastActivity = microtime(true);
                $isRoot = $record['span'] === $rootSpanId;
                switch ($record['type']) {
                    case 'request':
                        $spans[$record['span']] = $record;
                        if (!$isRoot) {
                            $pending[] = $record;
                        }
                        yield [$isRoot ? 'request' : 'span', $record];
                        break;
                    case 'step':
                        $record['service'] = $spans[$record['span']]['service'] ?? null;
                        $pending[] = $record;
                        yield ['step', $record];
                        break;
                    case 'end':
                        $ended[$record['span']] = true;
                        yield [$isRoot ? 'end' : 'spanEnd', $record];
                        break;
                }
            }

            $traceEnded = isset($ended[$rootSpanId]) && \count($ended) === \count($offsets);
            $idle = microtime(true) - $lastActivity;
            if ($pending && ($traceEnded || \count($pending) >= self::MAX_STEPS_PER_PART || $idle >= self::IDLE_SECONDS_BEFORE_EXPLAINING)) {
                $part = '';
                foreach ($this->explain($spans[$rootSpanId] ?? null, $spans, $explanation, $pending, $traceEnded) as $text) {
                    $part .= $text;
                    yield ['text', $text];
                }
                $explanation .= ('' === $explanation ? '' : "\n\n").trim($part);
                $pending = [];
                yield ['part', null];
                continue;
            }

            if ($traceEnded || $idle > $timeoutSeconds) {
                return;
            }

            usleep(self::POLL_MICROSECONDS);
        }
    }

    /**
     * @param array<string, array> $spans request records by span
     * @param list<array>          $records
     *
     * @return iterable<string>
     */
    private function explain(?array $request, array $spans, string $explanation, array $records, bool $traceEnded): iterable
    {
        $prompt = \sprintf("Request: %s %s to the %s service\n\n", $request['method'] ?? '?', $request['uri'] ?? '?', $request['service'] ?? '?');
        $prompt .= "Explanation so far:\n".('' === $explanation ? '(nothing yet)' : $explanation)."\n\n";
        $prompt .= "New executed lines:\n".$this->formatRecords($records, $spans)."\n\n";
        $prompt .= $traceEnded ? 'The request has finished.' : 'The request is still running. More lines will follow.';

        $result = $this->agent->call(new MessageBag(Message::ofUser($prompt)), ['stream' => true]);

        foreach ($result->getContent() as $delta) {
            if ($delta instanceof TextDelta) {
                yield $delta->getText();
            }
        }
    }

    /**
     * @param list<array>          $records
     * @param array<string, array> $spans
     */
    private function formatRecords(array $records, array $spans): string
    {
        $baseDepths = [];
        foreach ($records as $record) {
            if ('step' === $record['type']) {
                $baseDepths[$record['span']] = min($baseDepths[$record['span']] ?? \PHP_INT_MAX, $record['depth']);
            }
        }

        $lines = [];
        $function = null;
        foreach ($records as $record) {
            $service = $spans[$record['span']]['service'] ?? '?';
            if ('request' === $record['type']) {
                $caller = array_find($spans, static fn (array $span) => $span['spanId'] === $record['parentSpanId']);
                $lines[] = \sprintf('[%s] received %s %s from the %s service:', $service, $record['method'], $record['uri'], $caller['service'] ?? 'calling');
                $function = null;
                continue;
            }

            $indent = str_repeat('  ', $record['depth'] - $baseDepths[$record['span']]);
            if ($record['function'] !== $function) {
                $function = $record['function'];
                $lines[] = \sprintf('[%s] %sin %s():', $service, $indent, $function);
            }
            $lines[] = \sprintf('[%s] %s  %s:%d  %s', $service, $indent, $record['file'], $record['line'], $record['code']);
        }

        return implode("\n", $lines);
    }
}
