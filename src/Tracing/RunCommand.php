<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Records the traces and processes them, in two processes: describing a trace waits for the AI, and the recorder must
 * answer Xdebug in the meantime. The worker process is started again when it stops.
 */
#[AsCommand('domain-tracing:run', 'Record the traces, and name and describe them in a worker process')]
final readonly class RunCommand
{
    private const SECONDS_BETWEEN_WORKER_STARTS = 5;

    public function __construct(
        private Recorder $recorder,
        #[Autowire('%kernel.project_dir%/bin/console')]
        private string $console,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option] string $listen = 'tcp://0.0.0.0:9003'): int
    {
        $worker = null;
        $startedAt = 0.0;

        $this->recorder->run($io, $listen, function () use (&$worker, &$startedAt, $io): void {
            if ($worker?->isRunning() || microtime(true) - $startedAt < self::SECONDS_BETWEEN_WORKER_STARTS) {
                return;
            }
            if (null !== $worker) {
                $io->warning(\sprintf('The worker stopped with exit code %d. Starting it again.', $worker->getExitCode()));
            }

            $worker = new Process([\PHP_BINARY, $this->console, 'domain-tracing:process']);
            $worker->setTimeout(null);
            $worker->start(static fn (string $type, string $output) => $io->write($output));
            $startedAt = microtime(true);
        });
    }
}
