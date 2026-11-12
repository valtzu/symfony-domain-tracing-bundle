<?php

namespace App\Tracing;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TraceController
{
    #[Route('/_trace/{id}', methods: ['GET'])]
    public function __invoke(string $id, TraceStore $store): JsonResponse
    {
        return new JsonResponse($store->load($id) ?? throw new NotFoundHttpException('Trace not recorded yet.'));
    }
}
