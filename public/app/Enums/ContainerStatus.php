<?php

namespace App\Enums;

use \App\Enums\Concerns\HasOptions;

use App\Models\Concerns\HasOptions as ConcernsHasOptions;


enum ContainerStatus: string
{

    use ConcernsHasOptions;
    case OnOrder        = 'on_order';       // pagado, todavía no llega
    case AtSupplier     = 'at_supplier';    // comprado en release, sigue en el depósito
    case InTransit      = 'in_transit';
    case InYard         = 'in_yard';        // ÚNICO estado que cuenta como stock disponible
    case Reconditioning = 'reconditioning';
    case Reserved       = 'reserved';
    case Sold           = 'sold';
    case Rented         = 'rented';
    case Damaged        = 'damaged';
    case Scrapped       = 'scrapped';
    case Lost           = 'lost';

    /** Texto para pantalla. Un solo lugar para traducir. */
    public function label(): string
    {
        return match ($this) {
            self::OnOrder        => 'Por llegar',
            self::AtSupplier     => 'En el depósito',
            self::InTransit      => 'En tránsito',
            self::InYard         => 'En yarda',
            self::Reconditioning => 'En reacondicionamiento',
            self::Reserved       => 'Reservado',
            self::Sold           => 'Vendido',
            self::Rented         => 'Rentado',
            self::Damaged        => 'Dañado',
            self::Scrapped       => 'Desguazado',
            self::Lost           => 'Perdido',
        };
    }

    /** Color del badge en la tabla. */
    public function color(): string
    {
        return match ($this) {
            self::InYard                       => 'green',
            self::Sold, self::Rented           => 'blue',
            self::OnOrder, self::InTransit,
            self::AtSupplier                   => 'yellow',
            self::Reconditioning, self::Reserved => 'gray',
            default                            => 'red',
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::InYard;
    }

    /** Estados que ya no permiten mover el contenedor. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Sold, self::Scrapped, self::Lost], true);
    }

    /**
     * Para llenar un <select> sin escribir las opciones a mano.
     * Si mañana agregas un estado, el select se actualiza solo.
     */
    
    /*public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }*/
}
