<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the system prompts of the bundle, and adds the description of the application that the project configures in
 * "domain_tracing.context".
 */
final readonly class SystemPrompts
{
    public function __construct(
        #[Autowire(param: 'domain_tracing.context')]
        private ?string $context = null,
    ) {
    }

    public function get(string $name): string
    {
        $file = \dirname(__DIR__, levels: 2).'/config/prompts/'.$name.'.md';
        $prompt = file_get_contents($file);
        if (false === $prompt) {
            throw new \LogicException(\sprintf('Cannot read the prompt "%s".', $file));
        }

        if (null === $this->context) {
            return $prompt;
        }

        return $prompt."\n## About this application\n\nThe developers of the application describe it below. Use the description to choose the words and to decide what matters to the business. The rules above still apply.\n\n".$this->context;
    }
}
