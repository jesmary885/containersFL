<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/**
 * En qué punto va la venta.
 *
 * Ojo con la diferencia entre este estado y el de la factura: la venta
 * describe la OPERACIÓN (¿ya se entregó el contenedor?), la factura
 * describe el COBRO (¿ya pagó?). Una venta entregada puede tener la
 * factura sin pagar, y una venta pagada por adelantado puede seguir
 * sin entregar.
 */
enum SaleStatus: string
{

    use ConcernsHasOptions;

    case Pending   = 'pending';     // registrada, sin confirmar
    case Confirmed = 'confirmed';   // confirmada, pendiente de entrega
    case Delivered = 'delivered';   // el contenedor salió de la yarda
    case Completed = 'completed';   // entregado y cobrado
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pendiente',
            self::Confirmed => 'Confirmada',
            self::Delivered => 'Entregada',
            self::Completed => 'Completada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'green',
            self::Delivered => 'blue',
            self::Confirmed => 'yellow',
            self::Pending   => 'gray',
            self::Cancelled => 'red',
        };
    }

    /**
     * Los contenedores de esta venta todavía cuentan como comprometidos.
     *
     * Es lo que usa Container::scopeAvailable() para no ofrecer una
     * unidad que ya tiene dueño aunque siga físicamente en la yarda.
     */
    public function reservesContainers(): bool
    {
        return ! $this->is(self::Cancelled);
    }

    /**
     * RB-021: el comprador tiene 2 días para retirar; desde el 3ro se
     * generan cargos de almacenaje. El reloj arranca al confirmar,
     * no al registrar.
     */
    public function startsStorageClock(): bool
    {
        return $this->is(self::Confirmed, self::Delivered, self::Completed);
    }

    /** Ya no se puede editar: se anula y se hace una nueva. */
    public function isClosed(): bool
    {
        return $this->is(self::Completed, self::Cancelled);
    }
}