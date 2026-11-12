<?php

namespace App\Checkout;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PlaceOrder
{
    /**
     * @param list<OrderLine> $lines
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $customerId,
        #[Assert\Count(min: 1)]
        #[Assert\Valid]
        public array $lines,
        #[Assert\Regex('/^[A-Z]{2}$/')]
        public string $shipTo,
        public ?string $coupon = null,
    ) {
    }
}
