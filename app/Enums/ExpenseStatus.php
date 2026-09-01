<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum ExpenseStatus: string
{
 
     use ConcernsHasOptions;

    case Pending   = 'pending';     // registrado, sin aprobar
    case Approved  = 'approved';    // aprobado para pago
    case Partial   = 'partial';     // con abonos, aún con saldo
    case Paid      = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pendiente',
            self::Approved  => 'Aprobado',
            self::Partial   => 'Pago parcial',
            self::Paid      => 'Pagado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid      => 'green',
            self::Partial   => 'yellow',
            self::Approved  => 'blue',
            self::Pending   => 'gray',
            self::Cancelled => 'red',
        };
    }

    /** Entra en el reporte de cuentas por pagar. */
    public function isPayable(): bool
    {
        return $this->is(self::Pending, self::Approved, self::Partial);
    }
}