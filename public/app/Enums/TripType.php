<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum TripType: string
{
   
    use ConcernsHasOptions;

    case Delivery      = 'delivery';        // yarda -> cliente
    case Pickup        = 'pickup';          // depósito -> yarda
    case Move          = 'move';            // cliente -> cliente
    case Repositioning = 'repositioning';   // yarda -> yarda, sin cobro

    public function label(): string
    {
        return match ($this) {
            self::Delivery      => 'Entrega',
            self::Pickup        => 'Recogida',
            self::Move          => 'Traslado',
            self::Repositioning => 'Reubicación',
        };
    }

    /**
     * Cómo se sugiere el precio.
     * El pickup usa el fee fijo del depósito; el resto, millas × tarifa.
     */
    public function pricesByDepotFee(): bool
    {
        return $this === self::Pickup;
    }

    /** Movimiento interno: no se le cobra a nadie. */
    public function isInternal(): bool
    {
        return $this === self::Repositioning;
    }
}