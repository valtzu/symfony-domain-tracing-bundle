<?php

namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\AI\Agent\Attribute\AsInputProcessor;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Removes secrets from every message that the agents of the bundle send. It runs after the other input processors, and it refuses
 * messages it cannot check, such as files or tool results, instead of sending them unchecked.
 */
#[AsInputProcessor(agent: 'ai.agent.domain_tracing_operation_namer', priority: -1000)]
#[AsInputProcessor(agent: 'ai.agent.domain_tracing_trace_narrator', priority: -1000)]
#[AsInputProcessor(agent: 'ai.agent.domain_tracing_context_writer', priority: -1000)]
final readonly class RedactSecretsInputProcessor implements InputProcessorInterface
{
    public function __construct(
        private SecretRedactor $redactor,
    ) {
    }

    public function processInput(Input $input): void
    {
        $input->setMessageBag(new MessageBag(...array_map($this->redactMessage(...), $input->getMessageBag()->getMessages())));
    }

    private function redactMessage(MessageInterface $message): MessageInterface
    {
        return match (true) {
            $message instanceof SystemMessage && \is_string($message->getContent()) => new SystemMessage($this->redactor->redact($message->getContent())),
            $message instanceof UserMessage => new UserMessage(...$this->redactContent($message->getContent())),
            $message instanceof AssistantMessage => new AssistantMessage(...$this->redactContent($message->getContent())),
            default => throw new \LogicException(\sprintf('Cannot check a "%s" for secrets, so it is not sent.', get_debug_type($message))),
        };
    }

    /**
     * @return list<Text>
     */
    private function redactContent(array $content): array
    {
        return array_map(
            fn (object $part) => $part instanceof Text && null === $part->getSignature()
            ? new Text($this->redactor->redact($part->getText()))
            : throw new \LogicException(\sprintf('Cannot check "%s" content for secrets, so it is not sent.', get_debug_type($part))),
            $content,
        );
    }
}
