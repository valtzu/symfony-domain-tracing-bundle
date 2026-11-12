<?php

namespace App\Checkout;

use App\Checkout\Model\Coupon;
use App\Checkout\Model\Quote;

final class Shipping
{
    private const RATES_CENTS = ['FI' => 590, 'SE' => 790, 'EE' => 790, 'DE' => 990];
    private const FREE_SHIPPING_FROM_CENTS = 15000;

    public function apply(Quote $quote, string $country, ?Coupon $coupon): void
    {
        $rate = self::RATES_CENTS[$country] ?? throw new CheckoutFailed('shipping_unavailable', \sprintf('We do not ship to "%s".', $country));

        if ($coupon?->freeShipping || $quote->subtotalCents() - array_sum($quote->discounts) >= self::FREE_SHIPPING_FROM_CENTS) {
            $rate = 0;
        }

        $quote->shippingCents = $rate;
    }
}
