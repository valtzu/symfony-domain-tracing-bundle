<?php

namespace App\Controller;

use App\Checkout\Checkout;
use App\Checkout\CheckoutFailed;
use App\Checkout\Model\Order;
use App\Checkout\PlaceOrder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController
{
    #[Route('/orders', methods: ['POST'])]
    public function place(#[MapRequestPayload] PlaceOrder $command, Checkout $checkout): JsonResponse
    {
        try {
            $order = $checkout->placeOrder($command);
        } catch (CheckoutFailed $e) {
            return new JsonResponse(['error' => $e->reason, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(self::normalize($order), Response::HTTP_CREATED);
    }

    private static function normalize(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'customer' => $order->customer->id,
            'lines' => array_map(static fn (array $line) => [
                'sku' => $line['product']->sku,
                'quantity' => $line['quantity'],
                'unitPriceCents' => $line['product']->priceCents,
            ], $order->quote->lines),
            'subtotalCents' => $order->quote->subtotalCents(),
            'discountsCents' => (object) $order->quote->discounts,
            'shippingCents' => $order->quote->shippingCents,
            'totalCents' => $order->quote->totalCents(),
            'paymentId' => $order->paymentId,
        ];
    }
}
