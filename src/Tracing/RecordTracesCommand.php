<?php

namespace App\Tracing;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:record-traces', 'Listen for Xdebug connections and record the calls of the application methods of each request')]
final readonly class RecordTracesCommand
{
    private const POLL_SECONDS = 0.05;
    private const SECONDS_TO_WAIT_FOR_THE_TRACE_END = 5;

    public function __construct(
        private TraceStore $store,
        private XdebugValue $values,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option] string $listen = 'tcp://0.0.0.0:9003'): int
    {
        $server = stream_socket_server($listen, $errorCode, $errorMessage) ?: throw new \RuntimeException($errorMessage);
        $io->writeln(\sprintf('Listening on %s', $listen));

        /** @var array<int, array{DbgpConnection, TraceSession}> $sessions */
        $sessions = [];

        /** @var array<string, array{XdebugTraceReader, float}> trace readers of the requests whose connection ended, with when it ended */
        $readers = [];

        while (true) {
            $read = [$server, ...array_map(static fn (array $session) => $session[0]->socket, $sessions)];
            $write = $except = null;
            if (false === stream_select($read, $write, $except, 0, (int) (self::POLL_SECONDS * 1e6))) {
                continue;
            }

            foreach ($read as $socket) {
                if ($socket === $server) {
                    if ($client = stream_socket_accept($server, 0)) {
                        $connection = new DbgpConnection($client);
                        $sessions[(int) $client] = [$connection, new TraceSession($connection, $this->store, $this->values)];
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
                    $connection->close();
                    unset($sessions[(int) $socket]);
                    if (null !== $session->requestId()) {
                        $readers[$session->requestId()] = [$session->traceReader, microtime(true)];
                    }
                }
            }

            foreach ($sessions as [, $session]) {
                $session->traceReader?->read();
            }
            foreach ($readers as $requestId => [$reader, $connectionEndedAt]) {
                $ended = $reader?->read() ?? false;
                if ($ended || microtime(true) - $connectionEndedAt > self::SECONDS_TO_WAIT_FOR_THE_TRACE_END) {
                    $this->store->end($requestId, $reader?->endedAt() ?? microtime(true));
                    unset($readers[$requestId]);
                }
            }
        }
    }
}
