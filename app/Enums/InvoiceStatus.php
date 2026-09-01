<?php

namespace App\Enums;



use App\Models\Concerns\HasOptions as ConcernsHasOptions;


enum InvoiceStatus: string
{


    use ConcernsHasOptions;

    case Draft   = 'draft';
    case Sent    = 'sent';
    case Partial = 'partial';
    case Paid    = 'paid';
    case Overdue = 'overdue';
    case Void    = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft   => 'Borrador',
            self::Sent    => 'Enviada',
            self::Partial => 'Pago parcial',
            self::Paid    => 'Pagada',
            self::Overdue => 'Vencida',
            self::Void    => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid    => 'green',
            self::Partial => 'yellow',
            self::Overdue => 'red',
            self::Void    => 'gray',
            default       => 'blue',
        };
    }

    /** Ya no se puede editar ni recalcular. */
    public function isLocked(): bool
    {
        return in_array($this, [self::Paid, self::Void], true);
    }

    /*public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }*/
}