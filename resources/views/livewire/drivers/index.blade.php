{{--
    ═══════════════════════════════════════════════════════════════════════
    LOS CHOFERES
    ═══════════════════════════════════════════════════════════════════════

    Lista y formulario en la misma pantalla. El formulario se abre en un
    panel lateral: son pocos registros y se corrigen sobre la marcha.

    Las dos fechas que vencen —licencia y examen médico— se marcan en la
    lista antes de que venzan, no el día que vencen.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Choferes</h4>
            <small class="text-secondary">Quiénes manejan y cuánto cobran por viaje.</small>
        </div>

        @can('drivers.create')
            <button class="btn btn-primary" wire:click="nuevo">
                <i class="bi bi-plus-lg me-1"></i> Nuevo chofer
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

        {{-- ── LA LISTA ── --}}
        <div class="{{ $editando ? 'col-12 col-lg-7' : 'col-12' }}">

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-8 col-md-6">
                            <input type="text" class="form-control form-control-sm"
                                   placeholder="Nombre, teléfono o licencia..."
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
                                <th>Chofer</th>
                                <th>Teléfono</th>
                                <th>Licencia</th>
                                <th>Médico</th>
                                <th class="text-end">Cobra</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                        @forelse ($choferes as $d)
                            @php
                                // Rojo si ya venció, amarillo si faltan 30 días o menos.
                                $claseFecha = function ($fecha) {
                                    if (! $fecha) return '';
                                    if ($fecha->isPast()) return 'text-danger fw-semibold';
                                    if ($fecha->diffInDays(now()) <= 30) return 'text-warning-emphasis fw-semibold';
                                    return '';
                                };
                            @endphp
                            <tr class="{{ ! $d->is_active ? 'opacity-50' : '' }}">
                                <td>
                                    <span class="fw-semibold">
                                        {{ trim($d->first_name.' '.$d->last_name) }}
                                    </span>
                                    @if ($d->carrier)
                                        <div class="small text-secondary">{{ $d->carrier->name }}</div>
                                    @endif
                                    @if (! $d->is_active)
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactivo</span>
                                    @endif
                                </td>

                                <td>{{ $d->phone ?: '—' }}</td>

                                <td class="{{ $claseFecha($d->license_expires_at) }}">
                                    @if ($d->license_expires_at)
                                        {{ $d->license_expires_at->format('d/m/Y') }}
                                        @if ($d->license_expires_at->isPast())
                                            <div class="small">Vencida</div>
                                        @endif
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>

                                <td class="{{ $claseFecha($d->medical_expires_at) }}">
                                    @if ($d->medical_expires_at)
                                        {{ $d->medical_expires_at->format('d/m/Y') }}
                                        @if ($d->medical_expires_at->isPast())
                                            <div class="small">Vencido</div>
                                        @endif
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    @if ($d->default_pay_percent !== null)
                                        {{ rtrim(rtrim(number_format((float) $d->default_pay_percent, 2), '0'), '.') }}%
                                    @elseif ($d->default_pay_amount !== null)
                                        ${{ number_format((float) $d->default_pay_amount, 2) }}
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    @can('drivers.update')
                                        <button class="btn btn-sm btn-outline-secondary"
                                                wire:click="editar({{ $d->id }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">
                                    No hay choferes que cumplan con esos filtros.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($choferes->hasPages())
                    <div class="card-footer">{{ $choferes->links() }}</div>
                @endif
            </div>
        </div>

        {{-- ── EL PANEL DE EDICIÓN ── --}}
        @if ($editando)
            <div class="col-12 col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">
                            {{ $driverId ? 'Editar chofer' : 'Nuevo chofer' }}
                        </h6>
                        <button class="btn-close" wire:click="cerrar"></button>
                    </div>

                    <div class="card-body">
                        <x-ui.errores />

                        <div class="row g-3">

                            <div class="col-6">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                       wire:model="first_name">
                                @error('first_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Apellido <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                                       wire:model="last_name">
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" wire:model="phone">
                            </div>

                            <div class="col-6">
                                <label class="form-label">Correo</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                       wire:model="email">
                                @error('email')
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

                            <div class="col-12">
                                <label class="form-label">Número de licencia</label>
                                <input type="text" class="form-control" wire:model="license_number">
                            </div>

                            <div class="col-6">
                                <label class="form-label">Licencia vence</label>
                                <input type="date" class="form-control" wire:model="license_expires_at">
                            </div>

                            <div class="col-6">
                                <label class="form-label">Médico vence</label>
                                <input type="date" class="form-control" wire:model="medical_expires_at">
                                <div class="form-text">DOT medical card.</div>
                            </div>

                            <div class="col-12"><hr class="my-1"></div>

                            <div class="col-12">
                                <div class="small text-secondary mb-2">
                                    Lo que se propone al registrar un viaje. En cada viaje
                                    se puede cambiar.
                                </div>
                            </div>

                            <div class="col-6">
                                <label class="form-label">% del viaje</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100"
                                           class="form-control text-end"
                                           wire:model="default_pay_percent">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <div class="col-6">
                                <label class="form-label">O monto fijo</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end"
                                           wire:model="default_pay_amount">
                                </div>
                            </div>

                            <div class="col-6">
                                <label class="form-label">Entró el</label>
                                <input type="date" class="form-control" wire:model="hired_at">
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           id="rep1099" wire:model="is_1099_reportable">
                                    <label class="form-check-label" for="rep1099">
                                        Reportable en 1099
                                    </label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           id="activoCh" wire:model="is_active">
                                    <label class="form-check-label" for="activoCh">Activo</label>
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
