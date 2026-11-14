<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Component\Process\Process;
use Valtzu\DomainTracingBundle\Storage\TraceStore;

/**
 * Gives the events of a trace after the ones that a client has, for a client that cannot keep a stream open: a browser
 * on a server with one worker, or an MCP app. Each poll follows the trace again from its start, and another process
 * describes it.
 */
final readonly class TracePoller
{
    private const float POLL_SECONDS = 0.3;

    public function __construct(
        private TraceStore $store,
        private TraceStream $traces,
        private string $projectDir,
        private string $environment,
    ) {
    }

    /**
     * @param string $cursor the cursor of the previous poll, or "0"
     *
     * @return array{events: list<array{string, mixed}>, cursor: string, done: bool}
     */
    public function events(string $id, bool $describe, string $cursor): array
    {
        if ($describe) {
            $this->describeInBackground($id);
        }

        $startedAt = microtime(true);
        $stop = static fn (): bool => microtime(true) - $startedAt >= self::POLL_SECONDS;
        $all = iterator_to_array($this->traces->follow($id, $describe, $stop, describeHere: false), preserve_keys: false);
        // When the poll runs out of time, the trace goes on: the next poll gives the events that follow
        if ($stop() && 'done' === (end($all)[0] ?? null)) {
            array_pop($all);
        }
        [$events, $cursor] = EventCursor::after($all, $cursor);

        return ['events' => $events, 'cursor' => $cursor, 'done' => 'done' === (end($events)[0] ?? null)];
    }

    private function describeInBackground(string $id): void
    {
        if ($this->store->isDescribed($id) || !$this->store->claimDescription($id)) {
            return;
        }

        Process::fromShellCommandline('"${:php}" "${:console}" debug:domain:trace "${:id}" --describe --quiet --env="${:env}" > /dev/null 2>&1 &')
            ->run(null, ['php' => \PHP_BINARY, 'console' => $this->projectDir.'/bin/console', 'id' => $id, 'env' => $this->environment]);
    }
}
