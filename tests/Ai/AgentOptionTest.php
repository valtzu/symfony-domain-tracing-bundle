<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Tests\Ai;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AgentOptionTest extends KernelTestCase
{
    public function testTheBundleUsesTheAgentThatTheProjectChooses(): void
    {
        self::bootKernel(['environment' => 'agent_option']);
        $calls = [];
        self::getContainer()->set('test.platform', MockPlatformFactory::createPlatform(static function (Model $model, mixed $payload) use (&$calls): string {
            $calls[] = [$model->getName(), json_encode($payload, \JSON_THROW_ON_ERROR)];

            return 'ok';
        }));

        static::assertFalse(self::getContainer()->has('ai.agent.domain_tracing'), 'The bundle defines no agent of its own');

        $secret = bin2hex(random_bytes(16));
        $_SERVER['TEST_API_KEY'] = $secret;
        try {
            $agent = self::getContainer()->get('domain_tracing.agent');
            static::assertInstanceOf(AgentInterface::class, $agent);
            $agent->call(new MessageBag(Message::ofUser($secret)))->getContent();
        } finally {
            unset($_SERVER['TEST_API_KEY']);
        }

        static::assertSame('project-model', $calls[0][0] ?? null);
        static::assertStringNotContainsString($secret, $calls[0][1]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function withoutAgent(): iterable
    {
        yield 'not defined' => ['no_agent', 'the AI agent "domain_tracing" is not defined'];
        yield 'option off' => ['agent_off', 'the "domain_tracing.agent" option is off'];
    }

    #[DataProvider('withoutAgent')]
    public function testDoesNotDescribeWithoutAnAgent(string $environment, string $message): void
    {
        self::bootKernel(['environment' => $environment]);
        $container = self::getContainer();

        static::assertFalse($container->getParameter('domain_tracing.describe'));
        static::assertFalse($container->has('domain_tracing.context_generate_command'));
        static::assertTrue($container->has('domain_tracing.trace_controller'), 'The traces are still shown');

        $agent = $container->get('domain_tracing.agent');
        static::assertInstanceOf(AgentInterface::class, $agent);
        try {
            $agent->call('Name it');
            static::fail('The agent is not called');
        } catch (RuntimeException $exception) {
            static::assertStringContainsString($message, $exception->getMessage());
        }
    }
}
