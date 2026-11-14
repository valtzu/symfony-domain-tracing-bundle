<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('domain-tracing:context:generate', 'Describe the application with AI, in the file of the "domain_tracing.context_file" option')]
final readonly class ContextGenerateCommand
{
    public function __construct(
        private ContextWriter $writer,
        #[Autowire(param: 'domain_tracing.context_file')]
        private string $contextFile,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Option('Write the file without asking')]
        bool $write = false,
    ): int {
        $io->comment('Sending the README, the routes and the class names to the AI agent.');

        $context = '';
        foreach ($this->writer->write() as $text) {
            $io->write($text);
            $context .= $text;
        }
        $io->newLine(2);

        $context = trim($context);
        if ('' === $context) {
            $io->error('The AI agent did not answer with a description.');

            return Command::FAILURE;
        }

        $question = \sprintf(is_file($this->contextFile) ? 'Replace %s?' : 'Write %s?', $this->contextFile);
        if (!$write && !($input->isInteractive() && $io->confirm($question, !is_file($this->contextFile)))) {
            $io->note('Not written. Run the command with --write, or copy the description into this file:');
            $io->writeln($this->contextFile);

            return Command::SUCCESS;
        }

        if (!is_dir(\dirname($this->contextFile))) {
            mkdir(\dirname($this->contextFile), recursive: true);
        }
        file_put_contents($this->contextFile, $context."\n");
        $io->success('Wrote the description. Read it, change what is wrong, and commit it.');
        $io->writeln($this->contextFile);

        return Command::SUCCESS;
    }
}
