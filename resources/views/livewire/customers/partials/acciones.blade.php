{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS BOTONES DE ACCIÓN DE UNA FILA DE CLIENTE
    ═══════════════════════════════════════════════════════════════════════

    Está en un archivo aparte porque lo usan las dos vistas de la tabla.
    Si mañana hay que agregar una acción, se agrega una vez y aparece en
    las dos.

    Se le pasa el cliente:

        @include('livewire.customers.partials.acciones', ['c' => $cliente])

    ── EL CRITERIO DE LOS COLORES ──

    En reposo son todos gris. El color aparece al pasar el mouse: azul
    ver, ámbar editar, rojo desactivar. Es el mismo criterio del listado
    de presupuestos, y la razón es la misma: cuatro iconos de colores en
    cada una de quince filas es un semáforo roto.

    ── LA CONFIRMACIÓN VIVE AQUÍ ──

    Desactivar un cliente no se pregunta con un `confirm()` del navegador
    ni con un modal: se reemplaza el grupo de botones por "¿Desactivar?
    Sí / No" en el mismo sitio. No hay nada que abrir ni que cerrar, y
    Livewire puede repintar la fila sin que quede nada colgado.
--}}
<div class="acciones">

    @if ($porCambiar === $c->id)

        <div class="d-inline-flex align-items-center gap-2">
            <small class="text-secondary">
                {{ $c->is_active ? '¿Desactivar?' : '¿Activar?' }}
            </small>
            <button class="btn btn-sm btn-danger" wire:click="cambiarEstado">Sí</button>
            <button class="btn btn-sm btn-outline-secondary" wire:click="cancelarCambio">No</button>
        </div>

    @else

        <a href="{{ route('comercial.clientes.show', $c) }}"
           class="acc acc-ver" title="Ver ficha">
            <i class="bi bi-eye"></i>
        </a>

        @can('customers.update')
            <a href="{{ route('comercial.clientes.edit', $c) }}"
               class="acc acc-editar" title="Editar">
                <i class="bi bi-pencil"></i>
            </a>

            <button class="acc {{ $c->is_active ? 'acc-borrar' : 'acc-ver' }} acc-separado"
                    wire:click="pedirCambio({{ $c->id }})"
                    title="{{ $c->is_active ? 'Desactivar' : 'Activar' }}">
                <i class="bi bi-{{ $c->is_active ? 'person-dash' : 'person-check' }}"></i>
            </button>
        @endcan

    @endif

</div>
