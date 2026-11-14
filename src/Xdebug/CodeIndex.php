<?php

namespace Valtzu\DomainTracingBundle\Xdebug;

final class CodeIndex
{
    /**
     * The application classes of the project in $root, from the PSR-4 autoload section of its composer.json, with the
     * methods of each class. The fingerprint changes when a file is added, removed or modified. The full index needs the
     * autoloader of the application, so it is built at the end of the request.
     *
     * @return array{fingerprint: string, root?: string, namespaces?: list<string>, files?: array<string, array>}
     */
    public static function build(string $root, bool $full): array
    {
        $composer = json_decode((string) @file_get_contents($root.'/composer.json'), true) ?: [];

        $files = [];
        foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $directories) {
            foreach ((array) $directories as $directory) {
                if (!$directory = realpath($root.'/'.$directory)) {
                    continue;
                }
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ('php' === $file->getExtension()) {
                        $class = $prefix.strtr(substr($file->getPathname(), \strlen($directory) + 1, -4), '/', '\\');
                        $files[$file->getPathname()] = ['class' => $class, 'modified' => $file->getMTime()];
                    }
                }
            }
        }
        ksort($files);
        $fingerprint = md5(json_encode($files));

        if (!$full) {
            return ['fingerprint' => $fingerprint];
        }

        foreach ($files as $path => &$file) {
            $source = file_get_contents($path);
            $file['blob'] = sha1('blob '.\strlen($source)."\0".$source);
            $file['methods'] = [];
            $file['entrypoint'] = false;
            $file['closureLines'] = [];

            $tokens = \PhpToken::tokenize($source);
            foreach ($tokens as $i => $token) {
                $next = $i;
                while (($nextToken = $tokens[++$next] ?? null) && ($nextToken->isIgnorable() || '&' === $nextToken->text)) {
                }
                if ($token->is(\T_FN) || $token->is(\T_FUNCTION) && '(' === $nextToken?->text) {
                    $file['closureLines'][$token->line] = $token->line;
                }
            }
            $file['closureLines'] = array_values($file['closureLines']);

            if (!class_exists($file['class']) && !interface_exists($file['class']) && !trait_exists($file['class'])) {
                continue;
            }
            $class = new \ReflectionClass($file['class']);
            $file['entrypoint'] = (bool) ($class->getAttributes('Symfony\Component\Console\Attribute\AsCommand') ?: $class->getAttributes('Symfony\Component\Routing\Attribute\Route'));
            foreach ($class->getMethods() as $method) {
                if ($method->getFileName() === $path) {
                    $file['methods'][$method->getName()] = [$method->getStartLine(), $method->getEndLine(), array_map(static fn ($parameter) => $parameter->getName(), $method->getParameters())];
                    $file['entrypoint'] = $file['entrypoint'] || $method->getAttributes('Symfony\Component\Routing\Attribute\Route');
                }
            }
        }

        return ['fingerprint' => $fingerprint, 'root' => $root, 'namespaces' => array_keys($composer['autoload']['psr-4'] ?? []), 'files' => $files];
    }
}
