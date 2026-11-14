<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Xdebug;

/**
 * Gives the time base of a trace. Xdebug writes the time of each call since the start of the PHP script, and not since
 * the start of the trace, so the trace reader cannot use the time of the TRACE START line when the trace starts later,
 * such as in a worker process. The recorder calls mark() right after the trace starts, with this class in the tracing
 * filter: the trace reader subtracts the time of that call in the trace from the wall time that mark() returns.
 */
final class TraceClock
{
    public const string MARK = self::class.'::mark';

    /**
     * @return float the wall time, as a Unix timestamp
     */
    public static function mark(): float
    {
        return microtime(true);
    }
}
