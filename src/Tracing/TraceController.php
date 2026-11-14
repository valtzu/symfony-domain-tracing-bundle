<?php

namespace Valtzu\DomainTracingBundle\Tracing;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TraceController extends AbstractController
{
    private const TIMEOUT_SECONDS = 600;

    #[Route('/_tracing/{id}/events', name: 'domain_tracing_events', methods: ['GET'])]
    public function events(
        string $id,
        Request $request,
        TraceStore $store,
        BlobStore $blobs,
        OperationNamer $namer,
        TraceIngester $ingester,
        TraceDescriber $describer,
    ): Response {
        if (!TraceStore::isValidId($id)) {
            throw $this->createNotFoundException();
        }
        $id = $store->rootOf($id);
        $describe = $request->query->getBoolean('describe');

        $stream = function () use ($id, $describe, $store, $blobs, $namer, $ingester, $describer) {
            // The stream reads the traces and waits for the AI, for longer than a page usually takes
            set_time_limit(0);
            $send = static function (string $event, mixed $data): void {
                echo 'event: ', $event, "\ndata: ", json_encode($data, \JSON_UNESCAPED_SLASHES), "\n\n";
                flush();
            };

            $follower = new TraceFollower($store, $blobs, $id);
            $description = $describe ? $describer->describe($id) : null;
            $builder = new SpanBuilder();
            $names = [];
            $narrationOffset = 0;
            $startedAt = microtime(true);

            while (!connection_aborted() && microtime(true) - $startedAt < self::TIMEOUT_SECONDS) {
                $ingester->poll($follower->spanIds());
                foreach ($follower->poll() as $record) {
                    $changes = $builder->add($record);
                    match ($record['type']) {
                        'request' => $record['request'] === $id ? $send('request', $record) : null,
                        'step' => $send('step', $record + ['service' => $builder->span($builder->currentSpanOf($record['request']))['service'] ?? null]),
                        'end' => $record['request'] === $id ? $send('end', $record) : null,
                        default => null,
                    };
                    foreach ($changes as [$change, $span]) {
                        $function = null === $span['function'] ? null : $builder->function($span['function']);
                        $span['name'] = $names[$span['function'] ?? ''] ?? (null === $function ? null : $namer->cachedName($span['service'], $span['function'], $function['code'])) ?? $span['name'];
                        $send('span', ['change' => $change] + $span);
                    }
                }

                if (!$describe && $follower->hasEnded()) {
                    break;
                }

                $narration = $store->readNarration($id, $narrationOffset);
                $narrationOffset = $narration['offset'];
                foreach ($narration['records'] as $record) {
                    match ($record['type']) {
                        'name' => [$names[$record['function']] = $record['name'], $send('name', $record)],
                        'event' => $send('event', $record),
                        'failure' => $send('failure', $record['message']),
                        'end' => $send('done', ['traceId' => $record['traceId']]),
                        default => null,
                    };
                    if ('end' === $record['type']) {
                        return;
                    }
                }

                if ($description?->valid()) {
                    $description->next();
                } else {
                    usleep(100_000);
                }
            }

            $send('done', ['traceId' => $follower->traceId()]);
        };

        return new StreamedResponse($stream, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    #[Route('/_tracing/', name: 'domain_tracing_live', methods: ['GET'])]
    public function live(): Response
    {
        return $this->render('@DomainTracing/live.html.twig', ['now' => microtime(true)]);
    }

    #[Route('/_tracing/{file}', name: 'domain_tracing_asset', requirements: ['file' => 'trace\.(js|css)'], methods: ['GET'])]
    public function asset(string $file): BinaryFileResponse
    {
        $response = new BinaryFileResponse(\dirname(__DIR__, 2).'/public/'.$file);
        $response->headers->set('Content-Type', str_ends_with($file, '.js') ? 'text/javascript' : 'text/css');

        return $response;
    }

    #[Route('/_tracing/newest', name: 'domain_tracing_newest', methods: ['GET'])]
    public function newest(Request $request, TraceStore $store): JsonResponse
    {
        return new JsonResponse(['id' => $store->newestStartedAfter($request->query->get('after', 0.0) + 0.0)]);
    }
}
