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
                Hay que ir a buscar lo que falta.
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
                    <span class="kpi-pie d-block">Lo que se pagó</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ok">
                <span class="kpi-icono"><i class="bi bi-box-seam"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">En la yarda</span>
                    <span class="kpi-valor d-block">{{ $recibidas }}</span>
                    <span class="kpi-pie d-block">Ya llegaron de verdad</span>
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
                        {{ $faltan > 0 ? 'Siguen en el depósito' : 'Nada pendiente' }}
                    </span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-info">
                <span class="kpi-icono"><i class="bi bi-cash-coin"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Total pagado</span>
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
                        <i class="bi bi-list-ul me-1"></i> Qué se compró y qué ha llegado
                    </span>
                    <small class="text-secondary d-block">
                        Recibir una unidad la da de alta en el inventario con su número real.
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

                            {{-- ───── RECIBIR ───── --}}
                            @if ($pendientes > 0)
                                @can('purchases.update')

                                    @if ($itemRecibiendo === $item->id)

                                        <div class="border-top mt-3 pt-3">

                                            <div class="alert alert-light border py-2 small">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Se registra <strong>una unidad</strong>. Lea el número pintado
                                                en la puerta. Si no se lee, póngale un código interno con el
                                                que la yarda pueda pedirla.
                                            </div>

                                            <div class="row g-2">

                                                <div class="col-12 col-md-4">
                                                    <label class="form-label small">Número del contenedor</label>
                                                    <input type="text"
                                                           class="form-control form-control-sm text-uppercase font-monospace @error('numeroUnidad') is-invalid @enderror"
                                                           placeholder="MSCU1234567"
                                                           wire:model.blur="numeroUnidad">
                                                    @error('numeroUnidad')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>

                                                <div class="col-6 col-md-3">
                                                    <label class="form-label small">Código interno</label>
                                                    <input type="text" class="form-control form-control-sm"
                                                           placeholder="Unit #3"
                                                           wire:model.blur="codigoUnidad">
                                                </div>

                                                <div class="col-6 col-md-3">
                                                    <label class="form-label small">Dónde queda</label>
                                                    <select class="form-select form-select-sm" wire:model="ubicacionId">
                                                        <option value="">— Sin ubicación —</option>
                                                        @foreach ($ubicaciones as $u)
                                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="col-12 col-md-2 d-flex align-items-end gap-2">
                                                    <button type="button" class="btn btn-sm btn-success"
                                                            wire:click="recibirUnidad">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                            wire:click="cerrarRecibir">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </div>

                                                <div class="col-12">
                                                    <input type="text" class="form-control form-control-sm"
                                                           placeholder="Estado en que llegó: golpes, óxido, puertas duras…"
                                                           wire:model.blur="notaUnidad">
                                                </div>

                                            </div>

                                        </div>

                                    @else

                                        <button type="button" class="btn btn-sm btn-outline-success mt-3"
                                                wire:click="abrirRecibir({{ $item->id }})">
                                            <i class="bi bi-box-arrow-in-down me-1"></i>
                                            Recibir una unidad
                                        </button>

                                    @endif

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
                        <i class="bi bi-box-seam me-1"></i> Las unidades que entraron por esta compra
                    </span>
                    <small class="text-secondary d-block">
                        Detrás del número de "recibidas" hay contenedores con nombre y apellido.
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

                            <div class="text-end small">
                                <div class="monto">${{ number_format($u->total_cost, 2) }}</div>
                                <div class="text-secondary" style="font-size: .72rem;">costo puesta en yarda</div>
                            </div>

                        </div>
                    @empty
                        <div class="text-center py-3 text-secondary">
                            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                            <div class="small">
                                Todavía no ha llegado ninguna. El inventario no las cuenta hasta
                                que estén aquí.
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
                            <dt class="col-5 text-secondary fw-normal">Se llama al</dt>
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

                        <dt class="col-5 text-secondary fw-normal">Registró</dt>
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
                                <td class="text-secondary">Precio</td>
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

</div>
