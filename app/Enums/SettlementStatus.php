<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/** Liquidación semanal del chofer. */
enum SettlementStatus: string
{
  
    use ConcernsHasOptions;

    case Draft     = 'draft';       // armándose, se pueden agregar viajes
    case Approved  = 'approved';    // cerrada, lista para pagar
    case Paid      = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Borrador',
            self::Approved  => 'Aprobada',
            self::Paid      => 'Pagada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid      => 'green',
            self::Approved  => 'blue',
            self::Draft     => 'gray',
            self::Cancelled => 'red',
        };
    }

    /** Solo en borrador se pueden agregar o quitar viajes y deducciones. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}