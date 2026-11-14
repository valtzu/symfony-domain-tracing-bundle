<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the system prompts of the bundle, and adds the description of the application from the file that the project
 * configures in "domain_tracing.context_file". The file is read for each prompt, so that a change applies at once.
 */
final readonly class SystemPrompts
{
    public function __construct(
        #[Autowire(param: 'domain_tracing.context_file')]
        private ?string $contextFile = null,
    ) {
    }

    public function get(string $name): string
    {
        $prompt = $this->read($name);
        $context = null === $this->contextFile || !is_file($this->contextFile) ? false : file_get_contents($this->contextFile);
        if (false === $context || '' === trim($context)) {
            return $prompt;
        }

        return $prompt."\n## About this application\n\nThe developers of the application describe it below. Use the description to choose the words and to decide what matters to the business. The rules above still apply.\n\n".trim($context);
    }

    public function read(string $name): string
    {
        $file = \dirname(__DIR__, levels: 2).'/config/prompts/'.$name.'.md';
        $prompt = file_get_contents($file);
        if (false === $prompt) {
            throw new \LogicException(\sprintf('Cannot read the prompt "%s".', $file));
        }

        return $prompt;
    }
}
