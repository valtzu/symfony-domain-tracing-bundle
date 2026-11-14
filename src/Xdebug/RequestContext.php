<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Xdebug;

final class RequestContext
{
    /**
     * The W3C Trace Context (https://www.w3.org/TR/trace-context/) of the current request, which is one span: it joins
     * the trace of the caller when a traceparent header arrives. A console command is recorded only with --profile, like
     * its profile. Without OTEL_SERVICE_NAME, the service is named after the project directory, the parent of the
     * directory of the entry script (public/index.php or bin/console).
     *
     * @return array{traceId: string, spanId: string, parentSpanId: string|null, service: string|null, method: string|null, uri: string|null}|null null for the requests that are never recorded
     */
    public static function fromServer(array $server): ?array
    {
        /** @var string|null $method */
        $method = $server['REQUEST_METHOD'] ?? null;
        /** @var string|null $uri */
        $uri = $server['REQUEST_URI'] ?? null;
        if (null !== $uri && preg_match('{^/_(tracing|profiler|wdt)(/|\?|$)}', $uri)) {
            return null;
        }
        if (null === $method && \is_array($argv = $server['argv'] ?? null)) {
            // A console command has a profile, and so a trace, only with --profile
            /** @var list<string> $argv */
            if (!\in_array('--profile', $argv, true)) {
                return null;
            }
            $binary = isset($server['SYMFONY_CLI_BINARY_NAME']) ? $server['SYMFONY_CLI_BINARY_NAME'].' console' : $argv[0];
            [$method, $uri] = ['CLI', implode(' ', [$binary, ...\array_slice($argv, 1)])];
        }

        $parent = preg_match('/^00-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/', $server['HTTP_TRACEPARENT'] ?? '', $match) ? $match : null;
        $script = $server['SCRIPT_FILENAME'] ?? '';
        $project = basename(\dirname(realpath($script) ?: $script, 2));

        return [
            'traceId' => $parent[1] ?? bin2hex(random_bytes(16)),
            'spanId' => bin2hex(random_bytes(8)),
            'parentSpanId' => $parent[2] ?? null,
            'service' => \is_string($server['OTEL_SERVICE_NAME'] ?? null) ? $server['OTEL_SERVICE_NAME'] : (getenv('OTEL_SERVICE_NAME') ?: ('' === $project || '.' === $project ? null : $project)),
            'method' => $method,
            'uri' => $uri,
        ];
    }
}
