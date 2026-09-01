<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/** De qué es la factura. Define qué pantalla la generó y cómo se reporta. */
enum InvoiceType: string
{
   
     use ConcernsHasOptions;

    case Sale         = 'sale';           // venta de contenedor
    case Rental       = 'rental';         // mensualidad de renta
    case Transport    = 'transport';      // solo el viaje
    case Intercompany = 'intercompany';   // una compañía le cobra a la otra
    case Other        = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sale         => 'Venta',
            self::Rental       => 'Renta',
            self::Transport    => 'Transporte',
            self::Intercompany => 'Intercompañía',
            self::Other        => 'Otro',
        };
    }

    /** No entra en los reportes de ingresos consolidados: es dinero interno. */
    public function isIntercompany(): bool
    {
        return $this === self::Intercompany;
    }

    /** Estas facturas cubren un período de servicio, no una entrega puntual. */
    public function hasServicePeriod(): bool
    {
        return $this === self::Rental;
    }
}