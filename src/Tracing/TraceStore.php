<?php

namespace App\Tracing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

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

    public function save(string $id, array $trace): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }

        $path = $this->path($id);
        file_put_contents($path.'.tmp', json_encode($trace, \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
        rename($path.'.tmp', $path);
    }

    public function load(string $id): ?array
    {
        if (!self::isValidId($id) || !is_file($path = $this->path($id))) {
            return null;
        }

        return json_decode(file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function path(string $id): string
    {
        return $this->directory.'/'.$id.'.json';
    }
}
