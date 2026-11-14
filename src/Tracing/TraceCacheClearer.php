<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

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
