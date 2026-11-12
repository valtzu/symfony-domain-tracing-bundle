<?php

namespace App\Checkout\Model;

final class Quote
{
    /** @var array<string, int> */
    public array $discounts = [];
    public int $shippingCents = 0;

    /**
     * @param list<array{product: Product, quantity: int}> $lines
     */
    public function __construct(
        public readonly array $lines,
    ) {
    }

    public function subtotalCents(): int
    {
        return array_sum(array_map(static fn (array $line) => $line['product']->priceCents * $line['quantity'], $this->lines));
    }

    public function totalCents(): int
    {
        return $this->subtotalCents() - array_sum($this->discounts) + $this->shippingCents;
    }
}
