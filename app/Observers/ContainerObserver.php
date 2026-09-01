<?php

namespace App\Observers;

use App\Enums\ContainerStatus;
use App\Enums\MovementType;
use App\Models\Container;
use App\Models\ContainerMovement;

class ContainerObserver
{
    /**
     * **Qué vigila:** los contenedores.
    **Qué hace:** escribe la línea de tiempo de cada unidad.
     */



    /**
     * Escribe el historial de cada contenedor, automáticamente.
     *
     * Cada vez que una unidad cambia de estado o de ubicación, queda una
     * fila en container_movements con el antes, el después y quién lo hizo.
     *
     * Nadie crea movimientos a mano. Si alguien lo hiciera, tendríamos dos
     * fuentes del historial y tarde o temprano dirían cosas distintas.
     */

   /**
     * Antes de crear el contenedor: completar lo que falte.
     */
    public function creating(Container $container): void
    {
        // Estado inicial. Si viene de un release todavía no retirado,
        // quien lo crea debe pasar AtSupplier explícitamente.
        $container->status ??= ContainerStatus::InYard;

        $container->created_by ??= auth()->id();
    }

    /**
     * Después de crearlo: dejar la primera marca del historial.
     *
     * Sin este movimiento inicial, la línea de tiempo empezaría en el
     * segundo cambio y no sabrías cuándo entró la unidad al sistema.
     */
    public function created(Container $container): void
    {
        ContainerMovement::create([
            'container_id'   => $container->id,
            'type'           => MovementType::Receipt,

            // No hay estado anterior: es el nacimiento del registro.
            'status_before'  => null,
            'status_after'   => $container->status,

            'to_location_id' => $container->location_id,
            'from_depot_id'  => $container->depot_id,

            'moved_at'       => now(),
            'notes'          => 'Alta en el sistema',
            'created_by'     => auth()->id(),
        ]);
    }

    /**
     * Antes de actualizar: validar que el cambio de estado tenga sentido.
     *
     * Un contenedor vendido no vuelve a "en yarda". Si eso pasa, es un
     * error de captura, y es mejor detenerlo que descubrirlo en el
     * inventario del mes que viene.
     */
    public function updating(Container $container): void
    {
        if (! $container->isDirty('status')) {
            return;
        }

        $anterior = ContainerStatus::tryFrom(
            (string) $container->getRawOriginal('status'),
        );

        /* -----------------------------------------------------------
         | Estados finales: vendido, desguazado, perdido.
         |
         | isFinal() ya existe en el enum. Si el estado anterior era
         | final, el contenedor no debería moverse más.
         |
         | La única salida es corregirlo desde una pantalla de
         | administración que lo haga con saveQuietly(), saltándose
         | este observer a propósito y dejando constancia.
         * -------------------------------------------------------- */
        if ($anterior?->isFinal()) {
            throw new \RuntimeException(
                'El contenedor '.$container->full_identifier.' está en estado "'
                .$anterior->label().'", que es definitivo. '
                .'Para revertirlo se necesita una corrección administrativa.',
            );
        }
    }

    /**
     * Después de actualizar: escribir el movimiento si hubo cambio real.
     *
     * Usamos 'updated' y no 'updating' porque queremos registrar el
     * movimiento solo si el guardado salió bien. Si la base rechaza
     * el UPDATE, no debe quedar historial de algo que no pasó.
     */
    public function updated(Container $container): void
    {
        /* -----------------------------------------------------------
         | wasChanged() pregunta "¿esta columna cambió en el guardado
         | que acaba de terminar?".
         |
         | Solo nos importan estas tres. Si alguien corrigió una nota o
         | el peso tara, no hace falta una fila de historial: para eso
         | está la bitácora de Spatie (activitylog).
         * -------------------------------------------------------- */
        $cambioEstado    = $container->wasChanged('status');
        $cambioUbicacion = $container->wasChanged('location_id');
        $cambioDeposito  = $container->wasChanged('depot_id');

        if (! $cambioEstado && ! $cambioUbicacion && ! $cambioDeposito) {
            return;
        }

        $estadoAnterior = ContainerStatus::tryFrom(
            (string) $container->getRawOriginal('status'),
        );

        ContainerMovement::create([
            'container_id'     => $container->id,

            'type'             => $this->deducirTipo($container, $estadoAnterior),

            'status_before'    => $estadoAnterior,
            'status_after'     => $container->status,

            'from_location_id' => $container->getOriginal('location_id'),
            'to_location_id'   => $container->location_id,
            'from_depot_id'    => $container->getOriginal('depot_id'),

            'moved_at'         => now(),
            'created_by'       => auth()->id(),
        ]);
    }

    /**
     * Deduce qué clase de movimiento fue, a partir del estado nuevo.
     *
     * Es una aproximación: el sistema no sabe el motivo real, solo el
     * resultado. Cuando la venta o el viaje crean el movimiento por su
     * cuenta pueden pasar el tipo exacto y la referencia.
     *
     * Por eso container_movements tiene reference_type/reference_id:
     * para que un movimiento pueda decir "esto fue por la venta #340".
     */
    protected function deducirTipo(
        Container $container,
        ?ContainerStatus $anterior,
    ): MovementType {
        return match ($container->status) {
            ContainerStatus::Sold      => MovementType::Sale,
            ContainerStatus::Rented    => MovementType::Delivery,
            ContainerStatus::InTransit => MovementType::Transfer,

            // Volver a la yarda desde tránsito o renta es una devolución;
            // llegar por primera vez es una recepción.
            ContainerStatus::InYard    => in_array($anterior, [
                ContainerStatus::InTransit,
                ContainerStatus::Rented,
            ], true)
                ? MovementType::Return
                : MovementType::Receipt,

            default => MovementType::StatusChange,
        };
    }
}
