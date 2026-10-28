<?php

namespace App\Legacy;

final class MigrationFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly array $context = [])
    {
        parent::__construct($reason); // Never include a raw source value, SQL binding or credential.
    }
}
