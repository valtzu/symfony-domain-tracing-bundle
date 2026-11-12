<?php

namespace Valtzu\AiStacktrace\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Valtzu\AiStacktrace\Tracing\TraceDataCollector;
use Valtzu\AiStacktrace\Tracing\TraceStore;

final class TraceDataCollectorTest extends TestCase
{
    private const RECORDED = '8985fb33-258f-4229-81f6-78dc02d1ee28';

    private TraceStore $store;

    protected function setUp(): void
    {
        $this->store = new TraceStore(sys_get_temp_dir().'/ai-stacktrace-'.bin2hex(random_bytes(4)));
        $this->store->start(self::RECORDED, ['traceId' => '8985fb33258f422981f678dc02d1ee28']);
    }

    public function testKeepsTheIdOfARecordedRequest()
    {
        $this->assertSame(self::RECORDED, $this->collect(self::RECORDED));
    }

    public function testIgnoresARequestThatWasNotRecorded()
    {
        $this->assertNull($this->collect('97f16856-e73a-4d4a-9702-28b8732fbb17'));
        $this->assertNull($this->collect(null));
    }

    private function collect(?string $requestId): ?string
    {
        $collector = new TraceDataCollector($this->store);
        $request = new Request();
        $request->headers->set('X-Request-Id', $requestId);
        $collector->collect($request, new Response());

        return $collector->getRequestId();
    }
}
