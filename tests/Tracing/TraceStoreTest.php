<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Tracing\TraceStore;

final class TraceStoreTest extends TestCase
{
    private const ROOT = '8985fb33-258f-4229-81f6-78dc02d1ee28';
    private const CHILD = '97f16856-e73a-4d4a-9702-28b8732fbb17';
    private const TRACE = '8985fb33258f422981f678dc02d1ee28';

    private TraceStore $store;

    protected function setUp(): void
    {
        $this->store = new TraceStore(sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4)));
        $this->store->start(self::ROOT, ['traceId' => self::TRACE, 'parentSpanId' => null]);
        $this->store->start(self::CHILD, ['traceId' => self::TRACE, 'parentSpanId' => '8985fb33258f4229']);
    }

    public function testDescribesOnlyTheTracesThatSomeoneAskedFor()
    {
        $this->assertSame([], $this->store->unprocessedRoots());

        $this->store->requestDescription(self::ROOT);

        $this->assertSame([self::ROOT], $this->store->unprocessedRoots());
    }

    public function testDescribesATraceOnce()
    {
        $this->store->requestDescription(self::ROOT);
        $this->store->addNarration(self::ROOT, ['type' => 'start']);
        $this->store->requestDescription(self::ROOT);

        $this->assertSame([], $this->store->unprocessedRoots());
    }

    public function testFindsTheRequestThatStartedTheTrace()
    {
        $this->assertSame(self::ROOT, $this->store->rootOf(self::CHILD));
        $this->assertSame(self::ROOT, $this->store->rootOf(self::ROOT));
    }
}
