<?php

namespace App\Checkout;

use App\Checkout\Model\Coupon;
use App\Checkout\Model\Customer;
use App\Checkout\Model\Quote;
use Psr\Clock\ClockInterface;

final readonly class Pricing
{
    public function __construct(
        private ProductCatalog $catalog,
        private ClockInterface $clock,
    ) {
    }

    public function applyDiscounts(Quote $quote, Customer $customer, ?string $couponCode): ?Coupon
    {
        $subtotal = $quote->subtotalCents();

        if ($percent = $customer->tier->discountPercent()) {
            $quote->discounts['tier:'.$customer->tier->value] = intdiv($subtotal * $percent, 100);
        }

        if (null === $couponCode) {
            return null;
        }

        $coupon = $this->catalog->coupon($couponCode) ?? throw new CheckoutFailed('invalid_coupon', \sprintf('Coupon "%s" does not exist.', $couponCode));

        if ($coupon->validUntil && $coupon->validUntil < $this->clock->now()) {
            throw new CheckoutFailed('coupon_expired', \sprintf('Coupon "%s" expired on %s.', $coupon->code, $coupon->validUntil->format('Y-m-d')));
        }

        if ($subtotal < $coupon->minimumSubtotalCents) {
            throw new CheckoutFailed('coupon_minimum_not_met', \sprintf('Coupon "%s" requires a subtotal of at least %d cents.', $coupon->code, $coupon->minimumSubtotalCents));
        }

        if ($coupon->percentOff) {
            $quote->discounts['coupon:'.$coupon->code] = intdiv($subtotal * $coupon->percentOff, 100);
        }

        return $coupon;
    }
}
