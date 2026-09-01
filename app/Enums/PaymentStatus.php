<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum PaymentStatus: string
{

    use ConcernsHasOptions;

    case Pending   = 'pending';     // registrado, sin confirmar en el banco
    case Completed = 'completed';
    case Failed    = 'failed';      // cheque devuelto, tarjeta rechazada
    case Refunded  = 'refunded';
    case Disputed  = 'disputed';    // el cliente reclamó el cargo (chargeback)

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pendiente',
            self::Completed => 'Confirmado',
            self::Failed    => 'Fallido',
            self::Refunded  => 'Reembolsado',
            self::Disputed  => 'En disputa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'green',
            self::Pending   => 'yellow',
            self::Refunded  => 'gray',
            default         => 'red',
        };
    }

    /** El dinero está firme: solo estos pagos se pueden aplicar a facturas. */
    public function isSettled(): bool
    {
        return $this === self::Completed;
    }

    /** Hay que revertir lo aplicado a las facturas. */
    public function reversesAllocations(): bool
    {
        return $this->is(self::Failed, self::Refunded, self::Disputed);
    }
}