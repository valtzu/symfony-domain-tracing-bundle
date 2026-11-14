<?php

namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;

/**
 * Describes what happened inside the operations of a trace, in business terms, as events on their spans. It works on
 * the trace part by part while it is recorded, and each event refers to the call it describes.
 */
final readonly class TraceNarrator
{
    public function __construct(
        private AgentInterface $agent,
        private SystemPrompts $prompts = new SystemPrompts(),
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

        $prompt = "Events so far:\n".($eventsSoFar ? implode("\n", $eventsSoFar) : '(none yet)')."\n\nNew calls:\n".($steps ? '' : "(none)\n");
        foreach ($steps as $index => $step) {
            $span = $spans[$step['span']];
            $prompt .= \sprintf(
                "%d: [%s] %s\"%s\" (%s)%s%s%s\n",
                $index,
                $span['service'],
                str_repeat('  ', max(0, $step['depth'] - $steps[0]['depth'])),
                $span['name'],
                $span['function'],
                isset($span['arguments']) ? ' called with '.$json($span['arguments']) : '',
                \array_key_exists('returnValue', $span) ? ' returned '.$json($span['returnValue']) : '',
                isset($span['exception']) && 'server' !== $span['kind'] ? ' threw '.self::exception($span['exception']) : '',
            );
        }
        if ($code) {
            $prompt .= "\nCode of these operations:\n";
            foreach ($code as $function => $source) {
                $prompt .= \sprintf("\n%s():\n%s\n", $function, $source);
            }
        }
        $prompt .= "\n".($traceEnded ? "The request has finished.\n".self::outcomes($spans) : 'The request is still running. More calls will follow.');

        $buffer = '';
        foreach ($this->agent->call(new MessageBag(Message::forSystem($this->prompts->get('trace_narrator')), Message::ofUser($prompt)), ['stream' => true])->getContent() as $delta) {
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
     * @param array<string, array> $spans
     */
    private static function outcomes(array $spans): string
    {
        $outcomes = '';
        foreach ($spans as $span) {
            if ('server' !== $span['kind'] || (!isset($span['status']) && !isset($span['exception']))) {
                continue;
            }
            $request = \sprintf('[%s] %s %s', $span['service'], $span['method'], parse_url($span['uri'], \PHP_URL_PATH));
            $failed = isset($span['exception']) || ($span['status'] ?? 0) >= 400;
            $outcomes .= $failed ? $request.' failed' : $request.' succeeded';
            $outcomes .= isset($span['status']) ? ' with status '.$span['status'] : '';
            if (isset($span['exception'])) {
                $thrower = $spans[$span['exception']['span'] ?? ''] ?? null;
                $outcomes .= ': '.self::exception($span['exception']).(null === $thrower ? '' : \sprintf(' was thrown in "%s" (%s)', $thrower['name'], $thrower['function']));
            }
            $outcomes .= ".\n";
        }

        return $outcomes;
    }

    private static function exception(array $exception): string
    {
        return $exception['class'].': '.json_encode($exception['message'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
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
        if (!\is_string($event['text'] ?? null) || '' === trim($event['text'])) {
            return;
        }
        if (true === ($event['outcome'] ?? false)) {
            $at = null === $rootSpanId ? null : $spans[$rootSpanId]['end'] ?? $steps[array_key_last($steps) ?? -1]['at'] ?? null;
            if (null !== $rootSpanId && null !== $at) {
                yield ['span' => $rootSpanId, 'at' => (float) $at, 'text' => trim($event['text']), 'error' => true === ($event['error'] ?? false)];
            }

            return;
        }

        $step = $steps[$event['line'] ?? -1] ?? null;
        $atEnd = 'return' === ($event['when'] ?? null);
        // The outcome already says how the request ended, at the same place
        if (null === $step || $atEnd && $step['span'] === $rootSpanId) {
            return;
        }
        $spanId = $step['span'];
        $at = $atEnd ? $spans[$spanId]['end'] ?? $step['at'] : $step['at'];

        yield ['span' => $spanId, 'at' => $at, 'text' => trim($event['text']), 'error' => true === ($event['error'] ?? false)];
    }
}
