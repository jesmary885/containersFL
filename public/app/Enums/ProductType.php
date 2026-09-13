<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/** Qué es cada concepto facturable del catálogo. */
enum ProductType: string
{
    
    use ConcernsHasOptions;

    case Container = 'container';   // el contenedor en sí
    case Service   = 'service';     // delivery, pickup, modificación
    case Fee       = 'fee';         // recargo de tarjeta, mora, fee de depósito
    case Part      = 'part';        // repuesto, candado, piso
    case Rental     = 'rental';        // renta de yarda

    public function label(): string
    {
        return match ($this) {
            self::Container => 'Contenedor',
            self::Service   => 'Servicio',
            self::Fee       => 'Recargo',
            self::Part      => 'Repuesto',
            self::Rental      => 'Renta',
        };
    }

    /**
     * Si por defecto paga impuesto. Es solo la SUGERENCIA que se precarga
     * en la línea de la factura; ahí se puede cambiar.
     *
     * El delivery no es gravable en Florida; el contenedor sí.
     */
    public function defaultTaxable(): bool
    {
        return $this->is(self::Container, self::Part);
    }

    /** Requiere elegir un contenedor concreto al facturar. */
    public function requiresContainer(): bool
    {
        return $this === self::Container;
    }
}