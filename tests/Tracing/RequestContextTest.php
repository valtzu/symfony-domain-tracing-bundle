<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;

use function Valtzu\DomainTracingBundle\Prepend\request_context;

final class RequestContextTest extends TestCase
{
    public function testStartsATraceWhenNoTraceparentArrives()
    {
        $context = request_context(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/hello?name=ada', 'SCRIPT_FILENAME' => '/srv/greetings/public/index.php']);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $context['traceId']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $context['spanId']);
        $this->assertNull($context['parentSpanId']);
        $this->assertSame(['greetings', 'GET', '/hello?name=ada'], [$context['service'], $context['method'], $context['uri']]);
    }

    public function testJoinsTheTraceOfTheCaller()
    {
        $context = request_context([
            'HTTP_TRACEPARENT' => '00-8985fb33258f422981f678dc02d1ee28-97f16856e73a4d4a-01',
            'OTEL_SERVICE_NAME' => 'payments',
            'SCRIPT_FILENAME' => '/srv/app/public/index.php',
        ]);

        $this->assertSame('8985fb33258f422981f678dc02d1ee28', $context['traceId']);
        $this->assertSame('97f16856e73a4d4a', $context['parentSpanId']);
        $this->assertNotSame('97f16856e73a4d4a', $context['spanId']);
        $this->assertSame('payments', $context['service']);
    }

    public function testIgnoresAnInvalidTraceparent()
    {
        $context = request_context(['HTTP_TRACEPARENT' => '00-not-a-trace-01', 'REQUEST_METHOD' => 'GET', 'SCRIPT_FILENAME' => 'index.php']);

        $this->assertNull($context['parentSpanId']);
        $this->assertNull($context['service']);
    }

    public function testNeverRecordsThePagesOfTheBundleAndOfTheProfiler()
    {
        $this->assertNull(request_context(['REQUEST_URI' => '/_tracing/8985fb33258f4229/events']));
        $this->assertNull(request_context(['REQUEST_URI' => '/_profiler/a5c9e1?panel=domain_traces']));
        $this->assertNull(request_context(['REQUEST_URI' => '/_wdt/a5c9e1']));
        $this->assertNotNull(request_context(['REQUEST_URI' => '/_tracing_report']));
    }

    public function testRecordsAConsoleCommandOnlyWhenItIsProfiled()
    {
        $this->assertNull(request_context(['argv' => ['bin/console', 'app:import'], 'SCRIPT_FILENAME' => '/srv/greetings/bin/console']));

        $context = request_context(['argv' => ['bin/console', 'app:import', '--profile'], 'SCRIPT_FILENAME' => '/srv/greetings/bin/console']);

        $this->assertSame(['CLI', 'bin/console app:import --profile', 'greetings'], [$context['method'], $context['uri'], $context['service']]);
    }

    public function testNamesTheCommandLikeTheProfilerDoes()
    {
        $context = request_context(['argv' => ['/srv/greetings/bin/console', 'app:import', '--profile'], 'SYMFONY_CLI_BINARY_NAME' => 'symfony']);

        $this->assertSame('symfony console app:import --profile', $context['uri']);
    }
}
