<?php

namespace App\Checkout;

use App\Checkout\Model\Customer;
use App\Checkout\Model\CustomerTier;

final class CustomerRepository
{
    /** @var array<string, Customer> */
    private array $customers;

    public function __construct()
    {
        $this->customers = [
            'alice' => new Customer('alice', 'Alice Andersson', CustomerTier::Gold, new \DateTimeImmutable('2021-03-14')),
            'bob' => new Customer('bob', 'Bob Berg', CustomerTier::Regular, new \DateTimeImmutable('2026-11-01')),
            'carol' => new Customer('carol', 'Carol Carlsson', CustomerTier::Regular, new \DateTimeImmutable('2024-06-02')),
            'dave' => new Customer('dave', 'Dave Dahl', CustomerTier::Regular, new \DateTimeImmutable('2023-01-20'), blocked: true),
        ];
    }

    /**
     * @return list<Customer>
     */
    public function all(): array
    {
        return array_values($this->customers);
    }

    public function find(string $id): ?Customer
    {
        return $this->customers[$id] ?? null;
    }
}
