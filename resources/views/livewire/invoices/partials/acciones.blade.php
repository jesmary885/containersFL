{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS BOTONES DE ACCIÓN DE UNA FACTURA
    ═══════════════════════════════════════════════════════════════════════

        @include('livewire.invoices.partials.acciones', ['f' => $f])

    ── POR QUÉ "COBRAR" ES EL QUE RESALTA ──

    De todo lo que se puede hacer con una factura, registrar el pago es lo
    que se hace todos los días y lo que cambia el número de arriba. Ver y
    editar son de vez en cuando.

    Por eso el de cobrar lleva color en reposo y los demás no. Es el único
    de la fila que grita, y grita el que toca.

    Solo sale si la factura debe algo: en una pagada no hay nada que
    cobrar, y un botón que no hace nada es peor que ningún botón.

    ── NO HAY BOTÓN DE BORRAR ──

    Una factura emitida no se borra: se ANULA. Borrarla dejaría un hueco
    en la numeración, y una numeración con huecos es lo primero que mira
    una auditoría. La anulación vive en la ficha, donde hay sitio para
    preguntar el motivo.
--}}
<div class="acciones">

    <a href="{{ route('finanzas.facturacion.show', $f) }}"
       class="acc acc-ver" title="Ver la factura">
        <i class="bi bi-eye"></i>
    </a>

    @if ($f->isEditable())
        @can('invoices.update')
            <a href="{{ route('finanzas.facturacion.edit', $f) }}"
               class="acc acc-editar" title="Editar">
                <i class="bi bi-pencil"></i>
            </a>
        @endcan
    @endif

    {{--
        REGISTRAR UN PAGO

        Era un boton verde grande que decia "Cobrar". Dos problemas:

        1. "Cobrar" suena a que el sistema le cobra al cliente, y no hace
           eso: el cliente ya pagó, esto solo lo anota. El nombre
           prometia una accion que no ocurre.

        2. Un boton grande en cada una de quince filas convierte el
           listado en una pared verde. Lo que tiene que resaltar es la
           fila que necesita atencion, no la accion que esta en todas.

        Ahora es un icono como los otros, del mismo tamano, y se pinta de
        verde solo al pasar el mouse.
    --}}
    @if ((float) $f->balance_due > 0 && ! $f->status->isLocked())
        @can('payments.create')
            <a href="{{ route('finanzas.pagos.create') }}?factura={{ $f->id }}"
               class="acc acc-cobrar acc-separado"
               title="Registrar un pago de esta factura">
                <i class="bi bi-cash-coin"></i>
            </a>
        @endcan
    @endif

</div>
