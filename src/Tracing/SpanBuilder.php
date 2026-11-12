<?php

namespace App\Tracing;

/**
 * Turns the records of a trace into spans, as they arrive: one span per request, and one span per call of an
 * application method inside it. A closure belongs to the span of the method that runs it. A request from another
 * service becomes a child of the method span that was running when the request started, which is the one that called
 * the service.
 *
 * The span of a method starts at its first line. It ends at the first line that runs after it returns, or when it
 * returns to code outside the application.
 */
final class SpanBuilder
{
    /** @var array<string, array> spans by ID */
    private array $spans = [];

    /** @var array<string, list<string>> open method spans by request, innermost last */
    private array $stacks = [];

    /** @var array<string, string> request span ID by request */
    private array $requestSpans = [];

    /** @var array<string, string> request by request span ID */
    private array $requestsBySpanId = [];

    /** @var array<string, array> function records by function */
    private array $functions = [];

    private int $sequence = 0;

    /**
     * @return list<array{0: 'open'|'close', 1: array}> the spans that opened or closed
     */
    public function add(array $record): array
    {
        return match ($record['type']) {
            'request' => $this->openRequest($record),
            'function' => $this->describeFunction($record),
            'step' => $this->step($record),
            'exit' => $this->exit($record),
            'end' => $this->closeRequest($record['request'], $record['at']),
            default => [],
        };
    }

    public function span(string $id): ?array
    {
        return $this->spans[$id] ?? null;
    }

    /**
     * @return array<string, array>
     */
    public function spans(): array
    {
        return $this->spans;
    }

    public function function(string $function): ?array
    {
        return $this->functions[$function] ?? null;
    }

    public function requestSpanOf(string $request): ?string
    {
        return $this->requestSpans[$request] ?? null;
    }

    /**
     * @return string|null the span that a step of the given request belongs to
     */
    public function currentSpanOf(string $request): ?string
    {
        $stack = $this->stacks[$request] ?? [];

        return end($stack) ?: ($this->requestSpans[$request] ?? null);
    }

    private function openRequest(array $record): array
    {
        $parentSpanId = $record['parentSpanId'];
        if (null !== $parentSpanId && null !== $callerRequest = $this->requestsBySpanId[$parentSpanId] ?? null) {
            $parentSpanId = $this->currentSpanOf($callerRequest);
        }

        $span = [
            'id' => $record['spanId'],
            'traceId' => $record['traceId'],
            'parentId' => $parentSpanId,
            'request' => $record['request'],
            'service' => $record['service'] ?? 'unknown',
            'kind' => 'server',
            'function' => null,
            'name' => \sprintf('%s %s', $record['method'], parse_url($record['uri'], \PHP_URL_PATH)),
            'method' => $record['method'],
            'uri' => $record['uri'],
            'start' => $record['at'],
            'end' => null,
        ];
        $this->spans[$span['id']] = $span;
        $this->requestSpans[$record['request']] = $span['id'];
        $this->requestsBySpanId[$record['spanId']] = $record['request'];
        $this->stacks[$record['request']] = [];

        return [['open', $span]];
    }

    private function describeFunction(array $record): array
    {
        $this->functions[$record['function']] = $record;

        return [];
    }

    private function step(array $record): array
    {
        $request = $record['request'];
        if (!isset($this->requestSpans[$request]) || str_contains($record['function'], '{closure')) {
            return [];
        }

        $changes = [];
        $stack = &$this->stacks[$request];
        while ($stack) {
            $top = $this->spans[end($stack)];
            if ($top['depth'] > $record['depth'] || $top['depth'] === $record['depth'] && $top['function'] !== $record['function']) {
                $changes[] = $this->close(array_pop($stack), $record['at']);
                continue;
            }
            if ($top['depth'] === $record['depth']) {
                return $changes;
            }
            break;
        }

        $parentId = $stack ? end($stack) : $this->requestSpans[$request];
        $span = [
            'id' => substr(hash('sha256', $parentId.'#'.++$this->sequence), 0, 16),
            'traceId' => $this->spans[$parentId]['traceId'],
            'parentId' => $parentId,
            'request' => $request,
            'service' => $this->spans[$parentId]['service'],
            'kind' => 'internal',
            'function' => $record['function'],
            'name' => self::humanize($record['function']),
            'file' => $record['file'],
            'line' => $record['line'],
            'depth' => $record['depth'],
            'start' => $record['at'],
            'end' => null,
        ];
        $this->spans[$span['id']] = $span;
        $stack[] = $span['id'];
        $changes[] = ['open', $span];

        return $changes;
    }

    private function exit(array $record): array
    {
        $changes = [];
        $stack = &$this->stacks[$record['request']];
        while ($stack && $this->spans[end($stack)]['depth'] >= $record['depth']) {
            $changes[] = $this->close(array_pop($stack), $record['at']);
        }

        return $changes;
    }

    private function closeRequest(string $request, float $at): array
    {
        $changes = [];
        while ($spanId = array_pop($this->stacks[$request])) {
            $changes[] = $this->close($spanId, $at);
        }
        if (isset($this->requestSpans[$request])) {
            $changes[] = $this->close($this->requestSpans[$request], $at);
        }

        return $changes;
    }

    private function close(string $spanId, float $at): array
    {
        $this->spans[$spanId]['end'] = $at;

        return ['close', $this->spans[$spanId]];
    }

    /**
     * "App\Checkout\FraudScreening->needsManualReview" becomes "Fraud screening: needs manual review".
     */
    public static function humanize(string $function): string
    {
        [$class, $method] = preg_split('/->|::/', $function, 2) + [1 => ''];
        $words = static fn (string $name) => strtolower(trim(preg_replace('/(?<=[a-z0-9])(?=[A-Z])|_/', ' ', $name)));

        return ucfirst($words(substr(strrchr('\\'.$class, '\\'), 1))).': '.$words($method);
    }
}
