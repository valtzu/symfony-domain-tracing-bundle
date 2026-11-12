<?php

namespace App\Tracing;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * W3C Trace Context (https://www.w3.org/TR/trace-context/) for the current request. Each request is a span: its span
 * ID comes from the X-Request-Id header that Caddy sets, and it joins the trace of the caller when a traceparent header
 * arrives. The recorder derives the same IDs from the same headers.
 */
final readonly class TraceContext
{
    private const TRACEPARENT = '/^00-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

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

    public function traceparent(): ?string
    {
        $request = $this->requestStack->getMainRequest();
        $context = self::fromHeaders($request?->headers->get('traceparent'), $request?->headers->get('x-request-id'));

        return null === $context ? null : \sprintf('00-%s-%s-01', $context['traceId'], $context['spanId']);
    }
}
