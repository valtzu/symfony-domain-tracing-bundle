<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Ai;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Valtzu\DomainTracingBundle\Ai\OperationNamer;

final class OperationNamerTest extends TestCase
{
    private const CACHED = ['service' => 'shop', 'function' => 'App\OrderLine->__construct', 'code' => 'function __construct() {}'];
    private const NEW = ['service' => 'shop', 'function' => 'App\CheckoutFailed->__construct', 'code' => 'function __construct() {}'];
    private const OTHER = ['service' => 'shop', 'function' => 'App\Inventory->reserve', 'code' => 'function reserve() {}'];

    public function testGivesTheCachedNamesBeforeAskingForTheOthers()
    {
        $cache = new ArrayAdapter();
        iterator_to_array((new OperationNamer($this->agentAnswering(['{"operation": 0, "name": "Add product to order"}']), $cache))->name([self::CACHED]));

        $asked = false;
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturnCallback(function () use (&$asked) {
            $asked = true;

            return $this->execution(['{"operation": 0, "name": "Report checkout failure"}']);
        });

        $names = (new OperationNamer($agent, $cache))->name([self::CACHED, self::NEW]);

        $this->assertSame([self::CACHED['function'] => 'Add product to order'], [$names->key() => $names->current()]);
        $this->assertFalse($asked);
        $names->next();
        $this->assertSame([self::NEW['function'] => 'Report checkout failure'], [$names->key() => $names->current()]);
        $this->assertTrue($asked);
    }

    public function testGivesEachNameAsSoonAsItsLineOfTheAnswerArrives()
    {
        $received = [];
        $answer = (function () use (&$received) {
            foreach (["{\"operation\": 0, \"name\": \"Report checkout failure\"}\n{\"operation\"", ': 1, "name": "Reserve stock"}'] as $chunk) {
                $received[] = $chunk;
                yield new TextDelta($chunk);
            }
        })();
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn($this->execution($answer));

        $names = (new OperationNamer($agent, new ArrayAdapter()))->name([self::NEW, self::OTHER]);

        $this->assertSame([self::NEW['function'] => 'Report checkout failure'], [$names->key() => $names->current()]);
        $this->assertCount(1, $received);
        $names->next();
        $this->assertSame([self::OTHER['function'] => 'Reserve stock'], [$names->key() => $names->current()]);
    }

    /**
     * @param iterable<string|TextDelta> $answer
     */
    private function agentAnswering(iterable $answer): AgentInterface
    {
        $agent = $this->createStub(AgentInterface::class);
        $agent->method('call')->willReturn($this->execution($answer));

        return $agent;
    }

    /**
     * @param iterable<string|TextDelta> $answer
     */
    private function execution(iterable $answer): ExecutionInterface
    {
        $deltas = (static function () use ($answer) {
            foreach ($answer as $delta) {
                yield $delta instanceof TextDelta ? $delta : new TextDelta($delta);
            }
        })();
        $execution = $this->createStub(ExecutionInterface::class);
        $execution->method('getContent')->willReturn($deltas);

        return $execution;
    }
}
