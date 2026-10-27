<?php

namespace App\Http\Middleware;

use Exceedone\Exment\Middleware\LogOperation;

/** Keep native Exment operation audit, with structural redaction of escaped/nested passwords. */
final class AdminOperationLog extends LogOperation
{
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
