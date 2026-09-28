{{--
    ═══════════════════════════════════════════════════════════════════════
    EL DETALLE DE UN CONTRATO
    ═══════════════════════════════════════════════════════════════════════

    Una mensual enseña sus períodos. Una de yarda enseña el reporte de días,
    que es la hoja REPORTE YARDA del Excel puesta en pantalla.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Contrato {{ $renta->contract_number }}</h4>
            <small class="text-secondary">
                {{ $renta->customer?->name }} · {{ $renta->billing_cycle?->label() }}
            </small>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('operaciones.rentas.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>

            @can('rentals.update')
                <a href="{{ route('operaciones.rentas.edit', $renta) }}" class="btn btn-outline-primary">
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

    @error('periodo')
        <div class="alert alert-warning">{{ $message }}</div>
    @enderror

    <div class="row g-3">

        {{-- ═══════════ COLUMNA IZQUIERDA ═══════════ --}}
        <div class="col-12 col-lg-8">

            @if ($yarda)

                {{-- ── EL REPORTE DE YARDA ── --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-calendar3 me-1"></i> Días y deuda
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3 text-center mb-3">

                            <div class="col-4">
                                <div class="text-secondary small">Transcurridos</div>
                                <div class="fs-4 fw-semibold">{{ $yarda['transcurridos'] }}</div>
                            </div>

                            <div class="col-4">
                                <div class="text-secondary small">Pagados</div>
                                <div class="fs-4 fw-semibold text-success">{{ $yarda['pagados'] }}</div>
                            </div>

                            <div class="col-4">
                                <div class="text-secondary small">Pendientes</div>
                                <div class="fs-4 fw-semibold {{ $yarda['pendientes'] > 0 ? 'text-danger' : '' }}">
                                    {{ $yarda['pendientes'] }}
                                </div>
                            </div>

                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">
                                Deuda por días
                                <small>({{ $yarda['pendientes'] }} × ${{ number_format((float) $renta->daily_rate, 2) }})</small>
                            </span>
                            <span class="fw-semibold">${{ number_format($yarda['deudaDias'], 2) }}</span>
                        </div>

                        @if ($yarda['cargosFijos'] > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary">Entrada, salida, pintura y reparación</span>
                                <span>${{ number_format($yarda['cargosFijos'], 2) }}</span>
                            </div>
                        @endif

                        <hr>

                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">Debe en total</span>
                            <span class="fw-semibold fs-4 {{ ($yarda['deudaDias'] + $yarda['cargosFijos']) > 0 ? 'text-danger' : 'text-success' }}">
                                ${{ number_format($yarda['deudaDias'] + $yarda['cargosFijos'], 2) }}
                            </span>
                        </div>
                    </div>

                    @can('rentals.update')
                        <div class="card-footer">
                            <div class="row g-2 align-items-end">
                                <div class="col-7 col-md-5">
                                    <label class="form-label small">Registrar días pagados</label>
                                    <input type="number" min="0"
                                           class="form-control form-control-sm text-end @error('diasAPagar') is-invalid @enderror"
                                           wire:model="diasAPagar">
                                    @error('diasAPagar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-5 col-md-4">
                                    <button class="btn btn-sm btn-success w-100" wire:click="registrarDias">
                                        <i class="bi bi-check-lg me-1"></i> Registrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endcan
                </div>

            @endif

            {{-- ── LOS PERÍODOS ── --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-list-ol me-1"></i> Períodos de cobro
                    </h6>

                    @can('rentals.update')
                        <button class="btn btn-sm btn-outline-primary" wire:click="generarPeriodo">
                            <i class="bi bi-plus-lg me-1"></i> Generar el siguiente
                        </button>
                    @endcan
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Período</th>
                                <th>Vence</th>
                                <th class="text-end">Importe</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody>
                        @forelse ($periodos as $p)
                            <tr>
                                <td>{{ $p->period_number }}</td>
                                <td>
                                    {{ $p->period_start?->format('d/m/Y') }}
                                    @if ($p->period_end && ! $p->period_end->equalTo($p->period_start))
                                        — {{ $p->period_end->format('d/m/Y') }}
                                    @endif
                                </td>
                                <td>
                                    {{ $p->due_date?->format('d/m/Y') }}
                                    @if ($p->status === 'pending' && $p->due_date?->isPast())
                                        <span class="badge bg-danger-subtle text-danger-emphasis">Vencido</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    ${{ number_format((float) $p->amount, 2) }}
                                    @if ((float) $p->late_fee_amount > 0)
                                        <div class="small text-danger">
                                            + ${{ number_format((float) $p->late_fee_amount, 2) }} mora
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">
                                        {{ $p->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">
                                    Todavía no hay períodos. Este contrato no ha cobrado nada.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        {{-- ═══════════ COLUMNA DERECHA ═══════════ --}}
        <div class="col-12 col-lg-4">

            <div class="card mb-3">
                <div class="card-body">
                    <dl class="row mb-0">

                        <dt class="col-6 text-secondary fw-normal">Estado</dt>
                        <dd class="col-6 text-end">
                            <span class="badge bg-{{ $renta->status?->color() ?? 'secondary' }}-subtle
                                         text-{{ $renta->status?->color() ?? 'secondary' }}-emphasis">
                                {{ $renta->status?->label() }}
                            </span>
                        </dd>

                        <dt class="col-6 text-secondary fw-normal">Tarifa</dt>
                        <dd class="col-6 text-end fw-semibold">
                            @if ($renta->isDaily())
                                ${{ number_format((float) $renta->daily_rate, 2) }}/día
                            @else
                                ${{ number_format((float) $renta->monthly_rate, 2) }}/mes
                            @endif
                        </dd>

                        <dt class="col-6 text-secondary fw-normal">Desde</dt>
                        <dd class="col-6 text-end">{{ $renta->start_date?->format('d/m/Y') }}</dd>

                        <dt class="col-6 text-secondary fw-normal">Hasta</dt>
                        <dd class="col-6 text-end">
                            {{ $renta->end_date?->format('d/m/Y') ?? 'Abierto' }}
                        </dd>

                        @if (! $renta->isDaily())
                            <dt class="col-6 text-secondary fw-normal">Día de cobro</dt>
                            <dd class="col-6 text-end">{{ $renta->billing_anchor_day }}</dd>
                        @endif

                        <dt class="col-6 text-secondary fw-normal">Gracia</dt>
                        <dd class="col-6 text-end">{{ $renta->grace_days }} días</dd>

                        <dt class="col-6 text-secondary fw-normal">Mora</dt>
                        <dd class="col-6 text-end">
                            ${{ number_format((float) $renta->late_fee_amount, 2) }}
                            @if (! $renta->auto_apply_late_fee)
                                <div class="small text-secondary">manual</div>
                            @endif
                        </dd>

                    </dl>
                </div>
            </div>

            @if (! $renta->isDaily() && $renta->containers->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-box-seam me-1"></i>
                            Contenedores ({{ $renta->containers->count() }})
                        </h6>
                    </div>

                    <ul class="list-group list-group-flush">
                        @foreach ($renta->containers as $c)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="{{ route('operaciones.contenedores.show', $c) }}">
                                    {{ $c->full_identifier }}
                                </a>
                                <span class="text-secondary">
                                    ${{ number_format((float) $c->pivot->monthly_rate, 2) }}/mes
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($renta->notes)
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="text-secondary small mb-1">Notas</div>
                        <div>{{ $renta->notes }}</div>
                    </div>
                </div>
            @endif

        </div>

    </div>

</div>
