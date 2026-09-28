{{--
    ═══════════════════════════════════════════════════════════════════════
    UN VIAJE
    ═══════════════════════════════════════════════════════════════════════

    Cuatro bloques, en el orden en que se piensa un viaje:

      qué viaje es · de dónde a dónde · quién lo hace · cuánto se cobra

    La compañía cliente está arriba del todo porque es lo que pidió Denisse
    el 16-09 y es lo que define si este viaje entra en la factura semanal.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $tripId ? 'Viaje '.$numero : 'Nuevo viaje' }}
            </h4>
            <small class="text-secondary">
                Transporte de contenedores. Los viajes completados se facturan por semana.
            </small>
        </div>

        <a href="{{ route('operaciones.viajes.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            {{-- ═════════════════════════════════════════════════════
                 1 · QUÉ VIAJE ES Y PARA QUIÉN
            ═════════════════════════════════════════════════════ --}}
            <div class="col-12 col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-truck me-1"></i> El viaje
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.live="type">
                                    @foreach ($tipos as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Estado</label>
                                <select class="form-select" wire:model.live="status">
                                    @foreach ($estados as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Contenedor</label>
                                <select class="form-select" wire:model="container_id">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($contenedores as $c)
                                        <option value="{{ $c->id }}">{{ $c->full_identifier }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- ─────────────────────────────────────────────
                                 LA COMPAÑÍA CLIENTE · REUNIÓN 16-09

                                 Lo que Denisse pidió. Es quien recibe la
                                 factura: puede ser Florida Logistics, Maritin,
                                 Ricardo, o el mismo cliente que compró el
                                 contenedor.
                            ───────────────────────────────────────────── --}}
                            <div class="col-12">
                                <label class="form-label">
                                    Compañía cliente
                                    @if ((float) $customer_price > 0)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <select class="form-select @error('customer_id') is-invalid @enderror"
                                        wire:model.live="customer_id">
                                    <option value="">— Sin cliente (movimiento interno) —</option>
                                    @foreach ($clientes as $cl)
                                        <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Los viajes completados de una misma compañía se agrupan
                                    en una sola factura semanal.
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Programado para</label>
                                <input type="datetime-local" class="form-control"
                                       wire:model="scheduled_at">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">
                                    Completado el
                                    @if ($status === 'completed')
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <input type="datetime-local" class="form-control"
                                       wire:model="completed_at">
                                @if ($status === 'completed')
                                    <div class="form-text">
                                        Esta fecha decide en qué factura semanal entra el viaje.
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ═════════════════════════════════════════════════════
                     2 · DE DÓNDE A DÓNDE
                ═════════════════════════════════════════════════════ --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-signpost-2 me-1"></i> Recorrido
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            @if ($type === 'pickup')
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Depósito de origen</label>
                                    <select class="form-select" wire:model.live="depot_id">
                                        <option value="">— Elegir —</option>
                                        @foreach ($depositos as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">
                                        La recogida se cobra por el fee del depósito, no por millas.
                                    </div>
                                </div>
                            @else
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Sale de</label>
                                    <select class="form-select" wire:model="origin_location_id">
                                        <option value="">— La yarda —</option>
                                        @foreach ($origenes as $o)
                                            <option value="{{ $o->id }}">{{ $o->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="col-12 col-md-6">
                                <label class="form-label">Código postal del destino</label>
                                <input type="text" maxlength="10"
                                       class="form-control @error('destination_zip') is-invalid @enderror"
                                       wire:model.blur="destination_zip">
                                @error('destination_zip')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Dirección del destino</label>
                                <input type="text" class="form-control mb-2"
                                       placeholder="Calle y número"
                                       wire:model.blur="destination_address.line1">

                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="text" class="form-control"
                                               placeholder="Ciudad"
                                               wire:model.blur="destination_address.city">
                                    </div>
                                    <div class="col-3">
                                        <input type="text" class="form-control text-uppercase"
                                               maxlength="2" placeholder="FL"
                                               wire:model.blur="destination_address.state">
                                    </div>
                                    <div class="col-3">
                                        <input type="text" class="form-control"
                                               placeholder="ZIP"
                                               wire:model.blur="destination_address.zip">
                                    </div>
                                </div>
                            </div>

                            @if ($type !== 'pickup')
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Millas</label>

                                    {{-- El botón de Google Maps, el mismo de presupuestos
                                         y facturas. Sin clave no aparece. --}}
                                    @if ($this->puedeCalcularMillas)
                                        <div class="input-group">
                                            <input type="number" step="0.1" min="0"
                                                   class="form-control text-end @error('miles') is-invalid @enderror"
                                                   wire:model.live.debounce.500ms="miles">
                                            <button type="button" class="btn btn-outline-secondary"
                                                    wire:click="calcularMillas"
                                                    wire:loading.attr="disabled"
                                                    wire:target="calcularMillas"
                                                    title="Calcular con Google Maps">
                                                <span wire:loading.remove wire:target="calcularMillas">
                                                    <i class="bi bi-geo-alt"></i>
                                                </span>
                                                <span wire:loading wire:target="calcularMillas"
                                                      class="spinner-border spinner-border-sm"></span>
                                            </button>
                                        </div>
                                    @else
                                        <input type="number" step="0.1" min="0"
                                               class="form-control text-end @error('miles') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="miles">
                                    @endif

                                    @error('miles')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label">Tarifa por milla</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end"
                                               wire:model.live.debounce.500ms="rate_per_mile">
                                        <span class="input-group-text">/ mi</span>
                                    </div>
                                    <div class="form-text">
                                        Sale del rango de millas. Se puede pisar.
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>

            {{-- ═════════════════════════════════════════════════════
                 3 · QUIÉN LO HACE  ·  4 · LOS NÚMEROS
            ═════════════════════════════════════════════════════ --}}
            <div class="col-12 col-lg-5">

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-person-vcard me-1"></i> Quién lo hace
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Chofer</label>
                            <select class="form-select" wire:model.live="driver_id">
                                <option value="">— Elegir —</option>
                                @foreach ($choferes as $ch)
                                    <option value="{{ $ch->id }}">
                                        {{ trim($ch->first_name.' '.$ch->last_name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Camión</label>
                            <select class="form-select" wire:model="vehicle_id">
                                <option value="">— Elegir —</option>
                                @foreach ($camiones as $v)
                                    <option value="{{ $v->id }}">
                                        {{ $v->plate_number ?: ('#'.$v->id) }}
                                        @if ($v->make) · {{ $v->make }} {{ $v->model }} @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Transportista</label>
                            <select class="form-select" wire:model.live="carrier_id">
                                <option value="">— Propio —</option>
                                @foreach ($transportistas as $tr)
                                    <option value="{{ $tr->id }}">{{ $tr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-cash-stack me-1"></i> Los números
                        </h6>
                    </div>

                    <div class="card-body">

                        @if ($type === 'pickup')
                            <div class="mb-3">
                                <label class="form-label">Fee del depósito</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end"
                                           wire:model.live.debounce.500ms="pickup_fee">
                                </div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">
                                Se le cobra al cliente <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0"
                                       class="form-control text-end fw-semibold @error('customer_price') is-invalid @enderror"
                                       wire:model.live.debounce.500ms="customer_price">
                            </div>
                            @error('customer_price')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr>

                        <div class="row g-2 mb-3">
                            <div class="col-5">
                                <label class="form-label">% chofer</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100"
                                           class="form-control text-end"
                                           wire:model.live.debounce.500ms="driver_pay_percent">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <div class="col-7">
                                <label class="form-label">Se le paga al chofer</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control text-end"
                                           wire:model.blur="driver_pay">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Costo del transportista</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0"
                                       class="form-control text-end"
                                       wire:model.live.debounce.500ms="carrier_cost">
                            </div>
                            <div class="form-text">
                                Solo si el viaje lo hizo un transportista de fuera.
                            </div>
                        </div>

                        {{-- El margen, calculado en pantalla. No se guarda:
                             se deduce de los tres números de arriba y guardarlo
                             sería un cuarto que puede quedar desfasado. --}}
                        @php
                            $margen = (float) $customer_price
                                    - (float) ($carrier_cost ?: 0)
                                    - (float) ($driver_pay ?: 0);
                        @endphp

                        <div class="alert {{ $margen >= 0 ? 'alert-success' : 'alert-danger' }} mb-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small">Queda para la empresa</span>
                                <span class="fw-semibold">${{ number_format($margen, 2) }}</span>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <label class="form-label">Notas</label>
                        <textarea class="form-control" rows="3"
                                  wire:model.blur="notes"></textarea>
                    </div>
                </div>

            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('operaciones.viajes.index') }}" class="btn btn-outline-secondary">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> {{ __('common.save') }}
            </button>
        </div>
    </form>

</div>
