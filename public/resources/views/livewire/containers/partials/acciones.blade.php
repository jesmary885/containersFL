{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS BOTONES DE ACCIÓN DE UNA UNIDAD
    ═══════════════════════════════════════════════════════════════════════

    En un archivo aparte porque lo usan las dos vistas de la tabla.

        @include('livewire.containers.partials.acciones', ['u' => $u])

    ── NO HAY BOTÓN DE BORRAR ──

    Y no es un olvido. Un contenedor tiene movimientos, ventas, gastos y
    certificados colgando de su id. Borrarlo dejaría todo eso apuntando a
    la nada.

    Lo que de verdad pasa en la vida real es que el contenedor se
    desguaza, se pierde o se daña, y para eso están esos estados. Un
    contenedor "borrado" no existe; uno desguazado sí, y la diferencia
    importa cuando alguien pregunta qué pasó con la unidad tal.
--}}
<div class="acciones">

    <a href="{{ route('operaciones.contenedores.show', $u) }}"
       class="acc acc-ver" title="Ver la ficha">
        <i class="bi bi-eye"></i>
    </a>

    @can('containers.update')
        <a href="{{ route('operaciones.contenedores.edit', $u) }}"
           class="acc acc-editar" title="Editar">
            <i class="bi bi-pencil"></i>
        </a>
    @endcan

</div>
