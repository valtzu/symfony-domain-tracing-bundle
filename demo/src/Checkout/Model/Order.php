<?php

namespace App\Checkout\Model;

final readonly class Order
{
    public function __construct(
        public string $id,
        public Customer $customer,
        public Quote $quote,
        public OrderStatus $status,
        public ?string $paymentId,
    ) {
    }
}
