<?php

namespace App\Payments;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class ChargeController
{
    #[Route('/charges', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ChargeRequest $charge, PaymentProcessor $processor): JsonResponse
    {
        $result = $processor->charge($charge);

        return new JsonResponse($result, $result['approved'] ? Response::HTTP_CREATED : Response::HTTP_PAYMENT_REQUIRED);
    }
}
