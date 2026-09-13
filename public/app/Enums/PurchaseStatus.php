<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum PurchaseStatus: string
{
  
    use ConcernsHasOptions;

    case Draft             = 'draft';
    case Open              = 'open';                 // pagado, nada retirado
    case PartiallyReceived = 'partially_received';
    case Received          = 'received';             // todo retirado
    case Cancelled         = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft             => 'Borrador',
            self::Open              => 'Abierta',
            self::PartiallyReceived => 'Recibida parcial',
            self::Received          => 'Recibida',
            self::Cancelled         => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received          => 'green',
            self::PartiallyReceived => 'yellow',
            self::Open              => 'blue',
            self::Draft             => 'gray',
            self::Cancelled         => 'red',
        };
    }

    /** Todavía hay unidades por retirar del depósito. */
    public function hasPendingUnits(): bool
    {
        return $this->is(self::Open, self::PartiallyReceived);
    }
}