<?php

namespace App\Checkout;

final class CheckoutFailed extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
