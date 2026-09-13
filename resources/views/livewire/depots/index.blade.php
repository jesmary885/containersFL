{{--
    ═══════════════════════════════════════════════════════════════════════
    DEPÓSITOS
    ═══════════════════════════════════════════════════════════════════════

    La libreta de los patios ajenos donde nos guardan los contenedores.

    La columna que importa es el costo del pickup: es lo que entra en el
    costo de cada unidad que se trae de ahí, y es lo que nadie recuerda
    de memoria.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Depósitos</h4>
            <small class="text-secondary">
                Los patios donde el proveedor nos guarda los contenedores hasta que vamos a buscarlos.
            </small>
        </div>

        @can('depots.create')
            <a href="{{ route('compras.depositos.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nuevo depósito
            </a>
        @endcan
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="kpi kpi-info">
                <span class="kpi-icono"><i class="bi bi-building"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Activos</span>
                    <span class="kpi-valor d-block">{{ $resumen['activos'] }}</span>
                    <span class="kpi-pie d-block">Disponibles al comprar</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi {{ $resumen['sinCosto'] > 0 ? 'kpi-bad' : 'kpi-apagado' }}">
                <span class="kpi-icono"><i class="bi bi-question-circle"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Sin costo de pickup</span>
                    <span class="kpi-valor d-block">{{ $resumen['sinCosto'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['sinCosto'] > 0 ? 'Hay que inventarlo al comprar' : 'Todos con costo' }}
                    </span>
                </span>
            </div>
        </div>
    </div>

    <div class="card">

        <div class="card-header">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>
                        <input type="search" class="form-control"
                               placeholder="Nombre, código, ciudad o contacto…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select class="form-select" wire:model.live="estado">
                        <option value="activos">Activos</option>
                        <option value="inactivos">Desactivados</option>
                        <option value="">Todos</option>
                    </select>
                </div>

                <div class="col-6 col-md-2 text-end">
                    @if ($buscar || $estado !== 'activos')
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
                            <th>Depósito</th>
                            <th>Dónde está</th>
                            <th>Contacto</th>
                            <th class="text-end">Pickup</th>
                            <th class="text-center">Días libres</th>
                            <th class="text-end">Por día extra</th>
                            <th class="text-end" style="width: 90px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse ($depositos as $d)

                        <tr wire:key="dep-{{ $d->id }}"
                            class="fila-estado {{ $d->default_pickup_fee === null ? 'fila-bad' : 'fila-mute' }} {{ $d->is_active ? '' : 'opacity-50' }}">

                            <td>
                                <div class="fw-medium">{{ $d->name }}</div>
                                <div class="small text-secondary">
                                    @if ($d->code) {{ $d->code }} · @endif
                                    {{ $d->supplier?->name ?? 'Sin proveedor asociado' }}
                                </div>
                            </td>

                            <td class="small">
                                {{ collect([$d->city, $d->state])->filter()->implode(', ') ?: '—' }}
                                @if ($d->default_miles)
                                    <div class="text-secondary">
                                        {{ rtrim(rtrim(number_format((float) $d->default_miles, 1), '0'), '.') }} millas a la yarda
                                    </div>
                                @endif
                            </td>

                            <td class="small">
                                @if ($d->contact_name)<div>{{ $d->contact_name }}</div>@endif
                                @if ($d->phone)
                                    <div class="text-secondary">
                                        <i class="bi bi-telephone me-1"></i>{{ $d->phone }}
                                    </div>
                                @endif
                                @if (! $d->contact_name && ! $d->phone)
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>

                            <td class="text-end monto">
                                @if ($d->default_pickup_fee !== null)
                                    ${{ number_format((float) $d->default_pickup_fee, 2) }}
                                @else
                                    <span class="text-danger small">Sin cargar</span>
                                @endif
                            </td>

                            <td class="text-center">
                                {{ $d->default_pickup_days ?? '—' }}
                            </td>

                            <td class="text-end monto">
                                @if ($d->daily_late_fee !== null)
                                    ${{ number_format((float) $d->daily_late_fee, 2) }}
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>

                            <td class="text-end">
                                @can('depots.update')
                                    <div class="acciones">
                                        <a href="{{ route('compras.depositos.edit', $d) }}"
                                           class="acc acc-editar" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                @endcan
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="vacio">
                                    <i class="bi bi-building"></i>
                                    @if ($buscar || $estado !== 'activos')
                                        No hay depósitos que coincidan.
                                    @else
                                        Todavía no hay depósitos registrados.
                                        <div class="small text-secondary mt-1">
                                            Sin ellos, el costo del pickup hay que escribirlo a mano
                                            en cada compra.
                                        </div>
                                        @can('depots.create')
                                            <div class="mt-2">
                                                <a href="{{ route('compras.depositos.create') }}"
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

        @if ($depositos->hasPages())
            <div class="card-footer">{{ $depositos->links() }}</div>
        @endif

    </div>

    <div class="form-text mt-2">
        <i class="bi bi-lightbulb me-1"></i>
        El <strong>pickup es costo nuestro</strong> y nunca se le cotiza al cliente. Él paga el
        delivery desde la yarda hasta su terreno; traer el contenedor al patio es nuestro problema.
    </div>

</div>
