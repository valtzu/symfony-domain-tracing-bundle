<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Calls an agent with no tools. The Claude Code CLI has its own tools, such as reading files and running commands, and
 * the traces that the agent reads come from the requests: a request could ask the agent to use them.
 */
final readonly class AgentWithoutTools implements AgentInterface
{
    public function __construct(
        private AgentInterface $agent,
    ) {
    }

    #[\Override]
    public function call(string|MessageBag|UserMessage $input, array $options = []): ExecutionInterface
    {
        return $this->agent->call($input, ['tools' => []] + $options);
    }

    #[\Override]
    public function getName(): string
    {
        return $this->agent->getName();
    }
}
