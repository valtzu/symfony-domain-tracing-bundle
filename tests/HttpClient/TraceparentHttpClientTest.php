<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\HttpClient;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Valtzu\DomainTracingBundle\HttpClient\TraceparentHttpClient;

#[BackupGlobals(true)]
final class TraceparentHttpClientTest extends TestCase
{
    private const TRACEPARENT = '00-8985fb33258f422981f678dc02d1ee28-97f16856e73a4d4a-01';

    public function testPassesTheTraceContextOn()
    {
        $_SERVER['DOMAIN_TRACING_TRACEPARENT'] = self::TRACEPARENT;

        $this->assertSame(['traceparent: '.self::TRACEPARENT], $this->sentHeaders([]));
    }

    public function testKeepsATraceparentThatIsAlreadySet()
    {
        $_SERVER['DOMAIN_TRACING_TRACEPARENT'] = self::TRACEPARENT;

        $this->assertSame(['traceparent: 00-otel'], $this->sentHeaders(['Traceparent' => '00-otel']));
        $this->assertSame(['traceparent: 00-otel'], $this->sentHeaders(['traceparent: 00-otel']));
    }

    public function testSendsNothingWhenTheRequestIsNotRecorded()
    {
        unset($_SERVER['DOMAIN_TRACING_TRACEPARENT']);

        $this->assertSame([], $this->sentHeaders([]));
    }

    private function sentHeaders(array $headers): array
    {
        $sent = null;
        $client = new TraceparentHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent) {
            $sent = $options['headers'];

            return new MockResponse();
        }));
        $client->request('POST', 'http://payments/charge', ['headers' => $headers]);

        return array_values(array_filter(array_map(strtolower(...), $sent), static fn (string $header) => str_starts_with($header, 'traceparent')));
    }
}
