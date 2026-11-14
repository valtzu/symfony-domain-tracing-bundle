<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Valtzu\DomainTracingBundle\Mcp\TraceMcpServer;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TraceStream;
use Valtzu\DomainTracingBundle\Tracing\TraceTextFormatter;

#[AsCommand('debug:domain:trace', 'Print a trace while it is recorded: its operations, and the events that the AI writes')]
final readonly class TraceCommand
{
    private const int TIMEOUT_SECONDS = 600;
    private const string HINT = 'The web server records the requests when its php.ini has xdebug.mode=trace,coverage. When a request is not recorded, the "Domain traces" panel of its profile tells why.';

    public function __construct(
        private TraceStore $store,
        private TraceStream $traces,
        private bool $describes,
        private TraceMcpServer $mcp,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument('The ID of a request, as the profiler shows it, "latest" for the last request, or "next" to wait for the next one')]
        string $request = 'latest',
        #[Option('Describe the trace with AI, unless it is described already, and print the events')]
        bool $describe = false,
        #[Option('Print the arguments and the return values of the operations')]
        bool $values = false,
        #[Option('"text", "json" to print each event as a line of JSON, as the live view receives it, or "mcp" to serve the traces to AI tools as an MCP server on the standard input and output')]
        string $format = 'text',
    ): int {
        if ('mcp' === $format) {
            $this->mcp->run();

            return Command::SUCCESS;
        }
        $json = 'json' === $format;
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $startedAt = microtime(true);
        $stop = static fn (): bool => microtime(true) - $startedAt >= self::TIMEOUT_SECONDS;

        if ('next' === $request) {
            $errors->writeln('Waiting for the next request.');
        }
        $id = $this->store->find($request, $stop);
        if (null === $id) {
            $errors->writeln(match ($request) {
                'latest' => 'No request was recorded yet.',
                'next' => 'No request started.',
                default => \sprintf('No request "%s" was recorded.', $request),
            });
            if ('latest' === $request || 'next' === $request) {
                $errors->writeln(self::HINT);
            }

            return Command::FAILURE;
        }

        if ($describe && !$this->describes) {
            $errors->writeln('The trace is not described: no AI agent is defined for the bundle. See the "agent" option of domain_tracing.');
            $describe = false;
        }

        $formatter = new TraceTextFormatter($values);
        foreach ($this->traces->follow($this->store->rootOf($id), $describe, $stop) as [$event, $data]) {
            if ($json) {
                $output->writeln(json_encode(['event' => $event, 'data' => $data], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
                continue;
            }
            foreach ($formatter->format($event, $data) as $line) {
                $output->writeln($line, OutputInterface::OUTPUT_RAW);
            }
        }

        return Command::SUCCESS;
    }
}
