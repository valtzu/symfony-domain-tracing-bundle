<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Xdebug;

use PHPUnit\Framework\TestCase;

use Valtzu\DomainTracingBundle\Xdebug\RequestContext;

final class RequestContextTest extends TestCase
{
    public function testStartsATraceWhenNoTraceparentArrives()
    {
        $context = RequestContext::fromServer(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/hello?name=ada', 'SCRIPT_FILENAME' => '/srv/greetings/public/index.php']);

        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $context['traceId']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $context['spanId']);
        static::assertNull($context['parentSpanId']);
        static::assertSame(['greetings', 'GET', '/hello?name=ada'], [$context['service'], $context['method'], $context['uri']]);
    }

    public function testJoinsTheTraceOfTheCaller()
    {
        $context = RequestContext::fromServer([
            'HTTP_TRACEPARENT' => '00-8985fb33258f422981f678dc02d1ee28-97f16856e73a4d4a-01',
            'OTEL_SERVICE_NAME' => 'payments',
            'SCRIPT_FILENAME' => '/srv/app/public/index.php',
        ]);

        static::assertSame('8985fb33258f422981f678dc02d1ee28', $context['traceId']);
        static::assertSame('97f16856e73a4d4a', $context['parentSpanId']);
        static::assertNotSame('97f16856e73a4d4a', $context['spanId']);
        static::assertSame('payments', $context['service']);
    }

    public function testIgnoresAnInvalidTraceparent()
    {
        $context = RequestContext::fromServer(['HTTP_TRACEPARENT' => '00-not-a-trace-01', 'REQUEST_METHOD' => 'GET', 'SCRIPT_FILENAME' => 'index.php']);

        static::assertNull($context['parentSpanId']);
        static::assertNull($context['service']);
    }

    public function testNeverRecordsThePagesOfTheBundleAndOfTheProfiler()
    {
        static::assertNull(RequestContext::fromServer(['REQUEST_URI' => '/_tracing/8985fb33258f4229/events']));
        static::assertNull(RequestContext::fromServer(['REQUEST_URI' => '/_profiler/a5c9e1?panel=domain_traces']));
        static::assertNull(RequestContext::fromServer(['REQUEST_URI' => '/_wdt/a5c9e1']));
        static::assertNotNull(RequestContext::fromServer(['REQUEST_URI' => '/_tracing_report']));
    }

    public function testRecordsAConsoleCommandOnlyWhenItIsProfiled()
    {
        static::assertNull(RequestContext::fromServer(['argv' => ['bin/console', 'app:import'], 'SCRIPT_FILENAME' => '/srv/greetings/bin/console']));

        $context = RequestContext::fromServer(['argv' => ['bin/console', 'app:import', '--profile'], 'SCRIPT_FILENAME' => '/srv/greetings/bin/console']);

        static::assertSame(['CLI', 'bin/console app:import --profile', 'greetings'], [$context['method'], $context['uri'], $context['service']]);
    }

    public function testNamesTheCommandLikeTheProfilerDoes()
    {
        $context = RequestContext::fromServer(['argv' => ['/srv/greetings/bin/console', 'app:import', '--profile'], 'SYMFONY_CLI_BINARY_NAME' => 'symfony']);

        static::assertSame('symfony console app:import --profile', $context['uri']);
    }
}
