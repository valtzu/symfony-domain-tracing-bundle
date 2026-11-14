<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Ai;

/**
 * Puts the context into the configuration file of the bundle as text, so that the other options and the comments of
 * the file stay as they are.
 */
final class ContextConfigFile
{
    private const int WRAP_WIDTH = 108;

    /**
     * @return string|null the new contents of the file, or null when the file has no "domain_tracing" key to put it in
     */
    public static function withContext(?string $yaml, string $context): ?string
    {
        if (null === $yaml) {
            return "when@dev:\n    domain_tracing:\n".self::block($context, '        ');
        }

        $lines = explode(separator: "\n", string: $yaml);
        $root = self::find($lines, '/^(\s*)domain_tracing:\s*$/', 0);
        if (null === $root) {
            return null;
        }

        $rootIndent = self::indent($lines[$root] ?? '');
        $rootEnd = self::endOfBlock($lines, $root, $rootIndent);
        $firstChild = self::find(\array_slice($lines, offset: 0, length: $rootEnd), '/\S/', $root + 1);
        $childIndent = null === $firstChild ? $rootIndent.'    ' : self::indent($lines[$firstChild] ?? '');
        $existing = self::find(\array_slice($lines, offset: 0, length: $rootEnd), '/^'.$childIndent.'context:/', $root + 1);

        $start = $existing ?? $root + 1;
        $length = null === $existing ? 0 : self::endOfBlock($lines, $existing, $childIndent) - $existing;
        array_splice($lines, $start, $length, explode(separator: "\n", string: rtrim(self::block($context, $childIndent), characters: "\n")));

        return implode("\n", $lines);
    }

    private static function block(string $context, string $indent): string
    {
        $text = wordwrap(preg_replace('/[ \t]*\R/', replacement: "\n", subject: trim($context)) ?? $context, width: self::WRAP_WIDTH);

        return $indent."context: |\n".implode('', array_map(
            static fn (string $line): string => '' === $line ? "\n" : $indent.'    '.$line."\n",
            explode(separator: "\n", string: $text),
        ));
    }

    /**
     * @param list<string> $lines
     */
    private static function find(array $lines, string $pattern, int $from): ?int
    {
        foreach (\array_slice($lines, $from, preserve_keys: true) as $index => $line) {
            if (1 === preg_match($pattern, $line)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     *
     * @return int the index after the last line that belongs to the key at $start, trailing blank lines excluded
     */
    private static function endOfBlock(array $lines, int $start, string $indent): int
    {
        $end = $start + 1;
        foreach (\array_slice($lines, $start + 1, preserve_keys: true) as $i => $line) {
            if ('' === trim($line)) {
                continue;
            }
            if (\strlen(self::indent($line)) <= \strlen($indent)) {
                break;
            }
            $end = $i + 1;
        }

        return $end;
    }

    private static function indent(string $line): string
    {
        return substr($line, offset: 0, length: \strlen($line) - \strlen(ltrim($line)));
    }
}
