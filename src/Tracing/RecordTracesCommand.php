<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('domain-tracing:record', 'Listen for Xdebug connections and record the calls of the application methods of each request')]
final readonly class RecordTracesCommand
{
    public function __construct(
        private Recorder $recorder,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option] string $listen = 'tcp://0.0.0.0:9003'): int
    {
        $this->recorder->run($io, $listen, static function (): void {
        });
    }
}
