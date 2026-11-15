<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Valtzu\DomainTracingBundle\Tracing\SpanBuilder;
use Valtzu\DomainTracingBundle\Tracing\TraceTextFormatter;

final class TraceTextFormatterTest extends TestCase
{
    public function testWritesTheOperationsUnderTheirCallersAndTheEventsUnderTheirOperation(): void
    {
        static::assertSame(<<<'TEXT'
              +0.000s POST /orders [shop]
                        → Order controller: place  App\OrderController->place
              +0.100s   Pricing: apply discounts [shop]  App\Pricing->applyDiscounts
              +0.200s   Payment gateway: charge [shop]  App\PaymentGateway->charge
              +0.300s     POST /charges [payments]
                            → Charge controller: invoke  App\ChargeController->__invoke
              +0.400s     end of POST /charges [payments] after 0.100s
              +0.600s end of POST /orders [shop] after 0.600s
              +0.350s       ✗ Card declined
              +0.600s   • Order not placed: card declined
            TEXT, implode("\n", $this->format(new TraceTextFormatter())));
    }

    public function testWritesTheArgumentsWhenTheOperationStartsAndTheReturnValueWhenItEnds(): void
    {
        static::assertSame(<<<'TEXT'
              +0.000s POST /orders [shop]
                        → Order controller: place  App\OrderController->place
              +0.100s   Pricing: apply discounts [shop]  App\Pricing->applyDiscounts
                          arguments: {"coupon":"WELCOME10"}
                          returned: 0
              +0.200s   Payment gateway: charge [shop]  App\PaymentGateway->charge
                          arguments: {"cents":1500}
            TEXT, implode("\n", \array_slice($this->format(new TraceTextFormatter(values: true)), offset: 0, length: 7)));
    }

    public function testWritesTheReturnValueOfTheActionOnceWhenTheRequestEnds(): void
    {
        $formatter = new TraceTextFormatter(values: true);
        $builder = new SpanBuilder();
        $lines = [];
        foreach ([
            ['request' => 'shop', 'type' => 'request', 'at' => 1.0, 'spanId' => 'aaaaaaaaaaaaaaaa', 'traceId' => str_repeat('a', times: 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'GET', 'uri' => '/'],
            self::step('shop', 1.05, 'App\HomeController->show', 7),
            ['request' => 'shop', 'type' => 'exit', 'depth' => 7, 'return' => 'Welcome', 'at' => 1.5],
            ['request' => 'shop', 'type' => 'end', 'at' => 1.6],
        ] as $record) {
            foreach ($builder->add($record) as [$change, $span]) {
                $lines = [...$lines, ...$formatter->format('span', ['change' => $change] + $span)];
            }
        }

        static::assertSame(['            returned: "Welcome"'], array_values(preg_grep('/returned:/', $lines) ?: []));
    }

    /**
     * @return list<string>
     */
    private function format(TraceTextFormatter $formatter): array
    {
        $records = [
            ['request' => 'shop', 'type' => 'request', 'at' => 1.0, 'spanId' => 'aaaaaaaaaaaaaaaa', 'traceId' => str_repeat('a', times: 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'POST', 'uri' => '/orders?x=1'],
            self::step('shop', 1.05, 'App\OrderController->place', 7),
            self::step('shop', 1.1, 'App\Pricing->applyDiscounts', 8),
            ['request' => 'shop', 'type' => 'arguments', 'depth' => 8, 'values' => ['coupon' => 'WELCOME10'], 'at' => 1.1],
            ['request' => 'shop', 'type' => 'exit', 'depth' => 8, 'return' => 0, 'at' => 1.15],
            self::step('shop', 1.2, 'App\PaymentGateway->charge', 8),
            ['request' => 'shop', 'type' => 'arguments', 'depth' => 8, 'values' => ['cents' => 1500], 'at' => 1.2],
            ['request' => 'payments', 'type' => 'request', 'at' => 1.3, 'spanId' => 'bbbbbbbbbbbbbbbb', 'traceId' => str_repeat('a', times: 32), 'parentSpanId' => 'aaaaaaaaaaaaaaaa', 'service' => 'payments', 'method' => 'POST', 'uri' => '/charges'],
            self::step('payments', 1.32, 'App\ChargeController->__invoke', 7),
            ['request' => 'payments', 'type' => 'end', 'at' => 1.4],
            ['request' => 'shop', 'type' => 'end', 'at' => 1.6],
        ];

        $builder = new SpanBuilder();
        $lines = [];
        foreach ($records as $record) {
            foreach ($builder->add($record) as [$change, $span]) {
                $lines = [...$lines, ...$formatter->format('span', ['change' => $change] + $span)];
            }
        }
        $lines = [...$lines, ...$formatter->format('event', ['span' => 'bbbbbbbbbbbbbbbb', 'at' => 1.35, 'text' => 'Card declined', 'error' => true])];

        return [...$lines, ...$formatter->format('event', ['span' => 'aaaaaaaaaaaaaaaa', 'at' => 1.6, 'text' => 'Order not placed: card declined', 'error' => false])];
    }

    private static function step(string $request, float $at, string $function, int $depth): array
    {
        return ['request' => $request, 'type' => 'step', 'at' => $at, 'function' => $function, 'file' => 'src/App.php', 'line' => 1, 'depth' => $depth, 'code' => ''];
    }
}
