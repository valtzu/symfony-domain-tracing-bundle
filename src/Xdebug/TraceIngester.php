<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Xdebug;

use Valtzu\DomainTracingBundle\Storage\TraceStore;

use Valtzu\DomainTracingBundle\Ai\SecretRedactor;

/**
 * Reads the Xdebug trace files of spans into the trace store while Xdebug writes them. One process reads each span:
 * the one that holds its lock. When that process stops before the trace ends, another one takes the lock and reads
 * the trace again from its start. Before the end of a span, it records how the request ended: its response status and
 * the exception that it did not handle, from the file that the recorder writes next to the trace file.
 */
final class TraceIngester
{
    private const SECONDS_WITHOUT_CHANGE_BEFORE_ENDING = 30;
    private const int MAX_MESSAGE_LENGTH = 1000;

    /** @var array<string, array{lock: resource, request: array, reader: ?XdebugTraceReader}> */
    private array $spans = [];

    public function __construct(
        private readonly TraceStore $store,
        private readonly XdebugValue $values,
        private readonly SecretRedactor $redactor,
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
                $span['reader'] = new XdebugTraceReader($traceFile, $id, $span['request']['startedAt'], $codeIndex, $this->store, $this->values, \is_float($markedAt = $span['request']['markedAt'] ?? null) ? $markedAt : null);
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
        $this->store->end($id, $at, $this->outcome($id));
        fclose($this->spans[$id]['lock']);
        unset($this->spans[$id]);
    }

    /**
     * @return array{status?: int, exception?: array{class: string, message: string, functions: list<string>}}
     */
    private function outcome(string $id): array
    {
        $outcome = json_decode((string) @file_get_contents($this->store->outcomePath($id)), associative: true);
        $exception = $outcome['exception'] ?? null;

        return array_filter([
            'status' => \is_int($outcome['status'] ?? null) ? $outcome['status'] : null,
            'exception' => \is_array($exception) && \is_string($exception['class'] ?? null) && \is_string($exception['message'] ?? null) ? [
                'class' => $exception['class'],
                'message' => $this->redactor->redact(mb_substr($exception['message'], start: 0, length: self::MAX_MESSAGE_LENGTH)),
                'functions' => array_values(array_filter((array) ($exception['functions'] ?? []), is_string(...))),
            ] : null,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
