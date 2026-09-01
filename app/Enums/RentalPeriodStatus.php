<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum RentalPeriodStatus: string
{

    use ConcernsHasOptions;

    case Pending  = 'pending';    // generado, sin facturar
    case Invoiced = 'invoiced';   // facturado, sin cobrar
    case Paid     = 'paid';
    case Overdue  = 'overdue';
    case Waived   = 'waived';     // condonado por decisión del admin

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Pendiente',
            self::Invoiced => 'Facturado',
            self::Paid     => 'Pagado',
            self::Overdue  => 'Vencido',
            self::Waived   => 'Condonado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid, self::Waived => 'green',
            self::Overdue            => 'red',
            self::Invoiced           => 'yellow',
            self::Pending            => 'gray',
        };
    }

    /** Ya no se le calcula mora ni entra en cobranza. */
    public function isClosed(): bool
    {
        return $this->is(self::Paid, self::Waived);
    }
}