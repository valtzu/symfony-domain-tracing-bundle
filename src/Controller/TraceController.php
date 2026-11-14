<?php

namespace Valtzu\DomainTracingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Valtzu\DomainTracingBundle\Storage\TraceStore;
use Valtzu\DomainTracingBundle\Tracing\TraceStream;

final class TraceController extends AbstractController
{
    private const TIMEOUT_SECONDS = 600;

    #[Route('/_tracing/{id}/events', name: 'domain_tracing_events', methods: ['GET'])]
    public function events(
        string $id,
        Request $request,
        TraceStore $store,
        TraceStream $traces,
    ): Response {
        if (!TraceStore::isValidId($id)) {
            throw $this->createNotFoundException();
        }
        $id = $store->rootOf($id);
        $describe = $request->query->getBoolean('describe');

        $stream = function () use ($id, $describe, $traces) {
            // The stream reads the traces and waits for the AI, for longer than a page usually takes
            set_time_limit(0);
            $startedAt = microtime(true);
            $stop = static fn (): bool => connection_aborted() || microtime(true) - $startedAt >= self::TIMEOUT_SECONDS;
            foreach ($traces->follow($id, $describe, $stop) as [$event, $data]) {
                echo 'event: ', $event, "\ndata: ", json_encode($data, \JSON_UNESCAPED_SLASHES), "\n\n";
                flush();
            }
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
