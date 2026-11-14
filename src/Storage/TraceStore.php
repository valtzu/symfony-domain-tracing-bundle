<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Storage;

/**
 * Keeps each span (one request to one service) as a JSON Lines file that grows while the request runs, in the trace
 * directory of the bundle: a "request" record, which the recorder writes when the request starts, a step for each call of an application method with its arguments and its exit, an "outcome" record with the response status and the exception that the request did not handle, and an "end" record. A step
 * is the array [function, line, call depth, microseconds since the request started], because it is the most frequent
 * record. Readers can follow the file while it is written. A trace index file lists the spans of each trace, in the
 * order they started.
 */
final readonly class TraceStore
{
    public function __construct(
        private string $directory,
    ) {
    }

    public static function isValidId(string $id): bool
    {
        return 1 === preg_match('/^[0-9a-f]{16}$/', $id);
    }

    public function has(string $id): bool
    {
        return self::isValidId($id) && is_file($this->path($id));
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function createDirectory(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    /**
     * The lines of the application that ran during the request of the span, by file, from the code coverage.
     */
    public function linesPath(string $id): string
    {
        return $this->directory.'/'.$id.'.lines.json';
    }

    /**
     * The response status of the request of the span, and the exception that it did not handle.
     */
    public function outcomePath(string $id): string
    {
        return $this->directory.'/'.$id.'.outcome.json';
    }

    public function start(string $id, array $request): void
    {
        $this->createDirectory();

        file_put_contents($this->path($id), '');
        $this->append($id, ['type' => 'request', 'startedAt' => microtime(true)] + $request);
        file_put_contents($this->tracePath($request['traceId']), $id."\n", \FILE_APPEND);
    }

    /**
     * @return array|null the first record of the span, which the recorder wrote
     */
    public function request(string $id): ?array
    {
        if (!$this->has($id)) {
            return null;
        }
        $handle = fopen($this->path($id), 'r');
        $line = fgets($handle);
        fclose($handle);

        return \is_string($line) && str_ends_with($line, "\n") ? json_decode($line, true) : null;
    }

    public function hasEnded(string $id): bool
    {
        if (!$this->has($id)) {
            return false;
        }
        $handle = fopen($this->path($id), 'r');
        fseek($handle, max(0, filesize($this->path($id)) - 256));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        return str_contains($tail, '{"type":"end"');
    }

    /**
     * Removes all the records of the span but the first one, to read its Xdebug trace again.
     */
    public function restart(string $id): void
    {
        $request = $this->request($id);
        if (null !== $request) {
            file_put_contents($this->path($id), json_encode($request, \JSON_UNESCAPED_SLASHES)."\n");
        }
    }

    /**
     * Takes the lock for reading the Xdebug trace of a span, which is released when the returned handle is closed or
     * the process ends.
     *
     * @return resource|null null when another process holds the lock
     */
    public function lockSpan(string $id)
    {
        return $this->has($id) ? self::lock($this->path($id)) : null;
    }

    /**
     * Takes the lock for describing the trace that the given span started.
     *
     * @return resource|null null when another process holds the lock
     */
    public function lockDescription(string $rootId)
    {
        $traceId = $this->request($rootId)['traceId'] ?? '';

        return is_file($path = $this->tracePath($traceId)) ? self::lock($path) : null;
    }

    /**
     * @return array|null the index of the application code that the recorder wrote for the given version of the code
     */
    public function codeIndex(string $fingerprint): ?array
    {
        $path = $this->directory.'/codeindex-'.$fingerprint.'.json';

        return preg_match('/^[0-9a-f]{32}$/', $fingerprint) && is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    }

    /**
     * @return list<string> span IDs, in the order they started
     */
    public function spansOf(string $traceId): array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $traceId) || !is_file($path = $this->tracePath($traceId))) {
            return [];
        }

        return file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
    }

    public function addStep(string $id, int $function, int $line, int $depth, int $microseconds): void
    {
        file_put_contents($this->path($id), json_encode([$function, $line, $depth, $microseconds])."\n", \FILE_APPEND);
    }

    /**
     * The frames at the given call depth and deeper returned.
     */
    public function addExit(string $id, int $depth, float $at, array $details = []): void
    {
        $this->append($id, ['type' => 'exit', 'depth' => $depth, 'at' => $at] + $details);
    }

    public function addArguments(string $id, int $depth, array $arguments, float $at): void
    {
        $this->append($id, ['type' => 'arguments', 'depth' => $depth, 'values' => $arguments, 'at' => $at]);
    }

    /**
     * @param list<int> $lines the lines of the file that ran during the request
     */
    public function addExecutedLines(string $id, int $file, array $lines, float $at): void
    {
        $this->append($id, ['type' => 'lines', 'file' => $file, 'lines' => $lines, 'at' => $at]);
    }

    public function describeFile(string $id, array $file): void
    {
        $this->append($id, ['type' => 'file'] + $file);
    }

    public function describeFunction(string $id, array $function): void
    {
        $this->append($id, ['type' => 'function'] + $function);
    }

    /**
     * @param array{status?: int, exception?: array} $outcome how the request ended
     */
    public function end(string $id, float $at, array $outcome = []): void
    {
        if ($outcome) {
            $this->append($id, ['type' => 'outcome', 'at' => $at] + $outcome);
        }
        $this->append($id, ['type' => 'end', 'at' => $at]);
    }

    public function addNarration(string $rootId, array $record): void
    {
        file_put_contents($this->narrationPath($rootId), json_encode($record, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n", \FILE_APPEND);
    }

    /**
     * @return array{records: list<array>, offset: int}
     */
    public function readNarration(string $rootId, int $offset = 0): array
    {
        return $this->readLines($this->narrationPath($rootId), $offset) ?? ['records' => [], 'offset' => $offset];
    }

    /**
     * @return string the ID of the request that started the trace of the given request
     */
    public function rootOf(string $id): string
    {
        $request = $this->read($id)['records'][0] ?? [];

        return $this->spansOf($request['traceId'] ?? '')[0] ?? $id;
    }

    public function remove(string $id): void
    {
        if (self::isValidId($id)) {
            @unlink($this->path($id));
        }
    }

    /**
     * Removes the files of the traces, and of the Xdebug traces, that changed last before the given Unix timestamp.
     */
    public function removeOlderThan(int $timestamp): void
    {
        foreach ($this->files() as $path) {
            if ((@filemtime($path) ?: \PHP_INT_MAX) < $timestamp) {
                @unlink($path);
            }
        }
    }

    public function clear(): void
    {
        foreach ($this->files() as $path) {
            @unlink($path);
        }
    }

    public function isDescribed(string $rootId): bool
    {
        $path = $this->narrationPath($rootId);

        return is_file($path) && str_contains((string) file_get_contents($path, offset: max(0, filesize($path) - 200)), '{"type":"end"');
    }

    public function clearNarration(string $rootId): void
    {
        @unlink($this->narrationPath($rootId));
    }

    /**
     * @return array{records: list<array>, offset: int}|null null when the trace does not exist
     */
    public function read(string $id, int $offset = 0): ?array
    {
        return self::isValidId($id) ? $this->readLines($this->path($id), $offset) : null;
    }

    public function newestStartedAfter(float $timestamp): ?string
    {
        $newest = null;
        foreach (glob($this->directory.'/*.jsonl') ?: [] as $path) {
            if (filemtime($path) < floor($timestamp)) {
                continue;
            }

            $request = json_decode((string) fgets($handle = fopen($path, 'r')), true) ?? [];
            fclose($handle);
            $startedAt = $request['startedAt'] ?? 0;
            if (null === ($request['parentSpanId'] ?? null) && $startedAt > $timestamp && (null === $newest || $startedAt > $newest[1])) {
                $newest = [basename($path, '.jsonl'), $startedAt];
            }
        }

        return $newest[0] ?? null;
    }

    private function readLines(string $path, int $offset): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $handle = fopen($path, 'r');
        fseek($handle, $offset);
        $records = [];
        while (false !== $line = fgets($handle)) {
            if (!str_ends_with($line, "\n")) {
                break;
            }
            $records[] = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
            $offset += \strlen($line);
        }
        fclose($handle);

        return ['records' => $records, 'offset' => $offset];
    }

    private function append(string $id, array $record): void
    {
        file_put_contents($this->path($id), json_encode($record + ['at' => microtime(true)], \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n", \FILE_APPEND);
    }

    private function path(string $id): string
    {
        return $this->directory.'/'.$id.'.jsonl';
    }

    private function narrationPath(string $rootId): string
    {
        return $this->directory.'/'.$rootId.'.narration';
    }

    private function tracePath(string $traceId): string
    {
        return $this->directory.'/trace-'.$traceId.'.spans';
    }

    /**
     * @return list<string> the files that this store and the recorder write, and not the other files of the
     *                      directory
     */
    private function files(): array
    {
        $files = [];
        foreach (['*.jsonl', '*.lines.json', '*.outcome.json', '*.narration', 'trace-*.spans', 'trace.*.xt*', 'codeindex-*.json'] as $pattern) {
            $files = [...$files, ...glob($this->directory.'/'.$pattern) ?: []];
        }

        return $files;
    }

    /**
     * @return resource|null
     */
    private static function lock(string $path)
    {
        $handle = @fopen($path, 'r');
        if (false === $handle || !flock($handle, \LOCK_EX | \LOCK_NB)) {
            return null;
        }

        return $handle;
    }
}
