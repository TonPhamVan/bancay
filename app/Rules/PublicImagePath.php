<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PublicImagePath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || ! preg_match('#\A(products|categories|articles|banners|avatars|settings)/[A-Za-z0-9/_\-.]+\.(jpe?g|png|gif|webp)\z#i', $value)
            || in_array('..', explode('/', $value), true)
            || in_array('.', explode('/', $value), true)) {
            $fail('Use a validated image path relative to the public storage disk.');
        }
    }
}
