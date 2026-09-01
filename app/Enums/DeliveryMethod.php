<?php

namespace App\Enums;



use App\Models\Concerns\HasOptions as ConcernsHasOptions;


/**
 * Cómo llega el contenedor a manos del cliente.
 *
 * Decide dos cosas: si se cobra transporte y si hace falta programar
 * un viaje.
 */
enum DeliveryMethod: string
{
     use ConcernsHasOptions;

    case Delivery       = 'delivery';          // se lo llevamos nosotros
    case Pickup         = 'pickup';            // lo recogemos del depósito
    case CustomerPickup = 'customer_pickup';   // el cliente lo retira de la yarda

    public function label(): string
    {
        return match ($this) {
            self::Delivery       => 'Entrega a domicilio',
            self::Pickup         => 'Recogida en depósito',
            self::CustomerPickup => 'Retiro por el cliente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Delivery       => 'blue',
            self::Pickup         => 'yellow',
            self::CustomerPickup => 'gray',
        };
    }

    /**
     * ¿Hay que crear un viaje?
     *
     * Cuando el cliente retira por su cuenta no hay transporte que
     * programar ni chofer al que pagarle (RB-032).
     */
    public function createsTrip(): bool
    {
        return ! $this->is(self::CustomerPickup);
    }

    /**
     * RB-031: los pickups tienen fee FIJO por depósito (solo hay 3
     * depósitos); los deliveries se calculan por millas.
     *
     * Es la misma distinción que hace TripType::pricesByDepotFee().
     */
    public function pricesByDepotFee(): bool
    {
        return $this === self::Pickup;
    }

    /** Se le pide dirección de entrega al cliente. */
    public function requiresAddress(): bool
    {
        return $this === self::Delivery;
    }
}