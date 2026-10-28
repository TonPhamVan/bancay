<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** MariaDB TEXT is limited by bytes, including multibyte UTF-8 text. */
final class DatabaseText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > 65535) {
            $fail('Nội dung vượt giới hạn 65.535 byte của trường văn bản.');
        }
    }
}
