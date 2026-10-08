<?php

namespace App\Domain\Project\Enums;

use App\Support\Enums\HasOptions;

enum BusinessModel: string
{
    use HasOptions;

    case B2BSaas = 'b2b_saas';
    case B2CApp = 'b2c_app';
    case Marketplace = 'marketplace';
    case Agency = 'agency';
    case Ecommerce = 'ecommerce';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::B2BSaas => 'B2B SaaS',
            self::B2CApp => 'B2C app',
            self::Marketplace => 'Marketplace',
            self::Agency => 'Agency / services',
            self::Ecommerce => 'E-commerce',
            self::Other => 'Other',
        };
    }
}
