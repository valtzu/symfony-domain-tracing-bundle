<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Xdebug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Valtzu\DomainTracingBundle\Ai\SecretRedactor;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Xdebug\TraceIngester;
use Valtzu\DomainTracingBundle\Xdebug\XdebugValue;

final class TraceIngesterTest extends TestCase
{
    private const SPAN = '8985fb33258f4229';

    private string $directory;
    private TraceStore $store;
    private TraceIngester $ingester;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $this->store = new TraceStore($this->directory);
        $this->store->start(self::SPAN, ['traceId' => str_repeat('a', 32), 'parentSpanId' => null, 'traceFile' => $this->directory.'/trace.'.self::SPAN.'.xt', 'codeIndex' => 'none']);
        file_put_contents($this->directory.'/trace.'.self::SPAN.'.xt', "Version: 3.5.0\nFile format: 4\nTRACE START [2026-11-14 10:00:00.000000]\n");
        $redactor = new SecretRedactor([]);
        $this->ingester = new TraceIngester($this->store, new XdebugValue($redactor), $redactor);
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testWaitsForARequestThatRunsLongWithoutCallingTheApplication(): void
    {
        // Such as a slow query: the trace does not change for minutes
        touch($this->directory.'/trace.'.self::SPAN.'.xt', time() - 120);

        $this->ingester->poll([self::SPAN]);

        static::assertFalse($this->store->hasEnded(self::SPAN));
    }

    public function testEndsARequestWhoseTraceHasNoEnd(): void
    {
        // The recorder wrote how the request ended, but the trace has no end line, such as when PHP was killed
        file_put_contents($this->store->outcomePath(self::SPAN), '{"status":200}');
        touch($this->directory.'/trace.'.self::SPAN.'.xt', time() - 10);

        $this->ingester->poll([self::SPAN]);

        static::assertTrue($this->store->hasEnded(self::SPAN));
    }
}
