{{--
    ═══════════════════════════════════════════════════════════════════════
    PROVEEDORES
    ═══════════════════════════════════════════════════════════════════════

    Mismo diseño que los demás listados.

    SIN DATOS no es una estadística: son proveedores sin teléfono ni
    correo, a los que no hay cómo pedirles nada sin buscar en una
    libreta.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Proveedores</h4>
            <small class="text-secondary">
                A quién le compramos contenedores, piezas y servicios.
            </small>
        </div>


            {{--
                IMPORTAR

                Todavía no hace nada, y está a propósito: es para poder
                enseñar en la reunión que el sistema va a poder tragarse el
                Excel en vez de que alguien teclee cientos de fichas.

                Se construye cuando se decida el formato exacto del archivo
                de origen. Prometerlo en pantalla antes de eso sería
                prometer algo que todavía no se sabe cómo va a ser.
            --}}
            <button type="button" class="btn btn-outline-secondary" disabled
                    title="Disponible en la próxima fase">
                <i class="bi bi-upload me-1"></i> Importar
            </button>

        @can('suppliers.create')
            <a href="{{ route('compras.proveedores.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nuevo proveedor
            </a>
        @endcan
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ───── CONTADORES ───── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <button type="button" class="kpi kpi-info" wire:click="limpiarFiltros">
                <span class="kpi-icono"><i class="bi bi-shop"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Activos</span>
                    <span class="kpi-valor d-block">{{ $resumen['activos'] }}</span>
                    <span class="kpi-pie d-block">Disponibles para comprar</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['sinDatos'] > 0 ? 'kpi-bad' : 'kpi-apagado' }} {{ $marca === 'sin_datos' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('sin_datos')">
                <span class="kpi-icono"><i class="bi bi-telephone-x"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Sin forma de contacto</span>
                    <span class="kpi-valor d-block">{{ $resumen['sinDatos'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['sinDatos'] > 0 ? 'Ni teléfono ni correo' : 'Todos localizables' }}
                    </span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <a href="{{ route('compras.depositos.index') }}" class="kpi kpi-apagado text-decoration-none">
                <span class="kpi-icono"><i class="bi bi-building"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Depósitos</span>
                    <span class="kpi-valor d-block">{{ $resumen['depositos'] }}</span>
                    <span class="kpi-pie d-block">Patios donde nos guardan</span>
                </span>
            </a>
        </div>

    </div>

    {{-- ───── TABLA ───── --}}
    <div class="card">

        <div class="card-header">
            <div class="row g-2 align-items-center">

                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>
                        <input type="search" class="form-control"
                               placeholder="Nombre, número, contacto, teléfono o correo…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="tipo">
                        <option value="">Todo tipo</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="estado">
                        <option value="activos">Activos</option>
                        <option value="inactivos">Desactivados</option>
                        <option value="">Todos</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 text-end">
                    @if ($this->hayFiltros)
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
                            <th style="width: 110px;">Número</th>
                            <th>Proveedor</th>
                            <th>Contacto</th>
                            <th class="text-center">Compras</th>
                            <th class="text-center">Marcas</th>
                            <th class="text-end" style="width: 140px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse ($proveedores as $p)

                        <tr wire:key="prov-{{ $p->id }}"
                            class="fila-estado {{ $p->is_active ? 'fila-mute' : 'fila-warn opacity-50' }}">

                            <td><span class="doc-numero">{{ $p->supplier_number }}</span></td>

                            <td>
                                <div class="fw-medium">{{ $p->name }}</div>
                                <div class="small text-secondary">
                                    {{ $p->type?->label() }}
                                    @if ($p->depots_count > 0)
                                        · {{ $p->depots_count }}
                                        {{ $p->depots_count === 1 ? 'depósito' : 'depósitos' }}
                                    @endif
                                </div>
                            </td>

                            <td>
                                @if ($p->contact_name)
                                    <div class="small">{{ $p->contact_name }}</div>
                                @endif
                                @if ($p->phone)
                                    <div class="small text-secondary">
                                        <i class="bi bi-telephone me-1"></i>{{ $p->phone }}
                                    </div>
                                @endif
                                @if ($p->email)
                                    <div class="small text-secondary">
                                        <i class="bi bi-envelope me-1"></i>{{ $p->email }}
                                    </div>
                                @endif
                                @if (! $p->phone && ! $p->email)
                                    <small class="text-danger">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Sin forma de contacto
                                    </small>
                                @endif
                            </td>

                            <td class="text-center">{{ $p->purchases_count }}</td>

                            <td class="text-center">
                                @unless ($p->is_active)
                                    <span class="badge bg-secondary-subtle text-secondary">Desactivado</span>
                                @endunless
                            </td>

                            <td class="text-end">
                                <div class="acciones">
                                    @if ($porCambiar === $p->id)
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <small class="text-secondary">
                                                {{ $p->is_active ? '¿Desactivar?' : '¿Activar?' }}
                                            </small>
                                            <button class="btn btn-sm btn-danger" wire:click="cambiarEstado">Sí</button>
                                            <button class="btn btn-sm btn-outline-secondary" wire:click="cancelarCambio">No</button>
                                        </div>
                                    @else
                                        @can('suppliers.update')
                                            <a href="{{ route('compras.proveedores.edit', $p) }}"
                                               class="acc acc-editar" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button class="acc {{ $p->is_active ? 'acc-borrar' : 'acc-ver' }} acc-separado"
                                                    wire:click="pedirCambio({{ $p->id }})"
                                                    title="{{ $p->is_active ? 'Desactivar' : 'Activar' }}">
                                                <i class="bi bi-{{ $p->is_active ? 'slash-circle' : 'check-circle' }}"></i>
                                            </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="vacio">
                                    <i class="bi bi-shop"></i>
                                    @if ($this->hayFiltros)
                                        No hay proveedores que coincidan con el filtro.
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-secondary" wire:click="limpiarFiltros">
                                                Quitar los filtros
                                            </button>
                                        </div>
                                    @else
                                        Todavía no hay proveedores.
                                        @can('suppliers.create')
                                            <div class="mt-2">
                                                <a href="{{ route('compras.proveedores.create') }}"
                                                   class="btn btn-sm btn-primary">Registrar el primero</a>
                                            </div>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                </table>
            </div>
        </div>

        @if ($proveedores->hasPages())
            <div class="card-footer">{{ $proveedores->links() }}</div>
        @endif

    </div>

</div>
