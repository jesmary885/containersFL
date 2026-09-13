{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DE LA COMPRA — y donde se reciben las unidades
    ═══════════════════════════════════════════════════════════════════════

    Esta es la pantalla que arregla el problema de los 416 contenedores
    que el Excel dice y no están.

    Recibir no es marcar una casilla: es leer el número pintado en la
    puerta del contenedor y darlo de alta. En ese momento el inventario
    sube en uno, con una unidad que existe y tiene nombre.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">

        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="mb-0 fw-semibold font-monospace">{{ $purchase->purchase_number }}</h4>
                <x-ui.badge :color="$purchase->status->color()" :label="$purchase->status->label()" />
                <span class="badge bg-light text-dark border">{{ $purchase->type?->label() }}</span>
            </div>

            <small class="text-secondary">
                {{ $purchase->supplier?->name }}
                · {{ $purchase->purchase_date?->format('d/m/Y') }}
                @if ($purchase->reference) · release {{ $purchase->reference }} @endif
            </small>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                <i class="bi bi-arrow-left me-1"></i> Atrás
            </button>

            <a href="{{ route('compras.compras.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-list-ul me-1"></i> Todas las compras
            </a>

            @can('purchases.update')
                <a href="{{ route('compras.compras.edit', $purchase) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Editar
                </a>
            @endcan
        </div>

    </div>

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{--
        EL AVISO DEL PLAZO

        Va arriba del todo porque es lo único de esta pantalla que cuesta
        dinero cada día que pasa.
    --}}
    @if ($purchase->overdue_days > 0)
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-octagon-fill me-1"></i>
            <strong>El plazo se pasó hace {{ $purchase->overdue_days }} días.</strong>
            @if ($purchase->daily_late_fee)
                El depósito está cobrando ${{ number_format((float) $purchase->daily_late_fee, 2) }}
                por día: van
                <strong>${{ number_format((float) $purchase->daily_late_fee * $purchase->overdue_days, 2) }}</strong>
                de almacenaje.
            @else
                Quedan unidades pendientes de retirar.
            @endif
        </div>
    @elseif ($faltan > 0 && $purchase->pickup_deadline_at)
        @php
            $quedan = (int) now()->startOfDay()->diffInDays($purchase->pickup_deadline_at->startOfDay());
        @endphp

        <div class="alert {{ $quedan <= 5 ? 'alert-warning' : 'alert-light border' }}">
            <i class="bi bi-clock-history me-1"></i>
            @if ($quedan === 0)
                <strong>El plazo se acaba hoy.</strong>
            @else
                Quedan <strong>{{ $quedan }} días</strong> para retirar lo que falta.
            @endif
            Después, el depósito empieza a cobrar almacenaje por día.
        </div>
    @endif

    {{-- ───── LOS NÚMEROS ───── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-apagado">
                <span class="kpi-icono"><i class="bi bi-basket"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Compradas</span>
                    <span class="kpi-valor d-block">{{ $compradas }}</span>
                    <span class="kpi-pie d-block">Total adquirido</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ok">
                <span class="kpi-icono"><i class="bi bi-box-seam"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Recibidas</span>
                    <span class="kpi-valor d-block">{{ $recibidas }}</span>
                    <span class="kpi-pie d-block">Dadas de alta en inventario</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi {{ $faltan > 0 ? 'kpi-warn' : 'kpi-apagado' }}">
                <span class="kpi-icono"><i class="bi bi-hourglass-split"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Por traer</span>
                    <span class="kpi-valor d-block">{{ $faltan }}</span>
                    <span class="kpi-pie d-block">
                        {{ $faltan > 0 ? 'Pendientes en el depósito' : 'Sin pendientes' }}
                    </span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-info">
                <span class="kpi-icono"><i class="bi bi-cash-coin"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Total de la compra</span>
                    <span class="kpi-valor d-block">${{ number_format((float) $purchase->total, 2) }}</span>
                    <span class="kpi-pie d-block">
                        Precio + pick up, como en el Excel
                    </span>
                </span>
            </div>
        </div>

    </div>

    <div class="row g-3">

        {{-- ═══════════════════════════════════════════════════
             LOS RENGLONES Y LA RECEPCIÓN
        ═══════════════════════════════════════════════════ --}}
        <div class="col-12 col-lg-8">

            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold">
                        <i class="bi bi-list-ul me-1"></i> Detalle de la compra
                    </span>
                    <small class="text-secondary d-block">
                        Cada recepción da de alta las unidades en el inventario.
                    </small>
                </div>

                <div class="card-body">

                    @foreach ($purchase->items as $item)

                        @php
                            $pendientes = max(0, $item->quantity - $item->received_quantity);
                            $pct = $item->quantity > 0
                                ? (int) round($item->received_quantity / $item->quantity * 100)
                                : 0;
                        @endphp

                        <div class="border rounded p-3 mb-3 {{ $pendientes > 0 ? '' : 'border-success' }}"
                             wire:key="item-{{ $item->id }}">

                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">

                                <div>
                                    <div class="fw-semibold">
                                        {{ $item->size?->name ?? 'Sin medida' }}
                                        @if ($item->type) · {{ $item->type->name }} @endif
                                    </div>
                                    <div class="small text-secondary">
                                        {{ collect([$item->condition?->name, $item->grade?->name])
                                            ->filter()->implode(' · ') ?: 'Sin clasificar' }}
                                        · ${{ number_format((float) $item->unit_cost, 2) }} c/u
                                    </div>
                                    @if ($item->notes)
                                        <div class="small fst-italic text-secondary">{{ $item->notes }}</div>
                                    @endif
                                </div>

                                <div class="text-end">
                                    <div class="fw-semibold">
                                        {{ $item->received_quantity }} de {{ $item->quantity }}
                                    </div>
                                    <div class="small {{ $pendientes > 0 ? 'text-warning-emphasis' : 'text-success' }}">
                                        {{ $pendientes > 0 ? 'faltan '.$pendientes : 'completo' }}
                                    </div>
                                </div>

                            </div>

                            <div class="progress mt-2" style="height: 6px;">
                                <div class="progress-bar {{ $pendientes > 0 ? 'bg-warning' : 'bg-success' }}"
                                     style="width: {{ $pct }}%"></div>
                            </div>

                            {{-- ───── RETIRAR ───── --}}
                            @if ($pendientes > 0)
                                @can('purchases.update')
                                    <button type="button" class="btn btn-sm btn-outline-success mt-3"
                                            wire:click="abrirRetiro({{ $item->id }})">
                                        <i class="bi bi-truck me-1"></i>
                                        Registrar recepción
                                    </button>
                                @endcan
                            @endif

                        </div>

                    @endforeach

                </div>
            </div>

            {{-- ───── LAS UNIDADES QUE YA ENTRARON ───── --}}
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold">
                        <i class="bi bi-box-seam me-1"></i> Unidades recibidas
                    </span>
                    <small class="text-secondary d-block">
                        Cada una con su código y su costo total.
                    </small>
                </div>

                <div class="card-body">

                    @forelse ($unidades as $u)
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2"
                             wire:key="uni-{{ $u->id }}">

                            <div>
                                <a href="{{ route('operaciones.contenedores.show', $u) }}"
                                   class="doc-numero">{{ $u->full_identifier }}</a>
                                <div class="small text-secondary">
                                    {{ $u->size?->name }}
                                    · {{ $u->location?->name ?? 'Sin ubicación' }}
                                    · recibida {{ $u->received_at?->format('d/m/Y') }}
                                </div>
                            </div>

                            <div class="text-end small d-flex align-items-center gap-3">

                                <div>
                                    <div class="monto">${{ number_format($u->total_cost, 2) }}</div>
                                    <div class="text-secondary" style="font-size: .72rem;">costo total</div>
                                </div>

                                {{--
                                    CORREGIR

                                    Se teclea un número mal o se registran tres
                                    cuando vinieron dos. Sin esta salida había
                                    que editar el contenedor por un lado y el
                                    contador de la compra por otro, y quedaban
                                    diciendo cosas distintas.
                                --}}
                                @can('purchases.update')
                                    @if ($unidadPorQuitar === $u->id)
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <small class="text-secondary">¿Deshacer?</small>
                                            <button class="btn btn-sm btn-danger"
                                                    wire:click="quitarUnidad">Sí</button>
                                            <button class="btn btn-sm btn-outline-secondary"
                                                    wire:click="cancelarQuitarUnidad">No</button>
                                        </div>
                                    @else
                                        <button class="acc acc-borrar"
                                                wire:click="pedirQuitarUnidad({{ $u->id }})"
                                                title="Deshacer el alta de esta unidad">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    @endif
                                @endcan

                            </div>

                        </div>
                    @empty
                        <div class="text-center py-3 text-secondary">
                            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                            <div class="small">
                                Sin unidades recibidas todavía.
                            </div>
                        </div>
                    @endforelse

                </div>
            </div>

        </div>

        {{-- ═══════════════════════════════════════════════════
             LOS DATOS
        ═══════════════════════════════════════════════════ --}}
        <div class="col-12 col-lg-4">

            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-info-circle me-1"></i> Los datos</span>
                </div>
                <div class="card-body">

                    <dl class="row mb-0 small">

                        <dt class="col-5 text-secondary fw-normal">Proveedor</dt>
                        <dd class="col-7">{{ $purchase->supplier?->name ?? '—' }}</dd>

                        <dt class="col-5 text-secondary fw-normal">Depósito</dt>
                        <dd class="col-7">{{ $purchase->depot?->name ?? 'Entrega directa' }}</dd>

                        @if ($purchase->depot?->phone)
                            <dt class="col-5 text-secondary fw-normal">Contacto</dt>
                            <dd class="col-7">{{ $purchase->depot->phone }}</dd>
                        @endif

                        <dt class="col-5 text-secondary fw-normal">Plazo</dt>
                        <dd class="col-7">
                            {{ $purchase->pickup_deadline_at?->format('d/m/Y') ?? 'Sin plazo' }}

                            @if ($purchase->original_deadline_at
                                 && $purchase->pickup_deadline_at
                                 && ! $purchase->original_deadline_at->equalTo($purchase->pickup_deadline_at))
                                <div class="text-secondary" style="font-size: .72rem;">
                                    original: {{ $purchase->original_deadline_at->format('d/m/Y') }}
                                </div>
                            @endif
                        </dd>

                        <dt class="col-5 text-secondary fw-normal">Registrado por</dt>
                        <dd class="col-7">{{ $purchase->createdBy?->name ?? '—' }}</dd>

                    </dl>

                    @if ($purchase->notes)
                        <hr>
                        <div class="small">
                            <div class="text-secondary mb-1">Notas</div>
                            {{ $purchase->notes }}
                        </div>
                    @endif

                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-calculator me-1"></i> Las cuentas</span>
                </div>
                <div class="card-body">

                    {{--
                        Las mismas tres columnas de la hoja COMPRAS del Excel:
                        PRECIO, PICK UP y TOTAL.
                    --}}
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-secondary">Mercancía</td>
                                <td class="text-end monto">${{ number_format((float) $purchase->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-secondary">
                                    Pick up
                                    @if ((float) $purchase->pickup_fee > 0)
                                        <div class="small">
                                            ${{ number_format((float) $purchase->pickup_fee, 2) }}
                                            × {{ $compradas }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end monto">
                                    ${{ number_format((float) $purchase->pickup_fee * $compradas, 2) }}
                                </td>
                            </tr>
                            <tr class="fw-bold border-top">
                                <td>Total</td>
                                <td class="text-end monto">${{ number_format((float) $purchase->total, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>


                </div>
            </div>

        </div>

    </div>


    {{-- ═════════════════════════════════════════════════════════════
         EL RETIRO

         Un viaje al depósito: qué unidades vinieron, quién las trajo y
         cuánto costó cada una.

         Dibujado a mano y no con el JavaScript de Bootstrap, por lo de
         siempre: Livewire repinta este pedazo y un modal abierto por JS
         se queda colgado con el fondo gris pegado.
    ═════════════════════════════════════════════════════════════ --}}
    @if ($modalRetiro)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,.55);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Registrar recepción</h5>
                        <button type="button" class="btn-close" wire:click="cerrarRetiro"></button>
                    </div>

                    <div class="modal-body">

                        <div class="alert alert-light border py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Se registra una recepción por viaje. Las unidades pendientes quedan
                            disponibles para recepciones posteriores, con su propio costo de traslado.
                        </div>

                        {{-- ── CUÁNTAS VINIERON ── --}}
                        <div class="row g-3 mb-3">

                            <div class="col-6 col-md-2">
                                <label class="form-label small">Cantidad</label>
                                <input type="number" min="1"
                                       class="form-control @error('cuantas') is-invalid @enderror"
                                       wire:model.live="cuantas">
                                @error('cuantas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label small">Fecha de recepción</label>
                                <input type="date"
                                       class="form-control form-control-sm @error('fechaRetiro') is-invalid @enderror"
                                       wire:model="fechaRetiro">
                                @error('fechaRetiro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label small">Ubicación</label>
                                <select class="form-select form-select-sm" wire:model="ubicacionId">
                                    <option value="">— Sin ubicación —</option>
                                    @foreach ($ubicaciones as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{--
                                QUIÉN LAS TRAJO

                                Es la columna NOMBRE TRANS. del Excel, que tiene
                                tres clases de valor: un transportista externo,
                                un trabajador nuestro, o "directo a la yarda"
                                cuando lo trajo el proveedor.

                                Esas últimas son las que en el Excel llevan PICK
                                UP en $0.00.
                            --}}
                            <div class="col-12 col-md-4">
                                <label class="form-label small">Responsable del traslado</label>
                                <select class="form-select form-select-sm" wire:model.live="quienTrajo">
                                    <option value="proveedor">El proveedor (entrega directa)</option>
                                    <option value="trabajador">Personal propio</option>
                                    <option value="transportista">Transportista externo</option>
                                </select>
                            </div>

                            @if ($quienTrajo === 'trabajador')
                                <div class="col-12 col-md-5">
                                    <label class="form-label small">Seleccionar</label>
                                    <select class="form-select form-select-sm" wire:model="empleadoId">
                                        <option value="">— Elegir —</option>
                                        @foreach ($empleados as $e)
                                            <option value="{{ $e->id }}">{{ $e->name }} · {{ $e->role_label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($quienTrajo === 'transportista')
                                <div class="col-12 col-md-5">
                                    <label class="form-label small">Seleccionar</label>
                                    <select class="form-select form-select-sm" wire:model="transportistaId">
                                        <option value="">— Elegir —</option>
                                        @foreach ($transportistas as $t)
                                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                        </div>

                        {{-- ── LAS UNIDADES ── --}}
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;"></th>
                                        <th>Código de la unidad</th>
                                        <th>Código interno</th>
                                        <th class="text-end" style="width: 130px;">Traslado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($unidadesDelRetiro as $i => $u)
                                    <tr wire:key="ret-{{ $i }}">
                                        <td class="text-secondary small">{{ $i + 1 }}</td>

                                        <td>
                                            <input type="text"
                                                   class="form-control form-control-sm text-uppercase font-monospace @error('unidadesDelRetiro.'.$i.'.numero') is-invalid @enderror"
                                                   placeholder="MSCU1234567"
                                                   wire:model.blur="unidadesDelRetiro.{{ $i }}.numero">
                                            @error('unidadesDelRetiro.'.$i.'.numero')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>

                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                   placeholder="Opcional"
                                                   wire:model.blur="unidadesDelRetiro.{{ $i }}.codigo">
                                        </td>

                                        <td>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" class="form-control text-end"
                                                       wire:model.live.debounce.500ms="unidadesDelRetiro.{{ $i }}.pickup">
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <span class="small text-secondary">
                                El costo de traslado se propone desde el depósito y es editable por unidad.
                            </span>
                            <span class="fw-semibold">
                                Traslado: ${{ number_format($this->pickupDelRetiro, 2) }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <input type="text" class="form-control form-control-sm"
                                   placeholder="Observaciones de la recepción"
                                   wire:model.blur="notaRetiro">
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="cerrarRetiro">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-success" wire:click="registrarRetiro">
                            <i class="bi bi-check-lg me-1"></i>
                            Registrar recepción
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
