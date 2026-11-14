<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Xdebug;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Xdebug\TraceFile;

final class TraceFileTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4)).'.xt';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->path.'.gz');
    }

    public function testReadsWhatWasWrittenSinceTheLastRead()
    {
        $file = new TraceFile($this->path);
        static::assertSame('', $file->read());

        file_put_contents($this->path, "TRACE START\n1\t0");
        static::assertSame("TRACE START\n1\t0", $file->read());

        file_put_contents($this->path, "\t0\n", \FILE_APPEND);
        static::assertSame("\t0\n", $file->read());
    }

    public function testReadsACompressedTraceWhileItIsWritten()
    {
        $file = new TraceFile($this->path.'.gz');
        $deflate = deflate_init(\ZLIB_ENCODING_GZIP);

        file_put_contents($this->path.'.gz', deflate_add($deflate, "TRACE START\n1\t0", \ZLIB_SYNC_FLUSH));
        static::assertSame("TRACE START\n1\t0", $file->read());

        file_put_contents($this->path.'.gz', deflate_add($deflate, "\t0\nTRACE END\n", \ZLIB_FINISH), \FILE_APPEND);
        static::assertSame("\t0\nTRACE END\n", $file->read());
    }
}
