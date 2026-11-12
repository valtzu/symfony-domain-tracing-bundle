<?php

namespace App\Tracing;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Describes what happened inside the operations of a trace, in business terms, as events on their spans. It works on
 * the trace part by part while it is recorded, and each event refers to the call it describes.
 */
final readonly class TraceNarrator
{
    public function __construct(
        #[Autowire(service: 'ai.agent.trace_narrator')]
        private AgentInterface $agent,
    ) {
    }

    /**
     * @param list<array>           $steps       calls, each with the "span" it opened
     * @param array<string, array>  $spans
     * @param list<string>          $eventsSoFar
     * @param array<string, string> $code        source of the methods that the model has not seen yet, by function
     *
     * @return iterable<array{span: string, at: float, text: string, error: bool}>
     */
    public function narrate(array $steps, array $spans, ?string $rootSpanId, array $eventsSoFar, bool $traceEnded, array $code = []): iterable
    {
        $json = static fn (mixed $value) => json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        $prompt = "Events so far:\n".($eventsSoFar ? implode("\n", $eventsSoFar) : '(none yet)')."\n\nNew calls:\n";
        foreach ($steps as $index => $step) {
            $span = $spans[$step['span']];
            $prompt .= \sprintf("%d: [%s] %s\"%s\" (%s)%s%s\n",
                $index,
                $span['service'],
                str_repeat('  ', max(0, $step['depth'] - $steps[0]['depth'])),
                $span['name'],
                $span['function'],
                isset($span['arguments']) ? ' called with '.$json($span['arguments']) : '',
                \array_key_exists('returnValue', $span) ? ' returned '.$json($span['returnValue']) : '',
            );
        }
        if ($code) {
            $prompt .= "\nCode of these operations:\n";
            foreach ($code as $function => $source) {
                $prompt .= \sprintf("\n%s():\n%s\n", $function, $source);
            }
        }
        $prompt .= "\n".($traceEnded ? 'The request has finished.' : 'The request is still running. More calls will follow.');

        $buffer = '';
        foreach ($this->agent->call(new MessageBag(Message::ofUser($prompt)), ['stream' => true])->getContent() as $delta) {
            if (!$delta instanceof TextDelta) {
                continue;
            }
            $buffer .= $delta->getText();
            while (false !== $newline = strpos($buffer, "\n")) {
                yield from $this->toEvent(substr($buffer, 0, $newline), $steps, $spans, $rootSpanId);
                $buffer = substr($buffer, $newline + 1);
            }
        }
        yield from $this->toEvent($buffer, $steps, $spans, $rootSpanId);
    }

    /**
     * Places an event at the start of the call it is about, or at its end when the event is about how the call ended,
     * such as what it returned or why it failed. The outcome of the request is placed at its end.
     *
     * @return iterable<array{span: string, at: float, text: string, error: bool}>
     */
    private function toEvent(string $line, array $steps, array $spans, ?string $rootSpanId): iterable
    {
        $event = json_decode(trim($line), true);
        $outcome = true === ($event['outcome'] ?? false) && null !== $rootSpanId;
        $step = $outcome ? end($steps) : $steps[$event['line'] ?? -1] ?? null;
        if (!$step || !\is_string($event['text'] ?? null) || '' === trim($event['text'])) {
            return;
        }

        $spanId = $outcome ? $rootSpanId : $step['span'];
        $at = $outcome || 'return' === ($event['when'] ?? null) ? $spans[$spanId]['end'] ?? $step['at'] : $step['at'];

        yield ['span' => $spanId, 'at' => $at, 'text' => trim($event['text']), 'error' => true === ($event['error'] ?? false)];
    }
}
