{{--
    ═══════════════════════════════════════════════════════════════════════
    COMPRAS Y RELEASES
    ═══════════════════════════════════════════════════════════════════════

    El contador que manda es POR TRAER: cuántas unidades están pagadas y
    todavía no llegaron a la yarda.

    Es la diferencia entre lo que dice el papel y lo que hay en el patio,
    y es exactamente el número que el Excel no sabe calcular.

    La barra de cada fila dice cuánto de la compra ya llegó, sin leer.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Compras y releases</h4>
            <small class="text-secondary">
                Lo comprado y lo recibido son dos números distintos. Aquí se ven los dos.
            </small>
        </div>

        @can('purchases.create')
            <a href="{{ route('compras.compras.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nueva compra
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
            <button type="button"
                    class="kpi {{ $resumen['porTraer'] > 0 ? 'kpi-warn' : 'kpi-apagado' }} {{ $marca === 'pendientes' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('pendientes')"
                    title="Unidades pagadas que todavía no llegaron a la yarda">
                <span class="kpi-icono"><i class="bi bi-box-arrow-in-down"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Por traer</span>
                    <span class="kpi-valor d-block">{{ $resumen['porTraer'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['pendientes'] }} compras sin cerrar
                    </span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['porVencer'] > 0 ? 'kpi-warn' : 'kpi-apagado' }} {{ $marca === 'por_vencer' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('por_vencer')">
                <span class="kpi-icono"><i class="bi bi-bell"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Plazo por acabarse</span>
                    <span class="kpi-valor d-block">{{ $resumen['porVencer'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['porVencer'] > 0 ? 'Ir a buscarlos ya' : 'Nada urgente' }}
                    </span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['vencidos'] > 0 ? 'kpi-bad' : 'kpi-apagado' }} {{ $marca === 'vencidos' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('vencidos')"
                    title="El depósito ya está cobrando almacenaje por día">
                <span class="kpi-icono"><i class="bi bi-exclamation-octagon"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Plazo vencido</span>
                    <span class="kpi-valor d-block">{{ $resumen['vencidos'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['vencidos'] > 0 ? 'Cobrando almacenaje cada día' : 'Ninguno' }}
                    </span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-info">
                <span class="kpi-icono"><i class="bi bi-cart3"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Comprado del mes</span>
                    <span class="kpi-valor d-block">${{ number_format($resumen['delMes'], 2) }}</span>
                    <span class="kpi-pie d-block">{{ now()->translatedFormat('F Y') }}</span>
                </span>
            </div>
        </div>

    </div>

    <div class="card">

        <div class="card-header">
            <div class="row g-2 align-items-center">

                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>
                        <input type="search" class="form-control"
                               placeholder="Número, referencia del release o proveedor…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select class="form-select" wire:model.live="estado">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="tipo">
                        <option value="">Todo tipo</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
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
                            <th style="width: 120px;">Número</th>
                            <th>Proveedor</th>
                            <th>Dónde están</th>
                            <th class="text-center" style="width: 170px;">Traído</th>
                            <th>Plazo de retiro</th>
                            <th class="text-end">Total</th>
                            <th class="text-end" style="width: 110px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse ($compras as $c)

                        @php
                            $compradas = (int) ($c->compradas ?? 0);
                            $recibidas = (int) ($c->recibidas ?? 0);
                            $faltan    = max(0, $compradas - $recibidas);

                            $porcentaje = $compradas > 0
                                ? (int) round($recibidas / $compradas * 100)
                                : 0;

                            $diasTarde = $c->overdue_days;

                            /*
                             | La franja: rojo si el plazo ya pasó, ámbar si
                             | falta traer algo, verde si está todo adentro.
                             */
                            $franja = match (true) {
                                $diasTarde > 0 => 'fila-bad',
                                $faltan > 0    => 'fila-warn',
                                default        => 'fila-ok',
                            };
                        @endphp

                        <tr wire:key="comp-{{ $c->id }}" class="fila-estado {{ $franja }}">

                            <td>
                                <a href="{{ route('compras.compras.show', $c) }}"
                                   class="doc-numero">{{ $c->purchase_number }}</a>
                                <div class="small text-secondary">
                                    {{ $c->type?->label() }}
                                    @if ($c->reference) · {{ $c->reference }} @endif
                                </div>
                            </td>

                            <td>
                                <div class="fw-medium">{{ $c->supplier?->name ?? '—' }}</div>
                                <div class="small text-secondary">
                                    {{ $c->purchase_date?->format('d/m/Y') }}
                                </div>
                            </td>

                            <td class="small">
                                {{ $c->depot?->name ?? 'Entrega directa' }}
                            </td>

                            {{--
                                LA BARRA DE LO TRAÍDO

                                Dice de un vistazo cuánto de esa compra ya está
                                en la yarda. "3 de 7" se lee más rápido que
                                restar dos columnas.
                            --}}
                            <td>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="fw-medium">{{ $recibidas }} de {{ $compradas }}</span>
                                    @if ($faltan > 0)
                                        <span class="text-warning-emphasis">faltan {{ $faltan }}</span>
                                    @else
                                        <span class="text-success">completa</span>
                                    @endif
                                </div>

                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ $faltan > 0 ? 'bg-warning' : 'bg-success' }}"
                                         style="width: {{ $porcentaje }}%"></div>
                                </div>
                            </td>

                            <td class="small">
                                @if ($c->pickup_deadline_at)
                                    {{ $c->pickup_deadline_at->format('d/m/Y') }}

                                    @if ($diasTarde > 0)
                                        <div class="text-danger fw-medium">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            {{ $diasTarde }} días de más
                                        </div>
                                    @elseif ($faltan > 0)
                                        @php
                                            $quedan = (int) now()->startOfDay()
                                                ->diffInDays($c->pickup_deadline_at->startOfDay());
                                        @endphp
                                        <div class="text-secondary">
                                            @if ($quedan === 0)
                                                Se acaba hoy
                                            @else
                                                Quedan {{ $quedan }} días
                                            @endif
                                        </div>
                                    @endif
                                @else
                                    <span class="text-secondary">Sin plazo</span>
                                @endif
                            </td>

                            <td class="text-end monto">${{ number_format((float) $c->total, 2) }}</td>

                            <td class="text-end">
                                <div class="acciones">
                                    <a href="{{ route('compras.compras.show', $c) }}"
                                       class="acc acc-ver" title="Ver y recibir unidades">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    @can('purchases.update')
                                        <a href="{{ route('compras.compras.edit', $c) }}"
                                           class="acc acc-editar" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                </div>
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="vacio">
                                    <i class="bi bi-basket"></i>
                                    @if ($this->hayFiltros)
                                        No hay compras que coincidan con el filtro.
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-secondary"
                                                    wire:click="limpiarFiltros">Quitar los filtros</button>
                                        </div>
                                    @else
                                        Todavía no hay compras con {{ $empresa?->code }}.
                                        @can('purchases.create')
                                            <div class="mt-2">
                                                <a href="{{ route('compras.compras.create') }}"
                                                   class="btn btn-sm btn-primary">Registrar la primera</a>
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

        @if ($compras->hasPages())
            <div class="card-footer">{{ $compras->links() }}</div>
        @endif

    </div>

</div>
