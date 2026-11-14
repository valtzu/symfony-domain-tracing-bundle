<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\CacheClearer;

use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Valtzu\DomainTracingBundle\Storage\TraceStore;

/**
 * Removes the traces on cache:clear, which removes the profiles of the profiler too.
 */
final readonly class TraceCacheClearer implements CacheClearerInterface
{
    public function __construct(
        private TraceStore $store,
    ) {
    }

    #[\Override]
    public function clear(string $cacheDir): void
    {
        $this->store->clear();
    }
}
