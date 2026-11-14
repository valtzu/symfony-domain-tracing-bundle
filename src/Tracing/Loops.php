<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tracing;

/**
 * Finds the loops in the calls of a trace: calls that are made again and again, in the same order, such as for each
 * row of an import. A round of a loop can be one call or several calls one after the other, each with the calls they
 * made in turn. A loop is described by its first round, with the number of rounds, instead of by each round with its
 * own values. A round that makes other calls or fails, such as for a row that is rejected, ends the loop and is kept.
 */
final class Loops
{
    public const int MIN_ROUNDS = 3;
    public const int MAX_CALLS_PER_ROUND = 20;

    /**
     * @param list<array>          $steps  calls in the order they were made, each with its "depth" and "function"
     * @param ?callable(array): bool $failed tells whether a call failed, which makes its round differ from the others
     *
     * @return list<array> the calls, with the first round of each loop only. The first call of a round has the
     *                     number of "repeats" of the round and the number of calls in the round, "loopCalls".
     */
    public static function fold(array $steps, ?callable $failed = null): array
    {
        $failed ??= static fn (array $step): bool => false;

        // The calls at the level of the first one, each with the calls it made, folded
        $units = [];
        $count = \count($steps);
        for ($i = 0; $i < $count; $i = $end) {
            $end = self::callEnd($steps, $i);
            $calls = [$steps[$i], ...self::fold(\array_slice($steps, $i + 1, $end - $i - 1), $failed)];
            $units[] = ['calls' => $calls, 'path' => serialize(array_map(static fn (array $step): array => [$step['depth'], $step['function'], $failed($step)], $calls))];
        }

        $folded = [];
        $paths = array_column($units, 'path');
        $unitCount = \count($units);
        for ($i = 0; $i < $unitCount; $i += $size * $rounds) {
            [$size, $rounds] = self::loopAt($paths, $i);
            foreach (\array_slice($units, $i, $size) as $index => $unit) {
                if (0 === $index && $rounds >= self::MIN_ROUNDS) {
                    $unit['calls'][0] += ['repeats' => $rounds, 'loopCalls' => $size];
                }
                array_push($folded, ...$unit['calls']);
            }
        }

        return $folded;
    }

    /**
     * @param list<string> $paths what each call at the level does
     *
     * @return array{int, int} the number of calls in a round of the loop that starts at $start, and its number of
     *                         rounds; a call that starts no loop is one round of one call
     */
    private static function loopAt(array $paths, int $start): array
    {
        $best = [1, 1];
        $count = \count($paths);
        for ($size = 1; $size <= self::MAX_CALLS_PER_ROUND && $start + $size * self::MIN_ROUNDS <= $count; $size++) {
            $round = \array_slice($paths, $start, $size);
            $rounds = 1;
            while ($start + ($rounds + 1) * $size <= $count && \array_slice($paths, $start + $rounds * $size, $size) === $round) {
                $rounds++;
            }
            // The shortest round that covers the most calls
            if ($rounds >= self::MIN_ROUNDS && $size * $rounds > $best[0] * $best[1]) {
                $best = [$size, $rounds];
            }
        }

        return $best;
    }

    /**
     * @param list<array> $steps
     *
     * @return int the index after the calls that the call at $start made
     */
    private static function callEnd(array $steps, int $start): int
    {
        $end = $start + 1;
        while ($end < \count($steps) && $steps[$end]['depth'] > $steps[$start]['depth']) {
            $end++;
        }

        return $end;
    }
}
