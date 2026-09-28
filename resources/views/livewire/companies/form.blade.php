{{--
    ═══════════════════════════════════════════════════════════════════════
    DATOS DE LA EMPRESA
    ═══════════════════════════════════════════════════════════════════════

    Dos bloques: lo que sale impreso en la cabecera del documento, y las
    formas de pago que salen al pie.

    Lo segundo es lo que pidió Denisse el 16 de septiembre. Lo primero está
    porque hasta hoy la dirección y el teléfono de la empresa tampoco se
    podían cambiar desde ninguna pantalla.

    Edita la compañía ACTIVA, la de la barra de arriba. Para cargar la otra
    se cambia de compañía y se vuelve a entrar.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">{{ __('companies.title') }}</h4>
            <small class="text-secondary">
                {{ __('companies.subtitle') }}
            </small>
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
        <div class="row g-3">

            {{-- ═════════════════════════════════════════════════════
                 1 · LO QUE SALE EN LA CABECERA DEL DOCUMENTO
            ═════════════════════════════════════════════════════ --}}
            <div class="col-12 col-lg-5">

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-building me-1"></i>
                            {{ __('companies.header_data') }}
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12">
                                <label class="form-label">{{ __('companies.address') }}</label>
                                <input type="text" class="form-control"
                                       wire:model.blur="address_line1">
                            </div>

                            <div class="col-12">
                                <input type="text" class="form-control"
                                       placeholder="{{ __('companies.address_2') }}"
                                       wire:model.blur="address_line2">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">{{ __('companies.city') }}</label>
                                <input type="text" class="form-control" wire:model.blur="city">
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">{{ __('companies.state') }}</label>
                                <input type="text" class="form-control text-uppercase"
                                       maxlength="2" placeholder="FL"
                                       wire:model.blur="state">
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">{{ __('companies.zip') }}</label>
                                <input type="text" class="form-control" wire:model.blur="zip">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">{{ __('companies.phone') }}</label>
                                <input type="text" class="form-control" wire:model.blur="phone">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">{{ __('companies.email') }}</label>
                                <input type="email" class="form-control" wire:model.blur="email">
                            </div>

                            <div class="col-12">
                                <label class="form-label">{{ __('companies.website') }}</label>
                                <input type="text" class="form-control" wire:model.blur="website">
                            </div>

                            <div class="col-12">
                                <label class="form-label">{{ __('companies.footer_terms') }}</label>
                                <textarea class="form-control" rows="3"
                                          wire:model.blur="invoice_footer_terms"></textarea>
                                <div class="form-text">{{ __('companies.footer_terms_hint') }}</div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- ═════════════════════════════════════════════════════
                 2 · LAS FORMAS DE PAGO DEL PIE DE LA FACTURA
            ═════════════════════════════════════════════════════ --}}
            <div class="col-12 col-lg-7">

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-credit-card me-1"></i>
                            {{ __('companies.payment_methods') }}
                        </h6>

                        <button type="button" class="btn btn-sm btn-outline-primary"
                                wire:click="agregarForma">
                            <i class="bi bi-plus-lg me-1"></i> {{ __('companies.add_method') }}
                        </button>
                    </div>

                    <div class="card-body">

                        <p class="small text-secondary">
                            {{ __('companies.payment_methods_hint') }}
                        </p>

                        @forelse ($formasDePago as $i => $forma)
                            <div class="border rounded p-2 mb-2" wire:key="forma-{{ $i }}">
                                <div class="row g-2 align-items-start">

                                    <div class="col-12 col-md-4">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="{{ __('companies.method_name') }}"
                                               wire:model.blur="formasDePago.{{ $i }}.label">
                                    </div>

                                    <div class="col-12 col-md-7">
                                        {{-- Un textarea y no un input: los datos de una
                                             transferencia se escriben en renglones, y al
                                             imprimir se respetan tal cual. --}}
                                        <textarea class="form-control form-control-sm" rows="3"
                                                  placeholder="{{ __('companies.method_details') }}"
                                                  wire:model.blur="formasDePago.{{ $i }}.details"></textarea>
                                    </div>

                                    <div class="col-12 col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                wire:click="quitarForma({{ $i }})"
                                                title="{{ __('companies.remove_method') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                </div>
                            </div>
                        @empty
                            <div class="alert alert-light border mb-0">
                                {{ __('companies.no_methods') }}
                            </div>
                        @endforelse

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
