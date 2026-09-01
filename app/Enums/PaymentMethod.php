<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum PaymentMethod: string
{

    use ConcernsHasOptions;

    case Cash       = 'cash';
    case Check      = 'check';
    case Zelle      = 'zelle';
    case Ach        = 'ach';
    case Wire       = 'wire';
    case CreditCard = 'credit_card';
    case Other      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash       => 'Efectivo',
            self::Check      => 'Cheque',
            self::Zelle      => 'Zelle',
            self::Ach        => 'ACH',
            self::Wire       => 'Transferencia',
            self::CreditCard => 'Tarjeta de crédito',
            self::Other      => 'Otro',
        };
    }

    /** Si lleva recargo por procesamiento. Solo tarjeta. */
    public function hasFee(): bool
    {
        return $this === self::CreditCard;
    }

    /** El dinero entra al instante o hay que esperar a que compense. */
    public function isInstant(): bool
    {
        return $this->is(self::Cash, self::Zelle, self::CreditCard);
    }

    /** Estos exigen número de referencia para conciliar con el banco. */
    public function requiresReference(): bool
    {
        return $this->is(self::Check, self::Ach, self::Wire, self::Zelle);
    }
}