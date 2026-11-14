<?php

namespace Valtzu\DomainTracingBundle\Xdebug;

use Valtzu\DomainTracingBundle\Storage\TraceStore;

/**
 * Reads the Xdebug trace files of spans into the trace store while Xdebug writes them. One process reads each span:
 * the one that holds its lock. When that process stops before the trace ends, another one takes the lock and reads
 * the trace again from its start.
 */
final class TraceIngester
{
    private const SECONDS_WITHOUT_CHANGE_BEFORE_ENDING = 30;

    /** @var array<string, array{lock: resource, request: array, reader: ?XdebugTraceReader}> */
    private array $spans = [];

    public function __construct(
        private readonly TraceStore $store,
        private readonly XdebugValue $values,
    ) {
    }

    /**
     * @param list<string> $spanIds
     */
    public function poll(array $spanIds): void
    {
        foreach ($spanIds as $id) {
            if (!isset($this->spans[$id]) && !$this->take($id)) {
                continue;
            }

            $span = &$this->spans[$id];
            $traceFile = $span['request']['traceFile'] ?? '';
            if (null === $span['reader'] && null !== $codeIndex = $this->store->codeIndex($span['request']['codeIndex'] ?? '')) {
                $span['reader'] = new XdebugTraceReader($traceFile, $id, $span['request']['startedAt'], $codeIndex, $this->store, $this->values);
            }

            if ($span['reader']?->read()) {
                $this->end($id, $span['reader']->endedAt() ?? microtime(true));
            } elseif (microtime(true) - (@filemtime($traceFile) ?: $span['request']['startedAt']) > self::SECONDS_WITHOUT_CHANGE_BEFORE_ENDING) {
                $this->end($id, $span['reader']?->endedAt() ?? microtime(true));
            }
            unset($span);
        }
    }

    private function take(string $id): bool
    {
        if ($this->store->hasEnded($id) || null === $request = $this->store->request($id)) {
            return false;
        }
        if (null === $lock = $this->store->lockSpan($id)) {
            return false;
        }

        $this->store->restart($id);
        $this->spans[$id] = ['lock' => $lock, 'request' => $request, 'reader' => null];

        return true;
    }

    private function end(string $id, float $at): void
    {
        $this->store->end($id, $at);
        fclose($this->spans[$id]['lock']);
        unset($this->spans[$id]);
    }
}
