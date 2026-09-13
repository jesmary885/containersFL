{{--
    ═══════════════════════════════════════════════════════════════════════
    ROLES Y PERMISOS
    ═══════════════════════════════════════════════════════════════════════

    Se elige el rol arriba y se marca qué puede hacer. Módulos en las
    filas, acciones en las columnas.

    ── POR QUÉ LOS CAMBIOS NO SE GUARDAN SOLOS ──

    Marcar una casilla no escribe nada: se trabaja en memoria hasta que
    se pulsa Guardar. Es a propósito. Con guardado automático, un clic
    de más en "Borrar" de Facturación le daría permiso de borrar
    facturas a todo un departamento sin que nadie lo note, y no habría
    forma de deshacerlo salvo acordándose de qué se tocó.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Roles y permisos</h4>
            <small class="text-secondary">
                Qué puede hacer cada rol en cada módulo del sistema.
            </small>
        </div>

        <a href="{{ route('administracion.usuarios.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-people me-1"></i> Ver usuarios
        </a>

    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-info-circle-fill me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{--
        Aviso de permisos que existen en la base y no están en la matriz.
        Ver el comentario del render(): si alguien agrega un módulo al
        seeder y se olvida de esta pantalla, sus permisos quedan
        inasignables y en silencio.
    --}}
    @if (count($huerfanos))
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>Hay permisos que esta pantalla no está mostrando:</strong>
            <code>{{ implode(', ', $huerfanos) }}</code>.
            Agrégalos a <code>App\Livewire\Roles\Index::MODULOS</code> para poder asignarlos.
        </div>
    @endif

    {{-- ───── SELECTOR DE ROL ───── --}}
    <div class="card mb-3">
        <div class="card-body">

            <div class="d-flex flex-wrap gap-2">
                @foreach ($roles as $unRol)
                    <button type="button"
                            class="btn btn-{{ $rolActivo === $unRol->name ? 'primary' : 'outline-secondary' }}"
                            wire:click="cargarRol('{{ $unRol->name }}')">
                        {{ $this->etiquetaDelRol($unRol->name) }}
                    </button>
                @endforeach
            </div>

        </div>
    </div>

    {{-- ───── EL SUPER ADMIN NO SE EDITA ───── --}}
    @if ($rolActivo === 'super_admin')

        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-shield-lock fs-1 text-secondary opacity-50 d-block mb-3"></i>
                <h5 class="fw-semibold">Acceso total, por diseño</h5>
                <p class="text-secondary mb-0">
                    El super administrador pasa por encima de toda comprobación de permisos.<br>
                    No hace falta marcarle nada, y marcarlo no cambiaría nada.
                </p>
            </div>
        </div>

    @else

        {{-- ───── ATAJOS ───── --}}
        <div class="card mb-3">
            <div class="card-body py-3 d-flex flex-wrap align-items-center gap-2">

                <span class="small text-secondary me-2">Atajos:</span>

                <button class="btn btn-sm btn-outline-secondary" wire:click="soloLectura">
                    <i class="bi bi-eye me-1"></i> Solo consultar, todo
                </button>

                <div class="ms-auto d-flex align-items-center gap-2">

                    @if ($this->hayCambios)
                        <span class="badge bg-warning-subtle text-warning">
                            <i class="bi bi-dot"></i> Cambios sin guardar
                        </span>

                        <button class="btn btn-sm btn-outline-secondary" wire:click="descartar">
                            Descartar
                        </button>
                    @endif

                    @can('users.update')
                        <button class="btn btn-primary"
                                wire:click="guardar"
                                wire:loading.attr="disabled"
                                @disabled(! $this->hayCambios)>
                            <i class="bi bi-check-lg me-1"></i> Guardar permisos
                        </button>
                    @endcan

                </div>

            </div>
        </div>

        {{-- ───── LA MATRIZ ───── --}}
        @foreach ($bloques as $nombreBloque => $modulos)

            <div class="card mb-3">

                <div class="card-header bg-white d-flex justify-content-between align-items-center">

                    <span class="fw-semibold">{{ $nombreBloque }}</span>

                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary"
                                wire:click="marcarBloque('{{ $nombreBloque }}', true)">
                            Marcar todo
                        </button>
                        <button class="btn btn-outline-secondary"
                                wire:click="marcarBloque('{{ $nombreBloque }}', false)">
                            Quitar todo
                        </button>
                    </div>

                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">

                        <tbody>
                        @foreach ($modulos as $clave => $etiqueta)

                            <tr>

                                <td style="width: 220px;">
                                    <span class="fw-semibold">{{ $etiqueta }}</span>
                                </td>

                                <td>
                                    <div class="d-flex flex-wrap gap-3">

                                        @foreach ($this->accionesDe($clave) as $accion)

                                            <div class="form-check mb-0">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       id="p-{{ $clave }}-{{ $accion }}"
                                                       value="{{ $clave }}.{{ $accion }}"
                                                       wire:model.live="marcados">
                                                <label class="form-check-label small"
                                                       for="p-{{ $clave }}-{{ $accion }}">
                                                    {{ $acciones[$accion] ?? $accion }}
                                                </label>
                                            </div>

                                        @endforeach

                                    </div>
                                </td>

                                <td style="width: 90px;" class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary py-0 px-2"
                                                wire:click="marcarModulo('{{ $clave }}', true)"
                                                title="Marcar todas">
                                            <i class="bi bi-check-all"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary py-0 px-2"
                                                wire:click="marcarModulo('{{ $clave }}', false)"
                                                title="Quitar todas">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </td>

                            </tr>

                        @endforeach
                        </tbody>

                    </table>
                </div>

            </div>

        @endforeach

        {{-- El botón de abajo, para no tener que subir después de marcar. --}}
        @can('users.update')
            <div class="d-flex justify-content-end gap-2 mb-4">

                @if ($this->hayCambios)
                    <button class="btn btn-outline-secondary" wire:click="descartar">Descartar cambios</button>
                @endif

                <button class="btn btn-primary"
                        wire:click="guardar"
                        @disabled(! $this->hayCambios)>
                    <i class="bi bi-check-lg me-1"></i> Guardar permisos
                </button>

            </div>
        @endcan

    @endif

</div>
