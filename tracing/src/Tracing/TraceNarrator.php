<?php

namespace App\Tracing;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Describes what happened inside the operations of a trace, in business terms, as events on their spans. It works on
 * the trace part by part while it is recorded, and each event refers to the line it describes.
 */
final readonly class TraceNarrator
{
    public function __construct(
        #[Autowire(service: 'ai.agent.trace_narrator')]
        private AgentInterface $agent,
    ) {
    }

    /**
     * @param list<array>          $steps step records, each with the "span" it belongs to
     * @param array<string, array> $spans
     * @param list<string>         $eventsSoFar
     *
     * @return iterable<array{span: string, at: float, text: string, error: bool}>
     */
    public function narrate(array $steps, array $spans, ?string $rootSpanId, array $eventsSoFar, bool $traceEnded): iterable
    {
        $prompt = "Events so far:\n".($eventsSoFar ? implode("\n", $eventsSoFar) : '(none yet)')."\n\nNew executed lines:\n";
        $operation = null;
        foreach ($steps as $index => $step) {
            $span = $spans[$step['span']];
            if ($span['id'] !== $operation) {
                $operation = $span['id'];
                $prompt .= \sprintf("[%s] in operation \"%s\" (%s)%s%s:\n", $span['service'], $span['name'], $span['function'] ?? $span['uri'],
                    isset($span['arguments']) ? ', called with '.json_encode($span['arguments'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : '',
                    \array_key_exists('returnValue', $span) ? ', returned '.json_encode($span['returnValue'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : '',
                );
            }
            $prompt .= \sprintf("  %d: %s\n", $index, $step['code']);
        }
        $prompt .= "\n".($traceEnded ? 'The request has finished.' : 'The request is still running. More lines will follow.');

        $buffer = '';
        foreach ($this->agent->call(new MessageBag(Message::ofUser($prompt)), ['stream' => true])->getContent() as $delta) {
            if (!$delta instanceof TextDelta) {
                continue;
            }
            $buffer .= $delta->getText();
            while (false !== $newline = strpos($buffer, "\n")) {
                yield from $this->toEvent(substr($buffer, 0, $newline), $steps, $rootSpanId);
                $buffer = substr($buffer, $newline + 1);
            }
        }
        yield from $this->toEvent($buffer, $steps, $rootSpanId);
    }

    /**
     * @return iterable<array{span: string, at: float, text: string, error: bool}>
     */
    private function toEvent(string $line, array $steps, ?string $rootSpanId): iterable
    {
        $event = json_decode(trim($line), true);
        $outcome = true === ($event['outcome'] ?? false) && null !== $rootSpanId;
        $step = $outcome ? end($steps) : $steps[$event['line'] ?? -1] ?? null;
        if ($step && \is_string($event['text'] ?? null) && '' !== trim($event['text'])) {
            yield ['span' => $outcome ? $rootSpanId : $step['span'], 'at' => $step['at'], 'text' => trim($event['text']), 'error' => true === ($event['error'] ?? false)];
        }
    }
}
