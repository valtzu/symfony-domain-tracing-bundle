<?php

namespace App\Payments;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChargeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $customerId,
        #[Assert\Positive]
        public int $amountCents,
    ) {
    }
}
