{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL CONTENEDOR
    ═══════════════════════════════════════════════════════════════════════

    Lo primero que se ve es si se puede vender hoy. No el estado: si se
    puede vender.

    Son cosas distintas. Una unidad "en yarda" con una venta encima
    aparece en verde en cualquier listado y no está disponible (RB-019).
    Ese malentendido cuesta una llamada al cliente para desdecirse.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">

        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="mb-0 fw-semibold font-monospace">{{ $container->full_identifier }}</h4>

                <x-ui.badge :color="$container->status->color()" :label="$container->status->label()" />

                @if ($container->is_export_eligible)
                    <span class="badge bg-info-subtle text-info-emphasis">
                        <i class="bi bi-globe-americas"></i> Exportable
                    </span>
                @endif
            </div>

            <small class="text-secondary">
                {{ $container->classification ?: 'Sin clasificar' }}

                @if ($container->year_manufactured)
                    · año {{ $container->year_manufactured }}
                @endif

                @if ($container->received_at)
                    · recibida el {{ $container->received_at->format('d/m/Y') }}
                @endif
            </small>
        </div>

        <div class="d-flex flex-wrap gap-2">

            <button type="button" class="btn btn-outline-secondary"
                    onclick="history.back()" title="La pantalla de la que viene">
                <i class="bi bi-arrow-left me-1"></i> Atrás
            </button>

            <a href="{{ route('operaciones.contenedores.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-list-ul me-1"></i> Todo el inventario
            </a>

            @can('containers.update')
                <button type="button" class="btn btn-outline-warning" wire:click="abrirMover">
                    <i class="bi bi-arrows-move me-1"></i> Mover
                </button>

                <a href="{{ route('operaciones.contenedores.edit', $container) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Editar
                </a>
            @endcan
        </div>

    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{--
        ¿SE PUEDE VENDER HOY?

        Va arriba del todo, antes que cualquier dato, porque es la
        pregunta con la que se abre esta pantalla.
    --}}
    @if ($disponible)
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-1"></i>
            <strong>Disponible.</strong>
            Está en yarda y no tiene ninguna venta ni renta encima: se puede cotizar hoy.
        </div>
    @else
        <div class="alert alert-secondary">
            <i class="bi bi-slash-circle me-1"></i>
            <strong>No disponible.</strong>
            @if ($container->status->isFinal())
                Esta unidad ya salió del inventario.
            @elseif ($container->status->isAvailable())
                Está en yarda, pero tiene una venta o una renta encima.
            @else
                Está {{ mb_strtolower($container->status->label()) }}.
            @endif
        </div>
    @endif

    {{-- ───── LOS NÚMEROS ───── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-apagado">
                <span class="kpi-icono"><i class="bi bi-wallet2"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Nos costó</span>
                    <span class="kpi-valor d-block">${{ number_format($container->total_cost, 2) }}</span>
                    <span class="kpi-pie d-block">Puesta en yarda</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi {{ $container->list_price !== null ? 'kpi-info' : 'kpi-bad' }}">
                <span class="kpi-icono"><i class="bi bi-tag"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Precio de venta</span>
                    <span class="kpi-valor d-block">
                        {{ $container->list_price !== null
                            ? '$'.number_format((float) $container->list_price, 2)
                            : '—' }}
                    </span>
                    <span class="kpi-pie d-block">
                        {{ $container->list_price !== null ? 'Se precarga al cotizar' : 'Sin precio cargado' }}
                    </span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            @php
                $margen = ($container->list_price !== null && $container->total_cost > 0)
                    ? (float) $container->list_price - $container->total_cost
                    : null;
            @endphp

            <div class="kpi {{ $margen === null ? 'kpi-apagado' : ($margen > 0 ? 'kpi-ok' : 'kpi-bad') }}">
                <span class="kpi-icono"><i class="bi bi-graph-up-arrow"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Deja</span>
                    <span class="kpi-valor d-block">
                        {{ $margen === null ? '—' : '$'.number_format($margen, 2) }}
                    </span>
                    <span class="kpi-pie d-block">
                        @if ($margen === null)
                            Falta el costo o el precio
                        @else
                            {{ round(($margen / $container->total_cost) * 100, 1) }}% sobre el costo
                        @endif
                    </span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-apagado">
                <span class="kpi-icono"><i class="bi bi-calendar-check"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Renta mensual</span>
                    <span class="kpi-valor d-block">
                        {{ $container->monthly_rate !== null
                            ? '$'.number_format((float) $container->monthly_rate, 2)
                            : '—' }}
                    </span>
                    <span class="kpi-pie d-block">Por mes, anclada al día de entrega</span>
                </span>
            </div>
        </div>

    </div>

    <div class="row g-3">

        {{-- ═════════════════════════════════════════════════════════
             COLUMNA IZQUIERDA · LOS DATOS
        ═════════════════════════════════════════════════════════ --}}
        <div class="col-12 col-lg-5">

            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-info-circle me-1"></i> Qué es y dónde está</span>
                </div>
                <div class="card-body">

                    <dl class="row mb-0 small">

                        <dt class="col-5 text-secondary fw-normal">Número</dt>
                        <dd class="col-7 font-monospace">{{ $container->container_number ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Código interno</dt>
                        <dd class="col-7">{{ $container->internal_code ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Medida</dt>
                        <dd class="col-7">{{ $container->size?->name ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Tipo</dt>
                        <dd class="col-7">{{ $container->type?->name ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Condición</dt>
                        <dd class="col-7">{{ $container->condition?->name ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Calidad</dt>
                        <dd class="col-7">{{ $container->grade?->name ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Ubicación</dt>
                        <dd class="col-7">
                            {{ $container->location?->name ?? ($container->depot?->name ?? '—') }}
                            @if ($container->depot)
                                <span class="badge bg-warning-subtle text-warning-emphasis">En depósito</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-secondary fw-normal">Pesos</dt>
                        <dd class="col-7">
                            @if ($container->tare_weight_lbs || $container->max_weight_lbs)
                                {{ number_format((int) $container->tare_weight_lbs) }} lbs tara ·
                                {{ number_format((int) $container->max_weight_lbs) }} lbs máx.
                            @else
                                —
                            @endif
                        </dd>

                        @if ($container->is_export_eligible)
                            <dt class="col-5 text-secondary fw-normal">CSC vigente</dt>
                            <dd class="col-7">
                                {{ $container->csc_valid_through?->format('d/m/Y') ?? 'Sin registrar' }}
                                @if ($container->csc_valid_through && $container->csc_valid_through->isPast())
                                    <span class="badge bg-danger-subtle text-danger">Vencido</span>
                                @endif
                            </dd>
                        @endif

                        <dt class="col-5 text-secondary fw-normal">Dueña</dt>
                        <dd class="col-7">{{ $container->ownerCompany?->code ?: '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">La vende</dt>
                        <dd class="col-7">{{ $container->billingCompany?->code ?: '—' }}</dd>

                    </dl>

                    @if ($container->condition_notes)
                        <hr>
                        <div class="small">
                            <div class="text-secondary mb-1">Notas de condición</div>
                            {{ $container->condition_notes }}
                        </div>
                    @endif

                </div>
            </div>

            {{-- ───── EL DESGLOSE DEL COSTO ───── --}}
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-cash-coin me-1"></i> De dónde sale el costo</span>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-secondary">Compra</td>
                                <td class="text-end monto">
                                    ${{ number_format((float) $container->acquisition_cost, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-secondary">
                                    Recogida del depósito
                                    <i class="bi bi-info-circle small"
                                       title="Es costo nuestro. Nunca se le cotiza al cliente."></i>
                                </td>
                                <td class="text-end monto">
                                    ${{ number_format((float) $container->pickup_cost, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-secondary">Reacondicionamiento</td>
                                <td class="text-end monto">
                                    ${{ number_format((float) $container->reconditioning_cost, 2) }}
                                </td>
                            </tr>
                            <tr class="fw-semibold border-top">
                                <td>Total puesta en yarda</td>
                                <td class="text-end monto">
                                    ${{ number_format($container->total_cost, 2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        {{-- ═════════════════════════════════════════════════════════
             COLUMNA DERECHA · HISTORIAL
        ═════════════════════════════════════════════════════════ --}}
        <div class="col-12 col-lg-7">

            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-start">
                    <span>
                        <span class="fw-semibold">
                            <i class="bi bi-clock-history me-1"></i> Dónde ha estado
                        </span>
                        <small class="text-secondary d-block">
                            Cada alta, traslado y cambio de estado, con fecha y con autor.
                        </small>
                    </span>
                </div>

                <div class="card-body">

                    @forelse ($movimientos as $m)

                        <div class="d-flex gap-3 pb-3 mb-3 border-bottom" wire:key="mov-{{ $m->id }}">

                            <div class="flex-shrink-0 text-center" style="width: 46px;">
                                <div class="fw-semibold small">{{ $m->moved_at?->format('d/m') }}</div>
                                <div class="text-secondary" style="font-size: .7rem;">
                                    {{ $m->moved_at?->format('Y') }}
                                </div>
                            </div>

                            <div class="flex-grow-1 min-w-0">

                                <div class="fw-semibold small">
                                    {{ $m->type instanceof \App\Enums\MovementType
                                        ? $m->type->label()
                                        : $m->type }}
                                </div>

                                @if ($m->status_before && $m->status_after && $m->status_before !== $m->status_after)
                                    <div class="small">
                                        {{ \App\Enums\ContainerStatus::tryFrom($m->status_before)?->label() ?? $m->status_before }}
                                        <i class="bi bi-arrow-right mx-1 text-secondary"></i>
                                        <strong>{{ \App\Enums\ContainerStatus::tryFrom($m->status_after)?->label() ?? $m->status_after }}</strong>
                                    </div>
                                @endif

                                @if ($m->toLocation || $m->fromLocation)
                                    <div class="small text-secondary">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        {{ $m->fromLocation?->name ?? 'Sin ubicación' }}
                                        <i class="bi bi-arrow-right mx-1"></i>
                                        {{ $m->toLocation?->name ?? 'Sin ubicación' }}
                                    </div>
                                @endif

                                @if ($m->notes)
                                    <div class="small fst-italic text-secondary">{{ $m->notes }}</div>
                                @endif

                                <div class="text-secondary" style="font-size: .72rem;">
                                    {{ $m->createdBy?->name ?? 'El sistema' }}
                                    · {{ $m->moved_at?->format('H:i') }}
                                </div>

                            </div>

                        </div>

                    @empty

                        <div class="text-center py-3 text-secondary">
                            <i class="bi bi-clock-history fs-3 d-block mb-2 opacity-50"></i>
                            <div class="small">
                                Sin movimientos registrados. Los que se hagan desde ahora quedan aquí.
                            </div>
                        </div>

                    @endforelse

                </div>
            </div>

            {{-- ───── VENTAS Y RENTAS ───── --}}
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-receipt me-1"></i> A quién se le ha dado</span>
                </div>
                <div class="card-body">

                    <div class="fw-semibold small text-secondary mb-2">Ventas</div>

                    @forelse ($ventas as $v)
                        <div class="small mb-1">
                            {{ $v->sale_date?->format('d/m/Y') }} ·
                            <strong>{{ $v->customer?->name ?? '—' }}</strong> ·
                            ${{ number_format((float) ($v->pivot->unit_price ?? 0), 2) }}
                        </div>
                    @empty
                        <div class="small text-secondary mb-2">Nunca se ha vendido.</div>
                    @endforelse

                    <div class="fw-semibold small text-secondary mt-3 mb-2">Rentas</div>

                    @forelse ($rentas as $r)
                        <div class="small mb-1">
                            {{ $r->start_date?->format('d/m/Y') }} ·
                            <strong>{{ $r->customer?->name ?? '—' }}</strong> ·
                            ${{ number_format((float) ($r->pivot->monthly_rate ?? 0), 2) }}/mes
                        </div>
                    @empty
                        <div class="small text-secondary">Nunca se ha rentado.</div>
                    @endforelse

                </div>
            </div>

            {{-- ───── DOCUMENTOS ───── --}}
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-paperclip me-1"></i> Fotos y papeles</span>
                </div>
                <div class="card-body">

                    @forelse ($documentos as $doc)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2"
                             wire:key="doc-{{ $doc->id }}">
                            <div class="min-w-0">
                                <div class="small fw-medium text-truncate">{{ $doc->name }}</div>
                                <div class="text-secondary" style="font-size: .72rem;">
                                    {{ $doc->category?->label() }} · {{ $doc->readable_size }}
                                </div>
                            </div>

                            <a href="{{ route('documentos.descargar', $doc) }}"
                               class="btn btn-sm btn-outline-secondary flex-shrink-0">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                    @empty
                        <div class="small text-secondary">
                            Sin fotos ni papeles de esta unidad.
                        </div>
                    @endforelse

                </div>
            </div>

        </div>

    </div>

    {{-- ═════════════════════════════════════════════════════════════
         EL MODAL DE MOVER

         Dibujado a mano y no con el JavaScript de Bootstrap. Livewire
         vuelve a pintar este pedazo cada vez que algo cambia, y un modal
         abierto por JavaScript se queda colgado cuando eso pasa: el
         fondo gris pegado y los clics bloqueados.
    ═════════════════════════════════════════════════════════════ --}}
    @if ($modalMover)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,.55);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            Mover {{ $container->full_identifier }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="cerrarMover"></button>
                    </div>

                    <div class="modal-body">

                        <div class="alert alert-light border py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Queda registrado con la fecha y con su nombre. Así se puede contestar
                            después dónde estaba esta unidad en marzo.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select @error('nuevoEstado') is-invalid @enderror"
                                    wire:model="nuevoEstado">
                                @foreach ($estados as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            @error('nuevoEstado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ubicación</label>
                            <select class="form-select" wire:model="nuevaUbicacion">
                                <option value="">— Sin ubicación —</option>
                                @foreach ($ubicaciones as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Nota</label>
                            <input type="text" class="form-control"
                                   placeholder="Opcional. Ej: se movió a la fila 3 para pintar"
                                   wire:model="notaMovimiento">
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" wire:click="cerrarMover">
                            Cancelar
                        </button>
                        <button class="btn btn-primary" wire:click="mover">
                            <i class="bi bi-check-lg me-1"></i> Registrar el movimiento
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
