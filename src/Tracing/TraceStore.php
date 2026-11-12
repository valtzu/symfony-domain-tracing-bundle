<?php

namespace Valtzu\AiStacktrace\Tracing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps each span (one request to one service) as a JSON Lines file that grows while the request runs: a "request"
 * record, a step for each call of an application method with its arguments and its exit, and an "end" record. A step
 * is the array [function, line, call depth, microseconds since the request started], because it is the most frequent
 * record. Readers can follow the file while it is written. A trace index file lists the spans of each trace, in the
 * order they started.
 */
final readonly class TraceStore
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/traces')]
        private string $directory,
    ) {
    }

    public static function isValidId(string $id): bool
    {
        return 1 === preg_match('/^[0-9a-f-]{36}$/', $id);
    }

    public function has(string $id): bool
    {
        return self::isValidId($id) && is_file($this->path($id));
    }

    public function start(string $id, array $request): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }

        file_put_contents($this->path($id), '');
        $this->append($id, ['type' => 'request', 'startedAt' => microtime(true)] + $request);
        file_put_contents($this->tracePath($request['traceId']), $id."\n", \FILE_APPEND);
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

    public function end(string $id, float $at): void
    {
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

    /**
     * Asks for a description of a trace. Only the traces that someone looks at are described, because a description
     * costs AI usage.
     */
    public function requestDescription(string $rootId): void
    {
        if ($this->has($rootId) && !is_file($this->narrationPath($rootId))) {
            touch($this->requestedPath($rootId));
        }
    }

    /**
     * @return list<string> IDs of the traces that someone asked to describe and that have not been processed yet, the
     *                      most recently asked first
     */
    public function unprocessedRoots(): array
    {
        $roots = [];
        foreach (glob($this->directory.'/*.requested') ?: [] as $path) {
            $id = basename($path, '.requested');
            if (self::isValidId($id) && !is_file($this->narrationPath($id))) {
                $roots[$id] = filemtime($path);
            }
        }
        arsort($roots);

        return array_keys($roots);
    }

    /**
     * @return array{records: list<array>, offset: int}|null null when the trace does not exist
     */
    public function read(string $id, int $offset = 0): ?array
    {
        return self::isValidId($id) ? $this->readLines($this->path($id), $offset) : null;
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

    private function requestedPath(string $rootId): string
    {
        return $this->directory.'/'.$rootId.'.requested';
    }

    private function tracePath(string $traceId): string
    {
        return $this->directory.'/trace-'.$traceId.'.spans';
    }
}
