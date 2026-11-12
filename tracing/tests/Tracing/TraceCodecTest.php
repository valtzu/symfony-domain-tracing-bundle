<?php

namespace App\Tests\Tracing;

use App\Tracing\TraceCodec;
use PHPUnit\Framework\TestCase;

final class TraceCodecTest extends TestCase
{
    public function testRoundTripsRecordsAndLeavesAPartialRecordUnread()
    {
        $codec = new TraceCodec();
        $records = [
            ['type' => 'request', 'id' => 'e523f4bf-deab-4e5e-a0b2-aea3a167e498', 'service' => 'shop', 'method' => 'POST', 'uri' => '/', 'parentSpanId' => null, 'at' => 1000.5],
            ['type' => 'file', 'file' => 0, 'blob' => '96557814590ee2ada6205300c9a36a7fe08f486d', 'path' => 'src/Checkout/Pricing.php', 'at' => 1000.5],
            ['type' => 'function', 'fn' => 0, 'function' => 'App\Checkout\Pricing->applyDiscounts', 'file' => 0, 'line' => 18, 'endLine' => 45, 'at' => 1000.5],
            ['type' => 'step', 'fn' => 0, 'line' => 20, 'depth' => 9, 'at' => 1000.501234],
            ['type' => 'arguments', 'depth' => 9, 'at' => 1000.501234, 'values' => ['couponCode' => 'FREESHIP', 'customer' => ['@class' => 'App\Customer', 'id' => 'carol']]],
            ['type' => 'exit', 'depth' => 9, 'at' => 1000.502, 'return' => false],
            ['type' => 'exit', 'depth' => 8, 'at' => 1000.503],
            ['type' => 'end', 'at' => 1000.6],
        ];

        $data = implode('', array_map(static fn (array $record) => $codec->encode($record, 1000.5), $records));
        $partial = $codec->encode(['type' => 'step', 'fn' => 0, 'line' => 21, 'depth' => 9, 'at' => 1000.7], 1000.5);
        $decoded = $codec->decode($data.substr($partial, 0, -1), null);

        $this->assertSame(\strlen($data), $decoded['consumed']);
        $this->assertCount(\count($records), $decoded['records']);
        foreach ($records as $index => $record) {
            foreach ($record as $key => $value) {
                if ('at' === $key) {
                    $this->assertEqualsWithDelta($value, $decoded['records'][$index]['at'], 1e-6);
                } else {
                    $this->assertSame($value, $decoded['records'][$index][$key], $record['type'].'.'.$key);
                }
            }
        }
        $this->assertArrayNotHasKey('return', $decoded['records'][6]);
        // array header, type, index, bin header with 20 bytes of blob ID, string header with the path
        $this->assertSame(1 + 1 + 1 + 2 + 20 + 1 + \strlen('src/Checkout/Pricing.php'), \strlen($codec->encode($records[1], 1000.5)));
    }
}
