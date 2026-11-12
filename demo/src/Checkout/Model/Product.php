<?php

namespace App\Checkout\Model;

final readonly class Product
{
    public function __construct(
        public string $sku,
        public string $name,
        public int $priceCents,
    ) {
    }
}
