<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\Ai;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Valtzu\DomainTracingBundle\Ai\AgentWithoutTools;

final class AgentWithoutToolsTest extends TestCase
{
    public function testCallsTheAgentWithAnEmptyListOfTools(): void
    {
        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())->method('call')->with('Name it', ['tools' => [], 'stream' => true])->willReturn($this->createStub(ExecutionInterface::class));

        (new AgentWithoutTools($agent))->call('Name it', ['stream' => true]);
    }
}
