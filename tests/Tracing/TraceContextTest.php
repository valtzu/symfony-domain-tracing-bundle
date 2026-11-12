<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use Valtzu\DomainTracingBundle\Tracing\TraceContext;
use PHPUnit\Framework\TestCase;

final class TraceContextTest extends TestCase
{
    public function testStartsATraceWithoutTraceparent()
    {
        $this->assertSame(
            ['traceId' => '8985fb33258f422981f678dc02d1ee28', 'spanId' => '8985fb33258f4229', 'parentSpanId' => null],
            TraceContext::fromHeaders(null, '8985fb33-258f-4229-81f6-78dc02d1ee28'),
        );
    }

    public function testJoinsTheTraceOfTheCaller()
    {
        $this->assertSame(
            ['traceId' => '8985fb33258f422981f678dc02d1ee28', 'spanId' => '97f16856e73a4d4a', 'parentSpanId' => '8985fb33258f4229'],
            TraceContext::fromHeaders('00-8985fb33258f422981f678dc02d1ee28-8985fb33258f4229-01', '97f16856-e73a-4d4a-9702-28b8732fbb17'),
        );
    }

    public function testIgnoresAnInvalidTraceparent()
    {
        $this->assertNull(TraceContext::fromHeaders('garbage', '97f16856-e73a-4d4a-9702-28b8732fbb17')['parentSpanId']);
        $this->assertNull(TraceContext::fromHeaders('00-8985fb33258f422981f678dc02d1ee28-8985fb33258f4229-01', null));
    }
}
