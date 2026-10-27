<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Matches the Phase 1 MariaDB TIMESTAMP range, avoiding silent zero-date writes. */
final class DatabaseTimestamp implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $timestamp = CarbonImmutable::parse($value, config('app.timezone'))->getTimestamp();
            if ($timestamp < 1 || $timestamp > 2147483647) {
                $fail('Ngày nằm ngoài phạm vi TIMESTAMP của database (1970–2038).');
            }
        } catch (\Throwable) {
            $fail('Ngày không hợp lệ.');
        }
    }
}
