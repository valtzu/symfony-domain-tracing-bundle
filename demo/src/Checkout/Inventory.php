<?php

namespace App\Checkout;

use App\Checkout\Model\Quote;

final class Inventory
{
    /** @var array<string, int> */
    private array $stock = [
        'KB-01' => 12,
        'MS-02' => 40,
        'MN-27' => 2,
        'CB-USB' => 0,
    ];

    public function reserve(Quote $quote): void
    {
        foreach ($quote->lines as ['product' => $product, 'quantity' => $quantity]) {
            $available = $this->stock[$product->sku] ?? 0;
            if ($available < $quantity) {
                throw new CheckoutFailed('out_of_stock', \sprintf('Only %d of "%s" in stock, %d requested.', $available, $product->name, $quantity));
            }
        }

        foreach ($quote->lines as ['product' => $product, 'quantity' => $quantity]) {
            $this->stock[$product->sku] -= $quantity;
        }
    }
}
