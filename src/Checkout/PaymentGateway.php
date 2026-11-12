<?php

namespace App\Checkout;

use App\Checkout\Model\Customer;

final class PaymentGateway
{
    private const DECLINED_CUSTOMERS = ['carol' => 'insufficient_funds'];

    public function charge(Customer $customer, int $amountCents): string
    {
        if ($declineCode = self::DECLINED_CUSTOMERS[$customer->id] ?? null) {
            throw new CheckoutFailed('payment_declined', \sprintf('Card was declined (%s) for %d cents.', $declineCode, $amountCents));
        }

        return 'pay_'.bin2hex(random_bytes(6));
    }
}
