{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS CONTRATOS DE RENTA
    ═══════════════════════════════════════════════════════════════════════

    Dos negocios en la misma lista: la mensual (el cliente se lleva un
    contenedor nuestro) y la de yarda (el cliente deja el suyo acá).

    Arriba, lo que entra fijo cada mes y lo que está vencido.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Rentas</h4>
            <small class="text-secondary">Contratos mensuales y de yarda.</small>
        </div>

        @can('rentals.create')
            <a href="{{ route('operaciones.rentas.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nuevo contrato
            </a>
        @endcan
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── LOS NÚMEROS ── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary small">Contratos activos</div>
                    <div class="fs-3 fw-semibold">{{ $kpis['activas'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary small">Entra fijo al mes</div>
                    <div class="fs-3 fw-semibold">${{ number_format($kpis['mensualFijo'], 0) }}</div>
                    <div class="small text-secondary">Solo contratos mensuales</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <a href="#" wire:click.prevent="$set('marca', 'vencidos')" class="text-decoration-none">
                <div class="card h-100 {{ $kpis['vencidas'] > 0 ? 'border-danger' : '' }}">
                    <div class="card-body">
                        <div class="text-secondary small">Con pagos vencidos</div>
                        <div class="fs-3 fw-semibold text-body">{{ $kpis['vencidas'] }}</div>
                        <div class="small text-secondary">Para llamar el lunes</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-6 col-md-3">
            <a href="#" wire:click.prevent="$set('marca', 'sin_periodo')" class="text-decoration-none">
                <div class="card h-100 {{ $kpis['sinPeriodo'] > 0 ? 'border-warning' : '' }}">
                    <div class="card-body">
                        <div class="text-secondary small">Sin facturar nunca</div>
                        <div class="fs-3 fw-semibold text-body">{{ $kpis['sinPeriodo'] }}</div>
                        <div class="small text-secondary">Activos sin ningún período</div>
                    </div>
                </div>
            </a>
        </div>

    </div>

    {{-- ── FILTROS ── --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">

                <div class="col-12 col-md-4">
                    <input type="text" class="form-control form-control-sm"
                           placeholder="Número de contrato o cliente..."
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
                    <select class="form-select form-select-sm" wire:model.live="ciclo">
                        <option value="">Mensual y yarda</option>
                        @foreach ($ciclos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-10 col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="cliente">
                        <option value="">Todo cliente</option>
                        @foreach ($clientes as $cl)
                            <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-2 col-md-1 text-end">
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
                        {{ $marca === 'vencidos' ? 'Solo con pagos vencidos' : 'Solo sin facturar nunca' }}
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
                        <th>Contrato</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Desde</th>
                        <th class="text-end">Tarifa</th>
                        <th class="text-end">Deuda</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>
                @forelse ($rentas as $r)
                    <tr>
                        <td>
                            <a href="{{ route('operaciones.rentas.show', $r) }}" class="doc-numero">
                                {{ $r->contract_number }}
                            </a>
                        </td>

                        <td>{{ $r->customer?->name ?? '—' }}</td>

                        <td>
                            <span class="badge bg-{{ $r->isDaily() ? 'indigo' : 'primary' }}-subtle
                                         text-{{ $r->isDaily() ? 'indigo' : 'primary' }}-emphasis">
                                {{ $r->billing_cycle?->label() }}
                            </span>
                        </td>

                        <td>{{ $r->start_date?->format('d/m/Y') }}</td>

                        <td class="text-end">
                            @if ($r->isDaily())
                                ${{ number_format((float) $r->daily_rate, 2) }}<span class="text-secondary">/día</span>
                            @else
                                ${{ number_format((float) $r->monthly_rate, 2) }}<span class="text-secondary">/mes</span>
                            @endif
                        </td>

                        <td class="text-end">
                            @if ($r->isDaily())
                                @php $deuda = $r->dailyDebt() + $r->oneTimeFees(); @endphp
                                <span class="{{ $deuda > 0 ? 'fw-semibold text-danger' : 'text-secondary' }}">
                                    ${{ number_format($deuda, 2) }}
                                </span>
                                <div class="small text-secondary">
                                    {{ $r->unpaidDays() }} días sin pagar
                                </div>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>

                        <td>
                            <span class="badge bg-{{ $r->status?->color() ?? 'secondary' }}-subtle
                                         text-{{ $r->status?->color() ?? 'secondary' }}-emphasis">
                                {{ $r->status?->label() }}
                            </span>

                            @if ($r->periodos_vencidos > 0)
                                <span class="badge bg-danger-subtle text-danger-emphasis">
                                    {{ $r->periodos_vencidos }} vencido{{ $r->periodos_vencidos > 1 ? 's' : '' }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            @if ($this->hayFiltros)
                                No hay contratos que cumplan con esos filtros.
                            @else
                                Todavía no hay contratos de renta.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($rentas->hasPages())
            <div class="card-footer">{{ $rentas->links() }}</div>
        @endif
    </div>

</div>
