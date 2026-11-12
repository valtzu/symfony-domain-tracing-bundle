<?php

namespace Valtzu\AiStacktrace\Tracing;

/**
 * W3C Trace Context (https://www.w3.org/TR/trace-context/) of a recorded request. Each request is a span: its span ID
 * comes from the X-Request-Id header that the web server sets, and it joins the trace of the caller when a traceparent
 * header arrives.
 */
final class TraceContext
{
    private const TRACEPARENT = '/^00-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/';

    /**
     * @return array{traceId: string, spanId: string, parentSpanId: string|null}|null
     */
    public static function fromHeaders(?string $traceparent, ?string $requestId): ?array
    {
        if (null === $requestId || !TraceStore::isValidId($requestId)) {
            return null;
        }

        $requestHex = str_replace('-', '', $requestId);
        $parent = null !== $traceparent && preg_match(self::TRACEPARENT, $traceparent, $match) ? $match : null;

        return [
            'traceId' => $parent[1] ?? $requestHex,
            'spanId' => substr($requestHex, 0, 16),
            'parentSpanId' => $parent[2] ?? null,
        ];
    }

    /**
     * @param array{traceId: string, spanId: string} $context
     */
    public static function traceparent(array $context): string
    {
        return \sprintf('00-%s-%s-01', $context['traceId'], $context['spanId']);
    }
}
