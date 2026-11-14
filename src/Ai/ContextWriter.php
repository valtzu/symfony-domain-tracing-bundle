<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Writes a first description of the application for the "domain_tracing.context" option.
 */
final readonly class ContextWriter
{
    public function __construct(
        #[Autowire(service: 'ai.agent.domain_tracing_context_writer')]
        private AgentInterface $agent,
        private ProjectSummary $project,
        private SystemPrompts $prompts = new SystemPrompts(),
    ) {
    }

    /**
     * @return \Generator<int, string> the description, in parts while the agent writes it
     */
    public function write(): \Generator
    {
        $messages = new MessageBag(Message::forSystem($this->prompts->read('context_writer')), Message::ofUser($this->project->describe()));
        foreach ($this->agent->call($messages, ['stream' => true])->asStream() as $delta) {
            if (!$delta instanceof TextDelta) {
                continue;
            }
            yield $delta->getText();
        }
    }
}
