<?php

namespace App\Checkout;

use App\Checkout\Model\Coupon;
use App\Checkout\Model\Product;

final class ProductCatalog
{
    /** @var array<string, Product> */
    private array $products;

    /** @var array<string, Coupon> */
    private array $coupons;

    public function __construct()
    {
        $this->products = [
            'KB-01' => new Product('KB-01', 'Mechanical keyboard', 8900),
            'MS-02' => new Product('MS-02', 'Wireless mouse', 2900),
            'MN-27' => new Product('MN-27', '27" monitor', 32900),
            'CB-USB' => new Product('CB-USB', 'USB-C cable', 900),
        ];
        $this->coupons = [
            'WELCOME10' => new Coupon('WELCOME10', percentOff: 10, minimumSubtotalCents: 5000),
            'BLACKFRIDAY' => new Coupon('BLACKFRIDAY', percentOff: 25, validUntil: new \DateTimeImmutable('2025-11-30 23:59:59')),
            'FREESHIP' => new Coupon('FREESHIP', freeShipping: true),
        ];
    }

    /**
     * @return list<Product>
     */
    public function products(): array
    {
        return array_values($this->products);
    }

    /**
     * @return list<Coupon>
     */
    public function coupons(): array
    {
        return array_values($this->coupons);
    }

    public function product(string $sku): Product
    {
        return $this->products[$sku] ?? throw new CheckoutFailed('unknown_product', \sprintf('Product "%s" does not exist.', $sku));
    }

    public function coupon(string $code): ?Coupon
    {
        return $this->coupons[strtoupper($code)] ?? null;
    }
}
