<?php

namespace App\Enums;

use App\Models\Concerns\HasOptions as ConcernsHasOptions;


enum CommissionStatus: string
{
    use ConcernsHasOptions;

    case Pending   = 'pending';
    case Partial   = 'partial';
    case Paid      = 'paid';
    case Cancelled = 'cancelled';   // se anuló la venta

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pendiente',
            self::Partial   => 'Pago parcial',
            self::Paid      => 'Pagada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid      => 'green',
            self::Partial   => 'yellow',
            self::Pending   => 'gray',
            self::Cancelled => 'red',
        };
    }

    public function isPayable(): bool
    {
        return $this->is(self::Pending, self::Partial);
    }
}