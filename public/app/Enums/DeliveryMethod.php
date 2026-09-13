<?php

namespace App\Enums;

use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/**
 * Cómo sale el contenedor de la yarda hacia el cliente (RB-054).
 *
 * ── POR QUÉ YA NO ESTÁ "Pickup" ──
 *
 * Antes había un caso 'pickup' que significaba "lo recogemos del
 * depósito del proveedor". Eso no es una forma de entregarle al
 * cliente: es el viaje de ENTRADA del contenedor a la yarda, que
 * pasa antes y lo paga FLCHR (RB-031).
 *
 * Ese caso vive en TripType, que es donde corresponde. Tenerlo acá
 * hacía que apareciera en el desplegable de una venta, donde no
 * significa nada.
 */
enum DeliveryMethod: string
{
    use ConcernsHasOptions;

    case Delivery       = 'delivery';          // se lo llevamos nosotros
    case CustomerPickup = 'customer_pickup';   // el cliente lo retira de la yarda
    case ShippingLine   = 'shipping_line';     // lo recoge su naviera (exportación)

    public function label(): string
    {
        return match ($this) {
            self::Delivery       => 'Entrega a domicilio',
            self::CustomerPickup => 'Retiro por el cliente',
            self::ShippingLine   => 'Lo recoge la naviera',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivery       => 'blue',
            self::CustomerPickup => 'gray',
            self::ShippingLine   => 'indigo',
        };
    }

    /**
     * ¿Hay que crear un viaje nuestro?
     *
     * Solo la entrega. Si el cliente lo retira no hay camión que
     * programar ni chofer a quien pagarle (RB-032), y en exportación
     * el shipping line es del cliente (RB-017).
     */
    public function createsTrip(): bool
    {
        return $this === self::Delivery;
    }

    /** Se le pide dirección de entrega solo cuando la llevamos nosotros. */
    public function requiresAddress(): bool
    {
        return $this === self::Delivery;
    }

    /** ¿Se le cobra transporte al cliente? */
    public function isBillable(): bool
    {
        return $this === self::Delivery;
    }
}