{{--
    ═══════════════════════════════════════════════════════════════════════
    TARIFAS DE ENTREGA POR RANGO DE MILLAS
    ═══════════════════════════════════════════════════════════════════════

    Los rangos que dio Denisse el 16 de septiembre, editables. Todo se
    escribe encima y se guarda de una vez: son tres filas y no merecen un
    formulario por fila.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">{{ __('delivery_rates.title') }}</h4>
            <small class="text-secondary">{{ __('delivery_rates.subtitle') }}</small>
        </div>

        @if ($empresa)
            <span class="badge bg-primary-subtle text-primary-emphasis fs-6">
                {{ $empresa->name }}
            </span>
        @endif
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

    <form wire:submit.prevent="guardar">

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-signpost-split me-1"></i>
                    {{ __('delivery_rates.ranges') }}
                </h6>

                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="agregar">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('delivery_rates.add_range') }}
                </button>
            </div>

            <div class="card-body">

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 22%;">{{ __('delivery_rates.name') }}</th>
                                <th class="text-end" style="width: 14%;">{{ __('delivery_rates.from') }}</th>
                                <th class="text-end" style="width: 14%;">{{ __('delivery_rates.to') }}</th>
                                <th class="text-end" style="width: 16%;">{{ __('delivery_rates.rate') }}</th>
                                <th class="text-center" style="width: 10%;">{{ __('delivery_rates.active') }}</th>
                                <th style="width: 6%;"></th>
                            </tr>
                        </thead>

                        <tbody>
                        @foreach ($filas as $i => $f)
                            <tr wire:key="tarifa-{{ $i }}">

                                <td>
                                    <input type="text" class="form-control form-control-sm"
                                           placeholder="0 – 100 mi"
                                           wire:model.blur="filas.{{ $i }}.label">

                                    @if (! empty($f['notas']))
                                        <div class="small text-secondary mt-1">{{ $f['notas'] }}</div>
                                    @endif
                                </td>

                                <td>
                                    <input type="number" step="0.1" min="0"
                                           class="form-control form-control-sm text-end @error('filas.'.$i.'.min') is-invalid @enderror"
                                           wire:model.blur="filas.{{ $i }}.min">
                                    @error('filas.'.$i.'.min')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>

                                <td>
                                    <input type="number" step="0.1" min="0"
                                           class="form-control form-control-sm text-end @error('filas.'.$i.'.max') is-invalid @enderror"
                                           placeholder="{{ __('delivery_rates.no_cap') }}"
                                           wire:model.blur="filas.{{ $i }}.max">
                                    @error('filas.'.$i.'.max')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end @error('filas.'.$i.'.tarifa') is-invalid @enderror"
                                               wire:model.blur="filas.{{ $i }}.tarifa">
                                    </div>
                                    @error('filas.'.$i.'.tarifa')
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </td>

                                <td class="text-center">
                                    <input class="form-check-input" type="checkbox"
                                           wire:model.live="filas.{{ $i }}.activo">
                                </td>

                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            wire:click="quitar({{ $i }})"
                                            wire:confirm="{{ __('delivery_rates.confirm_remove') }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>

                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="small text-secondary">
                    {{ __('delivery_rates.bounds_hint') }}
                </div>

                {{-- HUECOS · avisa, no bloquea.
                     Un tramo sin tarifa no da error: cae al valor general y nadie
                     se entera. Por eso conviene verlo escrito. --}}
                @if (! empty($huecos))
                    <div class="alert alert-warning mt-3 mb-0">
                        <div class="fw-semibold mb-1">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ __('delivery_rates.gaps_title') }}
                        </div>
                        <ul class="mb-1 small">
                            @foreach ($huecos as $h)
                                <li>
                                    @if ($h['hasta'] === null)
                                        {{ __('delivery_rates.gap_over', ['n' => (int) $h['desde']]) }}
                                    @else
                                        {{ __('delivery_rates.gap_between', [
                                            'a' => (int) $h['desde'],
                                            'b' => (int) $h['hasta'],
                                        ]) }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                        <div class="small mb-0">{{ __('delivery_rates.gaps_hint') }}</div>
                    </div>
                @endif

            </div>
        </div>

        {{-- ═════════════════════════════════════════════════════
             EL RECARGO POR COMBUSTIBLE
        ═════════════════════════════════════════════════════ --}}
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-fuel-pump me-1"></i>
                    {{ __('delivery_rates.fuel') }}
                </h6>
            </div>

            <div class="card-body">
                <div class="row g-3 align-items-start">

                    <div class="col-12 col-md-4">
                        <label class="form-label">{{ __('delivery_rates.fuel_per_mile') }}</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0"
                                   class="form-control text-end"
                                   wire:model.blur="recargoCombustible">
                            <span class="input-group-text">/ mi</span>
                        </div>
                    </div>

                    <div class="col-12 col-md-8">
                        <div class="alert alert-light border mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            {{ __('delivery_rates.fuel_hint') }}
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> {{ __('common.save') }}
            </button>
        </div>
    </form>

</div>
