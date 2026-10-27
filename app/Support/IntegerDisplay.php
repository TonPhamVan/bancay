<?php

namespace App\Support;

final class IntegerDisplay
{
    /** Format integer VND and counts without converting BIGINT/SUM strings to floats. */
    public static function format(int|string $value): string
    {
        if (! preg_match('/\A-?[0-9]+\z/', (string) $value)) {
            throw new \InvalidArgumentException('Integer display requires an integer.');
        }

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) $value);
    }
}
