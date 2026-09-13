{{--
    ═══════════════════════════════════════════════════════════════════════
    ALTA Y EDICIÓN DE USUARIO
    ═══════════════════════════════════════════════════════════════════════

    Tres bloques en una sola pantalla, no un wizard: son doce campos
    contados y todos caben sin bajar. El wizard del presupuesto existe
    porque allí son treinta y muchos no aplican todavía; aquí sería
    ceremonia sin motivo.
--}}
{{--
    El salto al primer campo en rojo NO se pone aquí: lo trae ya el
    componente <x-ui.errores />, que está más abajo. Duplicarlo hace que
    dos escuchadores peleen por el scroll en la misma pulsación.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $userId ? 'Editar usuario' : 'Nuevo usuario' }}
            </h4>
            <small class="text-secondary">
                {{ $userId
                    ? 'Los cambios de rol surten efecto en el siguiente clic de esa persona.'
                    : 'Necesita al menos una empresa y un rol para poder trabajar.' }}
            </small>
        </div>

        <a href="{{ route('administracion.usuarios.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>

    </div>

    @if (session('error'))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
        </div>
    @endif

    <x-ui.errores />

    <div class="row g-3">

        {{-- ─────────────────────────────────────────────────────────
             BLOQUE 1 · QUIÉN ES
        ───────────────────────────────────────────────────────── --}}
        <div class="col-12 col-lg-7">

            <div class="card h-100">

                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-person me-1"></i> Quién es</span>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <label class="form-label">Nombre y apellido <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   wire:model.blur="name">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Correo <span class="text-danger">*</span></label>
                            <input type="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   wire:model.blur="email">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Con este correo inicia sesión.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   wire:model.blur="phone">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Idioma de la pantalla</label>
                            <select class="form-select" wire:model="locale">
                                <option value="es">Español</option>
                                <option value="en">English</option>
                            </select>
                        </div>

                        {{-- ───── CONTRASEÑA ───── --}}
                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-12">
                            <div class="fw-semibold small text-secondary mb-2">
                                <i class="bi bi-key me-1"></i>
                                {{ $userId ? 'Cambiar contraseña' : 'Contraseña' }}
                            </div>
                            @if ($userId)
                                <div class="alert alert-light border py-2 small mb-3">
                                    Déjelo en blanco para no cambiarla.
                                </div>
                            @endif
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">
                                Contraseña
                                @unless ($userId) <span class="text-danger">*</span> @endunless
                            </label>
                            <input type="password"
                                   class="form-control @error('password') is-invalid @enderror"
                                   autocomplete="new-password"
                                   wire:model.blur="password">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Mínimo 8 caracteres.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Repetir contraseña</label>
                            <input type="password"
                                   class="form-control"
                                   autocomplete="new-password"
                                   wire:model.blur="password_confirmation">
                        </div>

                        {{-- ───── ESTADO ───── --}}
                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="usuarioActivo"
                                       wire:model="is_active">
                                <label class="form-check-label" for="usuarioActivo">
                                    Usuario activo
                                </label>
                            </div>
                            <div class="form-text">
                                Al desactivarlo se le cierra la sesión en su siguiente clic, aunque
                                la tenga abierta. Los documentos que firmó conservan su nombre.
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>

        {{-- ─────────────────────────────────────────────────────────
             BLOQUE 2 Y 3 · DÓNDE ENTRA Y QUÉ PUEDE HACER
        ───────────────────────────────────────────────────────── --}}
        <div class="col-12 col-lg-5">

            {{-- ───── EMPRESAS ───── --}}
            <div class="card mb-3">

                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-buildings me-1"></i> A qué empresas entra</span>
                </div>

                <div class="card-body">

                    @error('empresas')
                        <div class="alert alert-danger py-2 small">{{ $message }}</div>
                    @enderror

                    @foreach ($empresasDisponibles as $empresa)

                        <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">

                            <div class="form-check mb-0">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="empresa{{ $empresa->id }}"
                                       value="{{ $empresa->id }}"
                                       wire:model.live="empresas">
                                <label class="form-check-label" for="empresa{{ $empresa->id }}">
                                    <span class="fw-semibold">{{ $empresa->code }}</span>
                                    <small class="text-secondary d-block">{{ $empresa->name }}</small>
                                </label>
                            </div>

                            {{--
                                El botón de "abre con esta" solo aparece si la
                                empresa está marcada. Ofrecer marcar como
                                predeterminada una a la que no tiene acceso no
                                significa nada.
                            --}}
                            @if (in_array($empresa->id, $empresas))
                                <button type="button"
                                        class="btn btn-sm btn-{{ $empresaPorDefecto == $empresa->id ? 'warning' : 'outline-secondary' }}"
                                        wire:click="$set('empresaPorDefecto', {{ $empresa->id }})"
                                        title="Abre el sistema con esta empresa">
                                    <i class="bi bi-star{{ $empresaPorDefecto == $empresa->id ? '-fill' : '' }}"></i>
                                </button>
                            @endif

                        </div>

                    @endforeach

                    @error('empresaPorDefecto')
                        <div class="alert alert-danger py-2 small mb-0">{{ $message }}</div>
                    @enderror

                    <div class="form-text mt-2">
                        <i class="bi bi-star-fill text-warning"></i>
                        marca con cuál abre el sistema. Puede cambiar de empresa desde la barra de arriba.
                    </div>

                </div>

            </div>

            {{-- ───── ROL ───── --}}
            <div class="card">

                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-shield-check me-1"></i> Qué puede hacer</span>

                    @can('users.update')
                        <a href="{{ route('administracion.roles.index') }}"
                           class="small text-decoration-none">
                            Editar permisos <i class="bi bi-arrow-right-short"></i>
                        </a>
                    @endcan
                </div>

                <div class="card-body">

                    @error('rol')
                        <div class="alert alert-danger py-2 small">{{ $message }}</div>
                    @enderror

                    @foreach ($rolesDisponibles as $rolDisponible)

                        <label class="d-block border rounded p-2 mb-2 {{ $rol === $rolDisponible['name'] ? 'border-primary bg-primary-subtle' : '' }}"
                               style="cursor: pointer;">

                            <div class="form-check mb-0">
                                <input class="form-check-input"
                                       type="radio"
                                       value="{{ $rolDisponible['name'] }}"
                                       wire:model.live="rol">
                                <span class="fw-semibold">{{ $rolDisponible['label'] }}</span>
                                <small class="text-secondary d-block">{{ $rolDisponible['ayuda'] }}</small>
                            </div>

                        </label>

                    @endforeach

                </div>

            </div>

        </div>

    </div>

    {{-- ───── BOTONES ───── --}}
    <div class="d-flex justify-content-end gap-2 mt-3">

        <a href="{{ route('administracion.usuarios.index') }}" class="btn btn-outline-secondary">
            Cancelar
        </a>

        <button class="btn btn-primary" wire:click="guardar" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="guardar">
                <i class="bi bi-check-lg me-1"></i>
                {{ $userId ? 'Guardar cambios' : 'Crear usuario' }}
            </span>
            <span wire:loading wire:target="guardar">
                <span class="spinner-border spinner-border-sm me-1"></span> Guardando...
            </span>
        </button>

    </div>

</div>
