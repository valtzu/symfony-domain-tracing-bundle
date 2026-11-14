<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Tracing\Loops;

final class LoopsTest extends TestCase
{
    public function testKeepsTheFirstRoundOfALoopWithTheNumberOfRounds(): void
    {
        $steps = [self::step(1, 'import')];
        for ($row = 0; $row < 4; $row++) {
            $steps[] = self::step(2, 'importRow');
            $steps[] = self::step(3, 'validate');
            $steps[] = self::step(3, 'save');
        }
        $steps[] = self::step(2, 'report');

        static::assertSame([
            ['import', null],
            ['importRow', 4],
            ['validate', null],
            ['save', null],
            ['report', null],
        ], array_map(static fn (array $step): array => [$step['function'], $step['repeats'] ?? null], Loops::fold($steps)));
    }

    public function testFoldsALoopInsideTheFirstRoundOfAnotherLoop(): void
    {
        $steps = [];
        for ($order = 0; $order < 3; $order++) {
            $steps[] = self::step(1, 'placeOrder');
            for ($line = 0; $line < 5; $line++) {
                $steps[] = self::step(2, 'addLine');
            }
        }

        static::assertSame([['placeOrder', 3], ['addLine', 5]], array_map(static fn (array $step): array => [$step['function'], $step['repeats'] ?? null], Loops::fold($steps)));
    }

    public function testKeepsARoundThatTakesAnotherPath(): void
    {
        $steps = [];
        foreach (['save', 'save', 'save', 'reject', 'save', 'save', 'save'] as $outcome) {
            $steps[] = self::step(1, 'importRow');
            $steps[] = self::step(2, $outcome);
        }

        static::assertSame([
            ['importRow', 3],
            ['save', null],
            ['importRow', null],
            ['reject', null],
            ['importRow', 3],
            ['save', null],
        ], array_map(static fn (array $step): array => [$step['function'], $step['repeats'] ?? null], Loops::fold($steps)));
    }

    public function testKeepsARoundThatFailed(): void
    {
        $steps = [];
        for ($row = 0; $row < 7; $row++) {
            $steps[] = ['depth' => 1, 'function' => 'importRow', 'span' => 'row'.$row];
        }

        $folded = Loops::fold($steps, static fn (array $step): bool => 'row3' === $step['span']);

        static::assertSame([['row0', 3], ['row3', null], ['row4', 3]], array_map(static fn (array $step): array => [$step['span'], $step['repeats'] ?? null], $folded));
    }

    public function testFoldsALoopWhoseRoundMakesSeveralCalls(): void
    {
        $steps = [self::step(1, 'import')];
        for ($row = 0; $row < 5; $row++) {
            $steps[] = self::step(2, 'parseRow');
            $steps[] = self::step(2, 'validate');
            $steps[] = self::step(3, 'checkEmail');
            $steps[] = self::step(2, 'save');
        }
        $steps[] = self::step(2, 'report');

        static::assertSame([
            ['import', null, null],
            ['parseRow', 5, 3],
            ['validate', null, null],
            ['checkEmail', null, null],
            ['save', null, null],
            ['report', null, null],
        ], array_map(static fn (array $step): array => [$step['function'], $step['repeats'] ?? null, $step['loopCalls'] ?? null], Loops::fold($steps)));
    }

    public function testLeavesACallThatRepeatsTwice(): void
    {
        $steps = [self::step(1, 'save'), self::step(1, 'save')];

        static::assertSame($steps, Loops::fold($steps));
    }

    /**
     * @return array<string, mixed>
     */
    private static function step(int $depth, string $function): array
    {
        return ['depth' => $depth, 'function' => $function];
    }
}
