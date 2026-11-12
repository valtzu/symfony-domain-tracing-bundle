<?php

namespace App\Tracing;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:record-traces', 'Listen for PHP Debugger connections and record the executed lines of the application code of each request')]
final readonly class RecordTracesCommand
{
    public function __construct(
        private TraceStore $store,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option] string $listen = 'tcp://0.0.0.0:9003', #[Option('Pause after each recorded line, to imitate a step-by-step debugging session')] int $stepDelay = 0): int
    {
        $server = stream_socket_server($listen, $errorCode, $errorMessage) ?: throw new \RuntimeException($errorMessage);
        $io->writeln(\sprintf('Listening on %s', $listen));

        /** @var array<int, array{DbgpConnection, TraceSession}> $sessions */
        $sessions = [];
        $clock = new TraceClock();

        while (true) {
            $read = [$server, ...array_map(static fn (array $session) => $session[0]->socket, $sessions)];
            $write = $except = null;
            if (false === stream_select($read, $write, $except, null)) {
                continue;
            }

            foreach ($read as $socket) {
                if ($socket === $server) {
                    if ($client = stream_socket_accept($server, 0)) {
                        $connection = new DbgpConnection($client);
                        $sessions[(int) $client] = [$connection, new TraceSession($connection, $this->store, $clock, $stepDelay)];
                    }
                    continue;
                }

                [$connection, $session] = $sessions[(int) $socket];
                $messages = $connection->receive();
                foreach ($messages ?? [] as $message) {
                    $session->handle($message);
                }
                $connection->flush();

                if (null === $messages || $session->finished) {
                    $session->abort();
                    $connection->close();
                    unset($sessions[(int) $socket]);
                }
            }
        }
    }
}
