{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS VIAJES
    ═══════════════════════════════════════════════════════════════════════

    Arriba los tres números que importan, y el de "por facturar" es un botón:
    lleva directo a la pantalla de facturación semanal, que es lo que pidió
    Denisse el 16-09.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Viajes</h4>
            <small class="text-secondary">Transporte de contenedores, propio y para terceros.</small>
        </div>

        <div class="d-flex gap-2">
            @can('trips.create')
                <a href="{{ route('operaciones.viajes.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo viaje
                </a>
            @endcan

            @can('invoices.create')
                <a href="{{ route('operaciones.viajes.facturacion') }}" class="btn btn-outline-primary">
                    <i class="bi bi-receipt me-1"></i> Facturar semana
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

    {{-- ── LOS TRES NÚMEROS ── --}}
    <div class="row g-3 mb-3">

        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary small">Programados</div>
                    <div class="fs-3 fw-semibold">{{ $kpis['programados'] }}</div>
                    <div class="small text-secondary">Todavía no salieron</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <a href="#" wire:click.prevent="$set('marca', 'por_facturar')"
               class="text-decoration-none">
                <div class="card h-100 {{ $kpis['porFacturar'] > 0 ? 'border-warning' : '' }}">
                    <div class="card-body">
                        <div class="text-secondary small">Por facturar</div>
                        <div class="fs-3 fw-semibold text-body">
                            ${{ number_format($kpis['montoPorFacturar'], 2) }}
                        </div>
                        <div class="small text-secondary">
                            {{ $kpis['porFacturar'] }} viajes completados sin cobrar
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-md-4">
            <a href="#" wire:click.prevent="$set('marca', 'por_pagar')"
               class="text-decoration-none">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Por pagar a choferes</div>
                        <div class="fs-3 fw-semibold text-body">
                            ${{ number_format($kpis['porPagarChofer'], 2) }}
                        </div>
                        <div class="small text-secondary">De viajes ya completados</div>
                    </div>
                </div>
            </a>
        </div>

    </div>

    {{-- ── FILTROS ── --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">

                <div class="col-12 col-md-3">
                    <input type="text" class="form-control form-control-sm"
                           placeholder="Número, ZIP o cliente..."
                           wire:model.live.debounce.400ms="buscar">
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="estado">
                        <option value="">Todo estado</option>
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="tipo">
                        <option value="">Todo tipo</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="cliente">
                        <option value="">Toda compañía</option>
                        @foreach ($clientes as $cl)
                            <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm" wire:model.live="chofer">
                        <option value="">Todo chofer</option>
                        @foreach ($choferes as $ch)
                            <option value="{{ $ch->id }}">
                                {{ trim($ch->first_name.' '.$ch->last_name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-1 text-end">
                    @if ($this->hayFiltros)
                        <button class="btn btn-sm btn-outline-secondary w-100"
                                wire:click="limpiarFiltros" title="Limpiar filtros">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    @endif
                </div>

            </div>

            @if ($marca)
                <div class="mt-2">
                    <span class="badge bg-primary-subtle text-primary-emphasis">
                        {{ $marca === 'por_facturar' ? 'Solo por facturar' : 'Solo por pagar al chofer' }}
                        <a href="#" wire:click.prevent="$set('marca', '')" class="ms-1 text-reset">✕</a>
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- ── LA LISTA ── --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Viaje</th>
                        <th>Compañía cliente</th>
                        <th>Destino</th>
                        <th>Chofer</th>
                        <th class="text-end">Millas</th>
                        <th class="text-end">Se cobra</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>
                @forelse ($viajes as $v)
                    <tr>
                        <td>
                            <a href="{{ route('operaciones.viajes.show', $v) }}" class="doc-numero">
                                {{ $v->trip_number }}
                            </a>
                            <div class="small text-secondary">
                                {{ $v->type?->label() }}
                                @if ($v->container) · {{ $v->container->full_identifier }} @endif
                            </div>
                        </td>

                        <td>
                            {{ $v->customer?->name ?? '—' }}
                        </td>

                        <td>
                            {{ $v->destination_zip ?: ($v->destination_address['city'] ?? '—') }}
                        </td>

                        <td>
                            {{ $v->driver ? trim($v->driver->first_name.' '.$v->driver->last_name) : '—' }}
                        </td>

                        <td class="text-end">
                            {{ $v->miles ? rtrim(rtrim(number_format((float) $v->miles, 1), '0'), '.') : '—' }}
                        </td>

                        <td class="text-end fw-semibold">
                            ${{ number_format((float) $v->customer_price, 2) }}
                        </td>

                        <td>
                            <span class="badge bg-{{ $v->status?->color() ?? 'secondary' }}-subtle
                                         text-{{ $v->status?->color() ?? 'secondary' }}-emphasis">
                                {{ $v->status?->label() }}
                            </span>

                            @if ($v->status === \App\Enums\TripStatus::Completed
                                 && (float) $v->customer_price > 0
                                 && ! $v->intercompany_invoice_id)
                                <span class="badge bg-warning-subtle text-warning-emphasis">
                                    Sin facturar
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            @if ($this->hayFiltros)
                                No hay viajes que cumplan con esos filtros.
                            @else
                                Todavía no hay viajes registrados.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($viajes->hasPages())
            <div class="card-footer">
                {{ $viajes->links() }}
            </div>
        @endif
    </div>

</div>
