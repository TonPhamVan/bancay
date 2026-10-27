<?php

namespace App\Domain\Commerce;

final class Money
{
    public static function multiply(int $price, int $quantity): int
    {
        if ($price <= 0 || $quantity <= 0) {
            throw new \InvalidArgumentException('Price and quantity must be positive.');
        }
        if ($price > intdiv(PHP_INT_MAX, $quantity)) {
            throw new \OverflowException('Money exceeds the supported integer range.');
        }

        return $price * $quantity;
    }

    public static function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0) {
            throw new \InvalidArgumentException('Money amounts must not be negative.');
        }
        if ($left > PHP_INT_MAX - $right) {
            throw new \OverflowException('Order total exceeds the supported integer range.');
        }

        return $left + $right;
    }
}
