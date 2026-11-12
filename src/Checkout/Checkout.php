<?php

namespace App\Checkout;

use App\Checkout\Model\Order;
use App\Checkout\Model\OrderStatus;
use App\Checkout\Model\Quote;

final readonly class Checkout
{
    public function __construct(
        private CustomerRepository $customers,
        private ProductCatalog $catalog,
        private Inventory $inventory,
        private Pricing $pricing,
        private Shipping $shipping,
        private FraudScreening $fraudScreening,
        private PaymentGateway $payments,
    ) {
    }

    public function placeOrder(PlaceOrder $command): Order
    {
        $customer = $this->customers->find($command->customerId) ?? throw new CheckoutFailed('unknown_customer', \sprintf('Customer "%s" does not exist.', $command->customerId));

        if ($customer->blocked) {
            throw new CheckoutFailed('customer_blocked', \sprintf('Customer "%s" is blocked.', $customer->id));
        }

        $quote = new Quote(array_map(
            fn (OrderLine $line) => ['product' => $this->catalog->product($line->sku), 'quantity' => $line->quantity],
            $command->lines,
        ));

        $coupon = $this->pricing->applyDiscounts($quote, $customer, $command->coupon);
        $this->shipping->apply($quote, $command->shipTo, $coupon);
        $this->inventory->reserve($quote);

        if ($this->fraudScreening->needsManualReview($customer, $quote)) {
            return new Order(self::newOrderId(), $customer, $quote, OrderStatus::PendingReview, null);
        }

        $paymentId = $this->payments->charge($customer, $quote->totalCents());

        return new Order(self::newOrderId(), $customer, $quote, OrderStatus::Confirmed, $paymentId);
    }

    private static function newOrderId(): string
    {
        return 'ord_'.bin2hex(random_bytes(6));
    }
}
