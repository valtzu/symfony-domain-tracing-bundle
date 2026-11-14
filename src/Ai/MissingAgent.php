<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * Stands in for the AI agent that the application has not defined, which the bundle then does not call.
 */
final readonly class MissingAgent implements AgentInterface
{
    public function __construct(
        private ?string $name,
    ) {
    }

    #[\Override]
    public function call(string|MessageBag|UserMessage $input, array $options = []): ExecutionInterface
    {
        $reason = null === $this->name
            ? 'the "domain_tracing.agent" option is off.'
            : \sprintf('the AI agent "%s" is not defined. Define it under "ai.agent" in config/packages/ai.yaml.', $this->name);

        throw new RuntimeException(\sprintf('The traces are not described: %s', $reason));
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name ?? 'none';
    }
}
