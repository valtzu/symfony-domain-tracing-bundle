<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Xdebug;

final class CodeIndex
{
    /**
     * The application classes of the project in $root, from the PSR-4 autoload section of its composer.json, with the
     * methods of each class. The fingerprint changes when a file is added, removed or modified, or when the entrypoints
     * change. The methods are read from the tokens of the files, not with reflection, because loading every class of
     * the application can fail: a file can extend a class that does not exist, or declare functions that Composer
     * loads already.
     *
     * @param list<string> $entrypoints the classes that the framework calls first: controllers, commands, listeners,
     *                                  message handlers, from the tags of their services
     *
     * @return array{fingerprint: string, root?: string, namespaces?: list<string>, files?: array<string, array>}
     */
    public static function build(string $root, bool $full, array $entrypoints = []): array
    {
        /** @var array{autoload?: array{psr-4?: array<string, string|list<string>>}} $composer */
        $composer = json_decode((string) @file_get_contents($root.'/composer.json'), true) ?: [];

        $files = [];
        foreach ($composer['autoload']['psr-4'] ?? [] as $prefix => $directories) {
            foreach ((array) $directories as $directory) {
                if (!$directory = realpath($root.'/'.$directory)) {
                    continue;
                }
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    /** @var \SplFileInfo $file */
                    if ('php' === $file->getExtension()) {
                        $class = $prefix.strtr(substr($file->getPathname(), \strlen($directory) + 1, -4), '/', '\\');
                        $files[$file->getPathname()] = ['class' => $class, 'modified' => $file->getMTime()];
                    }
                }
            }
        }
        ksort($files);
        sort($entrypoints);
        $fingerprint = md5(json_encode([$files, $entrypoints], \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR));

        if (!$full) {
            return ['fingerprint' => $fingerprint];
        }

        $entrypoints = array_flip($entrypoints);
        foreach ($files as $path => &$file) {
            $source = (string) file_get_contents($path);
            $file['blob'] = sha1('blob '.\strlen($source)."\0".$source);
            $file['entrypoint'] = isset($entrypoints[$file['class']]);
            [$file['methods'], $file['closureLines']] = self::read(\PhpToken::tokenize($source));
        }

        return ['fingerprint' => $fingerprint, 'root' => $root, 'namespaces' => array_keys($composer['autoload']['psr-4'] ?? []), 'files' => $files];
    }

    /**
     * @param list<\PhpToken> $tokens
     *
     * @return array{array<string, array{int, int, list<string>}>, list<int>} the methods of the classes declared in
     *                                                                         the file, with their first and last line
     *                                                                         and the names of their parameters, and
     *                                                                         the lines of the closures
     */
    private static function read(array $tokens): array
    {
        $methods = [];
        $closureLines = [];
        $depth = 0;
        // The depth of the body of each class that is declared in the file, and of each anonymous class
        $classes = [];
        $pendingClass = null;
        $method = null;

        foreach ($tokens as $i => $token) {
            if ($token->isIgnorable()) {
                continue;
            }
            $next = self::next($tokens, $i);

            if ($token->is([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM]) && !self::previous($tokens, $i)?->is(\T_DOUBLE_COLON)) {
                $pendingClass = self::previous($tokens, $i)?->is(\T_NEW) ? 'anonymous' : 'named';
            } elseif ($token->is(\T_FN) || $token->is(\T_FUNCTION) && '(' === self::next($tokens, $i, skipReference: true)?->text) {
                $closureLines[$token->line] = $token->line;
            } elseif ($token->is(\T_FUNCTION) && null === $method && 'named' === (end($classes)[1] ?? null) && end($classes)[0] === $depth) {
                $method = ['name' => self::next($tokens, $i, skipReference: true)->text ?? '', 'start' => $token->line, 'parameters' => [], 'parenthesis' => 0];
            }

            if (null !== $method && null === ($method['body'] ?? null)) {
                if ('(' === $token->text) {
                    $method['parenthesis']++;
                } elseif (')' === $token->text) {
                    $method['parenthesis']--;
                } elseif ($token->is(\T_VARIABLE) && 1 === $method['parenthesis'] && !isset($method['default'])) {
                    $method['parameters'][] = substr($token->text, 1);
                } elseif ('=' === $token->text && 1 === $method['parenthesis']) {
                    $method['default'] = true;
                } elseif (',' === $token->text && 1 === $method['parenthesis']) {
                    unset($method['default']);
                } elseif (';' === $token->text && 0 === $method['parenthesis']) {
                    $methods[$method['name']] = [$method['start'], $token->line, $method['parameters']];
                    $method = null;
                } elseif ('{' === $token->text && 0 === $method['parenthesis']) {
                    $method['body'] = $depth + 1;
                }
            }

            if ('{' === $token->text || $token->is([\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
                if (null !== $pendingClass) {
                    $classes[] = [$depth, $pendingClass];
                    $pendingClass = null;
                }
            } elseif ('}' === $token->text) {
                if (null !== $method && ($method['body'] ?? null) === $depth) {
                    $methods[$method['name']] = [$method['start'], $token->line, $method['parameters']];
                    $method = null;
                }
                if ((end($classes)[0] ?? null) === $depth) {
                    array_pop($classes);
                }
                $depth--;
            }
        }

        return [$methods, array_values($closureLines)];
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private static function next(array $tokens, int $i, bool $skipReference = false): ?\PhpToken
    {
        while (null !== $token = $tokens[++$i] ?? null) {
            if (!$token->isIgnorable() && !($skipReference && '&' === $token->text)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private static function previous(array $tokens, int $i): ?\PhpToken
    {
        while (null !== $token = $tokens[--$i] ?? null) {
            if (!$token->isIgnorable()) {
                return $token;
            }
        }

        return null;
    }
}
