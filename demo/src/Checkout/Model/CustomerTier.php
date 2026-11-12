<?php

namespace App\Checkout\Model;

enum CustomerTier: string
{
    case Regular = 'regular';
    case Gold = 'gold';

    public function discountPercent(): int
    {
        return match ($this) {
            self::Regular => 0,
            self::Gold => 5,
        };
    }
}
