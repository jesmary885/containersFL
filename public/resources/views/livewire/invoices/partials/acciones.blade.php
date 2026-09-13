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

    @if ((float) $f->balance_due > 0 && ! $f->status->isLocked())
        @can('payments.create')
            <a href="{{ route('finanzas.pagos.create') }}?factura={{ $f->id }}"
               class="btn btn-sm btn-success ms-1"
               title="Registrar un pago de esta factura">
                <i class="bi bi-cash-coin"></i>
                <span class="d-none d-xl-inline ms-1">Cobrar</span>
            </a>
        @endcan
    @endif

</div>
