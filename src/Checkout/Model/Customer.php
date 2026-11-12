<?php

namespace App\Checkout\Model;

final readonly class Customer
{
    public function __construct(
        public string $id,
        public string $name,
        public CustomerTier $tier,
        public \DateTimeImmutable $registeredAt,
        public bool $blocked = false,
    ) {
    }
}
