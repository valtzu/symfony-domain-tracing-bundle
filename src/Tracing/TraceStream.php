<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tracing;

use Valtzu\DomainTracingBundle\Ai\OperationNamer;
use Valtzu\DomainTracingBundle\Storage\BlobStore;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Xdebug\TraceIngester;

/**
 * Follows a trace while it is recorded, and gives what a person sees of it: the request, the spans with the names of
 * their operations, and, when the trace is described, the events and the names that the AI writes. It reads the Xdebug
 * traces into the trace store meanwhile, because nothing else does it.
 */
final readonly class TraceStream
{
    public function __construct(
        private TraceStore $store,
        private BlobStore $blobs,
        private OperationNamer $namer,
        private TraceIngester $ingester,
        private TraceDescriber $describer,
    ) {
    }

    /**
     * @param callable(): bool $stop tells, between two polls, whether to stop following
     *
     * @return \Generator<int, array{string, mixed}> events as [type, data]
     */
    public function follow(string $rootId, bool $describe, callable $stop): \Generator
    {
        $follower = new TraceFollower($this->store, $this->blobs, $rootId);
        $description = $describe ? $this->describer->describe($rootId) : null;
        $builder = new SpanBuilder();
        $names = [];
        $narrationOffset = 0;

        while (!$stop()) {
            $this->ingester->poll($follower->spanIds());
            foreach ($follower->poll() as $record) {
                $changes = $builder->add($record);
                $event = match ($record['type']) {
                    'request' => $record['request'] === $rootId ? ['request', $record] : null,
                    'step' => ['step', $record + ['service' => $builder->span($builder->currentSpanOf($record['request']) ?? '')['service'] ?? null]],
                    'end' => $record['request'] === $rootId ? ['end', $record] : null,
                    default => null,
                };
                if (null !== $event) {
                    yield $event;
                }
                foreach ($changes as [$change, $span]) {
                    $function = null === $span['function'] ? null : $builder->function($span['function']);
                    $span['name'] = $names[$span['function'] ?? ''] ?? (null === $function ? null : $this->namer->cachedName($span['service'], $span['function'], $function['code'])) ?? $span['name'];
                    yield ['span', ['change' => $change] + $span];
                }
            }

            if (!$describe && $follower->hasEnded()) {
                break;
            }

            $narration = $this->store->readNarration($rootId, $narrationOffset);
            $narrationOffset = $narration['offset'];
            foreach ($narration['records'] as $record) {
                if ('name' === $record['type']) {
                    $names[$record['function']] = $record['name'];
                }
                $event = match ($record['type']) {
                    'name' => ['name', $record],
                    'event' => ['event', $record],
                    'failure' => ['failure', $record['message']],
                    'end' => ['done', ['traceId' => $record['traceId']]],
                    default => null,
                };
                if (null !== $event) {
                    yield $event;
                }
                if ('end' === $record['type']) {
                    return;
                }
            }

            if ($description?->valid()) {
                $description->next();
            } else {
                usleep(100_000);
            }
        }

        yield ['done', ['traceId' => $follower->traceId()]];
    }
}
