<?php

namespace App\Tracing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire('%kernel.project_dir%/var/blobs')]
        private readonly string $directory,
        #[Autowire('%env(default::TRACE_GIT_REPOSITORIES)%')]
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

        if (is_file($path = $this->directory.'/'.$id)) {
            return $this->blobs[$id] = file_get_contents($path);
        }

        foreach ($this->repositories as $repository) {
            $process = new Process(['git', '-C', $repository, 'cat-file', 'blob', $id]);
            if (0 === $process->run()) {
                return $this->blobs[$id] = $process->getOutput();
            }
        }

        if (microtime(true) - $this->lastScan >= self::SECONDS_BETWEEN_WORKING_TREE_SCANS) {
            $this->scanWorkingTrees();
            if (is_file($path)) {
                return $this->blobs[$id] = file_get_contents($path);
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
            file_put_contents($path.'.tmp', $contents);
            rename($path.'.tmp', $path);
        }

        return $id;
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
                if (is_file($file = $repository.'/'.substr($entry, 3))) {
                    $this->put(file_get_contents($file));
                }
            }
        }
    }
}
