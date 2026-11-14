<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Passes the trace context of the recorded request on to the services that it calls, so that their requests join its
 * trace. A traceparent header that the application or its OpenTelemetry instrumentation already set is kept.
 */
#[AsDecorator('http_client.transport', onInvalid: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]
final class TraceparentHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    #[\Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $traceparent = $_SERVER['DOMAIN_TRACING_TRACEPARENT'] ?? null;
        if (\is_string($traceparent) && !self::hasTraceparent($options['headers'] ?? [])) {
            $options['headers']['traceparent'] = $traceparent;
        }

        return $this->client->request($method, $url, $options);
    }

    private static function hasTraceparent(iterable $headers): bool
    {
        foreach ($headers as $name => $value) {
            if ('traceparent' === strtolower((string) $name) || \is_string($value) && str_starts_with(strtolower($value), 'traceparent:')) {
                return true;
            }
        }

        return false;
    }
}
