<?php

namespace App\Domain\Commerce;

enum OrderStatus: string
{
    case New = 'NEW';
    case Confirmed = 'CONFIRMED';
    case Packing = 'PACKING';
    case Shipping = 'SHIPPING';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case Returned = 'RETURNED';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::New => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Packing, self::Cancelled],
            self::Packing => [self::Shipping, self::Cancelled],
            self::Shipping => [self::Completed, self::Returned],
            self::Completed => [self::Returned],
            self::Cancelled, self::Returned => [],
        }, true);
    }
}
