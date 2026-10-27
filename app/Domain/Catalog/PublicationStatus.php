<?php

namespace App\Domain\Catalog;

enum PublicationStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';
}
