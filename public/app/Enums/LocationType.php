<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\Concerns\HasOptions as ConcernsHasOptions;



/**
 * Qué clase de sitio es.
 *
 * Los depósitos de proveedor NO están acá: tienen su propia tabla
 * (depots) porque necesitan cosas que una ubicación no tiene —fee de
 * recogida, plazo de retiro, fee diario por demora (RB-020, RB-031).
 */
enum LocationType: string
{
    use ConcernsHasOptions;
    

    case Yard         = 'yard';            // yarda propia
    case CustomerSite = 'customer_site';   // terreno del cliente
    case Port         = 'port';            // puerto o terminal
    case Other        = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Yard         => 'Yarda propia',
            self::CustomerSite => 'Sitio del cliente',
            self::Port         => 'Puerto / terminal',
            self::Other        => 'Otro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Yard         => 'green',
            self::CustomerSite => 'blue',
            self::Port         => 'purple',
            self::Other        => 'gray',
        };
    }

    /**
     * Solo las yardas propias tienen dueño.
     *
     * Un puerto o el terreno de un cliente no le pertenecen a ninguna
     * de las dos compañías: por eso locations.company_id es nullable.
     */
    public function belongsToCompany(): bool
    {
        return $this === self::Yard;
    }

    /**
     * RB-021: solo en la yarda propia corre el reloj de almacenaje.
     * Si el contenedor está en el terreno del cliente, el problema ya
     * es de él.
     */
    public function chargesStorage(): bool
    {
        return $this === self::Yard;
    }

    /**
     * Cuenta como inventario propio.
     *
     * Es lo que separa "tengo 40 contenedores" de "40 contenedores
     * pasaron por aquí": solo los que están en yarda son stock.
     */
    public function holdsInventory(): bool
    {
        return $this === self::Yard;
    }
}