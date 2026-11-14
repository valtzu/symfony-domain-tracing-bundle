<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tracing;

/**
 * Finds the loops in the calls of a trace: a method that is called again and again at the same depth, such as for
 * each row of an import, and that makes the same calls each time. A loop is described by its first round, with the
 * number of rounds, instead of by each round with its own values. A round that makes other calls, such as for a row
 * that fails, is kept.
 */
final class Loops
{
    public const int MIN_ROUNDS = 3;

    /**
     * @param list<array> $steps calls in the order they were made, each with its "depth" and "function"
     *
     * @return list<array> the calls, with the first round of each loop only, whose first call has the number of
     *                     "repeats"
     */
    public static function fold(array $steps): array
    {
        $folded = [];
        $count = \count($steps);
        for ($i = 0; $i < $count; $i = $next) {
            $head = $steps[$i];
            $bodyEnd = self::roundEnd($steps, $i);
            $body = self::fold(\array_slice($steps, $i + 1, $bodyEnd - $i - 1));
            $rounds = 1;
            $next = $bodyEnd;
            while ($next < $count && $steps[$next]['depth'] === $head['depth'] && $steps[$next]['function'] === $head['function']) {
                $end = self::roundEnd($steps, $next);
                if (self::path(self::fold(\array_slice($steps, $next + 1, $end - $next - 1))) !== self::path($body)) {
                    break;
                }
                $next = $end;
                $rounds++;
            }
            if ($rounds < self::MIN_ROUNDS) {
                $next = $bodyEnd;
                $folded[] = $head;
            } else {
                $folded[] = $head + ['repeats' => $rounds];
            }
            array_push($folded, ...$body);
        }

        return $folded;
    }

    /**
     * @param list<array> $steps folded calls
     *
     * @return list<array{mixed, mixed}> the calls of a round, without the number of rounds of its inner loops
     */
    private static function path(array $steps): array
    {
        return array_map(static fn (array $step): array => [$step['depth'], $step['function']], $steps);
    }

    /**
     * @param list<array> $steps
     *
     * @return int the index after the calls that the call at $start made
     */
    private static function roundEnd(array $steps, int $start): int
    {
        $end = $start + 1;
        while ($end < \count($steps) && $steps[$end]['depth'] > $steps[$start]['depth']) {
            $end++;
        }

        return $end;
    }
}
