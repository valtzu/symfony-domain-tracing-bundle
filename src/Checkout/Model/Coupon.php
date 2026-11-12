<?php

namespace App\Checkout\Model;

final readonly class Coupon
{
    public function __construct(
        public string $code,
        public int $percentOff = 0,
        public bool $freeShipping = false,
        public int $minimumSubtotalCents = 0,
        public ?\DateTimeImmutable $validUntil = null,
    ) {
    }
}
