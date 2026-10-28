<?php

namespace App\Legacy\Transformers;

use App\Legacy\MigrationFailure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class Values
{
    public static function integer(mixed $value, int $max = PHP_INT_MAX, bool $grouped = false): int
    {
        $text = (string) $value;
        if ($grouped && str_contains($text, ',')) {
            if (! preg_match('/\A[1-9][0-9]{0,2}(?:,[0-9]{3})+\z/', $text)) {
                throw new MigrationFailure('INVALID_MONEY_FORMAT');
            }
            $text = str_replace(',', '', $text);
        }
        if (! preg_match('/\A[0-9]+\z/', $text)) {
            throw new MigrationFailure('INVALID_UNSIGNED_INTEGER');
        }
        $normalized = ltrim($text, '0') ?: '0';
        if (strlen($normalized) > strlen((string) $max) || (strlen($normalized) === strlen((string) $max) && strcmp($normalized, (string) $max) > 0)) {
            throw new MigrationFailure('INTEGER_OVERFLOW');
        }

        return (int) $normalized;
    }

    public static function text(mixed $value, int $max, bool $required = false): string
    {
        if (! is_string($value) && $value !== null) {
            throw new MigrationFailure('INVALID_TEXT');
        }
        $text = (string) $value;
        if (! mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > $max || ($required && trim($text) === '') || str_contains($text, "\0")) {
            throw new MigrationFailure('INVALID_TEXT');
        }

        return $text;
    }

    public static function slug(array $row, string $name): string
    {
        return substr(Str::slug(trim((string) ($row['alias'] ?? '')) ?: $name) ?: 'legacy', 0, 155).'-legacy-'.self::integer($row['id']);
    }

    public static function recorded(mixed $timestamp): string
    {
        $value = self::integer($timestamp, 2147483647);
        if ($value < 1) {
            throw new MigrationFailure('INVALID_RECORDED_TIME');
        }

        return CarbonImmutable::createFromTimestamp($value, config('app.timezone'))->toDateTimeString();
    }

    public static function flag(mixed $value): bool
    {
        return (string) $value === '1';
    }
}
