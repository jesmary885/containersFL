{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL PROVEEDOR
    ═══════════════════════════════════════════════════════════════════════

    Una sola pantalla, sin asistente. Son ocho campos: partirlos en tres
    pasos son dos clics de más a cambio de nada.

    El criterio que venimos usando: el asistente entra cuando el
    formulario no cabe de un vistazo. Cliente, presupuesto y factura no
    caben. Este sí.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $supplierId ? 'Editar proveedor' : 'Nuevo proveedor' }}
                @if ($numero)
                    <span class="text-secondary fw-normal font-monospace fs-6">{{ $numero }}</span>
                @endif
            </h4>
            <small class="text-secondary">
                @if (! $supplierId)
                    El número se asigna solo al guardar.
                @endif
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="leyenda-obligatorio"><strong>*</strong> Campo obligatorio</span>
            <a href="{{ route('compras.proveedores.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            <div class="col-12 col-lg-7">

                <div class="card mb-3 seccion seccion-cliente">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">1</span>
                            <i class="bi bi-shop"></i>
                            <span>Datos generales</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-7">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                       wire:model.blur="name">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-5">
                                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model="type">
                                    @foreach ($tipos as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Persona de contacto</label>
                                <input type="text" class="form-control" wire:model.blur="contact_name">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Teléfono</label>
                                <x-ui.telefono model="phone" :value="$phone"
                                               :invalid="$errors->has('phone')" />
                                @error('phone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Correo</label>
                                <x-ui.correo model="email" :value="$email"
                                             :invalid="$errors->has('email')" />
                                @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                        </div>
                    </div>
                </div>

                <div class="card mb-3 seccion seccion-direccion">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">2</span>
                            <i class="bi bi-geo-alt"></i>
                            <span>Dirección</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-2">

                            <div class="col-12">
                                <label class="form-label small">Calle y número</label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.blur="address.line1">
                            </div>

                            <div class="col-12">
                                <label class="form-label small">Suite o unidad</label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.blur="address.line2">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small">Estado</label>
                                <select class="form-select form-select-sm @error('address.state') is-invalid @enderror"
                                        wire:model.live="address.state">
                                    <option value="">— Elegir —</option>
                                    @foreach (\App\Support\UsPlaces::estadosParaSelect() as $cod => $nom)
                                        <option value="{{ $cod }}">{{ $nom }}</option>
                                    @endforeach
                                </select>
                                @error('address.state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-5">
                                <label class="form-label small">Ciudad</label>
                                <input type="text" list="ciudades-prov" autocomplete="off"
                                       class="form-control form-control-sm"
                                       wire:model.blur="address.city">
                                <datalist id="ciudades-prov">
                                    @foreach (($address['state'] ?? '')
                                        ? \App\Support\UsPlaces::ciudadesDe($address['state'])
                                        : \App\Support\UsPlaces::todasLasCiudades() as $ciu)
                                        <option value="{{ $ciu }}"></option>
                                    @endforeach
                                </datalist>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label small">ZIP</label>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.blur="address.zip">
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <div class="col-12 col-lg-5">

                <div class="card mb-3 seccion seccion-datos">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">3</span>
                            <i class="bi bi-file-earmark-ruled"></i>
                            <span>Estado</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        {{--
                            Aquí estaba la casilla del 1099.

                            La quité: busqué "1099" en las tres actas y no
                            aparece en ninguna. La columna sigue en la base
                            —alguien del equipo la pensó antes— pero pedirla en
                            pantalla sin que el cliente la haya pedido es una
                            casilla que nadie va a marcar porque nadie sabe para
                            qué es.

                            Si Denisse la pide, vuelve con diez líneas.
                        --}}
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   id="activo" wire:model="is_active">
                            <label class="form-check-label" for="activo">Proveedor activo</label>
                        </div>
                        <div class="form-text mb-3">
                            Al apagarlo deja de aparecer al registrar compras nuevas.
                            Las viejas no se tocan.
                        </div>

                        <label class="form-label">Notas internas</label>
                        <textarea class="form-control" rows="4"
                                  placeholder="Condiciones de pago, plazos de entrega, con quién hablar…"
                                  wire:model.blur="notes"></textarea>

                    </div>
                </div>

            </div>

        </div>

        <div class="ps-pie">
            <div>
                <a href="{{ route('compras.proveedores.index') }}" class="btn btn-outline-secondary">
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
                    {{ $supplierId ? 'Guardar cambios' : 'Registrar proveedor' }}
                </button>
            </div>
        </div>

    </form>

</div>
