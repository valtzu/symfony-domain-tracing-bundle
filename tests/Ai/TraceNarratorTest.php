<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Ai;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Valtzu\DomainTracingBundle\Ai\TraceNarrator;
use Valtzu\DomainTracingBundle\Tracing\SpanBuilder;

final class TraceNarratorTest extends TestCase
{
    private const STEPS = [
        ['span' => 'request', 'at' => 1.0, 'depth' => 0],
        ['span' => 'greet', 'at' => 1.1, 'depth' => 1],
    ];
    private const SPANS = [
        'request' => ['kind' => 'server', 'service' => 'shop', 'name' => 'Show greeting page', 'function' => 'App\HelloController->__invoke', 'end' => 2.0],
        'greet' => ['kind' => 'internal', 'service' => 'shop', 'name' => 'Greet visitor', 'function' => 'App\Greeter->greet', 'end' => 1.2],
    ];

    public function testLeavesHowTheRequestEndedToTheOutcome()
    {
        /** @var list<array{span: string, at: float, text: string, error: bool}> $events */
        $events = iterator_to_array($this->narrate(
            '{"line": 1, "when": "return", "text": "Greeting is \"Hello\""}',
            '{"line": 0, "when": "return", "text": "Greeting page shown"}',
            '{"outcome": true, "text": "Greeting page shown to Ada"}',
        ), false);

        $this->assertSame(['Greeting is "Hello"', 'Greeting page shown to Ada'], array_column($events, 'text'));
        $this->assertSame(['greet', 'request'], array_column($events, 'span'));
    }

    public function testTellsHowTheRequestFailed(): void
    {
        $builder = new SpanBuilder();
        $steps = [];
        foreach ([
            ['request' => 'shop', 'type' => 'request', 'at' => 1.0, 'receivedAt' => 0.82, 'spanId' => 'aaaaaaaaaaaaaaaa', 'traceId' => str_repeat('a', times: 32), 'parentSpanId' => null, 'service' => 'shop', 'method' => 'POST', 'uri' => '/'],
            ['request' => 'shop', 'type' => 'step', 'at' => 1.1, 'function' => 'App\Checkout\CheckoutController->place', 'file' => 'src/App.php', 'line' => 1, 'depth' => 7, 'code' => ''],
            ['request' => 'shop', 'type' => 'step', 'at' => 1.2, 'function' => 'App\Checkout\PaymentGateway->charge', 'file' => 'src/App.php', 'line' => 1, 'depth' => 8, 'code' => ''],
            [
                'request' => 'shop',
                'type' => 'outcome',
                'at' => 1.5,
                'status' => 500,
                'exception' => [
                    'class' => 'Symfony\Component\HttpClient\Exception\TransportException',
                    'message' => 'Could not resolve host: payments.invalid',
                    'functions' => ['Symfony\Component\HttpClient\Response\CurlResponse::perform', 'App\Checkout\PaymentGateway->charge', 'App\Checkout\CheckoutController->place'],
                ],
            ],
            ['request' => 'shop', 'type' => 'end', 'at' => 1.5],
        ] as $record) {
            $builder->add($record);
            if ('step' === $record['type']) {
                $steps[] = ['span' => $builder->currentSpanOf('shop')] + $record;
            }
        }

        $prompt = '';
        $execution = $this->createStub(ExecutionInterface::class);
        $execution->method('getContent')->willReturn((static fn () => yield new TextDelta(''))());
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(static function (MessageBag $messages) use (&$prompt, $execution): ExecutionInterface {
            $prompt = (string) $messages->getUserMessage()?->asText();

            return $execution;
        });
        iterator_to_array((new TraceNarrator($agent))->narrate($steps, $builder->spans(), 'aaaaaaaaaaaaaaaa', [], true));

        static::assertStringContainsString('1: [shop]   "Payment gateway: charge" (App\Checkout\PaymentGateway->charge) threw Symfony\Component\HttpClient\Exception\TransportException: "Could not resolve host: payments.invalid"', $prompt);
        static::assertStringContainsString('[shop] POST / failed with status 500', $prompt);
        static::assertStringContainsString('[shop] POST / waited 180 ms for the service to start before it was handled.', $prompt);
    }

    private function narrate(string ...$lines): iterable
    {
        $execution = $this->createStub(ExecutionInterface::class);
        $execution->method('getContent')->willReturn((static fn () => yield new TextDelta(implode("\n", $lines)))());
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn($execution);

        return (new TraceNarrator($agent))->narrate(self::STEPS, self::SPANS, 'request', [], true);
    }
}
