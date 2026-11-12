<?php

namespace App\Tracing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends spans to an OpenTelemetry collector, such as Jaeger, with OTLP over HTTP and JSON.
 */
final readonly class OtlpExporter
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(OTEL_EXPORTER_OTLP_ENDPOINT)%')]
        private string $endpoint,
    ) {
    }

    /**
     * @param list<array> $spans  spans from {@see SpanBuilder}, each with a final "name"
     * @param list<array> $events events with the "span" they belong to
     */
    public function export(array $spans, array $events): void
    {
        $this->httpClient->request('POST', rtrim($this->endpoint, '/').'/v1/traces', ['json' => self::payload($spans, $events)])->getContent();
    }

    public static function payload(array $spans, array $events): array
    {
        $eventsBySpan = [];
        foreach ($events as $event) {
            $eventsBySpan[$event['span']][] = $event;
        }

        $spansByService = [];
        foreach ($spans as $span) {
            $failed = array_filter($eventsBySpan[$span['id']] ?? [], static fn (array $event) => $event['error']);
            $spansByService[$span['service']][] = array_filter([
                'traceId' => $span['traceId'],
                'spanId' => $span['id'],
                'parentSpanId' => $span['parentId'],
                'name' => $span['name'],
                'kind' => 'server' === $span['kind'] ? 2 : 1,
                'startTimeUnixNano' => self::nanoseconds($span['start']),
                'endTimeUnixNano' => self::nanoseconds($span['end'] ?? $span['start']),
                'attributes' => self::attributes(array_filter([
                    'http.request.method' => $span['method'] ?? null,
                    'url.path' => isset($span['uri']) ? parse_url($span['uri'], \PHP_URL_PATH) : null,
                    'code.function.name' => $span['function'],
                    'code.file.path' => $span['file'] ?? null,
                    'code.line.number' => $span['line'] ?? null,
                    'app.arguments' => isset($span['arguments']) ? json_encode($span['arguments'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : null,
                    'app.return_value' => \array_key_exists('returnValue', $span) ? json_encode($span['returnValue'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) : null,
                ], static fn ($value) => null !== $value)),
                'events' => array_map(static fn (array $event) => [
                    'timeUnixNano' => self::nanoseconds($event['at']),
                    'name' => $event['text'],
                    'attributes' => $event['error'] ? self::attributes(['error' => true]) : [],
                ], $eventsBySpan[$span['id']] ?? []),
                'status' => $failed ? ['code' => 2, 'message' => reset($failed)['text']] : ['code' => 1],
            ], static fn ($value) => null !== $value);
        }

        $resourceSpans = [];
        foreach ($spansByService as $service => $serviceSpans) {
            $resourceSpans[] = [
                'resource' => ['attributes' => self::attributes(['service.name' => $service])],
                'scopeSpans' => [['scope' => ['name' => 'app.tracing'], 'spans' => $serviceSpans]],
            ];
        }

        return ['resourceSpans' => $resourceSpans];
    }

    private static function nanoseconds(float $seconds): string
    {
        return \sprintf('%.0f', $seconds * 1e9);
    }

    private static function attributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $key => $value) {
            $result[] = ['key' => $key, 'value' => match (true) {
                \is_bool($value) => ['boolValue' => $value],
                \is_int($value) => ['intValue' => (string) $value],
                default => ['stringValue' => (string) $value],
            }];
        }

        return $result;
    }
}
