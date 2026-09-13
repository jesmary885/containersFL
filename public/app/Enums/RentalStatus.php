<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum RentalStatus: string
{
    
     use ConcernsHasOptions;

    case Draft     = 'draft';       // armando el contrato, sin entregar
    case Active    = 'active';      // entregado, generando períodos
    case Ended     = 'ended';       // se recogió el contenedor
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Borrador',
            self::Active    => 'Activa',
            self::Ended     => 'Finalizada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active    => 'green',
            self::Draft     => 'gray',
            self::Ended     => 'blue',
            self::Cancelled => 'red',
        };
    }

    /** Solo las activas generan el período del mes siguiente. */
    public function generatesPeriods(): bool
    {
        return $this === self::Active;
    }
}