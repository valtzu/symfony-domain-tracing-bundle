<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Xdebug;

/**
 * A trace file that Xdebug is still writing. It is compressed with gzip when its name ends with ".gz", which is the
 * default when Xdebug is built with zlib (xdebug.use_compression).
 */
final class TraceFile
{
    private int $offset = 0;
    private ?\InflateContext $inflate = null;

    public function __construct(
        private readonly string $path,
    ) {
        if (str_ends_with($path, '.gz')) {
            $this->inflate = inflate_init(\ZLIB_ENCODING_GZIP) ?: throw new \RuntimeException('Cannot read the gzip compressed trace file.');
        }
    }

    /**
     * @return string the text written since the last call
     */
    public function read(): string
    {
        if (!is_file($this->path)) {
            return '';
        }

        $data = (string) file_get_contents($this->path, offset: $this->offset);
        $this->offset += \strlen($data);

        return null === $this->inflate || '' === $data ? $data : (string) inflate_add($this->inflate, $data, \ZLIB_SYNC_FLUSH);
    }
}
