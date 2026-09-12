{{--
    ═══════════════════════════════════════════════════════════════════════
    CUANDO NO HAY NADA QUE MOSTRAR
    ═══════════════════════════════════════════════════════════════════════

    Distingue los dos casos, que no son lo mismo:

      · "no hay resultados con este filtro"  →  quitar el filtro
      · "todavía no hay nada"                →  registrar el primero

    Si los dos dijeran lo mismo, quien tiene un filtro puesto concluiría
    que se le borró el inventario.
--}}
<div class="vacio">
    <i class="bi bi-box-seam"></i>

    @if ($this->hayFiltros)
        No hay unidades que coincidan con el filtro.
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-secondary" wire:click="limpiarFiltros">
                Quitar los filtros
            </button>
        </div>
    @else
        Todavía no hay contenedores registrados con esta empresa.
        @can('containers.create')
            <div class="mt-2">
                <a href="{{ route('operaciones.contenedores.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Registrar el primero
                </a>
            </div>
        @endcan
    @endif
</div>
