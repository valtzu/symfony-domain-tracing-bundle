<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tracing;

/**
 * The requests that are not recorded, from the regular expressions of the "exclude_uris" option. The pages of this
 * bundle and of the profiler are never recorded.
 */
final readonly class ExcludedUris
{
    /**
     * @param list<string> $patterns regular expressions without delimiters, matched against the path and the query
     */
    public function __construct(
        private array $patterns = [],
    ) {
    }

    public function matches(string $uri): bool
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match('{'.$pattern.'}', $uri)) {
                return true;
            }
        }

        return false;
    }
}
