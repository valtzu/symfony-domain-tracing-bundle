<?php

namespace App\Tracing;

/**
 * Reads the records of a trace while they are written: those of the request that started it and those of the requests
 * it made to other services, merged in time order.
 */
final class TraceFollower
{
    /** @var array<string, int> read offsets by request */
    private array $offsets;
    private ?string $traceId = null;

    /** @var array<string, true> */
    private array $ended = [];

    public function __construct(
        private readonly TraceStore $store,
        public readonly string $rootId,
    ) {
        $this->offsets = [$rootId => 0];
    }

    /**
     * @return list<array> the new records, each with the "request" ID it belongs to
     */
    public function poll(): array
    {
        $records = [];
        do {
            $this->discoverRequests();
            $knownRequests = \count($this->offsets);

            foreach ($this->offsets as $requestId => $offset) {
                $read = $this->store->read($requestId, $offset);
                foreach ($read['records'] ?? [] as $record) {
                    $records[] = ['request' => $requestId] + $record;
                    if ('request' === $record['type'] && $requestId === $this->rootId) {
                        $this->traceId = $record['traceId'];
                    }
                    if ('end' === $record['type']) {
                        $this->ended[$requestId] = true;
                    }
                }
                $this->offsets[$requestId] = $read['offset'] ?? $offset;
            }
            $this->discoverRequests();
        } while (\count($this->offsets) > $knownRequests);

        usort($records, static fn (array $a, array $b) => $a['at'] <=> $b['at']);

        return $records;
    }

    public function hasEnded(): bool
    {
        return isset($this->ended[$this->rootId]) && \count($this->ended) === \count($this->offsets);
    }

    public function traceId(): ?string
    {
        return $this->traceId;
    }

    private function discoverRequests(): void
    {
        foreach (null === $this->traceId ? [] : $this->store->spansOf($this->traceId) as $requestId) {
            $this->offsets[$requestId] ??= 0;
        }
    }
}
