<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/**
 * Qué clase de movimiento fue.
 *
 * La línea de tiempo de un contenedor se lee de arriba abajo con estos
 * tipos: recibido → transferido → entregado → devuelto.
 *
 * El ContainerObserver lo deduce del cambio de estado. Cuando la venta
 * o el viaje crean el movimiento por su cuenta, pasan el tipo exacto y
 * además la referencia (reference_type / reference_id), que es lo que
 * permite decir "esto pasó por la venta #340".
 */
enum MovementType: string
{
    
     use ConcernsHasOptions;

    case Receipt      = 'receipt';         // entró a la yarda
    case Transfer     = 'transfer';        // cambió de ubicación
    case Delivery     = 'delivery';        // salió hacia el cliente
    case Return       = 'return';          // volvió del cliente
    case Sale         = 'sale';            // salió vendido, no vuelve
    case StatusChange = 'status_change';   // cambió de estado sin moverse
    case Adjustment   = 'adjustment';      // corrección manual de inventario

    public function label(): string
    {
        return match ($this) {
            self::Receipt      => 'Recepción',
            self::Transfer     => 'Traslado',
            self::Delivery     => 'Entrega',
            self::Return       => 'Devolución',
            self::Sale         => 'Venta',
            self::StatusChange => 'Cambio de estado',
            self::Adjustment   => 'Ajuste',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Receipt, self::Return => 'green',
            self::Delivery, self::Sale  => 'blue',
            self::Transfer              => 'yellow',
            self::Adjustment            => 'red',
            self::StatusChange          => 'gray',
        };
    }

    /** Icono de Bootstrap Icons para la línea de tiempo. */
    public function icon(): string
    {
        return match ($this) {
            self::Receipt      => 'bi-box-arrow-in-down',
            self::Transfer     => 'bi-arrow-left-right',
            self::Delivery     => 'bi-truck',
            self::Return       => 'bi-arrow-return-left',
            self::Sale         => 'bi-cash-coin',
            self::StatusChange => 'bi-arrow-repeat',
            self::Adjustment   => 'bi-pencil-square',
        };
    }

    /**
     * ¿El contenedor cambió de sitio físicamente?
     *
     * Un cambio de estado (de "en yarda" a "reservado") no mueve nada:
     * la unidad sigue donde estaba. Sirve para filtrar el historial
     * cuando alguien pregunta "¿por dónde ha pasado esta unidad?".
     */
    public function isPhysical(): bool
    {
        return $this->is(
            self::Receipt,
            self::Transfer,
            self::Delivery,
            self::Return,
            self::Sale,
        );
    }

    /**
     * Los ajustes son correcciones manuales de inventario y conviene
     * poder auditarlos aparte: si aparecen muchos, algo se está
     * registrando mal en el flujo normal.
     */
    public function isManual(): bool
    {
        return $this === self::Adjustment;
    }
}