{{--
    ═══════════════════════════════════════════════════════════════════════
    CUANDO NO HAY NADA QUE MOSTRAR
    ═══════════════════════════════════════════════════════════════════════

    Tres casos distintos, y cada uno lleva a un sitio distinto:

      · hay filtros puestos          →  quitarlos
      · está el filtro de pendientes →  enseñar también las pagadas
      · no hay ninguna factura       →  hacer la primera, o convertir un
                                        presupuesto aceptado

    El tercero es el que más importa. La forma normal de que nazca una
    factura en este sistema NO es el botón de "Nueva factura": es un
    presupuesto que el cliente aceptó. Decirlo aquí ahorra explicarlo.
--}}
<div class="vacio">
    <i class="bi bi-receipt"></i>

    @if ($buscar || $estado || $tipo)

        No hay facturas que coincidan con el filtro.
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-secondary" wire:click="limpiarFiltros">
                Quitar los filtros
            </button>
        </div>

    @elseif ($soloPendientes)

        No hay facturas con saldo pendiente. Todo cobrado.
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-secondary"
                    wire:click="$set('soloPendientes', false)">
                Ver también las pagadas
            </button>
        </div>

    @else

        Todavía no hay facturas.
        <div class="mt-2 d-flex flex-wrap gap-2 justify-content-center">
            @can('invoices.create')
                <a href="{{ route('finanzas.facturacion.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Hacer una a mano
                </a>
            @endcan

            @can('estimates.view')
                <a href="{{ route('comercial.presupuestos.index') }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-file-earmark-text me-1"></i> Convertir un presupuesto
                </a>
            @endcan
        </div>

        <div class="small text-secondary mt-2">
            Lo normal es lo segundo: el cliente acepta el presupuesto y se convierte.
        </div>

    @endif
</div>
