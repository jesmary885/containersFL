{{--
    ═══════════════════════════════════════════════════════════════════════
    TRABAJADORES
    ═══════════════════════════════════════════════════════════════════════

    La gente de la empresa. No son los usuarios del sistema.

    Miguelito cobra comisiones desde 2024 y probablemente no ha abierto un
    sistema en su vida. Hasta ahora no existía en ninguna parte: su nombre
    estaba suelto dentro de una celda del Excel.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Trabajadores</h4>
            <small class="text-secondary">
                Registro de personal de la empresa, con su rol.
            </small>
        </div>

        @can('users.create')
            <a href="{{ route('sistema.trabajadores.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> Nuevo trabajador
            </a>
        @endcan
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-light border py-2 small">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Un trabajador no es un usuario.</strong> Un usuario entra con contraseña; un
        trabajador vende, maneja o limpia. Si además necesita entrar, se le crea su usuario
        aparte en <a href="{{ route('administracion.usuarios.index') }}">Usuarios</a>.
    </div>

    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-4">
            <div class="kpi kpi-info">
                <span class="kpi-icono"><i class="bi bi-people"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Activos</span>
                    <span class="kpi-valor d-block">{{ $resumen['activos'] }}</span>
                    <span class="kpi-pie d-block">Trabajando ahora</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-4">
            <div class="kpi kpi-ok">
                <span class="kpi-icono"><i class="bi bi-percent"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Pueden vender</span>
                    <span class="kpi-valor d-block">{{ $resumen['vendedores'] }}</span>
                    <span class="kpi-pie d-block">Salen al facturar</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-4">
            <div class="kpi {{ $resumen['sinTelefono'] > 0 ? 'kpi-warn' : 'kpi-apagado' }}">
                <span class="kpi-icono"><i class="bi bi-telephone-x"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Sin teléfono</span>
                    <span class="kpi-valor d-block">{{ $resumen['sinTelefono'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['sinTelefono'] > 0
                            ? 'No hay cómo avisarles del pago' : 'Todos localizables' }}
                    </span>
                </span>
            </div>
        </div>

    </div>

    <div class="card">

        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>
                        <input type="search" class="form-control"
                               placeholder="Nombre, apellido o teléfono…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="rol">
                        <option value="">Todos</option>
                        @foreach ($roles as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="estado">
                        <option value="activos">Activos</option>
                        <option value="inactivos">Inactivos</option>
                        <option value="">Todos</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 text-end">
                    @if ($buscar || $rol || $estado !== 'activos')
                        <button class="btn btn-outline-secondary w-100" wire:click="limpiarFiltros">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-compacta">

                    <thead>
                        <tr>
                            <th>Trabajador</th>
                            <th>Rol</th>
                            <th>Contacto</th>
                            <th>Empresa</th>
                            <th class="text-end">Comisión</th>
                            <th class="text-center">Ventas</th>
                            <th class="text-end" style="width: 120px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse ($trabajadores as $t)

                        <tr wire:key="emp-{{ $t->id }}"
                            class="fila-estado {{ $t->is_active ? 'fila-mute' : 'fila-warn opacity-50' }}">

                            <td>
                                <div class="fw-medium">{{ $t->name }}</div>
                            </td>

                            <td>
                                <span class="badge bg-light text-dark border">{{ $t->role_label }}</span>
                            </td>

                            <td class="small">
                                @if ($t->phone)
                                    <div><i class="bi bi-telephone me-1 text-secondary"></i>{{ $t->phone }}</div>
                                @endif
                                @if ($t->email)
                                    <div class="text-secondary">
                                        <i class="bi bi-envelope me-1"></i>{{ $t->email }}
                                    </div>
                                @endif
                                @if (! $t->phone && ! $t->email)
                                    <span class="text-warning">
                                        <i class="bi bi-exclamation-triangle"></i> Sin datos
                                    </span>
                                @endif
                            </td>

                            <td class="small">
                                {{-- Sin empresa quiere decir que trabaja para las dos --}}
                                {{ $t->company?->code ?? 'Ambas' }}
                            </td>

                            <td class="text-end small">
                                @if ($t->default_commission_amount !== null)
                                    <span class="monto">
                                        ${{ number_format((float) $t->default_commission_amount, 2) }}
                                    </span>
                                    <div class="text-secondary" style="font-size: .72rem;">por venta</div>
                                @elseif ($t->default_commission_percent !== null)
                                    {{ rtrim(rtrim(number_format((float) $t->default_commission_percent, 2), '0'), '.') }}%
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>

                            <td class="text-center">{{ $t->sales_count }}</td>

                            <td class="text-end">
                                <div class="acciones">
                                    @if ($porCambiar === $t->id)
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <small class="text-secondary">
                                                {{ $t->is_active ? '¿Dar de baja?' : '¿Reactivar?' }}
                                            </small>
                                            <button class="btn btn-sm btn-danger" wire:click="cambiarEstado">Sí</button>
                                            <button class="btn btn-sm btn-outline-secondary" wire:click="cancelarCambio">No</button>
                                        </div>
                                    @else
                                        @can('users.update')
                                            <a href="{{ route('sistema.trabajadores.edit', $t) }}"
                                               class="acc acc-editar" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button class="acc {{ $t->is_active ? 'acc-borrar' : 'acc-ver' }} acc-separado"
                                                    wire:click="pedirCambio({{ $t->id }})"
                                                    title="{{ $t->is_active ? 'Dar de baja' : 'Reactivar' }}">
                                                <i class="bi bi-{{ $t->is_active ? 'person-dash' : 'person-check' }}"></i>
                                            </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="vacio">
                                    <i class="bi bi-people"></i>
                                    @if ($buscar || $rol || $estado !== 'activos')
                                        Nadie coincide con el filtro.
                                    @else
                                        Todavía no hay trabajadores cargados.
                                        <div class="small text-secondary mt-1">
                                            Corra <code>php artisan db:seed --class=EmployeeSeeder</code>
                                            para traer los cinco vendedores del Excel.
                                        </div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                </table>
            </div>
        </div>

        @if ($trabajadores->hasPages())
            <div class="card-footer">{{ $trabajadores->links() }}</div>
        @endif

    </div>

</div>
