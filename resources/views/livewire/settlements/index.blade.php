{{--
    ═══════════════════════════════════════════════════════════════════════
    LIQUIDACIÓN DE CHOFERES
    ═══════════════════════════════════════════════════════════════════════

    De la transportista, no de la de contenedores. Quedó aclarado en la
    reunión del 16-09: los choferes son de RST y es RST quien les liquida.

    Si está elegida la compañía de contenedores, esta pantalla no va a
    encontrar viajes. Eso es correcto, no es un error.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Liquidación de choferes</h4>
            <small class="text-secondary">
                Lo que se le debe a cada chofer por sus viajes completados.
            </small>
        </div>

        @can('settlements.create')
            <button class="btn btn-primary" wire:click="abrirGenerar">
                <i class="bi bi-plus-lg me-1"></i> Nueva liquidación
            </button>
        @endcan
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @error('elegidos')
        <div class="alert alert-warning">{{ $message }}</div>
    @enderror

    {{-- ── LO QUE SE DEBE EN TOTAL ── --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-5">
            <div class="card {{ $pendienteTotal > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <div class="text-secondary small">Sin liquidar todavía</div>
                    <div class="fs-3 fw-semibold">${{ number_format($pendienteTotal, 2) }}</div>
                    <div class="small text-secondary">
                        De viajes completados, todos los choferes.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ EL PANEL DE GENERAR ═══════════ --}}
    @if ($generando)
        <div class="card mb-3 border-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-calculator me-1"></i> Nueva liquidación
                </h6>
                <button class="btn-close" wire:click="cerrar"></button>
            </div>

            <div class="card-body">
                <div class="row g-3 align-items-end mb-3">

                    <div class="col-12 col-md-5">
                        <label class="form-label">Chofer</label>
                        <select class="form-select" wire:model.live="driver_id">
                            <option value="">— Elegir —</option>
                            @foreach ($choferes as $d)
                                <option value="{{ $d->id }}">
                                    {{ trim($d->first_name.' '.$d->last_name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" class="form-control" wire:model.live="desde">
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" class="form-control" wire:model.live="hasta">
                    </div>

                </div>

                @if (! $driver_id)
                    <div class="alert alert-light border mb-0">
                        Elija un chofer para ver sus viajes sin liquidar.
                    </div>

                @elseif ($viajes->isEmpty())
                    <div class="alert alert-info mb-0">
                        Ese chofer no tiene viajes sin liquidar en ese rango.
                        <div class="small mt-1">
                            Un viaje aparece acá si está <strong>completado</strong>, tiene pago
                            al chofer y todavía no entró en ninguna liquidación.
                        </div>
                    </div>

                @else
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <button class="btn btn-sm btn-link p-0" wire:click="marcarTodos">
                                            Todos
                                        </button>
                                    </th>
                                    <th>Viaje</th>
                                    <th>Completado</th>
                                    <th>Cliente</th>
                                    <th class="text-end">Se le paga</th>
                                </tr>
                            </thead>

                            <tbody>
                            @foreach ($viajes as $v)
                                <tr class="{{ ! empty($elegidos[$v->id]) ? 'table-active' : '' }}">
                                    <td>
                                        <input class="form-check-input" type="checkbox"
                                               wire:model.live="elegidos.{{ $v->id }}">
                                    </td>
                                    <td>{{ $v->trip_number }}</td>
                                    <td>{{ $v->completed_at?->format('d/m/Y') ?? '—' }}</td>
                                    <td>{{ $v->customer?->name ?? '—' }}</td>
                                    <td class="text-end fw-semibold">
                                        ${{ number_format((float) $v->driver_pay, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>

                            <tfoot>
                                <tr class="border-top">
                                    <td colspan="4" class="text-end fw-semibold">Total</td>
                                    <td class="text-end fw-semibold fs-5">
                                        ${{ number_format($total, 2) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small text-secondary">
                            Queda en <strong>borrador</strong>. Aprobarla es otro paso.
                        </div>

                        <button class="btn btn-primary" wire:click="generar" @disabled($total <= 0)>
                            <i class="bi bi-check-lg me-1"></i>
                            Generar por ${{ number_format($total, 2) }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══════════ LA LISTA ═══════════ --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="estado">
                        <option value="">Todo estado</option>
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Liquidación</th>
                        <th>Chofer</th>
                        <th>Período</th>
                        <th class="text-end">Viajes</th>
                        <th class="text-end">Neto</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                @forelse ($liquidaciones as $l)
                    <tr>
                        <td><span class="doc-numero">{{ $l->settlement_number }}</span></td>

                        <td>
                            {{ $l->driver ? trim($l->driver->first_name.' '.$l->driver->last_name) : '—' }}
                        </td>

                        <td>
                            {{ $l->period_start?->format('d/m/Y') }} —
                            {{ $l->period_end?->format('d/m/Y') }}
                        </td>

                        <td class="text-end">{{ $l->items_count }}</td>

                        <td class="text-end fw-semibold">
                            ${{ number_format((float) $l->net_amount, 2) }}
                            @if ((float) $l->deductions_amount > 0)
                                <div class="small text-danger">
                                    − ${{ number_format((float) $l->deductions_amount, 2) }} deducciones
                                </div>
                            @endif
                        </td>

                        <td>
                            <span class="badge bg-{{ $l->status?->color() ?? 'secondary' }}-subtle
                                         text-{{ $l->status?->color() ?? 'secondary' }}-emphasis">
                                {{ $l->status?->label() }}
                            </span>
                        </td>

                        <td class="text-end">
                            @can('settlements.update')
                                @if ($l->isEditable())
                                    <button class="btn btn-sm btn-outline-success"
                                            wire:click="aprobar({{ $l->id }})"
                                            wire:confirm="Al aprobar, los viajes quedan liquidados y ya no se pueden quitar. ¿Seguir?">
                                        <i class="bi bi-check2-circle me-1"></i> Aprobar
                                    </button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            Todavía no hay liquidaciones.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($liquidaciones->hasPages())
            <div class="card-footer">{{ $liquidaciones->links() }}</div>
        @endif
    </div>

</div>
