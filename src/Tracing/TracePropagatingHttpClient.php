<?php

namespace App\Tracing;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sends the trace context with each outgoing request, so the service that receives it records its span in the same
 * trace.
 */
#[AsDecorator('http_client.transport')]
final class TracePropagatingHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    public function __construct(
        HttpClientInterface $client,
        private readonly TraceContext $context,
    ) {
        $this->client = $client;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (null !== $traceparent = $this->context->traceparent()) {
            $options['headers']['traceparent'] ??= $traceparent;
        }

        return $this->client->request($method, $url, $options);
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }
}
