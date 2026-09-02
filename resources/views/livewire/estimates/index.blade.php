{{--
    ═══════════════════════════════════════════════════════════════════════
    LISTADO DE PRESUPUESTOS
    ═══════════════════════════════════════════════════════════════════════

    Clases de Bootstrap 5 y AdminLTE, no de Tailwind: el layout interno
    del sistema está armado con AdminLTE. El login sí usa Tailwind, y
    está bien que sean distintos porque son dos pantallas con dueños
    distintos.

    Todo el archivo va dentro de UN solo <div>. Livewire lo exige: es
    cómo sabe qué pedazo de la página tiene que refrescar.
--}}
<div>

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0">Presupuestos</h4>
            <small class="text-secondary">
                Cotizaciones enviadas a clientes. Al aceptarse se convierten en factura.
            </small>
        </div>

        <a href="{{ route('comercial.presupuestos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nuevo presupuesto
        </a>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         AVISOS

         session()->flash() guarda un mensaje que se muestra UNA vez y
         desaparece solo. Es lo correcto para un "se guardó bien": si
         se quedara fijo, el usuario lo seguiría viendo media hora
         después y ya no sabría a qué se refiere.
    ───────────────────────────────────────────────────────────── --}}
    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         LOS CUATRO CONTADORES

         No son decoración. Responden de un vistazo las preguntas que
         se hace un vendedor al abrir la pantalla: cuánto tengo en la
         calle, y qué se me está venciendo.
    ───────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body py-3">
                    <div class="text-secondary small text-uppercase">Abiertos</div>
                    <div class="fs-4 fw-semibold">{{ $resumen['abiertos'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body py-3">
                    <div class="text-secondary small text-uppercase">Monto en la calle</div>
                    <div class="fs-4 fw-semibold">
                        ${{ number_format($resumen['montoAbierto'], 2) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body py-3">
                    <div class="text-secondary small text-uppercase">Aceptados sin facturar</div>
                    <div class="fs-4 fw-semibold text-success">{{ $resumen['aceptados'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100 {{ $resumen['porVencer'] > 0 ? 'border-warning' : '' }}">
                <div class="card-body py-3">
                    <div class="text-secondary small text-uppercase">Vencidos</div>
                    <div class="fs-4 fw-semibold {{ $resumen['porVencer'] > 0 ? 'text-warning' : '' }}">
                        {{ $resumen['porVencer'] }}
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         LA TABLA
    ───────────────────────────────────────────────────────────── --}}
    <div class="card">

        {{-- FILTROS --}}
        <div class="card-header">
            <div class="row g-2 align-items-center">

                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>

                        {{--
                            .live.debounce.400ms significa: manda lo que
                            escribo al servidor, pero espera 400
                            milisegundos a que deje de teclear.

                            Sin el debounce, escribir "Homestead" son
                            nueve consultas a la base de datos. Con él,
                            una.
                        --}}
                        <input
                            type="search"
                            class="form-control"
                            placeholder="Número, cliente o descripción de una línea…"
                            wire:model.live.debounce.400ms="buscar"
                        >
                    </div>
                </div>

                <div class="col-8 col-md-4">
                    <select class="form-select" wire:model.live="estado">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-4 col-md-2 text-end">
                    @if ($buscar || $estado)
                        <button class="btn btn-outline-secondary w-100" wire:click="limpiarFiltros">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </button>
                    @endif
                </div>

            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            {{--
                                Cada encabezado es un botón que ordena.
                                La flechita solo aparece en la columna
                                por la que se está ordenando ahora.
                            --}}
                            <th role="button" wire:click="ordenar('estimate_number')">
                                Número
                                @if ($ordenarPor === 'estimate_number')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th>Cliente</th>

                            <th role="button" wire:click="ordenar('issue_date')">
                                Emisión
                                @if ($ordenarPor === 'issue_date')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th role="button" wire:click="ordenar('valid_until')">
                                Vence
                                @if ($ordenarPor === 'valid_until')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th class="text-end" role="button" wire:click="ordenar('total')">
                                Total
                                @if ($ordenarPor === 'total')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th>Estado</th>
                            <th class="text-end" style="width: 140px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($presupuestos as $p)
                            <tr wire:key="presupuesto-{{ $p->id }}">

                                <td>
                                    <a href="{{ route('comercial.presupuestos.show', $p) }}"
                                       class="fw-semibold text-decoration-none">
                                        {{ $p->estimate_number }}
                                    </a>
                                </td>

                                <td>
                                    {{ $p->customer?->name ?? '—' }}
                                    <div class="small text-secondary">
                                        {{ $p->customer?->customer_number }}
                                    </div>
                                </td>

                                <td>{{ $p->issue_date?->format('d/m/Y') }}</td>

                                <td>
                                    @if ($p->valid_until)
                                        {{ $p->valid_until->format('d/m/Y') }}

                                        {{--
                                            El aviso de vencido solo tiene
                                            sentido si el presupuesto sigue
                                            vivo. Uno ya convertido en
                                            factura no "vence".
                                        --}}
                                        @if ($p->isExpired())
                                            <div class="small text-danger">
                                                <i class="bi bi-clock-history"></i> Vencido
                                            </div>
                                        @elseif ($p->days_left !== null && $p->days_left <= 1)
                                            <div class="small text-warning">
                                                Vence {{ $p->days_left === 0 ? 'hoy' : 'mañana' }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-secondary">Sin límite</span>
                                    @endif
                                </td>

                                <td class="text-end fw-semibold">
                                    ${{ number_format((float) $p->total, 2) }}
                                </td>

                                <td>
                                    <x-ui.badge
                                        :color="$p->status->color()"
                                        :label="$p->status->label()"
                                    />
                                </td>

                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">

                                        <a href="{{ route('comercial.presupuestos.show', $p) }}"
                                           class="btn btn-outline-secondary"
                                           title="Ver">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        @if ($p->isEditable())
                                            <a href="{{ route('comercial.presupuestos.edit', $p) }}"
                                               class="btn btn-outline-secondary"
                                               title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endif

                                        <button class="btn btn-outline-secondary"
                                                wire:click="duplicar({{ $p->id }})"
                                                title="Duplicar">
                                            <i class="bi bi-files"></i>
                                        </button>

                                        @if ($p->status === \App\Enums\EstimateStatus::Draft)
                                            <button class="btn btn-outline-danger"
                                                    wire:click="confirmarBorrado({{ $p->id }})"
                                                    title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif

                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="bi bi-file-earmark-text d-block mb-2" style="font-size: 2rem;"></i>

                                    @if ($buscar || $estado)
                                        No hay presupuestos que coincidan con el filtro.
                                    @else
                                        Todavía no hay presupuestos.
                                        <a href="{{ route('comercial.presupuestos.create') }}">Crear el primero</a>.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>

        @if ($presupuestos->hasPages())
            <div class="card-footer">
                {{ $presupuestos->links() }}
            </div>
        @endif

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         CONFIRMACIÓN DE BORRADO

         Es un modal de Bootstrap dibujado a mano en vez de usar el
         JavaScript de Bootstrap. Motivo: Livewire vuelve a pintar este
         pedazo de página cada vez que algo cambia, y un modal abierto
         por JavaScript se queda "colgado" cuando eso pasa.

         Dibujándolo con una condición de PHP, el modal existe solo
         mientras hay algo que confirmar. No se puede desincronizar.
    ───────────────────────────────────────────────────────────── --}}
    @if ($porBorrar)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">¿Eliminar el presupuesto?</h5>
                        <button type="button" class="btn-close" wire:click="cancelarBorrado"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-0">
                            Se va a eliminar el presupuesto y todas sus líneas.
                            Esta acción no se puede deshacer.
                        </p>
                        <p class="text-secondary small mt-2 mb-0">
                            El número consumido no se reutiliza: el siguiente
                            presupuesto seguirá la numeración donde iba.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="cancelarBorrado">
                            Cancelar
                        </button>
                        <button class="btn btn-danger" wire:click="borrar">
                            <i class="bi bi-trash me-1"></i> Sí, eliminar
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
