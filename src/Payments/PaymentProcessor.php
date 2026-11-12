<?php

namespace App\Payments;

final class PaymentProcessor
{
    private const DECLINED_CARDS = ['carol' => 'insufficient_funds'];
    private const SINGLE_CHARGE_LIMIT_CENTS = 100000;

    /**
     * @return array{approved: bool, paymentId?: string, declineCode?: string}
     */
    public function charge(ChargeRequest $charge): array
    {
        if ($charge->amountCents > self::SINGLE_CHARGE_LIMIT_CENTS) {
            return ['approved' => false, 'declineCode' => 'amount_over_limit'];
        }

        if ($declineCode = self::DECLINED_CARDS[$charge->customerId] ?? null) {
            return ['approved' => false, 'declineCode' => $declineCode];
        }

        return ['approved' => true, 'paymentId' => 'pay_'.bin2hex(random_bytes(6))];
    }
}
