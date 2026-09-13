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
    <i class="bi bi-file-earmark-text"></i>

    @if ($buscar || $estado)
        No hay presupuestos que coincidan con el filtro.
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-secondary" wire:click="limpiarFiltros">
                Quitar los filtros
            </button>
        </div>
    @else
        Todavía no hay presupuestos.
        <div class="mt-2">
            <a href="{{ route('comercial.presupuestos.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Crear el primero
            </a>
        </div>
    @endif
</div>
