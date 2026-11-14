<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Valtzu\DomainTracingBundle\Tracing\TraceDataCollector;
use Valtzu\DomainTracingBundle\Tracing\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\XdebugSetup;

final class TraceDataCollectorTest extends TestCase
{
    private const RECORDED = '8985fb33258f4229';

    private TraceStore $store;

    protected function setUp(): void
    {
        $this->store = new TraceStore(sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4)));
        $this->store->start(self::RECORDED, ['traceId' => '8985fb33258f422981f678dc02d1ee28']);
    }

    public function testKeepsTheIdOfARecordedRequest()
    {
        $this->assertSame(self::RECORDED, $this->collect(self::RECORDED));
    }

    public function testIgnoresARequestThatWasNotRecorded()
    {
        $this->assertNull($this->collect('97f16856e73a4d4a'));
        $this->assertNull($this->collect(null));
    }

    public function testSaysWhyARequestWasNotRecorded()
    {
        if (\extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug is loaded.');
        }

        $collector = $this->collector(null);

        $this->assertSame(['Xdebug is not loaded.'], $collector->getProblems());
        $this->assertStringContainsString('xdebug.output_dir=/srv/app/var/share/traces', $collector->getXdebugSettings());
        $this->assertSame([], $this->collector(self::RECORDED)->getProblems());
    }

    private function collect(?string $requestId): ?string
    {
        return $this->collector($requestId)->getRequestId();
    }

    private function collector(?string $requestId): TraceDataCollector
    {
        $collector = new TraceDataCollector($this->store, new XdebugSetup('/srv/app/var/share/traces'));
        $request = new Request();
        $request->server->set('DOMAIN_TRACING_SPAN_ID', $requestId);
        $collector->collect($request, new Response());

        return $collector;
    }
}
