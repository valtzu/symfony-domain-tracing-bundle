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
     * @return \Generator<array{0: string, 1: mixed}> pairs of event name and data
     */
    public function follow(string $id, float $timeoutSeconds = 600): \Generator
    {
        $offset = 0;
        $request = null;
        $pending = [];
        $explanation = '';
        $ended = false;
        $lastActivity = microtime(true);

        while (true) {
            $read = $this->store->read($id, $offset);
            foreach ($read['records'] ?? [] as $record) {
                $lastActivity = microtime(true);
                match ($record['type']) {
                    'request' => $request = $record,
                    'step' => $pending[] = $record,
                    'end' => $ended = $record,
                };
                yield [$record['type'], $record];
            }
            $offset = $read['offset'] ?? 0;

            $idle = microtime(true) - $lastActivity;
            if ($pending && ($ended || \count($pending) >= self::MAX_STEPS_PER_PART || $idle >= self::IDLE_SECONDS_BEFORE_EXPLAINING)) {
                $part = '';
                foreach ($this->explain($request, $explanation, $pending, false !== $ended) as $text) {
                    $part .= $text;
                    yield ['text', $text];
                }
                $explanation .= ('' === $explanation ? '' : "\n\n").trim($part);
                $pending = [];
                yield ['part', null];
                continue;
            }

            if ($ended || $idle > $timeoutSeconds) {
                return;
            }

            usleep(self::POLL_MICROSECONDS);
        }
    }

    /**
     * @param list<array> $steps
     *
     * @return iterable<string>
     */
    private function explain(?array $request, string $explanation, array $steps, bool $requestEnded): iterable
    {
        $prompt = \sprintf("Request: %s %s\n\n", $request['method'] ?? '?', $request['uri'] ?? '?');
        $prompt .= "Explanation so far:\n".('' === $explanation ? '(nothing yet)' : $explanation)."\n\n";
        $prompt .= "New executed lines:\n".$this->formatSteps($steps)."\n\n";
        $prompt .= $requestEnded ? 'The request has finished.' : 'The request is still running. More lines will follow.';

        $result = $this->agent->call(new MessageBag(Message::ofUser($prompt)), ['stream' => true]);

        foreach ($result->getContent() as $delta) {
            if ($delta instanceof TextDelta) {
                yield $delta->getText();
            }
        }
    }

    /**
     * @param list<array> $steps
     */
    private function formatSteps(array $steps): string
    {
        $baseDepth = min(array_column($steps, 'depth'));
        $lines = [];
        $function = null;
        foreach ($steps as $step) {
            $indent = str_repeat('  ', $step['depth'] - $baseDepth);
            if ($step['function'] !== $function) {
                $function = $step['function'];
                $lines[] = $indent.'in '.$function.'():';
            }
            $lines[] = \sprintf('%s  %s:%d  %s', $indent, $step['file'], $step['line'], $step['code']);
        }

        return implode("\n", $lines);
    }
}
