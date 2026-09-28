{{--
    ═══════════════════════════════════════════════════════════════════════
    REPORTES
    ═══════════════════════════════════════════════════════════════════════

    Cinco reportes, un selector y un período. Cada uno contesta una
    pregunta que alguien hace de verdad.

    Se imprimen con el navegador: las reglas de @media print del final
    esconden el menú y los filtros y dejan solo la tabla.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-imprimir">
        <div>
            <h4 class="mb-0 fw-semibold">Reportes</h4>
            <small class="text-secondary">
                {{ $empresa?->name }} ·
                {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} —
                {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
            </small>
        </div>

        <button class="btn btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Imprimir
        </button>
    </div>

    {{-- ── SELECTOR Y PERÍODO ── --}}
    <div class="card mb-3 no-imprimir">
        <div class="card-body">

            <ul class="nav nav-pills mb-3 flex-wrap gap-1">
                @foreach ([
                    'resultado'  => ['Ingresos y gastos', 'bi-graph-up'],
                    'impuestos'  => ['Impuestos',         'bi-percent'],
                    'cobrar'     => ['Por cobrar',        'bi-inbox'],
                    'pagar'      => ['Por pagar',         'bi-outbox'],
                    'inventario' => ['Inventario',        'bi-box-seam'],
                ] as $clave => [$titulo, $icono])
                    <li class="nav-item">
                        <a href="#" wire:click.prevent="$set('reporte', '{{ $clave }}')"
                           class="nav-link {{ $reporte === $clave ? 'active' : '' }}">
                            <i class="bi {{ $icono }} me-1"></i> {{ $titulo }}
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($reporte !== 'cobrar' && $reporte !== 'pagar' && $reporte !== 'inventario')
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label small">Desde</label>
                        <input type="date" class="form-control form-control-sm" wire:model.live="desde">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small">Hasta</label>
                        <input type="date" class="form-control form-control-sm" wire:model.live="hasta">
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="d-flex flex-wrap gap-1">
                            <button class="btn btn-sm btn-outline-secondary" wire:click="periodo('mes')">Este mes</button>
                            <button class="btn btn-sm btn-outline-secondary" wire:click="periodo('mes_pasado')">Mes pasado</button>
                            <button class="btn btn-sm btn-outline-secondary" wire:click="periodo('trimestre')">Trimestre</button>
                            <button class="btn btn-sm btn-outline-secondary" wire:click="periodo('trim_pasado')">Trim. pasado</button>
                            <button class="btn btn-sm btn-outline-secondary" wire:click="periodo('anio')">Año</button>
                        </div>
                    </div>
                </div>
            @else
                <div class="small text-secondary">
                    Este reporte no usa período: una deuda vieja sigue siendo deuda,
                    y el inventario es lo que hay hoy.
                </div>
            @endif

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════
         1 · INGRESOS Y GASTOS
    ═══════════════════════════════════════════════════ --}}
    @if ($reporte === 'resultado')

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Facturado</div>
                        <div class="fs-4 fw-semibold">${{ number_format($datos['facturado'], 2) }}</div>
                        <div class="small text-secondary">Lo que se vendió</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Cobrado</div>
                        <div class="fs-4 fw-semibold">${{ number_format($datos['cobrado'], 2) }}</div>
                        <div class="small text-secondary">Lo que entró</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Gastado</div>
                        <div class="fs-4 fw-semibold text-danger">${{ number_format($datos['gastado'], 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100 {{ $datos['resultado'] >= 0 ? 'border-success' : 'border-danger' }}">
                    <div class="card-body">
                        <div class="text-secondary small">Resultado</div>
                        <div class="fs-4 fw-semibold {{ $datos['resultado'] >= 0 ? 'text-success' : 'text-danger' }}">
                            ${{ number_format($datos['resultado'], 2) }}
                        </div>
                        <div class="small text-secondary">Facturado − gastado</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">En qué se gastó</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-end">Cuántos</th>
                                    <th class="text-end">Importe</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse ($datos['porCategoria'] as $c)
                                <tr>
                                    <td>{{ $c->category?->name ?? 'Sin categoría' }}</td>
                                    <td class="text-end">{{ $c->cuantos }}</td>
                                    <td class="text-end">${{ number_format((float) $c->total, 2) }}</td>
                                    <td class="text-end text-secondary">
                                        {{ $datos['gastado'] > 0
                                           ? number_format((float) $c->total / $datos['gastado'] * 100, 1)
                                           : '0.0' }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-secondary py-3">
                                        No hay gastos en el período.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0 fw-semibold">Los viajes del período</h6>
                    </div>
                    <div class="card-body">
                        @php $v = $datos['viajes']; @endphp

                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Viajes completados</span>
                            <span>{{ $v?->cuantos ?? 0 }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Se cobró</span>
                            <span>${{ number_format((float) ($v?->cobrado ?? 0), 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Pagado a choferes</span>
                            <span class="text-danger">− ${{ number_format((float) ($v?->choferes ?? 0), 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Transportistas de fuera</span>
                            <span class="text-danger">− ${{ number_format((float) ($v?->terceros ?? 0), 2) }}</span>
                        </div>

                        <hr>

                        @php
                            $margenViajes = (float) ($v?->cobrado ?? 0)
                                          - (float) ($v?->choferes ?? 0)
                                          - (float) ($v?->terceros ?? 0);
                        @endphp

                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">Quedó del transporte</span>
                            <span class="fw-semibold {{ $margenViajes >= 0 ? 'text-success' : 'text-danger' }}">
                                ${{ number_format($margenViajes, 2) }}
                            </span>
                        </div>

                        <div class="small text-secondary mt-2">
                            Sin contar combustible ni peajes, que están arriba en gastos.
                        </div>
                    </div>
                </div>
            </div>
        </div>

    {{-- ═══════════════════════════════════════════════════
         2 · IMPUESTOS
    ═══════════════════════════════════════════════════ --}}
    @elseif ($reporte === 'impuestos')

        <div class="alert alert-light border no-imprimir">
            <i class="bi bi-info-circle me-1"></i>
            Cuenta las facturas <strong>emitidas</strong> en el período, no las cobradas.
            En Florida el sales tax se devenga con la factura: si se emitió en marzo y
            el cliente paga en mayo, el impuesto es de marzo.
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Base imponible</div>
                        <div class="fs-4 fw-semibold">${{ number_format($datos['baseTotal'], 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="card h-100 border-primary">
                    <div class="card-body">
                        <div class="text-secondary small">Impuesto a declarar</div>
                        <div class="fs-4 fw-semibold">${{ number_format($datos['impuestoTotal'], 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Ventas exentas</div>
                        <div class="fs-4 fw-semibold">${{ number_format($datos['exento'], 2) }}</div>
                        <div class="small text-secondary">
                            {{ $datos['exentoCuantas'] }} facturas · deben tener certificado
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Mes a mes</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th class="text-end">Facturas</th>
                            <th class="text-end">Base</th>
                            <th class="text-end">Impuesto</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($datos['porMes'] as $m)
                        <tr>
                            <td>{{ $m->mes }}</td>
                            <td class="text-end">{{ $m->facturas }}</td>
                            <td class="text-end">${{ number_format((float) $m->base, 2) }}</td>
                            <td class="text-end fw-semibold">${{ number_format((float) $m->impuesto, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-3">
                                No hay facturas emitidas en el período.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ═══════════════════════════════════════════════════
         3 · POR COBRAR
    ═══════════════════════════════════════════════════ --}}
    @elseif ($reporte === 'cobrar')

        <div class="row g-2 mb-3">
            @foreach ([
                'al_dia' => ['Al día',        'secondary'],
                'd30'    => ['1 – 30 días',   'warning'],
                'd60'    => ['31 – 60 días',  'warning'],
                'd90'    => ['61 – 90 días',  'danger'],
                'mas'    => ['Más de 90',     'danger'],
            ] as $clave => [$titulo, $color])
                <div class="col-6 col-md">
                    <div class="card h-100 {{ $datos['tramos'][$clave] > 0 && $clave !== 'al_dia' ? 'border-'.$color : '' }}">
                        <div class="card-body py-2">
                            <div class="text-secondary small">{{ $titulo }}</div>
                            <div class="fs-5 fw-semibold">
                                ${{ number_format($datos['tramos'][$clave], 0) }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="alert alert-{{ $datos['total'] > 0 ? 'primary' : 'success' }}">
            Nos deben <strong>${{ number_format($datos['total'], 2) }}</strong> en total.
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Quién debe</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th class="text-end">Facturas</th>
                            <th class="text-end">Debe</th>
                            <th class="text-end">De eso, vencido</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($datos['porCliente'] as $nombre => $d)
                        <tr>
                            <td>{{ $nombre }}</td>
                            <td class="text-end">{{ $d['facturas'] }}</td>
                            <td class="text-end fw-semibold">${{ number_format($d['total'], 2) }}</td>
                            <td class="text-end {{ $d['vencido'] > 0 ? 'text-danger fw-semibold' : 'text-secondary' }}">
                                ${{ number_format($d['vencido'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-3">
                                No hay nada por cobrar. Todas las facturas están pagadas.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ═══════════════════════════════════════════════════
         4 · POR PAGAR
    ═══════════════════════════════════════════════════ --}}
    @elseif ($reporte === 'pagar')

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Debemos en total</div>
                        <div class="fs-4 fw-semibold">${{ number_format($datos['total'], 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="card h-100 {{ $datos['vencido'] > 0 ? 'border-danger' : '' }}">
                    <div class="card-body">
                        <div class="text-secondary small">Ya vencido</div>
                        <div class="fs-4 fw-semibold text-danger">${{ number_format($datos['vencido'], 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Vence esta semana</div>
                        <div class="fs-4 fw-semibold">${{ number_format($datos['estaSemana'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Lo que vence primero</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Gasto</th>
                            <th>A quién</th>
                            <th>Categoría</th>
                            <th>Vence</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($datos['gastos'] as $g)
                        <tr>
                            <td>
                                {{ $g->expense_number }}
                                <div class="small text-secondary">{{ $g->description }}</div>
                            </td>
                            <td>{{ $g->supplier?->name ?? $g->payee_name ?? '—' }}</td>
                            <td>{{ $g->category?->name ?? '—' }}</td>
                            <td class="{{ $g->due_date && $g->due_date->isPast() ? 'text-danger fw-semibold' : '' }}">
                                {{ $g->due_date?->format('d/m/Y') ?? 'Sin fecha' }}
                            </td>
                            <td class="text-end fw-semibold">${{ number_format((float) $g->balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-3">
                                No hay nada por pagar.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ═══════════════════════════════════════════════════
         5 · INVENTARIO
    ═══════════════════════════════════════════════════ --}}
    @elseif ($reporte === 'inventario')

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Unidades</div>
                        <div class="fs-4 fw-semibold">{{ $datos['total'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Disponibles</div>
                        <div class="fs-4 fw-semibold">{{ $datos['disponibles'] }}</div>
                        <div class="small text-secondary">Sin vender ni rentar</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100 {{ $datos['sinPrecio'] > 0 ? 'border-warning' : '' }}">
                    <div class="card-body">
                        <div class="text-secondary small">Sin precio</div>
                        <div class="fs-4 fw-semibold">{{ $datos['sinPrecio'] }}</div>
                        <div class="small text-secondary">No se pueden cotizar</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-secondary small">Valor en yarda</div>
                        <div class="fs-4 fw-semibold">${{ number_format($datos['valorCosto'], 0) }}</div>
                        <div class="small text-secondary">A costo de adquisición</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0 fw-semibold">Por estado</h6></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                            @forelse ($datos['porEstado'] as $e)
                                <tr>
                                    <td>{{ $e->status?->label() ?? $e->status }}</td>
                                    <td class="text-end fw-semibold">{{ $e->cuantos }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-secondary py-3">Sin unidades.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0 fw-semibold">Por medida</h6></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                            @forelse ($datos['porMedida'] as $m)
                                <tr>
                                    <td>{{ $m->size?->name ?? 'Sin medida' }}</td>
                                    <td class="text-end fw-semibold">{{ $m->cuantos }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-secondary py-3">Sin unidades.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if ($datos['porReparar'] > 0)
            <div class="alert alert-warning mt-3">
                <i class="bi bi-tools me-1"></i>
                Hay <strong>{{ $datos['porReparar'] }}</strong> unidades marcadas por reparar.
            </div>
        @endif

    @endif

    {{-- ─────────────────────────────────────────────────────────────
         IMPRESIÓN

         Se esconde el menú, la cabecera y los filtros, y se deja la
         tabla. Es lo que se lleva a la reunión o al contador.
    ───────────────────────────────────────────────────────────── --}}
    <style>
        @media print {
            .app-sidebar,
            .app-header,
            .app-footer,
            .no-imprimir {
                display: none !important;
            }

            .app-main,
            .app-content,
            .app-wrapper {
                margin: 0 !important;
                padding: 0 !important;
            }

            .card {
                border: 1px solid #dee2e6 !important;
                break-inside: avoid;
            }
        }
    </style>

</div>
