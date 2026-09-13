{{--
    ═══════════════════════════════════════════════════════════════════════
    LISTADO DE USUARIOS
    ═══════════════════════════════════════════════════════════════════════

    Administración › Usuarios.

    Dos avisos que no son decoración: "sin empresa" y "sin rol". Los dos
    describen cuentas que existen y no sirven —una no puede iniciar
    sesión, la otra entra y no ve ninguna pantalla— y las dos se crean
    sin querer. Puestos como contadores, se ven antes de que la persona
    llame diciendo que el sistema está roto.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Usuarios</h4>
            <small class="text-secondary">
                Quién entra al sistema, a qué empresas y con qué permisos.
            </small>
        </div>

        @can('users.create')
            <a href="{{ route('administracion.usuarios.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> Nuevo usuario
            </a>
        @endcan

    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ───── CONTADORES ───── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ok">
                <div class="kpi-label"><i class="bi bi-people"></i> Activos</div>
                <div class="kpi-valor">{{ $resumen['activos'] }}</div>
                <div class="kpi-pie">de {{ $resumen['total'] }} registrados</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi {{ $resumen['sinEmpresa'] > 0 ? 'kpi-warn' : 'kpi-apagado' }}">
                <div class="kpi-label"><i class="bi bi-building-slash"></i> Sin empresa</div>
                <div class="kpi-valor">{{ $resumen['sinEmpresa'] }}</div>
                <div class="kpi-pie">No pueden iniciar sesión</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi {{ $resumen['sinRol'] > 0 ? 'kpi-warn' : 'kpi-apagado' }}">
                <div class="kpi-label"><i class="bi bi-shield-slash"></i> Sin rol</div>
                <div class="kpi-valor">{{ $resumen['sinRol'] }}</div>
                <div class="kpi-pie">Entran y no ven nada</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <a href="{{ route('administracion.roles.index') }}" class="kpi kpi-apagado text-decoration-none">
                <div class="kpi-label"><i class="bi bi-sliders"></i> Roles</div>
                <div class="kpi-valor">{{ $roles->count() }}</div>
                <div class="kpi-pie">Ver qué puede hacer cada uno</div>
            </a>
        </div>

    </div>

    {{-- ───── FILTROS ───── --}}
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">

                <div class="col-12 col-md-5">
                    <label class="form-label small text-secondary mb-1">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text"
                               class="form-control"
                               placeholder="Nombre, correo o teléfono"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label small text-secondary mb-1">Rol</label>
                    <select class="form-select" wire:model.live="rol">
                        <option value="">Todos</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->name }}">
                                {{ \App\Livewire\Users\Form::ETIQUETAS[$r->name]['label'] ?? $r->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small text-secondary mb-1">Estado</label>
                    <select class="form-select" wire:model.live="estado">
                        <option value="">Todos</option>
                        <option value="activos">Activos</option>
                        <option value="inactivos">Desactivados</option>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <button class="btn btn-outline-secondary w-100" wire:click="limpiarFiltros">
                        <i class="bi bi-x-lg me-1"></i> Limpiar
                    </button>
                </div>

            </div>
        </div>
    </div>

    {{-- ───── LA TABLA ───── --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th>Persona</th>
                        <th>Rol</th>
                        <th>Empresas</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                @forelse ($usuarios as $usuario)

                    <tr class="{{ $usuario->is_active ? '' : 'opacity-50' }}">

                        <td>
                            <div class="fw-semibold">
                                {{ $usuario->name }}

                                @if ($usuario->id === auth()->id())
                                    <span class="badge bg-secondary-subtle text-secondary ms-1">usted</span>
                                @endif
                            </div>
                            <small class="text-secondary d-block">{{ $usuario->email }}</small>
                            @if ($usuario->phone)
                                <small class="text-secondary">{{ $usuario->phone }}</small>
                            @endif
                        </td>

                        <td>
                            @forelse ($usuario->roles as $rolDelUsuario)
                                <span class="badge bg-primary-subtle text-primary">
                                    {{ \App\Livewire\Users\Form::ETIQUETAS[$rolDelUsuario->name]['label'] ?? $rolDelUsuario->name }}
                                </span>
                            @empty
                                <span class="badge bg-warning-subtle text-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i> Sin rol
                                </span>
                            @endforelse
                        </td>

                        <td>
                            @forelse ($usuario->companies as $empresa)
                                <span class="badge bg-light text-dark border me-1">
                                    {{ $empresa->code }}
                                    @if ($empresa->pivot->is_default)
                                        <i class="bi bi-star-fill text-warning ms-1" title="Abre con esta"></i>
                                    @endif
                                </span>
                            @empty
                                <span class="badge bg-warning-subtle text-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i> Sin empresa
                                </span>
                            @endforelse
                        </td>

                        <td class="text-center">
                            @if ($usuario->is_active)
                                <span class="badge bg-success-subtle text-success">Activo</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Desactivado</span>
                            @endif
                        </td>

                        <td class="text-end">

                            {{--
                                La confirmación es en línea y no un modal a
                                propósito: desactivar a alguien lo echa del
                                sistema en su siguiente clic, y eso merece
                                un segundo de fricción y una frase que diga
                                exactamente qué va a pasar.
                            --}}
                            @if ($porCambiar === $usuario->id)

                                <div class="d-inline-flex align-items-center gap-2">
                                    <small class="text-secondary">
                                        {{ $usuario->is_active ? '¿Desactivar?' : '¿Activar?' }}
                                    </small>
                                    <button class="btn btn-sm btn-danger" wire:click="cambiarEstado">
                                        Sí
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" wire:click="cancelarCambio">
                                        No
                                    </button>
                                </div>

                            @else

                                @can('users.update')
                                    <a href="{{ route('administracion.usuarios.edit', $usuario) }}"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <button class="btn btn-sm btn-outline-{{ $usuario->is_active ? 'danger' : 'success' }}"
                                            wire:click="pedirCambio({{ $usuario->id }})"
                                            title="{{ $usuario->is_active ? 'Desactivar' : 'Activar' }}">
                                        <i class="bi bi-{{ $usuario->is_active ? 'person-dash' : 'person-check' }}"></i>
                                    </button>
                                @endcan

                            @endif

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="5" class="text-center py-5 text-secondary">
                            <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                            No hay usuarios que coincidan con el filtro.
                        </td>
                    </tr>

                @endforelse
                </tbody>

            </table>
        </div>

        @if ($usuarios->hasPages())
            <div class="card-footer bg-white">
                {{ $usuarios->links() }}
            </div>
        @endif

    </div>

</div>
