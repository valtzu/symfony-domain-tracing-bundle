<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Valtzu\DomainTracingBundle\Ai\AgentWithoutTools;
use Valtzu\DomainTracingBundle\DependencyInjection\AgentPass;

final class AgentPassTest extends TestCase
{
    public function testCallsAnAgentOnClaudeCodeWithoutTools(): void
    {
        $container = $this->container(['Symfony\AI\Platform\Bridge\ClaudeCode\Factory', 'createPlatform']);

        (new AgentPass())->process($container);

        static::assertSame(AgentWithoutTools::class, $container->getDefinition('domain_tracing.agent')->getClass());
        static::assertTrue($container->getParameter('domain_tracing.describe'));
    }

    public function testLeavesTheToolsOfOtherPlatformsToTheirConfiguration(): void
    {
        $container = $this->container(['Symfony\AI\Platform\Bridge\Anthropic\Factory', 'createPlatform']);

        (new AgentPass())->process($container);

        static::assertSame('ai.agent.domain_tracing', (string) $container->getAlias('domain_tracing.agent'));
    }

    /**
     * @param array{string, string} $platformFactory
     */
    private function container(array $platformFactory): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('domain_tracing.trace_controller');
        $container->register('app.platform')->setFactory($platformFactory);
        $container->register('ai.agent.domain_tracing', 'Symfony\AI\Agent\Agent')->setArgument('$platform', new Reference('app.platform'));
        $container->setAlias('domain_tracing.agent', 'ai.agent.domain_tracing');

        return $container;
    }
}
