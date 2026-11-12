<?php

namespace App\Tracing;

/**
 * The time of a trace without the time its processes spent stopped in the debugger, so that the durations of a
 * recorded trace are close to those of the same request without recording. All spans of a trace share one clock,
 * because a service that waits for another one also waits while the other one is stopped.
 */
final class TraceClock
{
    /** @var array<string, float> seconds stopped, by trace */
    private array $stopped = [];

    public function addStopped(string $traceId, float $seconds): void
    {
        $this->stopped[$traceId] = ($this->stopped[$traceId] ?? 0.0) + $seconds;
    }

    public function now(string $traceId, ?float $wallTime = null): float
    {
        return ($wallTime ?? microtime(true)) - ($this->stopped[$traceId] ?? 0.0);
    }

    public function forget(string $traceId): void
    {
        unset($this->stopped[$traceId]);
    }
}
