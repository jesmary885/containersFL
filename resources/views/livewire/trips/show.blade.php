{{--
    ═══════════════════════════════════════════════════════════════════════
    EL DETALLE DE UN VIAJE
    ═══════════════════════════════════════════════════════════════════════
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Viaje {{ $viaje->trip_number }}</h4>
            <small class="text-secondary">
                {{ $viaje->type?->label() }}
                @if ($viaje->customer) · {{ $viaje->customer->name }} @endif
            </small>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('operaciones.viajes.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>

            @can('trips.update')
                <a href="{{ route('operaciones.viajes.edit', $viaje) }}" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> Editar
                </a>
            @endcan
        </div>
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">

        {{-- ── ESTADO Y ACCIONES ── --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">

                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-{{ $viaje->status?->color() ?? 'secondary' }}-subtle
                                     text-{{ $viaje->status?->color() ?? 'secondary' }}-emphasis fs-6">
                            {{ $viaje->status?->label() }}
                        </span>

                        @if ($viaje->intercompanyInvoice)
                            <a href="{{ route('finanzas.facturacion.show', $viaje->intercompanyInvoice) }}"
                               class="badge bg-success-subtle text-success-emphasis text-decoration-none fs-6">
                                <i class="bi bi-receipt"></i>
                                Facturado · {{ $viaje->intercompanyInvoice->invoice_number ?? '' }}
                            </a>
                        @elseif ($viaje->status === \App\Enums\TripStatus::Completed
                                 && (float) $viaje->customer_price > 0)
                            <span class="badge bg-warning-subtle text-warning-emphasis fs-6">
                                Sin facturar
                            </span>
                        @endif

                        @if ($viaje->driver_payment_status === 'paid')
                            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-6">
                                Chofer pagado
                            </span>
                        @endif
                    </div>

                    @can('trips.update')
                        <div class="d-flex gap-2">
                            @if ($viaje->status !== \App\Enums\TripStatus::Completed
                                 && $viaje->status !== \App\Enums\TripStatus::Cancelled)
                                <button class="btn btn-success" wire:click="completar">
                                    <i class="bi bi-check2-circle me-1"></i> Marcar completado
                                </button>
                            @endif

                            @if ($viaje->driver_payment_status !== 'paid'
                                 && (float) $viaje->driver_pay > 0)
                                <button class="btn btn-outline-secondary"
                                        wire:click="marcarPagadoChofer"
                                        wire:confirm="¿Marcar el pago al chofer como hecho?">
                                    <i class="bi bi-cash me-1"></i> Chofer pagado
                                </button>
                            @endif
                        </div>
                    @endcan

                </div>
            </div>
        </div>

        {{-- ── RECORRIDO ── --}}
        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-signpost-2 me-1"></i> Recorrido
                    </h6>
                </div>

                <div class="card-body">
                    <dl class="row mb-0">

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Sale de</dt>
                        <dd class="col-7 col-md-8">
                            {{ $viaje->depot?->name ?? 'La yarda' }}
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Destino</dt>
                        <dd class="col-7 col-md-8">
                            @if ($viaje->destination_address)
                                {{ $viaje->destination_address['line1'] ?? '' }}
                                @if (! empty($viaje->destination_address['city']))
                                    <div>
                                        {{ $viaje->destination_address['city'] }},
                                        {{ $viaje->destination_address['state'] ?? '' }}
                                        {{ $viaje->destination_address['zip'] ?? '' }}
                                    </div>
                                @endif
                            @else
                                {{ $viaje->destination_zip ?: '—' }}
                            @endif
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Millas</dt>
                        <dd class="col-7 col-md-8">
                            {{ $viaje->miles ? rtrim(rtrim(number_format((float) $viaje->miles, 1), '0'), '.').' mi' : '—' }}
                            @if ($viaje->rate_per_mile)
                                <span class="text-secondary">
                                    × ${{ number_format((float) $viaje->rate_per_mile, 2) }}/mi
                                </span>
                            @endif
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Contenedor</dt>
                        <dd class="col-7 col-md-8">
                            @if ($viaje->container)
                                <a href="{{ route('operaciones.contenedores.show', $viaje->container) }}">
                                    {{ $viaje->container->full_identifier }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Programado</dt>
                        <dd class="col-7 col-md-8">
                            {{ $viaje->scheduled_at?->format('d/m/Y H:i') ?? '—' }}
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Completado</dt>
                        <dd class="col-7 col-md-8">
                            {{ $viaje->completed_at?->format('d/m/Y H:i') ?? '—' }}
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Chofer</dt>
                        <dd class="col-7 col-md-8">
                            {{ $viaje->driver ? trim($viaje->driver->first_name.' '.$viaje->driver->last_name) : '—' }}
                        </dd>

                        <dt class="col-5 col-md-4 text-secondary fw-normal">Camión</dt>
                        <dd class="col-7 col-md-8">
                            {{ $viaje->vehicle?->plate_number ?? '—' }}
                        </dd>

                        @if ($viaje->carrier)
                            <dt class="col-5 col-md-4 text-secondary fw-normal">Transportista</dt>
                            <dd class="col-7 col-md-8">{{ $viaje->carrier->name }}</dd>
                        @endif

                    </dl>

                    @if ($viaje->notes)
                        <hr>
                        <div class="small text-secondary">{{ $viaje->notes }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── LOS NÚMEROS ── --}}
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-cash-stack me-1"></i> Los números
                    </h6>
                </div>

                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Se le cobra al cliente</span>
                        <span class="fw-semibold">${{ number_format((float) $viaje->customer_price, 2) }}</span>
                    </div>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">
                            Pago al chofer
                            @if ($viaje->driver_pay_percent)
                                <small>({{ rtrim(rtrim(number_format((float) $viaje->driver_pay_percent, 2), '0'), '.') }}%)</small>
                            @endif
                        </span>
                        <span class="text-danger">− ${{ number_format((float) $viaje->driver_pay, 2) }}</span>
                    </div>

                    @if ((float) $viaje->carrier_cost > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Costo del transportista</span>
                            <span class="text-danger">− ${{ number_format((float) $viaje->carrier_cost, 2) }}</span>
                        </div>
                    @endif

                    @php
                        $queda = (float) $viaje->customer_price
                               - (float) $viaje->driver_pay
                               - (float) $viaje->carrier_cost;
                    @endphp

                    <hr>

                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">Queda para la empresa</span>
                        <span class="fw-semibold fs-5 {{ $queda >= 0 ? 'text-success' : 'text-danger' }}">
                            ${{ number_format($queda, 2) }}
                        </span>
                    </div>

                    <div class="small text-secondary mt-2">
                        Sin contar los gastos del viaje (combustible, peajes), que se
                        registran aparte.
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
