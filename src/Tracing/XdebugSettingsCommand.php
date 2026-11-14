<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('domain-tracing:xdebug-settings', 'Print the php.ini settings that Xdebug needs to record the requests of this application')]
final readonly class XdebugSettingsCommand
{
    public function __construct(
        private XdebugSetup $setup,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->write($this->setup->settings(), false, OutputInterface::OUTPUT_RAW);

        return 0;
    }
}
