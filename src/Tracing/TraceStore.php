<?php

namespace App\Tracing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps each span (one request to one service) as a JSON Lines file that grows while the request runs: a "request"
 * record, one "step" record per executed line and an "end" record. Readers can follow the file while it is written.
 * A trace index file lists the spans of each trace, in the order they started.
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

    public function addStep(string $id, array $step): void
    {
        $this->append($id, ['type' => 'step'] + $step);
    }

    public function end(string $id, int $durationMs): void
    {
        $this->append($id, ['type' => 'end', 'durationMs' => $durationMs]);
    }

    /**
     * @return array{records: list<array>, offset: int}|null null when the trace does not exist
     */
    public function read(string $id, int $offset = 0): ?array
    {
        if (!self::isValidId($id) || !is_file($path = $this->path($id))) {
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

    private function tracePath(string $traceId): string
    {
        return $this->directory.'/trace-'.$traceId.'.spans';
    }
}
