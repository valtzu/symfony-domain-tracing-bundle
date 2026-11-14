<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Storage;

use Symfony\Component\Process\Process;

/**
 * Finds the contents of source files by git blob ID: in its own directory, in the configured git repositories, and in
 * the files of their working trees that are not committed, which are the files of a local development setup.
 */
final class BlobStore
{
    private const SECONDS_BETWEEN_WORKING_TREE_SCANS = 1.0;

    /** @var array<string, string|null> */
    private array $blobs = [];
    private float $lastScan = 0.0;

    /** @var list<string> */
    private readonly array $repositories;

    public function __construct(
        private readonly string $directory,
        ?string $repositories,
    ) {
        $this->repositories = array_values(array_filter(array_map(trim(...), explode(',', (string) $repositories))));
    }

    public static function id(string $contents): string
    {
        return sha1('blob '.\strlen($contents)."\0".$contents);
    }

    public function get(string $id): ?string
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $id)) {
            return null;
        }

        if (\array_key_exists($id, $this->blobs) && null !== $this->blobs[$id]) {
            return $this->blobs[$id];
        }

        if (is_file($path = $this->directory.'/'.$id) && false !== $contents = file_get_contents($path)) {
            // The blobs expire by the time they were last read
            @touch($path);

            return $this->blobs[$id] = $contents;
        }

        foreach ($this->repositories as $repository) {
            $process = new Process(['git', '-C', $repository, 'cat-file', 'blob', $id]);
            if (0 === $process->run()) {
                return $this->blobs[$id] = $process->getOutput();
            }
        }

        if (microtime(true) - $this->lastScan >= self::SECONDS_BETWEEN_WORKING_TREE_SCANS) {
            $this->scanWorkingTrees();
            if (is_file($path) && false !== $contents = file_get_contents($path)) {
                return $this->blobs[$id] = $contents;
            }
        }

        return $this->blobs[$id] = null;
    }

    public function put(string $contents): string
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }

        $id = self::id($contents);
        if (!is_file($path = $this->directory.'/'.$id)) {
            // Another process can write the same blob meanwhile
            $temporary = $path.'.'.bin2hex(random_bytes(4));
            file_put_contents($temporary, $contents);
            rename($temporary, $path);
        }

        return $id;
    }

    /**
     * Removes the blobs that were not written or read since the given time, as the traces expire.
     */
    public function removeOlderThan(int $timestamp): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            if ((int) @filemtime($path) < $timestamp) {
                @unlink($path);
            }
        }
    }

    public function clear(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $path) {
            @unlink($path);
        }
    }

    private function scanWorkingTrees(): void
    {
        $this->lastScan = microtime(true);

        foreach ($this->repositories as $repository) {
            $process = new Process(['git', '-C', $repository, 'status', '--porcelain=v1', '-z', '--untracked-files=all']);
            if (0 !== $process->run()) {
                continue;
            }

            $entries = explode("\0", rtrim($process->getOutput(), "\0"));
            while (null !== $entry = array_shift($entries)) {
                if ('' === $entry) {
                    continue;
                }
                if ('R' === $entry[0] || 'C' === $entry[0]) {
                    array_shift($entries);
                }
                if (is_file($file = $repository.'/'.substr($entry, 3)) && false !== $contents = file_get_contents($file)) {
                    $this->put($contents);
                }
            }
        }
    }
}
