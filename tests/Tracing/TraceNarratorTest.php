<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Valtzu\DomainTracingBundle\Tracing\TraceNarrator;

final class TraceNarratorTest extends TestCase
{
    private const STEPS = [
        ['span' => 'request', 'at' => 1.0, 'depth' => 0],
        ['span' => 'greet', 'at' => 1.1, 'depth' => 1],
    ];
    private const SPANS = [
        'request' => ['service' => 'shop', 'name' => 'Show greeting page', 'function' => 'App\HelloController->__invoke', 'end' => 2.0],
        'greet' => ['service' => 'shop', 'name' => 'Greet visitor', 'function' => 'App\Greeter->greet', 'end' => 1.2],
    ];

    public function testLeavesHowTheRequestEndedToTheOutcome()
    {
        $events = iterator_to_array($this->narrate(
            '{"line": 1, "when": "return", "text": "Greeting is \"Hello\""}',
            '{"line": 0, "when": "return", "text": "Greeting page shown"}',
            '{"outcome": true, "text": "Greeting page shown to Ada"}',
        ), false);

        $this->assertSame(['Greeting is "Hello"', 'Greeting page shown to Ada'], array_column($events, 'text'));
        $this->assertSame(['greet', 'request'], array_column($events, 'span'));
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
