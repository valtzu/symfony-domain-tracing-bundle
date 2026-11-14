<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Valtzu\DomainTracingBundle\Storage\BlobStore;

final class BlobStoreTest extends TestCase
{
    private string $directory;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testRemovesTheBlobsThatWereNotReadForAWhile(): void
    {
        $blobs = new BlobStore($this->directory, null);
        $old = $blobs->put("<?php // old\n");
        $read = $blobs->put("<?php // read\n");
        touch($this->directory.'/'.$old, time() - 3 * 86_400);
        touch($this->directory.'/'.$read, time() - 3 * 86_400);
        (new BlobStore($this->directory, null))->get($read);

        $blobs->removeOlderThan(time() - 2 * 86_400);

        static::assertSame([$read], array_map(basename(...), glob($this->directory.'/*') ?: []));
    }

    public function testRemovesAllTheBlobs(): void
    {
        $blobs = new BlobStore($this->directory, null);
        $blobs->put("<?php\n");

        $blobs->clear();

        static::assertSame([], glob($this->directory.'/*') ?: []);
    }
}
