<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum EstimateStatus: string
{
  

     use ConcernsHasOptions;

    case Draft     = 'draft';
    case Sent      = 'sent';
    case Accepted  = 'accepted';
    case Rejected  = 'rejected';
    case Expired   = 'expired';
    case Converted = 'converted';   // ya se convirtió en factura

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Borrador',
            self::Sent      => 'Enviada',
            self::Accepted  => 'Aceptada',
            self::Rejected  => 'Rechazada',
            self::Expired   => 'Vencida',
            self::Converted => 'Convertida a factura',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Accepted, self::Converted => 'green',
            self::Sent                      => 'blue',
            self::Rejected                  => 'red',
            self::Expired                   => 'yellow',
            self::Draft                     => 'gray',
        };
    }

    /** Solo se convierte una vez, y solo si el cliente la aceptó o la recibió. */
    public function canConvert(): bool
    {
        return $this->is(self::Sent, self::Accepted);
    }

    /** Ya no se puede editar. */
    public function isClosed(): bool
    {
        return $this->is(self::Converted, self::Rejected, self::Expired);
    }
}