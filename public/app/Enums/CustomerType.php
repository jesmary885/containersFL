<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum CustomerType: string
{
    use ConcernsHasOptions;

    case Individual = 'individual';
    case Business   = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Persona natural',
            self::Business   => 'Empresa',
        };
    }

    /** El formulario pide razón social y EIN en vez de nombre y apellido. */
    public function usesCompanyName(): bool
    {
        return $this === self::Business;
    }

    /** Solo las empresas pueden tener certificado de exención de impuesto. */
    public function canBeTaxExempt(): bool
    {
        return $this === self::Business;
    }

    /** Solo las empresas se verifican en Sunbiz. */
    public function verifiesInSunbiz(): bool
    {
        return $this === self::Business;
    }
}