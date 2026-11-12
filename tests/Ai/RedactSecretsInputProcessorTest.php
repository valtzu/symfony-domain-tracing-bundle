<?php

namespace Valtzu\AiStacktrace\Tests\Ai;

use Valtzu\AiStacktrace\Ai\RedactSecretsInputProcessor;
use Valtzu\AiStacktrace\Ai\SecretRedactor;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RedactSecretsInputProcessorTest extends KernelTestCase
{
    public function testRedactsEveryMessage()
    {
        $input = new Input('opus', new MessageBag(
            Message::forSystem('Explain the trace.'),
            Message::ofUser('$token = \'abc123secret\';'),
            Message::ofAssistant('The key is sk-ant-api03-AbCdEfGhIjKlMnOpQrStUv.'),
        ));

        (new RedactSecretsInputProcessor(new SecretRedactor([])))->processInput($input);

        $messages = $input->getMessageBag()->getMessages();
        $this->assertSame('Explain the trace.', $messages[0]->getContent());
        $this->assertSame('$token = \'[redacted]\';', $messages[1]->asText());
        $this->assertSame('The key is [redacted].', $messages[2]->getContent()[0]->getText());
    }

    public function testRefusesContentItCannotCheck()
    {
        $input = new Input('opus', new MessageBag(Message::ofUser(new Image('binary', 'image/png'))));

        $this->expectException(\LogicException::class);

        (new RedactSecretsInputProcessor(new SecretRedactor([])))->processInput($input);
    }

    public function testTraceExplainerSendsNoSecrets()
    {
        $_SERVER['TEST_API_KEY'] = 'live-key-1234567890';
        $sent = null;
        self::getContainer()->set('ai_stacktrace.platform', MockPlatformFactory::createPlatform(static function ($model, $payload) use (&$sent) {
            $sent = json_encode($payload);

            return 'ok';
        }));

        self::getContainer()->get('ai.agent.trace_narrator')->call(new MessageBag(Message::ofUser(
            "\$client = new Client('live-key-1234567890');\n\$password = 'hunter2!';",
        )))->getContent();

        $this->assertStringNotContainsString('live-key-1234567890', $sent);
        $this->assertStringNotContainsString('hunter2!', $sent);
        $this->assertStringContainsString('[TEST_API_KEY redacted]', $sent);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['TEST_API_KEY']);
        parent::tearDown();
    }
}
