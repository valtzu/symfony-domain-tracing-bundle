<?php

namespace Valtzu\DomainTracingBundle\Tests\Tracing;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Valtzu\DomainTracingBundle\Tracing\OperationNamer;

final class OperationNamerTest extends TestCase
{
    private const CACHED = ['service' => 'shop', 'function' => 'App\OrderLine->__construct', 'code' => 'function __construct() {}'];
    private const NEW = ['service' => 'shop', 'function' => 'App\CheckoutFailed->__construct', 'code' => 'function __construct() {}'];

    public function testGivesTheCachedNamesBeforeAskingForTheOthers()
    {
        $cache = new ArrayAdapter();
        iterator_to_array((new OperationNamer($this->agentAnswering('{"operation": 0, "name": "Add product to order"}'), $cache))->name([self::CACHED]));

        $asked = false;
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(function () use (&$asked) {
            $asked = true;

            return $this->execution('{"operation": 0, "name": "Report checkout failure"}');
        });

        $names = (new OperationNamer($agent, $cache))->name([self::CACHED, self::NEW]);

        $this->assertSame([self::CACHED['function'] => 'Add product to order'], [$names->key() => $names->current()]);
        $this->assertFalse($asked);
        $names->next();
        $this->assertSame([self::NEW['function'] => 'Report checkout failure'], [$names->key() => $names->current()]);
        $this->assertTrue($asked);
    }

    private function agentAnswering(string $answer): AgentInterface
    {
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn($this->execution($answer));

        return $agent;
    }

    private function execution(string $answer): ExecutionInterface
    {
        $execution = $this->createStub(ExecutionInterface::class);
        $execution->method('getContent')->willReturn($answer);

        return $execution;
    }
}
