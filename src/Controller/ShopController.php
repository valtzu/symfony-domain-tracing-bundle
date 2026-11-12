<?php

namespace App\Controller;

use App\Checkout\Checkout;
use App\Checkout\CheckoutFailed;
use App\Checkout\CustomerRepository;
use App\Checkout\OrderLine;
use App\Checkout\PlaceOrder;
use App\Checkout\ProductCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShopController extends AbstractController
{
    private const COUNTRIES = ['FI' => 'Finland', 'SE' => 'Sweden', 'EE' => 'Estonia', 'DE' => 'Germany', 'US' => 'United States'];

    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly ProductCatalog $catalog,
    ) {
    }

    #[Route('/', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderShop(['customer' => 'alice', 'country' => 'FI', 'coupon' => '', 'quantities' => []]);
    }

    #[Route('/', methods: ['POST'])]
    public function checkout(Request $request, Checkout $checkout): Response
    {
        $form = [
            'customer' => $request->request->getString('customer'),
            'country' => $request->request->getString('country'),
            'coupon' => trim($request->request->getString('coupon')),
            'quantities' => array_map(intval(...), $request->request->all('quantities')),
        ];

        $lines = [];
        foreach (array_filter($form['quantities'], static fn (int $quantity) => $quantity > 0) as $sku => $quantity) {
            $lines[] = new OrderLine((string) $sku, $quantity);
        }

        if (!$lines) {
            return $this->renderShop($form, error: ['reason' => 'empty_cart', 'message' => 'Add at least one product.'], status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $order = $checkout->placeOrder(new PlaceOrder($form['customer'], $lines, $form['country'], '' === $form['coupon'] ? null : $form['coupon']));
        } catch (CheckoutFailed $e) {
            return $this->renderShop($form, error: ['reason' => $e->reason, 'message' => $e->getMessage()], status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->renderShop($form, order: $order, status: Response::HTTP_CREATED);
    }

    private function renderShop(array $form, ?object $order = null, ?array $error = null, int $status = Response::HTTP_OK): Response
    {
        return $this->render('shop.html.twig', [
            'form' => $form,
            'customers' => $this->customers->all(),
            'products' => $this->catalog->products(),
            'coupons' => $this->catalog->coupons(),
            'countries' => self::COUNTRIES,
            'order' => $order,
            'error' => $error,
        ], new Response(status: $status));
    }
}
