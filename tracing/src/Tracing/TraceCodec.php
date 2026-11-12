<?php

namespace App\Tracing;

use MessagePack\BufferUnpacker;
use MessagePack\PackOptions;
use MessagePack\Packer;
use MessagePack\Type\Bin;

/**
 * The binary format of a span file: a sequence of MessagePack arrays, one per record, with positional fields. Times
 * are microseconds since the request started, and blob IDs are 20 raw bytes. MessagePack values delimit themselves,
 * so a reader can follow the file while it grows.
 *
 *     [0, {request fields, "at": float}]
 *     [1, file, blob, path]
 *     [2, fn, function, file, line, endLine]
 *     [3, fn, line, depth, µs]
 *     [4, depth, µs] or [4, depth, µs, return value]
 *     [5, depth, µs, {argument: value}]
 *     [6, µs]
 */
final class TraceCodec
{
    private const REQUEST = 0;
    private const FILE = 1;
    private const FUNCTION = 2;
    private const STEP = 3;
    private const EXIT = 4;
    private const ARGUMENTS = 5;
    private const END = 6;

    private readonly Packer $packer;

    public function __construct()
    {
        $this->packer = new Packer(PackOptions::FORCE_STR | PackOptions::DETECT_ARR_MAP | PackOptions::FORCE_FLOAT64);
    }

    public function encode(array $record, float $startedAt): string
    {
        $microseconds = static fn () => (int) round(($record['at'] - $startedAt) * 1e6);

        return $this->packer->pack(match ($record['type']) {
            'request' => [self::REQUEST, array_diff_key($record, ['type' => true])],
            'file' => [self::FILE, $record['file'], new Bin(hex2bin($record['blob'])), $record['path']],
            'function' => [self::FUNCTION, $record['fn'], $record['function'], $record['file'], $record['line'], $record['endLine']],
            'step' => [self::STEP, $record['fn'], $record['line'], $record['depth'], $microseconds()],
            'exit' => \array_key_exists('return', $record)
                ? [self::EXIT, $record['depth'], $microseconds(), $record['return']]
                : [self::EXIT, $record['depth'], $microseconds()],
            'arguments' => [self::ARGUMENTS, $record['depth'], $microseconds(), $record['values']],
            'end' => [self::END, $microseconds()],
        });
    }

    /**
     * @return array{records: list<array>, consumed: int} the complete records at the start of the data
     */
    public function decode(string $data, ?float $startedAt): array
    {
        $unpacker = new BufferUnpacker($data);
        $records = [];
        foreach ($unpacker->tryUnpack() as $value) {
            $record = $this->decodeRecord($value, $startedAt);
            $startedAt ??= $record['at'];
            $records[] = $record;
        }

        return ['records' => $records, 'consumed' => \strlen($data) - $unpacker->getRemainingCount()];
    }

    private function decodeRecord(array $value, ?float $startedAt): array
    {
        $at = static fn (int $microseconds) => $startedAt + $microseconds / 1e6;

        return match ($value[0]) {
            self::REQUEST => ['type' => 'request'] + $value[1],
            self::FILE => ['type' => 'file', 'file' => $value[1], 'blob' => bin2hex($value[2]), 'path' => $value[3], 'at' => $startedAt],
            self::FUNCTION => ['type' => 'function', 'fn' => $value[1], 'function' => $value[2], 'file' => $value[3], 'line' => $value[4], 'endLine' => $value[5], 'at' => $startedAt],
            self::STEP => ['type' => 'step', 'fn' => $value[1], 'line' => $value[2], 'depth' => $value[3], 'at' => $at($value[4])],
            self::EXIT => ['type' => 'exit', 'depth' => $value[1], 'at' => $at($value[2])] + (\array_key_exists(3, $value) ? ['return' => $value[3]] : []),
            self::ARGUMENTS => ['type' => 'arguments', 'depth' => $value[1], 'at' => $at($value[2]), 'values' => $value[3]],
            self::END => ['type' => 'end', 'at' => $at($value[1])],
        };
    }
}
