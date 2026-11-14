<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tracing;

/**
 * Turns the records of a trace into spans, as they arrive: one span per call of an application method. The first method
 * of a request, the action of its entrypoint, shares the span of the request: the span has the name of the action and
 * the duration of the whole request. A closure belongs to the span of the method that runs it. A request from another
 * service becomes a child of the method span that was running when the request started, which is the one that called
 * the service.
 *
 * The span of a method starts at its first line. It ends at the first line that runs after it returns, or when it
 * returns to code outside the application.
 *
 * The span of a request gets the response status and the exception that the request did not handle. The exception also
 * goes to the span of the innermost method that was running when it was thrown.
 */
final class SpanBuilder
{
    /** @var array<string, array> spans by ID */
    private array $spans = [];

    /** @var array<array-key, list<string>> open method spans by request, innermost last */
    private array $stacks = [];

    /** @var array<array-key, string> request span ID by request */
    private array $requestSpans = [];

    /** @var array<array-key, string> request by request span ID */
    private array $requestsBySpanId = [];

    /** @var array<array-key, array> function records by function */
    private array $functions = [];

    private int $sequence = 0;

    /**
     * @return list<array{0: 'open'|'close', 1: array}> the spans that opened or closed
     */
    public function add(array $record): array
    {
        /** @var list<array{0: 'open'|'close', 1: array}> $changes */
        $changes = match ($record['type']) {
            'request' => $this->openRequest($record),
            'function' => $this->describeFunction($record),
            'step' => $this->step($record),
            'exit' => $this->exit($record),
            'arguments' => $this->setArguments($record),
            'lines' => $this->setExecutedLines($record),
            'outcome' => $this->setOutcome($record),
            'end' => $this->closeRequest($record['request'], $record['at']),
            default => [],
        };

        return $changes;
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

    /**
     * "App\Checkout\FraudScreening->needsManualReview" becomes "Fraud screening: needs manual review".
     */
    public static function humanize(string $function): string
    {
        [$class, $method] = (preg_split('/->|::/', $function, 2) ?: []) + ['', ''];
        $words = static fn (string $name) => strtolower(trim((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|_/', ' ', $name)));

        return ucfirst($words(substr((string) strrchr('\\'.$class, '\\'), 1))).': '.$words($method);
    }

    private function openRequest(array $record): array
    {
        /** @var array{parentSpanId: string|null, spanId: string, traceId: string, request: string, service?: string|null, method: string, uri: string, at: float, receivedAt?: float|null} $record */
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
            // The request waits for the service to start before the recording starts
            'start' => min($record['at'], \is_float($record['receivedAt'] ?? null) ? $record['receivedAt'] : $record['at']),
            'handledAt' => $record['at'],
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
                $changes[] = $this->close((string) array_pop($stack), $record['at']);
                continue;
            }
            if ($top['depth'] === $record['depth']) {
                return $changes;
            }
            break;
        }

        $requestSpanId = $this->requestSpans[$request];
        if (!$stack && null === $this->spans[$requestSpanId]['function']) {
            $this->spans[$requestSpanId] = [
                'function' => $record['function'],
                'name' => self::humanize($record['function']),
                'file' => $record['file'],
                'line' => $record['line'],
                'depth' => $record['depth'],
            ] + $this->spans[$requestSpanId];
            $stack[] = $requestSpanId;

            return [...$changes, ['open', $this->spans[$requestSpanId]]];
        }

        $parentId = $stack ? end($stack) : $requestSpanId;
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

    /**
     * Gives each span of the request the lines of its method that ran during the request. When a method ran more than
     * once, these are the lines of all its calls.
     */
    private function setExecutedLines(array $record): array
    {
        $changes = [];
        foreach ($this->spans as $id => $span) {
            $function = null === $span['function'] ? null : $this->functions[$span['function']] ?? null;
            if ($span['request'] !== $record['request'] || null === $function || $function['file'] !== $record['file'] || null === $function['line']) {
                continue;
            }
            $this->spans[$id]['executedLines'] = array_values(array_filter($record['lines'], static fn (int $line) => $line >= $function['line'] && $line <= $function['endLine']));
            $changes[] = ['open', $this->spans[$id]];
        }

        return $changes;
    }

    private function setArguments(array $record): array
    {
        $spanId = end($this->stacks[$record['request']]);
        if (!$spanId || $this->spans[$spanId]['depth'] !== $record['depth']) {
            return [];
        }
        $this->spans[$spanId]['arguments'] = $record['values'];

        return [['open', $this->spans[$spanId]]];
    }

    private function exit(array $record): array
    {
        $changes = [];
        $stack = &$this->stacks[$record['request']];
        if ([] !== $stack && \array_key_exists('return', $record) && $this->spans[end($stack)]['depth'] === $record['depth']) {
            $this->spans[(string) end($stack)]['returnValue'] = $record['return'];
        }
        while ([] !== $stack && $this->spans[end($stack)]['depth'] >= $record['depth']) {
            $changes[] = $this->close(array_pop($stack), $record['at']);
        }

        return $changes;
    }

    private function setOutcome(array $record): array
    {
        $requestSpanId = $this->requestSpans[$record['request']] ?? null;
        if (null === $requestSpanId) {
            return [];
        }
        if (isset($record['status'])) {
            $this->spans[$requestSpanId]['status'] = $record['status'];
        }
        if (!isset($record['exception'])) {
            return [];
        }

        $exception = ['class' => $record['exception']['class'], 'message' => $record['exception']['message']];
        $thrower = $this->innermostSpanOf($record['request'], $record['exception']['functions']);
        $this->spans[$requestSpanId]['exception'] = $exception + ['span' => $thrower];
        if (null === $thrower || $thrower === $requestSpanId) {
            return [];
        }
        $this->spans[$thrower]['exception'] = $exception;

        return \in_array($thrower, $this->stacks[$record['request']], strict: true) ? [] : [['close', $this->spans[$thrower]]];
    }

    /**
     * @param list<string> $functions the methods that were running, innermost first
     *
     * @return string|null the last span of the request that runs the innermost of these methods
     */
    private function innermostSpanOf(string $request, array $functions): ?string
    {
        foreach ($functions as $function) {
            foreach (array_reverse($this->spans) as $span) {
                if ($span['request'] === $request && $span['function'] === $function) {
                    return $span['id'];
                }
            }
        }

        return null;
    }

    private function closeRequest(string $request, float $at): array
    {
        $changes = [];
        while ($spanId = array_pop($this->stacks[$request])) {
            $changes[] = $this->close($spanId, $at);
        }
        if (isset($this->requestSpans[$request])) {
            $this->spans[$this->requestSpans[$request]]['end'] = $at;
            $changes[] = ['close', $this->spans[$this->requestSpans[$request]]];
        }

        return $changes;
    }

    private function close(string $spanId, float $at): array
    {
        if ('server' !== $this->spans[$spanId]['kind']) {
            $this->spans[$spanId]['end'] = $at;
        }

        return ['close', $this->spans[$spanId]];
    }
}
