<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Listens for Xdebug connections and records the calls of the application methods of each request.
 */
final readonly class Recorder
{
    private const POLL_SECONDS = 0.05;
    private const SECONDS_TO_WAIT_FOR_THE_TRACE_END = 5;

    public function __construct(
        private TraceStore $store,
        private XdebugValue $values,
    ) {
    }

    /**
     * @param \Closure(): void $tick called between the polls, to do other work in the same process
     */
    public function run(SymfonyStyle $io, string $listen, \Closure $tick): never
    {
        $server = stream_socket_server($listen, $errorCode, $errorMessage) ?: throw new \RuntimeException($errorMessage);
        $io->writeln(\sprintf('Listening on %s', $listen));

        /** @var array<int, array{DbgpConnection, TraceSession}> $sessions */
        $sessions = [];

        /** @var array<string, array{XdebugTraceReader, float}> trace readers of the requests whose connection ended, with when it ended */
        $readers = [];

        while (true) {
            $tick();
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
