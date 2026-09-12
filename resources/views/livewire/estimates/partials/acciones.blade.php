{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS BOTONES DE ACCIÓN DE UNA FILA
    ═══════════════════════════════════════════════════════════════════════

    Está en un archivo aparte porque lo usan las tres vistas de la tabla.
    Si mañana hay que agregar una acción, se agrega una vez y aparece en
    las tres.

    Se le pasa el presupuesto:

        @include('livewire.estimates.partials.acciones', ['p' => $p])

    ── EL CRITERIO DE LOS COLORES ──

    En reposo son todos gris. El color aparece al pasar el mouse: azul
    ver, ámbar editar, rojo borrar.

    Es al revés de como suele hacerse, y es a propósito: cuatro iconos de
    colores distintos en cada una de quince filas es un semáforo roto.
    En gris, la vista se va a los datos, que es donde tiene que ir.

    Y el de borrar va separado por una línea, para que nadie lo pulse por
    inercia viniendo de la izquierda.
--}}
<div class="acciones">

    <a href="{{ route('comercial.presupuestos.show', $p) }}"
       class="acc acc-ver" title="Ver">
        <i class="bi bi-eye"></i>
    </a>

    @if ($p->isEditable())
        <a href="{{ route('comercial.presupuestos.edit', $p) }}"
           class="acc acc-editar" title="Editar">
            <i class="bi bi-pencil"></i>
        </a>
    @endif

    {{--
        DUPLICAR

        Para dos situaciones de todos los días: el presupuesto se venció a
        los 3 días y el cliente llama al cuarto, o pide "lo mismo pero con
        dos contenedores".

        La copia nace como borrador y con número nuevo. No toca el
        original.
    --}}
    @can('estimates.create')
        <button class="acc acc-copiar"
                wire:click="duplicar({{ $p->id }})"
                title="Duplicar como borrador nuevo">
            <i class="bi bi-files"></i>
        </button>
    @endcan

    @if ($p->status === \App\Enums\EstimateStatus::Draft)
        @can('estimates.delete')
            <button class="acc acc-borrar acc-separado"
                    wire:click="confirmarBorrado({{ $p->id }})"
                    title="Eliminar">
                <i class="bi bi-trash"></i>
            </button>
        @endcan
    @endif

</div>
