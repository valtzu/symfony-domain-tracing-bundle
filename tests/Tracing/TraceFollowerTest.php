<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Valtzu\DomainTracingBundle\Storage\BlobStore;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TraceFollower;

final class TraceFollowerTest extends TestCase
{
    private const ROOT = '8985fb33258f4229';

    private string $directory;
    private TraceStore $store;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/domain-tracing-'.bin2hex(random_bytes(4));
        $this->store = new TraceStore($this->directory.'/traces');
        $this->store->start(self::ROOT, ['traceId' => str_repeat('a', 32), 'parentSpanId' => null]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testKeepsReadingWhenAnotherProcessReadsTheXdebugTraceAgain(): void
    {
        $follower = new TraceFollower($this->store, new BlobStore($this->directory.'/blobs', $this->directory), self::ROOT);
        $this->write(1, 30);
        static::assertSame(range(1, 30), $this->exits($follower->poll()));

        // Another process reads the Xdebug trace from its start again, and writes the same records, with other times
        $this->store->restart(self::ROOT);
        $this->write(1, 40);

        static::assertSame(range(31, 40), $this->exits($follower->poll()));
    }

    public function testTimesTheStepsFromTheStartOfTheRequest(): void
    {
        // The recorder starts the request before the store writes its record, which the store stamps later
        $this->store->start('59f1b6a0c3d2e417', ['traceId' => str_repeat('b', 32), 'parentSpanId' => null, 'startedAt' => 1000.0]);
        $this->store->describeFunction('59f1b6a0c3d2e417', ['fn' => 0, 'function' => 'App\\Shop->charge', 'file' => 0, 'line' => null, 'endLine' => null]);
        $this->store->addStep('59f1b6a0c3d2e417', 0, 1, 1, 40_000);

        $records = (new TraceFollower($this->store, new BlobStore($this->directory.'/blobs', $this->directory), '59f1b6a0c3d2e417'))->poll();

        $request = array_values(array_filter($records, static fn (array $record): bool => 'request' === $record['type']))[0] ?? [];
        $step = array_values(array_filter($records, static fn (array $record): bool => 'step' === $record['type']))[0] ?? [];
        static::assertSame(1000.0, $request['at'] ?? null);
        static::assertEqualsWithDelta(1000.04, $step['at'] ?? null, 0.000001);
    }

    private function write(int $from, int $to): void
    {
        for ($depth = $from; $depth <= $to; $depth++) {
            // The store stamps a record without a time with the current time, which differs on the second read
            usleep(10);
            $this->store->describeFunction(self::ROOT, ['fn' => $depth, 'function' => 'App\\Shop->step'.$depth, 'file' => 0, 'line' => null, 'endLine' => null]);
            $this->store->addExit(self::ROOT, $depth, 1.0 + $depth);
        }
    }

    /**
     * @param list<array> $records
     *
     * @return list<int>
     */
    private function exits(array $records): array
    {
        return array_values(array_map(intval(...), array_column(array_filter($records, static fn (array $record): bool => 'exit' === $record['type']), 'depth')));
    }
}
