<?php

namespace App\Domain\Catalog;

enum PricingType: string
{
    case Fixed = 'FIXED';
    case Contact = 'CONTACT';
}
