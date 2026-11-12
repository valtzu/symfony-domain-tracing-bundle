<?php

namespace App\Checkout\Model;

enum OrderStatus: string
{
    case Confirmed = 'confirmed';
    case PendingReview = 'pending_review';
}
