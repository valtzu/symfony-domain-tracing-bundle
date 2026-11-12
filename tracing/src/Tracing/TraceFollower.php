<?php

namespace App\Tracing;

/**
 * Reads the decoded records of a trace while they are written: those of the request that started it and those of the requests
 * it made to other services, merged in time order.
 */
final class TraceFollower
{
    /** @var array<string, int> read offsets by request */
    private array $offsets;
    private ?string $traceId = null;

    /** @var array<string, true> */
    private array $ended = [];

    /** @var array<string, float> start time by request */
    private array $startedAt = [];

    private readonly TraceDecoder $decoder;

    public function __construct(
        private readonly TraceStore $store,
        BlobStore $blobs,
        public readonly string $rootId,
    ) {
        $this->offsets = [$rootId => 0];
        $this->decoder = new TraceDecoder($blobs);
    }

    /**
     * @return list<array> the new records, each with the "request" ID it belongs to
     */
    public function poll(): array
    {
        $recordsByRequest = [];
        do {
            $this->discoverRequests();
            $knownRequests = \count($this->offsets);

            foreach ($this->offsets as $requestId => $offset) {
                $read = $this->store->read($requestId, $offset);
                foreach ($read['records'] ?? [] as $record) {
                    if (array_is_list($record)) {
                        [$function, $line, $depth, $microseconds] = $record;
                        $record = ['type' => 'step', 'fn' => $function, 'line' => $line, 'depth' => $depth, 'at' => $this->startedAt[$requestId] + $microseconds / 1e6];
                    }
                    if ('request' === $record['type']) {
                        $this->startedAt[$requestId] = $record['at'];
                    }
                    $recordsByRequest[$requestId][] = ['request' => $requestId] + $record;
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

        return array_values(array_filter(array_map($this->decoder->decode(...), self::mergeByTime($recordsByRequest))));
    }

    /**
     * Merges the records of the requests in time order, keeping the order of the records of each request, because a
     * table record must come before the records that refer to it.
     *
     * @param array<string, list<array>> $recordsByRequest
     *
     * @return list<array>
     */
    private static function mergeByTime(array $recordsByRequest): array
    {
        $merged = [];
        while ($recordsByRequest = array_filter($recordsByRequest)) {
            $next = array_key_first($recordsByRequest);
            foreach ($recordsByRequest as $requestId => $records) {
                if ($records[0]['at'] < $recordsByRequest[$next][0]['at']) {
                    $next = $requestId;
                }
            }
            $merged[] = array_shift($recordsByRequest[$next]);
        }

        return $merged;
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
