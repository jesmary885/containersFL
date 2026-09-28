{{--
    ═══════════════════════════════════════════════════════════════════════
    UN CONTRATO DE RENTA
    ═══════════════════════════════════════════════════════════════════════

    La pantalla cambia según el ciclo:

      MENSUAL   pregunta por los contenedores nuestros que se lleva
      DE YARDA  pregunta por los días y los cargos de entrada y salida

    Lo que no cambia —cliente, fechas, mora, entrega— está fuera del
    condicional para no escribirlo dos veces.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $rentalId ? 'Contrato '.$numero : 'Nuevo contrato de renta' }}
            </h4>
            <small class="text-secondary">
                {{ $billing_cycle === 'daily'
                   ? 'El cliente deja su contenedor guardado en la yarda. Se cobra por día.'
                   : 'El cliente se lleva un contenedor nuestro. Se cobra por mes.' }}
            </small>
        </div>

        <a href="{{ route('operaciones.rentas.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            {{-- ═══════════ 1 · EL CONTRATO ═══════════ --}}
            <div class="col-12 col-lg-7">

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-file-earmark-text me-1"></i> El contrato
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <label class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select class="form-select @error('customer_id') is-invalid @enderror"
                                        wire:model="customer_id">
                                    <option value="">— Elegir —</option>
                                    @foreach ($clientes as $cl)
                                        <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.live="billing_cycle">
                                    @foreach ($ciclos as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" wire:model.live="status">
                                    @foreach ($estados as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label">Desde <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                                       wire:model.live="start_date">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label">Hasta</label>
                                <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                                       wire:model="end_date">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Vacío = sigue abierto.</div>
                            </div>

                            @if ($billing_cycle !== 'daily')
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Día de cobro <span class="text-danger">*</span></label>
                                    <input type="number" min="1" max="31"
                                           class="form-control @error('billing_anchor_day') is-invalid @enderror"
                                           wire:model="billing_anchor_day">
                                    @error('billing_anchor_day')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        El ciclo va de este día al mismo del mes siguiente,
                                        no del 1 al 30.
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>

                {{-- ═══════════ 2 · LOS CONTENEDORES (solo mensual) ═══════════ --}}
                @if ($billing_cycle !== 'daily')
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-semibold">
                                <i class="bi bi-box-seam me-1"></i> Contenedores que se lleva
                            </h6>

                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    wire:click="agregarUnidad">
                                <i class="bi bi-plus-lg me-1"></i> Agregar
                            </button>
                        </div>

                        <div class="card-body">
                            @error('unidades')
                                <div class="alert alert-danger py-2">{{ $message }}</div>
                            @enderror

                            @forelse ($unidades as $i => $u)
                                <div class="border rounded p-2 mb-2" wire:key="unidad-{{ $i }}">
                                    <div class="row g-2 align-items-end">

                                        <div class="col-12 col-md-5">
                                            <label class="form-label small">Contenedor</label>
                                            <select class="form-select form-select-sm @error('unidades.'.$i.'.container_id') is-invalid @enderror"
                                                    wire:model="unidades.{{ $i }}.container_id">
                                                <option value="">— Elegir —</option>
                                                @foreach ($contenedores as $c)
                                                    <option value="{{ $c->id }}">{{ $c->full_identifier }}</option>
                                                @endforeach
                                            </select>
                                            @error('unidades.'.$i.'.container_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-6 col-md-3">
                                            <label class="form-label small">Por mes</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" min="0"
                                                       class="form-control text-end"
                                                       wire:model="unidades.{{ $i }}.monthly_rate">
                                            </div>
                                        </div>

                                        <div class="col-5 col-md-3">
                                            <label class="form-label small">Desde</label>
                                            <input type="date" class="form-control form-control-sm"
                                                   wire:model="unidades.{{ $i }}.from_date">
                                        </div>

                                        <div class="col-1 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    wire:click="quitarUnidad({{ $i }})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>

                                    </div>
                                </div>
                            @empty
                                <div class="alert alert-light border mb-0 small">
                                    Todavía no hay contenedores en este contrato. Un contrato puede
                                    llevar varios a distinta tarifa.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                {{-- ═══════════ 3 · ENTREGA Y RECOGIDA ═══════════ --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-truck me-1"></i> Entrega y recogida
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-6 col-md-3">
                                <label class="form-label">Millas</label>
                                <input type="number" step="0.1" min="0"
                                       class="form-control text-end" wire:model="miles">
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Cobro de entrega</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end" wire:model="delivery_amount">
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Depósito de recogida</label>
                                <select class="form-select" wire:model="depot_id">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($depositos as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-2">
                                <label class="form-label">Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end" wire:model="pickup_fee">
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ 4 · LOS NÚMEROS ═══════════ --}}
            <div class="col-12 col-lg-5">

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-cash-stack me-1"></i> Tarifa
                        </h6>
                    </div>

                    <div class="card-body">

                        @if ($billing_cycle === 'daily')

                            <div class="mb-3">
                                <label class="form-label">
                                    Por día <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end fw-semibold @error('daily_rate') is-invalid @enderror"
                                           wire:model.live.debounce.500ms="daily_rate">
                                    <span class="input-group-text">/día</span>
                                </div>
                                @error('daily_rate')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Días ya pagados</label>
                                <input type="number" min="0"
                                       class="form-control text-end @error('paid_days') is-invalid @enderror"
                                       wire:model="paid_days">
                                @error('paid_days')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Para contratos que vienen del Excel con historia.
                                    La deuda sale de los días transcurridos menos estos.
                                </div>
                            </div>

                            <hr>

                            <div class="small text-secondary mb-2">
                                Cargos de una sola vez. No dependen del tiempo.
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small">Entrada</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end" wire:model="entry_fee">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Salida</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end" wire:model="exit_fee">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Pintura</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end" wire:model="paint_fee">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Reparación</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end" wire:model="repair_fee">
                                    </div>
                                </div>
                            </div>

                        @else

                            <div class="mb-3">
                                <label class="form-label">
                                    Por mes <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end fw-semibold @error('monthly_rate') is-invalid @enderror"
                                           wire:model.live.debounce.500ms="monthly_rate">
                                    <span class="input-group-text">/mes</span>
                                </div>
                                @error('monthly_rate')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Se propone en cada contenedor que se agregue.
                                </div>
                            </div>

                        @endif

                        <div class="mb-0 mt-3">
                            <label class="form-label">Impuesto</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" max="100"
                                       class="form-control text-end" wire:model="tax_rate">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ═══════════ 5 · MORA Y AUTOMATISMOS ═══════════ --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-alarm me-1"></i> Mora
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Días de gracia</label>
                                <input type="number" min="0" max="60"
                                       class="form-control form-control-sm text-end"
                                       wire:model="grace_days">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Cargo por mora</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end" wire:model="late_fee_amount">
                                </div>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox"
                                   id="autoMora" wire:model="auto_apply_late_fee">
                            <label class="form-check-label" for="autoMora">
                                Aplicar la mora automáticamente
                            </label>
                        </div>

                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox"
                                   id="autoFactura" wire:model="auto_invoice">
                            <label class="form-check-label" for="autoFactura">
                                Generar la factura de cada período sola
                            </label>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <label class="form-label">Notas</label>
                        <textarea class="form-control" rows="3" wire:model.blur="notes"></textarea>
                    </div>
                </div>

            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('operaciones.rentas.index') }}" class="btn btn-outline-secondary">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> {{ __('common.save') }}
            </button>
        </div>
    </form>

</div>
