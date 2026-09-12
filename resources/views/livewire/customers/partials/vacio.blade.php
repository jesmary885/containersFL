{{--
    ═══════════════════════════════════════════════════════════════════════
    CUANDO NO HAY NADA QUE MOSTRAR
    ═══════════════════════════════════════════════════════════════════════

    Una tabla sin filas no debería ser un hueco blanco. Debería decir qué
    pasó y qué hacer a continuación.

    Y distingue los dos casos, que no son lo mismo:

      · "no hay resultados con este filtro"  →  quitar el filtro
      · "todavía no hay nada"                →  crear el primero

    Si los dos dijeran lo mismo, el usuario con un filtro puesto
    concluiría que se le borraron los datos.
--}}
<div class="vacio">
    <i class="bi bi-people"></i>

    @if ($buscar || $tipo || $marca || $estado === 'inactivos')
        No hay clientes que coincidan con el filtro.
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-secondary" wire:click="limpiarFiltros">
                Quitar los filtros
            </button>
        </div>
    @else
        Todavía no hay clientes registrados.
        @can('customers.create')
            <div class="mt-2">
                <a href="{{ route('comercial.clientes.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Registrar el primero
                </a>
            </div>
        @endcan
    @endif
</div>
