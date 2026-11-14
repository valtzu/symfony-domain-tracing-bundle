<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Tests\Ai;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AgentOverrideTest extends KernelTestCase
{
    /** @var list<array{string, string}> model name and payload of each call */
    private array $calls = [];

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'agent_override']);
        self::getContainer()->set('test.platform', MockPlatformFactory::createPlatform(function (Model $model, mixed $payload): string {
            $this->calls[] = [$model->getName(), json_encode($payload, \JSON_THROW_ON_ERROR)];

            return 'ok';
        }));
    }

    public function testTheProjectChoosesThePlatformAndTheModel(): void
    {
        $this->agent('ai.agent.domain_tracing')->call(new MessageBag(Message::ofUser('Name it')))->getContent();

        static::assertSame('other-model', $this->calls[0][0] ?? null);
    }

    public function testSecretsAreRemovedOnlyForTheAgentsOfTheBundle(): void
    {
        $secret = bin2hex(random_bytes(16));
        $_SERVER['TEST_API_KEY'] = $secret;
        try {
            $this->agent('ai.agent.domain_tracing')->call(new MessageBag(Message::ofUser($secret)))->getContent();
            $this->agent('ai.agent.project_agent')->call(new MessageBag(Message::ofUser($secret)))->getContent();
        } finally {
            unset($_SERVER['TEST_API_KEY']);
        }

        static::assertStringNotContainsString($secret, $this->calls[0][1] ?? '');
        static::assertStringContainsString($secret, $this->calls[1][1] ?? '');
    }

    private function agent(string $id): AgentInterface
    {
        $agent = self::getContainer()->get($id);
        static::assertInstanceOf(AgentInterface::class, $agent);

        return $agent;
    }
}
