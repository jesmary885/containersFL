{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS CAMIONES
    ═══════════════════════════════════════════════════════════════════════

    Misma forma que Choferes. Registro y seguro se marcan 30 días antes de
    vencer, no el día que vencen.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Camiones</h4>
            <small class="text-secondary">La flota, con sus vencimientos.</small>
        </div>

        @can('vehicles.create')
            <button class="btn btn-primary" wire:click="nuevo">
                <i class="bi bi-plus-lg me-1"></i> Nuevo camión
            </button>
        @endcan
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">

        <div class="{{ $editando ? 'col-12 col-lg-7' : 'col-12' }}">

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-8 col-md-6">
                            <input type="text" class="form-control form-control-sm"
                                   placeholder="Placa, VIN, marca o modelo..."
                                   wire:model.live.debounce.400ms="buscar">
                        </div>
                        <div class="col-4 col-md-3">
                            <select class="form-select form-select-sm" wire:model.live="activos">
                                <option value="1">Solo activos</option>
                                <option value="0">Solo inactivos</option>
                                <option value="">Todos</option>
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
                                <th>Camión</th>
                                <th>Tipo</th>
                                <th>Registro</th>
                                <th>Seguro</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                        @php
                            $claseFecha = function ($fecha) {
                                if (! $fecha) return '';
                                if ($fecha->isPast()) return 'text-danger fw-semibold';
                                if ($fecha->diffInDays(now()) <= 30) return 'text-warning-emphasis fw-semibold';
                                return '';
                            };
                        @endphp

                        @forelse ($camiones as $v)
                            <tr class="{{ ! $v->is_active ? 'opacity-50' : '' }}">
                                <td>
                                    <span class="fw-semibold">
                                        {{ $v->plate_number ?: $v->vin }}
                                    </span>
                                    @if ($v->make || $v->model || $v->year)
                                        <div class="small text-secondary">
                                            {{ trim($v->make.' '.$v->model.' '.$v->year) }}
                                        </div>
                                    @endif
                                    @if ($v->carrier)
                                        <div class="small text-secondary">{{ $v->carrier->name }}</div>
                                    @endif
                                    @if (! $v->is_active)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                                    @endif
                                </td>

                                <td>{{ $v->type ?: '—' }}</td>

                                <td class="{{ $claseFecha($v->registration_expires_at) }}">
                                    @if ($v->registration_expires_at)
                                        {{ $v->registration_expires_at->format('d/m/Y') }}
                                        @if ($v->registration_expires_at->isPast())
                                            <div class="small">Vencido</div>
                                        @endif
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>

                                <td class="{{ $claseFecha($v->insurance_expires_at) }}">
                                    @if ($v->insurance_expires_at)
                                        {{ $v->insurance_expires_at->format('d/m/Y') }}
                                        @if ($v->insurance_expires_at->isPast())
                                            <div class="small">Vencido</div>
                                        @endif
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    @can('vehicles.update')
                                        <button class="btn btn-sm btn-outline-secondary"
                                                wire:click="editar({{ $v->id }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4">
                                    No hay camiones que cumplan con esos filtros.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($camiones->hasPages())
                    <div class="card-footer">{{ $camiones->links() }}</div>
                @endif
            </div>
        </div>

        {{-- ── EL PANEL ── --}}
        @if ($editando)
            <div class="col-12 col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">
                            {{ $vehicleId ? 'Editar camión' : 'Nuevo camión' }}
                        </h6>
                        <button class="btn-close" wire:click="cerrar"></button>
                    </div>

                    <div class="card-body">
                        <x-ui.errores />

                        <div class="row g-3">

                            <div class="col-6">
                                <label class="form-label">Placa</label>
                                <input type="text" class="form-control text-uppercase @error('plate_number') is-invalid @enderror"
                                       wire:model="plate_number">
                                @error('plate_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Tipo</label>
                                <input type="text" class="form-control"
                                       placeholder="Tractor, chasis..."
                                       wire:model="type">
                            </div>

                            <div class="col-12">
                                <label class="form-label">VIN</label>
                                <input type="text" maxlength="17"
                                       class="form-control text-uppercase @error('vin') is-invalid @enderror"
                                       wire:model="vin">
                                @error('vin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">17 caracteres. Único en toda la base.</div>
                            </div>

                            <div class="col-5">
                                <label class="form-label">Marca</label>
                                <input type="text" class="form-control"
                                       placeholder="Freightliner" wire:model="make">
                            </div>

                            <div class="col-4">
                                <label class="form-label">Modelo</label>
                                <input type="text" class="form-control"
                                       placeholder="Cascadia" wire:model="model">
                            </div>

                            <div class="col-3">
                                <label class="form-label">Año</label>
                                <input type="number" class="form-control text-end @error('year') is-invalid @enderror"
                                       wire:model="year">
                                @error('year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Transportista</label>
                                <select class="form-select" wire:model="carrier_id">
                                    <option value="">— Propio —</option>
                                    @foreach ($transportistas as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12"><hr class="my-1"></div>

                            <div class="col-6">
                                <label class="form-label">Registro vence</label>
                                <input type="date" class="form-control" wire:model="registration_expires_at">
                            </div>

                            <div class="col-6">
                                <label class="form-label">Seguro vence</label>
                                <input type="date" class="form-control" wire:model="insurance_expires_at">
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           id="activoCam" wire:model="is_active">
                                    <label class="form-check-label" for="activoCam">Activo</label>
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
