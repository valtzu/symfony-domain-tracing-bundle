<?php

namespace App\Checkout;

use App\Checkout\Model\Customer;
use App\Checkout\Model\Quote;
use Psr\Clock\ClockInterface;

final readonly class FraudScreening
{
    private const NEW_ACCOUNT_DAYS = 30;
    private const NEW_ACCOUNT_LIMIT_CENTS = 30000;

    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function needsManualReview(Customer $customer, Quote $quote): bool
    {
        $accountAgeDays = $customer->registeredAt->diff($this->clock->now())->days;

        return $accountAgeDays < self::NEW_ACCOUNT_DAYS && $quote->totalCents() > self::NEW_ACCOUNT_LIMIT_CENTS;
    }
}
