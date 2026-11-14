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
    /** After the request ended, a trace without an end line, such as when PHP was killed */
    private const int SECONDS_WITHOUT_CHANGE_AFTER_THE_REQUEST = 5;
    /** A request that never wrote how it ended, such as when its process crashed */
    private const int SECONDS_WITHOUT_CHANGE_BEFORE_GIVING_UP = 600;
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
            } elseif ($this->hasStopped($id, $traceFile, $span['request'])) {
                $this->end($id, $span['reader']?->endedAt() ?? microtime(true));
            }
            unset($span);
        }
    }

    /**
     * A request can run long without calling the application, such as during a slow query, so its trace is waited for
     * until the recorder writes how the request ended.
     */
    private function hasStopped(string $id, string $traceFile, array $request): bool
    {
        $unchanged = microtime(true) - (@filemtime($traceFile) ?: (is_numeric($request['startedAt'] ?? null) ? (float) $request['startedAt'] : 0.0));

        return $unchanged > (is_file($this->store->outcomePath($id)) ? self::SECONDS_WITHOUT_CHANGE_AFTER_THE_REQUEST : self::SECONDS_WITHOUT_CHANGE_BEFORE_GIVING_UP);
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
        $path = $this->store->outcomePath($id);
        $outcome = is_file($path) ? json_decode((string) file_get_contents($path), associative: true) : null;
        $respondedAt = \is_array($outcome) ? $outcome['respondedAt'] ?? null : null;
        $this->store->end($id, \is_float($respondedAt) ? min($at, $respondedAt) : $at, $this->outcome($outcome));
        fclose($this->spans[$id]['lock']);
        unset($this->spans[$id]);
    }

    /**
     * @return array{status?: int, exception?: array{class: string, message: string, functions: list<string>}}
     */
    private function outcome(mixed $outcome): array
    {
        $exception = $outcome['exception'] ?? null;

        return array_filter([
            'status' => \is_int($outcome['status'] ?? null) ? $outcome['status'] : null,
            'exception' => \is_array($exception) && \is_string($exception['class'] ?? null) && \is_string($exception['message'] ?? null) ? [
                'class' => $exception['class'],
                'message' => $this->redactor->redact(mb_substr($exception['message'], start: 0, length: self::MAX_MESSAGE_LENGTH)),
                'functions' => array_values(array_filter((\is_array($exception['functions'] ?? null) ? $exception['functions'] : []), is_string(...))),
            ] : null,
        ], static fn (mixed $value): bool => null !== $value);
    }
}
