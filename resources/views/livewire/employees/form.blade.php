{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL TRABAJADOR
    ═══════════════════════════════════════════════════════════════════════
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $employeeId ? 'Editar trabajador' : 'Nuevo trabajador' }}
            </h4>
            <small class="text-secondary">
                Registro de personal. No es el usuario que entra al sistema.
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="leyenda-obligatorio"><strong>*</strong> Campo obligatorio</span>
            <a href="{{ route('sistema.trabajadores.index') }}" class="btn btn-outline-secondary">
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
                            <i class="bi bi-person-vcard"></i>
                            <span>Datos generales</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                       wire:model.blur="first_name">
                                @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Apellido</label>
                                <input type="text" class="form-control" wire:model.blur="last_name">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Rol <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.live="role">
                                    @foreach ($roles as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Teléfono</label>
                                <x-ui.telefono model="phone" :value="$phone" />
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Correo</label>
                                <x-ui.correo model="email" :value="$email" />
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <div class="col-12 col-lg-5">

                <div class="card mb-3 seccion seccion-lineas">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">2</span>
                            <i class="bi bi-cash-coin"></i>
                            <span>Comisión y asignación</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        {{--
                            La comisión solo se pregunta si aplica.

                            Sin el interruptor, los dos campos salen siempre,
                            incluso para una secretaria o el personal de
                            limpieza. Un campo que no aplica en la mayoría de
                            los casos ensucia el formulario y hace dudar de si
                            hay que llenarlo.
                        --}}
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox"
                                   id="cobraComision" wire:model.live="cobraComision">
                            <label class="form-check-label" for="cobraComision">
                                Cobra comisión por venta
                            </label>
                        </div>

                        @if ($cobraComision)

                            <div class="mb-3">
                                <label class="form-label">Monto por venta</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01"
                                           class="form-control @error('default_commission_amount') is-invalid @enderror"
                                           wire:model.blur="default_commission_amount">
                                </div>
                                @error('default_commission_amount')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Porcentaje sobre la venta</label>
                                <div class="input-group">
                                    <input type="number" step="0.01"
                                           class="form-control @error('default_commission_percent') is-invalid @enderror"
                                           wire:model.blur="default_commission_percent">
                                    <span class="input-group-text">%</span>
                                </div>
                                @error('default_commission_percent')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                        @endif

                        <hr>

                        <div class="mb-3">
                            <label class="form-label">Empresa</label>
                            <select class="form-select" wire:model="company_id">
                                <option value="">Ambas</option>
                                @foreach ($empresas as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fecha de ingreso</label>
                            <input type="date" class="form-control" wire:model="hired_at">
                        </div>

                        {{--
                            ───── SOLO SI MANEJA ─────

                            Aparece únicamente cuando el rol es chofer. Un
                            vendedor no tiene licencia comercial ni certificado
                            médico, y enseñarle esos campos sería pedirle datos
                            que no existen.

                            Por dentro esto mantiene la ficha de `drivers`, que
                            es a donde apuntan los viajes y las liquidaciones.
                            El usuario no tiene por qué saberlo: registra a la
                            persona una vez.
                        --}}
                        @if ($role === 'chofer')

                            <div class="row g-2 mb-3">

                                <div class="col-12">
                                    <label class="form-label small">Número de licencia</label>
                                    <input type="text" class="form-control form-control-sm"
                                           wire:model.blur="license_number">
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Vencimiento de licencia</label>
                                    <input type="date" class="form-control form-control-sm"
                                           wire:model="license_expires_at">
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Vencimiento de certificado médico</label>
                                    <input type="date" class="form-control form-control-sm"
                                           wire:model="medical_expires_at">
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Pago por viaje</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" class="form-control"
                                               wire:model.blur="default_pay_amount">
                                    </div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Pago en porcentaje</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" class="form-control"
                                               wire:model.blur="default_pay_percent">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>

                            </div>

                            <hr>

                        @endif

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox"
                                   id="empActivo" wire:model="is_active">
                            <label class="form-check-label" for="empActivo">Activo</label>
                        </div>

                        <label class="form-label">Notas</label>
                        <textarea class="form-control" rows="3" wire:model.blur="notes"></textarea>

                    </div>
                </div>

            </div>

        </div>

        <div class="ps-pie">
            <div>
                <a href="{{ route('sistema.trabajadores.index') }}" class="btn btn-outline-secondary">
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
                    {{ $employeeId ? 'Guardar cambios' : 'Registrar trabajador' }}
                </button>
            </div>
        </div>

    </form>

</div>
