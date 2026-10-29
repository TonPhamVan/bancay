<?php

namespace App\Http\Middleware;

use Exceedone\Exment\Middleware\LogOperation;

/** Keep native Exment operation audit, with structural redaction of escaped/nested passwords. */
final class AdminOperationLog extends LogOperation
{
    public static function getHideColumns(): array
    {
        return array_values(array_unique(array_merge(parent::getHideColumns(), [
            'checkout_token', 'api_key', 'app_key', 'secret', 'token', 'authorization', 'smtp_password', 'db_password',
        ])));
    }

    public static function maskArrayRecursive(array $data, array $columns): array
    {
        $columns = array_map('strtolower', $columns);
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $columns, true)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = self::maskArrayRecursive($value, $columns);
            }
        }

        return $data;
    }

    protected function hidePasswords($stringToLog)
    {
        $data = json_decode($stringToLog, true);
        if (! is_array($data)) {
            return '{}';
        }
        $redact = function (array $values) use (&$redact): array {
            foreach ($values as $key => $value) {
                if (in_array(strtolower((string) $key), self::getHideColumns(), true)) {
                    $values[$key] = '***';
                } elseif (is_array($value)) {
                    $values[$key] = $redact($value);
                }
            }

            return $values;
        };

        return json_encode($redact($data), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
