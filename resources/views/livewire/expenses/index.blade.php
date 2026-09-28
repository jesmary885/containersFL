{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS GASTOS
    ═══════════════════════════════════════════════════════════════════════

    Lo que hace que los números de rentabilidad signifiquen algo. El margen
    de un contenedor y el "queda para la empresa" de un viaje son mentira
    mientras el combustible y las reparaciones no estén acá.

    Arranca filtrado por el mes en curso.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Gastos</h4>
            <small class="text-secondary">
                Colgados de un contenedor, un viaje o un camión se vuelven costo real.
            </small>
        </div>

        @can('expenses.create')
            <button class="btn btn-primary" wire:click="nuevo">
                <i class="bi bi-plus-lg me-1"></i> Nuevo gasto
            </button>
        @endcan
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── LOS NÚMEROS DEL PERÍODO ── --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary small">Gastado en el período</div>
                    <div class="fs-3 fw-semibold">${{ number_format($kpis['total'], 2) }}</div>
                    <div class="small text-secondary">{{ $kpis['cantidad'] }} gastos</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="card h-100 {{ $kpis['porPagar'] > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <div class="text-secondary small">Todavía sin pagar</div>
                    <div class="fs-3 fw-semibold">${{ number_format($kpis['porPagar'], 2) }}</div>
                    <div class="small text-secondary">Cuentas por pagar</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        <div class="{{ $editando ? 'col-12 col-lg-7' : 'col-12' }}">

            {{-- ── FILTROS ── --}}
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <input type="text" class="form-control form-control-sm"
                                   placeholder="Número, descripción o a quién..."
                                   wire:model.live.debounce.400ms="buscar">
                        </div>

                        <div class="col-6 col-md-3">
                            <select class="form-select form-select-sm" wire:model.live="categoria">
                                <option value="">Toda categoría</option>
                                @foreach ($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <select class="form-select form-select-sm" wire:model.live="estado">
                                <option value="">Todo estado</option>
                                @foreach ($estados as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <input type="date" class="form-control" wire:model.live="desde">
                                <input type="date" class="form-control" wire:model.live="hasta">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── LA LISTA ── --}}
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Gasto</th>
                                <th>Categoría</th>
                                <th>A quién</th>
                                <th>Se cuelga de</th>
                                <th class="text-end">Importe</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                        @forelse ($gastos as $g)
                            <tr>
                                <td>
                                    <span class="doc-numero">{{ $g->expense_number }}</span>
                                    <div class="small text-secondary">
                                        {{ $g->expense_date?->format('d/m/Y') }} · {{ $g->description }}
                                    </div>
                                </td>

                                <td>{{ $g->category?->name ?? '—' }}</td>

                                <td>{{ $g->supplier?->name ?? $g->payee_name ?? '—' }}</td>

                                <td class="small">
                                    @if ($g->trip_id)
                                        <span class="badge bg-info-subtle text-info-emphasis">Viaje</span>
                                    @endif
                                    @if ($g->container_id)
                                        <span class="badge bg-primary-subtle text-primary-emphasis">Contenedor</span>
                                    @endif
                                    @if ($g->vehicle_id)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">Camión</span>
                                    @endif
                                    @if ($g->driver_id)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">Chofer</span>
                                    @endif
                                    @if (! $g->trip_id && ! $g->container_id && ! $g->vehicle_id && ! $g->driver_id)
                                        <span class="text-secondary">General</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <span class="fw-semibold">${{ number_format((float) $g->amount, 2) }}</span>
                                    @if ((float) $g->balance > 0)
                                        <div class="small text-warning-emphasis">
                                            debe ${{ number_format((float) $g->balance, 2) }}
                                        </div>
                                    @endif
                                </td>

                                <td class="text-end">
                                    @can('expenses.update')
                                        <button class="btn btn-sm btn-outline-secondary"
                                                wire:click="editar({{ $g->id }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">
                                    No hay gastos en ese período.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($gastos->hasPages())
                    <div class="card-footer">{{ $gastos->links() }}</div>
                @endif
            </div>
        </div>

        {{-- ── EL PANEL ── --}}
        @if ($editando)
            <div class="col-12 col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">
                            {{ $expenseId ? 'Editar gasto' : 'Nuevo gasto' }}
                        </h6>
                        <button class="btn-close" wire:click="cerrar"></button>
                    </div>

                    <div class="card-body">
                        <x-ui.errores />

                        <div class="row g-3">

                            <div class="col-12">
                                <label class="form-label">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select @error('expense_category_id') is-invalid @enderror"
                                        wire:model="expense_category_id">
                                    <option value="">— Elegir —</option>
                                    @foreach ($categorias as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                                @error('expense_category_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Descripción <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('description') is-invalid @enderror"
                                       placeholder="Diésel, peaje, llantas..."
                                       wire:model="description">
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Importe <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end @error('amount') is-invalid @enderror"
                                           wire:model="amount">
                                </div>
                                @error('amount')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('expense_date') is-invalid @enderror"
                                       wire:model="expense_date">
                                @error('expense_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12"><hr class="my-1"></div>

                            <div class="col-12">
                                <label class="form-label">Proveedor</label>
                                <select class="form-select" wire:model.live="supplier_id">
                                    <option value="">— No está en el catálogo —</option>
                                    @foreach ($proveedores as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if (! $supplier_id)
                                <div class="col-12">
                                    <label class="form-label">A quién se le pagó <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('payee_name') is-invalid @enderror"
                                           wire:model="payee_name">
                                    @error('payee_name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <div class="col-6">
                                <label class="form-label">Vence</label>
                                <input type="date" class="form-control @error('due_date') is-invalid @enderror"
                                       wire:model="due_date">
                                @error('due_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Factura del proveedor</label>
                                <input type="text" class="form-control" wire:model="supplier_invoice_number">
                            </div>

                            <div class="col-12"><hr class="my-1"></div>

                            <div class="col-12">
                                <div class="small text-secondary mb-2">
                                    Colgarlo de algo es opcional, pero es lo que lo convierte
                                    en costo real de ese algo.
                                </div>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Viaje</label>
                                <select class="form-select form-select-sm" wire:model="trip_id">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($viajes as $v)
                                        <option value="{{ $v->id }}">{{ $v->trip_number }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Contenedor</label>
                                <select class="form-select form-select-sm" wire:model="container_id">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($contenedores as $c)
                                        <option value="{{ $c->id }}">{{ $c->full_identifier }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Camión</label>
                                <select class="form-select form-select-sm" wire:model="vehicle_id">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($camiones as $v)
                                        <option value="{{ $v->id }}">{{ $v->plate_number ?: $v->vin }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Chofer</label>
                                <select class="form-select form-select-sm" wire:model="driver_id">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($choferes as $d)
                                        <option value="{{ $d->id }}">
                                            {{ trim($d->first_name.' '.$d->last_name) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Depósito</label>
                                <select class="form-select form-select-sm" wire:model="depot_id">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($depositos as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notas</label>
                                <textarea class="form-control" rows="2" wire:model="notes"></textarea>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           id="facturable" wire:model="is_billable">
                                    <label class="form-check-label" for="facturable">
                                        Se le vuelve a cobrar al cliente
                                    </label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           id="rep1099g" wire:model="is_1099_reportable">
                                    <label class="form-check-label" for="rep1099g">
                                        Reportable en 1099
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-end gap-2">
                        <button class="btn btn-outline-secondary" wire:click="cerrar">
                            {{ __('common.cancel') }}
                        </button>
                        <button class="btn btn-primary" wire:click="guardar">
                            <i class="bi bi-check-lg me-1"></i> {{ __('common.save') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>

</div>
