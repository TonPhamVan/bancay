<?php

namespace App\Domain\Catalog;

enum RecordStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
