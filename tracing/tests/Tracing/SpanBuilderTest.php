<?php

namespace App\Tests\Tracing;

use App\Tracing\SpanBuilder;
use PHPUnit\Framework\TestCase;

final class SpanBuilderTest extends TestCase
{
    public function testBuildsACallTreeAcrossServices()
    {
        $builder = new SpanBuilder();
        $records = [
            ['request' => 'shop', 'type' => 'request', 'at' => 1.0, 'spanId' => 'aaaaaaaaaaaaaaaa', 'traceId' => str_repeat('a', 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'POST', 'uri' => '/?x=1'],
            self::step('shop', 1.1, 'App\Checkout\Checkout->placeOrder', 7),
            self::step('shop', 1.2, 'App\Checkout\Pricing->applyDiscounts', 8),
            self::step('shop', 1.3, 'App\Checkout\Pricing->applyDiscounts', 8),
            self::step('shop', 1.35, 'App\Checkout\Pricing::{closure:/app/src/Checkout/Pricing.php:20-20}', 10),
            self::step('shop', 1.4, 'App\Checkout\Checkout->placeOrder', 7),
            self::step('shop', 1.5, 'App\Checkout\PaymentGateway->charge', 8),
            ['request' => 'payments', 'type' => 'request', 'at' => 1.6, 'spanId' => 'bbbbbbbbbbbbbbbb', 'traceId' => str_repeat('a', 32), 'parentSpanId' => 'aaaaaaaaaaaaaaaa', 'service' => 'payments', 'method' => 'POST', 'uri' => '/charges'],
            self::step('payments', 1.7, 'App\Payments\ChargeController->__invoke', 7),
            ['request' => 'payments', 'type' => 'end', 'at' => 1.8],
            self::step('shop', 1.9, 'App\Checkout\Checkout->placeOrder', 7),
            ['request' => 'shop', 'type' => 'exit', 'depth' => 6, 'at' => 1.95],
            ['request' => 'shop', 'type' => 'end', 'at' => 2.0],
        ];
        foreach ($records as $record) {
            $builder->add($record);
        }

        $tree = array_map(static fn (array $span) => [
            $span['name'],
            null === $span['parentId'] ? null : $builder->span($span['parentId'])['name'],
            $span['start'],
            $span['end'],
        ], array_values($builder->spans()));

        $this->assertSame([
            ['POST /', null, 1.0, 2.0],
            ['Checkout: place order', 'POST /', 1.1, 1.95],
            ['Pricing: apply discounts', 'Checkout: place order', 1.2, 1.4],
            ['Payment gateway: charge', 'Checkout: place order', 1.5, 1.9],
            ['POST /charges', 'Payment gateway: charge', 1.6, 1.8],
            ['Charge controller: invoke', 'POST /charges', 1.7, 1.8],
        ], $tree);
    }

    private static function step(string $request, float $at, string $function, int $depth): array
    {
        return ['request' => $request, 'type' => 'step', 'at' => $at, 'function' => $function, 'depth' => $depth, 'file' => 'src/X.php', 'line' => 1, 'code' => ''];
    }
}
