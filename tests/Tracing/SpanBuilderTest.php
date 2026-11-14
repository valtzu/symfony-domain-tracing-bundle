<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use Valtzu\DomainTracingBundle\Tracing\SpanBuilder;
use PHPUnit\Framework\TestCase;

final class SpanBuilderTest extends TestCase
{
    public function testBuildsACallTreeAcrossServicesWithTheEntrypointInTheRequestSpan()
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
            null === $span['parentId'] ? null : $builder->span($span['parentId'])['name'] ?? null,
            $span['start'],
            $span['end'],
        ], array_values($builder->spans()));

        $this->assertSame([
            ['Checkout: place order', null, 1.0, 2.0],
            ['Pricing: apply discounts', 'Checkout: place order', 1.2, 1.4],
            ['Payment gateway: charge', 'Checkout: place order', 1.5, 1.9],
            ['Charge controller: invoke', 'Payment gateway: charge', 1.6, 1.8],
        ], $tree);
    }

    public function testASpanDoesNotEndBeforeItStarts()
    {
        $builder = new SpanBuilder();
        $records = [
            ['request' => 'shop', 'type' => 'request', 'at' => 1.0, 'spanId' => 'aaaaaaaaaaaaaaaa', 'traceId' => str_repeat('a', 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'GET', 'uri' => '/'],
            self::step('shop', 1.1, 'App\Shop->show', 7),
            // The time of a call is rounded to the microsecond, and the time of its return is not
            self::step('shop', 1.2000005, 'App\Catalog->products', 8),
            ['request' => 'shop', 'type' => 'exit', 'depth' => 8, 'at' => 1.2000001],
            ['request' => 'shop', 'type' => 'end', 'at' => 1.3],
        ];
        foreach ($records as $record) {
            $builder->add($record);
        }

        foreach ($builder->spans() as $span) {
            $this->assertGreaterThanOrEqual($span['start'], $span['end'], $span['name']);
        }
    }

    public function testGivesEachSpanTheLinesOfItsMethodThatRan()
    {
        $builder = new SpanBuilder();
        $records = [
            ['request' => 'shop', 'type' => 'request', 'at' => 1.0, 'spanId' => 'aaaaaaaaaaaaaaaa', 'traceId' => str_repeat('a', 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'POST', 'uri' => '/'],
            ['request' => 'shop', 'type' => 'function', 'function' => 'App\Shipping->apply', 'file' => 'src/Shipping.php', 'line' => 13, 'endLine' => 22, 'code' => '', 'at' => 1.1],
            ['request' => 'shop', 'type' => 'function', 'function' => 'App\Fraud->check', 'file' => 'src/Fraud.php', 'line' => 19, 'endLine' => 24, 'code' => '', 'at' => 1.1],
            self::step('shop', 1.1, 'App\Shipping->apply', 7),
            ['request' => 'shop', 'type' => 'exit', 'depth' => 7, 'at' => 1.2],
            self::step('shop', 1.3, 'App\Fraud->check', 7),
            ['request' => 'shop', 'type' => 'exit', 'depth' => 7, 'at' => 1.4],
            ['request' => 'shop', 'type' => 'lines', 'file' => 'src/Shipping.php', 'lines' => [8, 15, 17, 18, 21, 30], 'at' => 1.5],
        ];
        foreach ($records as $record) {
            $builder->add($record);
        }

        $lines = array_column(array_values($builder->spans()), 'executedLines', 'function');

        $this->assertSame(['App\Shipping->apply' => [15, 17, 18, 21]], $lines);
    }

    public function testStartsTheSpanOfARequestWhenTheRequestArrives(): void
    {
        $builder = new SpanBuilder();
        $builder->add(['request' => 'shop', 'type' => 'request', 'at' => 1.0, 'receivedAt' => 0.8, 'spanId' => 'aaaaaaaaaaaaaaaa', 'traceId' => str_repeat('a', 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'POST', 'uri' => '/']);

        $span = $builder->span('aaaaaaaaaaaaaaaa');
        $this->assertNotNull($span);
        $this->assertSame([0.8, 1.0], [$span['start'], $span['handledAt']]);
    }

    public function testUpdatesTheSpanWhenItsArgumentsArrive(): void
    {
        $builder = new SpanBuilder();
        $builder->add(['request' => 'shop', 'type' => 'request', 'at' => 1.0, 'spanId' => 'aaaaaaaaaaaaaaaa', 'traceId' => str_repeat('a', 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'POST', 'uri' => '/']);
        $builder->add(self::step('shop', 1.1, 'App\Checkout\Checkout->placeOrder', 7));
        $builder->add(self::step('shop', 1.2, 'App\Checkout\Pricing->applyDiscounts', 8));

        $changes = $builder->add(['request' => 'shop', 'type' => 'arguments', 'depth' => 8, 'values' => ['coupon' => 'WELCOME10'], 'at' => 1.2]);

        $this->assertSame([['open', 'Pricing: apply discounts', ['coupon' => 'WELCOME10']]], array_map(static fn (array $change) => [$change[0], $change[1]['name'], $change[1]['arguments']], $changes));
    }

    private static function step(string $request, float $at, string $function, int $depth): array
    {
        return ['request' => $request, 'type' => 'step', 'at' => $at, 'function' => $function, 'depth' => $depth, 'file' => 'src/X.php', 'line' => 1, 'code' => ''];
    }
}
