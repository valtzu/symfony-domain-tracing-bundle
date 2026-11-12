<?php

namespace App\Tracing;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TraceController extends AbstractController
{
    #[Route('/_trace/{id}/events', methods: ['GET'])]
    public function events(string $id, TraceStore $store, TraceExplainer $explainer): Response
    {
        if (!TraceStore::isValidId($id)) {
            throw $this->createNotFoundException();
        }

        return new StreamedResponse(function () use ($id, $store, $explainer) {
            $waitUntil = microtime(true) + 15;
            while (null === $store->read($id) && microtime(true) < $waitUntil) {
                usleep(100_000);
            }

            try {
                foreach ($explainer->follow($id) as [$event, $data]) {
                    echo 'event: ', $event, "\ndata: ", json_encode($data, \JSON_UNESCAPED_SLASHES), "\n\n";
                    flush();
                    if (connection_aborted()) {
                        return;
                    }
                }
            } catch (\Throwable $e) {
                echo "event: failure\ndata: ", json_encode($e->getMessage()), "\n\n";
            }

            echo "event: done\ndata: null\n\n";
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    #[Route('/_traces/live', methods: ['GET'])]
    public function live(): Response
    {
        return $this->render('trace_live.html.twig', ['now' => microtime(true)]);
    }

    #[Route('/_traces/newest', methods: ['GET'])]
    public function newest(Request $request, TraceStore $store): JsonResponse
    {
        return new JsonResponse(['id' => $store->newestStartedAfter($request->query->get('after', 0.0) + 0.0)]);
    }
}
