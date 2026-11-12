<?php

namespace App\Tracing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps each span (one request to one service) as a file that grows while the request runs: a "request" record, one
 * record per executed line, call and return, and an "end" record, in the binary format of {@see TraceCodec}. Readers
 * can follow the file while it is written. A trace index file lists the spans of each trace, in the order they
 * started. The descriptions that the worker adds are JSON Lines, next to the span file of the request that started
 * the trace.
 */
final class TraceStore
{
    private readonly TraceCodec $codec;

    /** @var array<string, float> start time by request, to encode and decode the times of its records */
    private array $startedAt = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/traces')]
        private readonly string $directory,
    ) {
        $this->codec = new TraceCodec();
    }

    public static function isValidId(string $id): bool
    {
        return 1 === preg_match('/^[0-9a-f-]{36}$/', $id);
    }

    public function start(string $id, array $request): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }

        file_put_contents($this->path($id), '');
        $this->startedAt[$id] = $request['at'];
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

    public function addStep(string $id, int $function, int $line, int $depth, float $at): void
    {
        $this->append($id, ['type' => 'step', 'fn' => $function, 'line' => $line, 'depth' => $depth, 'at' => $at]);
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
     * @return list<string> IDs of the requests that started a trace and have not been processed yet, oldest first
     */
    public function unprocessedRoots(): array
    {
        $roots = [];
        foreach (glob($this->directory.'/*.trace') ?: [] as $path) {
            $id = basename($path, '.trace');
            if (!self::isValidId($id) || is_file($this->narrationPath($id))) {
                continue;
            }
            $request = $this->readRequest($path);
            if (\array_key_exists('parentSpanId', $request) && null === $request['parentSpanId']) {
                $roots[$id] = $request['startedAt'];
            }
        }
        asort($roots);

        return array_keys($roots);
    }

    /**
     * @return array{records: list<array>, offset: int}|null null when the trace does not exist
     */
    public function read(string $id, int $offset = 0): ?array
    {
        if (!self::isValidId($id) || !is_file($path = $this->path($id))) {
            return null;
        }

        if (0 !== $offset && !isset($this->startedAt[$id])) {
            $this->startedAt[$id] = $this->readRequest($path)['at'] ?? 0.0;
        }

        $decoded = $this->codec->decode((string) file_get_contents($path, offset: $offset), 0 === $offset ? null : $this->startedAt[$id]);
        if (0 === $offset && $decoded['records']) {
            $this->startedAt[$id] = $decoded['records'][0]['at'];
        }

        return ['records' => $decoded['records'], 'offset' => $offset + $decoded['consumed']];
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
        foreach (glob($this->directory.'/*.trace') ?: [] as $path) {
            if (filemtime($path) < floor($timestamp)) {
                continue;
            }

            $request = $this->readRequest($path);
            $startedAt = $request['startedAt'] ?? 0;
            if (null === ($request['parentSpanId'] ?? null) && $startedAt > $timestamp && (null === $newest || $startedAt > $newest[1])) {
                $newest = [basename($path, '.trace'), $startedAt];
            }
        }

        return $newest[0] ?? null;
    }

    private function append(string $id, array $record): void
    {
        file_put_contents($this->path($id), $this->codec->encode($record, $this->startedAt[$id]), \FILE_APPEND);
    }

    private function readRequest(string $path): array
    {
        $record = $this->codec->decode((string) file_get_contents($path, length: 4096), null)['records'][0] ?? [];

        return 'request' === ($record['type'] ?? null) ? $record : [];
    }

    private function path(string $id): string
    {
        return $this->directory.'/'.$id.'.trace';
    }

    private function narrationPath(string $rootId): string
    {
        return $this->directory.'/'.$rootId.'.narration';
    }

    private function tracePath(string $traceId): string
    {
        return $this->directory.'/trace-'.$traceId.'.spans';
    }
}
