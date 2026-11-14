<?php

declare(strict_types=1);

namespace Valtzu\DomainTracingBundle\Ai;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('domain-tracing:context:generate', 'Describe the application with AI, for the "domain_tracing.context" option')]
final readonly class ContextGenerateCommand
{
    private const string CONFIG_FILE = 'config/packages/domain_tracing.yaml';

    public function __construct(
        private ContextWriter $writer,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Option('Write the configuration file without asking')]
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

        $file = $this->projectDir.'/'.self::CONFIG_FILE;
        $current = is_file($file) ? file_get_contents($file) : false;
        $yaml = ContextConfigFile::withContext(false === $current ? null : $current, $context);
        if (null === $yaml) {
            $io->warning(\sprintf('%s has no "domain_tracing" key. Add the description as "domain_tracing.context" yourself.', self::CONFIG_FILE));

            return Command::FAILURE;
        }

        $question = \sprintf(false === $current ? 'Write %s?' : 'Put the description into %s?', self::CONFIG_FILE);
        if (!$write && !($input->isInteractive() && $io->confirm($question))) {
            $io->note(\sprintf('Not written. Run the command with --write, or copy the description into %s.', self::CONFIG_FILE));

            return Command::SUCCESS;
        }

        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), recursive: true);
        }
        file_put_contents($file, $yaml);
        $io->success(\sprintf('Wrote %s. Read the description, change what is wrong, and commit it.', self::CONFIG_FILE));

        return Command::SUCCESS;
    }
}
