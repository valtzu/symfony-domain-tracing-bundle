<?php

namespace App\Checkout;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrderLine
{
    public function __construct(
        #[Assert\NotBlank]
        public string $sku,
        #[Assert\Positive]
        public int $quantity,
    ) {
    }
}
