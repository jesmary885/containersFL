{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL DEPÓSITO
    ═══════════════════════════════════════════════════════════════════════

    Tres bloques: dónde está, a quién se llama, y los tres números que se
    copian solos a cada compra que se haga ahí.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $depotId ? 'Editar depósito' : 'Nuevo depósito' }}
            </h4>
            <small class="text-secondary">
                El patio de un tercero donde nos guardan los contenedores hasta que vamos a buscarlos.
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="leyenda-obligatorio"><strong>*</strong> Campo obligatorio</span>
            <a href="{{ route('compras.depositos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            <div class="col-12 col-lg-7">

                <div class="card mb-3 seccion seccion-direccion">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">1</span>
                            <i class="bi bi-building"></i>
                            <span>Cuál es y dónde está</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       placeholder="Ej: Medley Container Depot"
                                       wire:model.blur="name">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" wire:model.blur="code">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Proveedor</label>
                                <select class="form-select" wire:model="supplier_id">
                                    <option value="">— Ninguno —</option>
                                    @foreach ($proveedores as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">De quién es el contenedor que está ahí.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label small">Calle y número</label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.blur="address.line1">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small">Estado</label>
                                <select class="form-select form-select-sm @error('state') is-invalid @enderror"
                                        wire:model.live="state">
                                    <option value="">— Elegir —</option>
                                    @foreach (\App\Support\UsPlaces::estadosParaSelect() as $cod => $nom)
                                        <option value="{{ $cod }}">{{ $nom }}</option>
                                    @endforeach
                                </select>
                                @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-5">
                                <label class="form-label small">Ciudad</label>
                                <input type="text" list="ciudades-dep" autocomplete="off"
                                       class="form-control form-control-sm" wire:model.blur="city">
                                <datalist id="ciudades-dep">
                                    @foreach ($state
                                        ? \App\Support\UsPlaces::ciudadesDe($state)
                                        : \App\Support\UsPlaces::todasLasCiudades() as $ciu)
                                        <option value="{{ $ciu }}"></option>
                                    @endforeach
                                </datalist>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label small">ZIP</label>
                                <input type="text" class="form-control form-control-sm" wire:model.blur="zip">
                            </div>

                        </div>
                    </div>
                </div>

                <div class="card mb-3 seccion seccion-cliente">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">2</span>
                            <i class="bi bi-telephone"></i>
                            <span>A quién se llama</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Contacto</label>
                                <input type="text" class="form-control" wire:model.blur="contact_name">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Teléfono</label>
                                <x-ui.telefono model="phone" :value="$phone" />
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Correo</label>
                                <x-ui.correo model="email" :value="$email" />
                            </div>

                            <div class="col-12">
                                <div class="form-text">
                                    Es a quien hay que avisarle antes de mandar el camión.
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <div class="col-12 col-lg-5">

                <div class="card mb-3 seccion seccion-lineas">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">3</span>
                            <i class="bi bi-cash-coin"></i>
                            <span>Lo que cuesta este depósito</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        <div class="alert alert-light border py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Estos tres se copian solos a cada compra que se haga aquí. Es la
                            diferencia entre que el costo sea un dato y que sea lo que alguien
                            se acuerde.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Costo del pickup</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01"
                                       class="form-control @error('default_pickup_fee') is-invalid @enderror"
                                       wire:model.blur="default_pickup_fee">
                            </div>
                            @error('default_pickup_fee') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Traer una unidad de aquí a la yarda. <strong>Es costo nuestro</strong>:
                                nunca se le cotiza al cliente.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Días libres</label>
                            <div class="input-group">
                                <input type="number"
                                       class="form-control @error('default_pickup_days') is-invalid @enderror"
                                       wire:model.blur="default_pickup_days">
                                <span class="input-group-text">días</span>
                            </div>
                            @error('default_pickup_days') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Cuánto tiempo dejan el contenedor sin cobrar almacenaje. Lo normal
                                son 14 días desde el release.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cargo por día extra</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01"
                                       class="form-control @error('daily_late_fee') is-invalid @enderror"
                                       wire:model.blur="daily_late_fee">
                                <span class="input-group-text">/día</span>
                            </div>
                            @error('daily_late_fee') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Lo que cobran pasados los días libres. Es lo que avisa cuándo un
                                release se está poniendo caro por no ir a buscarlo.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Millas hasta la yarda</label>
                            <input type="number" step="0.1" class="form-control"
                                   wire:model.blur="default_miles">
                            <div class="form-text">Opcional. Para estimar el viaje.</div>
                        </div>

                        <hr>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   id="depActivo" wire:model="is_active">
                            <label class="form-check-label" for="depActivo">Depósito activo</label>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" rows="3"
                                      placeholder="Horarios, cómo entrar, qué papel piden…"
                                      wire:model.blur="notes"></textarea>
                        </div>

                    </div>
                </div>

            </div>

        </div>

        <div class="ps-pie">
            <div>
                <a href="{{ route('compras.depositos.index') }}" class="btn btn-outline-secondary">
                    Cancelar
                </a>
            </div>

            <div class="ps-pie-medio">
                @if ($errors->any())
                    <span class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        {{ $errors->count() === 1 ? 'Falta 1 dato' : 'Faltan '.$errors->count().' datos' }}
                    </span>
                @endif
                <div wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1"></span> Guardando…
                </div>
            </div>

            <div>
                <button type="submit" class="btn btn-success" wire:loading.attr="disabled">
                    <i class="bi bi-check-lg me-1"></i>
                    {{ $depotId ? 'Guardar cambios' : 'Registrar depósito' }}
                </button>
            </div>
        </div>

    </form>

</div>
