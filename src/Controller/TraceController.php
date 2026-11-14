<?php

declare(strict_types=1);


namespace Valtzu\DomainTracingBundle\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ServerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\BuiltInServer;
use Valtzu\DomainTracingBundle\Tracing\TraceStream;
use Twig\Environment;

final readonly class TraceController
{
    private const TIMEOUT_SECONDS = 600;
    private const HEARTBEAT_SECONDS = 15;
    private const POLL_MICROSECONDS = 250_000;
    private const POLL_SECONDS = 0.3;

    public function __construct(
        private TraceStore $store,
        private TraceStream $traces,
        private Environment $twig,
        private string $projectDir,
        private string $environment,
    ) {
    }

    #[Route('/_tracing/{id}/events', name: 'domain_tracing_events', methods: ['GET'])]
    public function events(string $id, Request $request): Response
    {
        if (!TraceStore::isValidId($id)) {
            throw new NotFoundHttpException();
        }
        $id = $this->store->rootOf($id);
        $describe = $request->query->getBoolean('describe');
        if ($request->query->has('cursor')) {
            return $this->pollEvents($id, $describe, $request->query->getInt('cursor'));
        }

        $stream = function () use ($id, $describe) {
            // The stream reads the traces and waits for the AI, for longer than a page usually takes
            set_time_limit(0);
            $startedAt = microtime(true);
            $stop = static fn (): bool => connection_aborted() || microtime(true) - $startedAt >= self::TIMEOUT_SECONDS;
            foreach ($this->traces->follow($id, $describe, $stop) as [$event, $data]) {
                yield new ServerEvent(json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR), $event);
            }
        };

        return new EventStreamResponse($stream);
    }

    #[Route('/_tracing/', name: 'domain_tracing_live', methods: ['GET'])]
    public function live(): Response
    {
        return new Response($this->twig->render('@DomainTracing/live.html.twig', ['now' => microtime(true), 'poll' => BuiltInServer::hasOneWorker()]));
    }

    #[Route('/_tracing/{file}', name: 'domain_tracing_asset', requirements: ['file' => 'trace\.(js|css)'], methods: ['GET'])]
    public function asset(string $file): BinaryFileResponse
    {
        $response = new BinaryFileResponse(\dirname(__DIR__, 2).'/public/'.$file);
        $response->headers->set('Content-Type', str_ends_with($file, '.js') ? 'text/javascript' : 'text/css');

        return $response;
    }

    /**
     * Sends each trace that starts after the time of the "after" query parameter. Each event and heartbeat carries the
     * time it reached as its ID, so that the browser resumes from there when it reconnects.
     */
    #[Route('/_tracing/started', name: 'domain_tracing_started', methods: ['GET'])]
    public function started(Request $request): Response
    {
        $after = $request->headers->get('Last-Event-ID') ?? $request->query->get('cursor') ?? $request->query->get('after');
        $after = is_numeric($after) ? (float) $after : microtime(true);
        if ($request->query->has('cursor')) {
            $traces = $this->store->startedAfter($after);

            return new JsonResponse([
                'events' => array_map(static fn (array $trace): array => ['trace', $trace], $traces),
                'cursor' => [] === $traces ? $after : end($traces)['startedAt'],
                'done' => false,
            ]);
        }

        $stream = function () use ($after) {
            set_time_limit(0);
            $startedAt = $heartbeatAt = microtime(true);
            while (!connection_aborted() && microtime(true) - $startedAt < self::TIMEOUT_SECONDS) {
                foreach ($this->store->startedAfter($after) as $trace) {
                    $after = $trace['startedAt'];
                    yield new ServerEvent(json_encode($trace, \JSON_THROW_ON_ERROR), 'trace', id: json_encode($after, \JSON_THROW_ON_ERROR));
                    $heartbeatAt = microtime(true);
                }
                if (microtime(true) - $heartbeatAt >= self::HEARTBEAT_SECONDS) {
                    yield new ServerEvent('', id: json_encode($after, \JSON_THROW_ON_ERROR));
                    $heartbeatAt = microtime(true);
                }
                usleep(self::POLL_MICROSECONDS);
            }
        };

        return new EventStreamResponse($stream);
    }

    /**
     * Gives the events of a trace after the first ones that the browser has, for a server that cannot keep a stream
     * open. Each poll follows the trace again from its start, and another process describes it.
     */
    private function pollEvents(string $id, bool $describe, int $cursor): JsonResponse
    {
        if ($describe) {
            $this->describeInBackground($id);
        }

        $startedAt = microtime(true);
        $stop = static fn (): bool => microtime(true) - $startedAt >= self::POLL_SECONDS;
        $all = iterator_to_array($this->traces->follow($id, $describe, $stop, describeHere: false), preserve_keys: false);
        // When the poll runs out of time, the trace goes on: the next poll gives the events that follow
        if ($stop() && 'done' === (end($all)[0] ?? null)) {
            array_pop($all);
        }
        $events = \array_slice($all, $cursor);
        $done = 'done' === (end($events)[0] ?? null);

        return new JsonResponse(['events' => $events, 'cursor' => $cursor + \count($events), 'done' => $done]);
    }

    private function describeInBackground(string $id): void
    {
        if ($this->store->isDescribed($id) || !$this->store->claimDescription($id)) {
            return;
        }

        Process::fromShellCommandline('"${:php}" "${:console}" debug:domain:trace "${:id}" --describe --quiet --env="${:env}" > /dev/null 2>&1 &')
            ->run(null, ['php' => \PHP_BINARY, 'console' => $this->projectDir.'/bin/console', 'id' => $id, 'env' => $this->environment]);
    }
}
