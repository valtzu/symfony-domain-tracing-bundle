<?php

namespace App\Tracing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps each trace as a JSON Lines file that grows while the request runs: a "request" record, one "step" record per
 * executed line and an "end" record. Readers can follow the file while it is written.
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

            $startedAt = json_decode((string) fgets($handle = fopen($path, 'r')), true)['startedAt'] ?? 0;
            fclose($handle);
            if ($startedAt > $timestamp && (null === $newest || $startedAt > $newest[1])) {
                $newest = [basename($path, '.jsonl'), $startedAt];
            }
        }

        return $newest[0] ?? null;
    }

    private function append(string $id, array $record): void
    {
        file_put_contents($this->path($id), json_encode($record, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n", \FILE_APPEND);
    }

    private function path(string $id): string
    {
        return $this->directory.'/'.$id.'.jsonl';
    }
}
