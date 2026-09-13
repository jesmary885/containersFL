{{--
    ═══════════════════════════════════════════════════════════════════════
    LISTADO DE FACTURAS — con las dos formas de ver la tabla
    ═══════════════════════════════════════════════════════════════════════

    Mismo diseño que Presupuestos, Clientes y Contenedores: contadores con
    chip de color, selector de vista, filtros en la cabecera, acciones y
    estado vacío en partials compartidos.

    ── LOS CUATRO CONTADORES RESPONDEN PREGUNTAS DISTINTAS ──

    POR COBRAR   ¿cuánto me deben en total?
    VENCIDO      ¿cuánto de eso ya se pasó de fecha?
    FACTURAS     ¿en cuántas está repartido?
    DEL MES      ¿cuánto facturé este mes?

    El tercero importa más de lo que parece: $40,000 en una sola factura
    de un cliente bueno es una conversación. Los mismos $40,000 repartidos
    en treinta facturas de veinte clientes son un problema de cobranza.

    ── EL FILTRO DE PENDIENTES NACE ENCENDIDO ──

    Quien abre esta pantalla casi siempre viene a cobrar, no a repasar
    historia. Las pagadas están a un clic, pero no estorban de entrada.
--}}
<div x-data="{
        vista: (() => {
            try { return localStorage.getItem('vistaFacturas') || 'compacta' }
            catch (e) { return 'compacta' }
        })(),
        recordar(v) {
            this.vista = v;
            try { localStorage.setItem('vistaFacturas', v) } catch (e) {}
        },
     }">

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Facturación</h4>
            <small class="text-secondary">
                Lo que se le cobró al cliente. Casi siempre nace de un presupuesto aceptado.
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">

            <div class="selector-vista" title="Cómo ver la lista">
                <button type="button" x-on:click="recordar('compacta')"
                        :class="vista === 'compacta' && 'activo'">
                    <i class="bi bi-list"></i> Lista
                </button>
                <button type="button" x-on:click="recordar('tarjetas')"
                        :class="vista === 'tarjetas' && 'activo'">
                    <i class="bi bi-grid-1x2"></i> Tarjetas
                </button>
            </div>

            @can('invoices.create')
                <a href="{{ route('finanzas.facturacion.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Nueva factura
                </a>
            @endcan

        </div>

    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         LOS CONTADORES
    ───────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <button type="button" class="kpi kpi-info" wire:click="limpiarFiltros">
                <span class="kpi-icono"><i class="bi bi-hourglass-split"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Por cobrar</span>
                    <span class="kpi-valor d-block">${{ number_format($resumen['porCobrar'], 2) }}</span>
                    <span class="kpi-pie d-block">Saldo de todo lo abierto</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['vencido'] > 0 ? 'kpi-bad' : 'kpi-apagado' }}"
                    wire:click="verVencidas">
                <span class="kpi-icono"><i class="bi bi-exclamation-octagon"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Vencido</span>
                    <span class="kpi-valor d-block">${{ number_format($resumen['vencido'], 2) }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['vencido'] > 0 ? 'Ya pasó la fecha de pago' : 'Nada atrasado' }}
                    </span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['vencidas'] > 0 ? 'kpi-warn' : 'kpi-apagado' }}"
                    wire:click="verVencidas"
                    title="En cuántas facturas está repartido lo vencido">
                <span class="kpi-icono"><i class="bi bi-files"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Facturas vencidas</span>
                    <span class="kpi-valor d-block">{{ $resumen['vencidas'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['vencidas'] > 0 ? 'A cuántos clientes llamar' : 'Ninguna' }}
                    </span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ok">
                <span class="kpi-icono"><i class="bi bi-graph-up-arrow"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Facturado del mes</span>
                    <span class="kpi-valor d-block">${{ number_format($resumen['delMes'], 2) }}</span>
                    <span class="kpi-pie d-block">{{ now()->translatedFormat('F Y') }}</span>
                </span>
            </div>
        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         LA TABLA
    ───────────────────────────────────────────────────────────── --}}
    <div class="card">

        {{-- FILTROS --}}
        <div class="card-header">
            <div class="row g-2 align-items-center">

                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>
                        <input type="search" class="form-control"
                               placeholder="Número, cliente o descripción de una línea…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-2">
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

                <div class="col-6 col-md-2">
                    {{--
                        El interruptor de pendientes.

                        Va en la barra de filtros y no escondido, porque
                        apagarlo cambia lo que la pantalla significa: de
                        "lo que hay que cobrar" a "todo lo que se facturó".
                    --}}
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox"
                               id="soloPendientes" wire:model.live="soloPendientes">
                        <label class="form-check-label small" for="soloPendientes">
                            Solo con saldo
                        </label>
                    </div>
                </div>

                <div class="col-6 col-md-1 text-end">
                    @if ($buscar || $estado || $tipo || ! $soloPendientes)
                        <button class="btn btn-outline-secondary w-100" wire:click="limpiarFiltros"
                                title="Volver a lo pendiente">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    @endif
                </div>

            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             A · COMPACTA
        ═══════════════════════════════════════════════════ --}}
        <div x-show="vista === 'compacta'">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabla-compacta">

                        <thead>
                            <tr>
                                <th role="button" wire:click="ordenar('invoice_number')">
                                    Número
                                    @if ($ordenarPor === 'invoice_number')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th>Cliente</th>
                                <th role="button" wire:click="ordenar('issue_date')">
                                    Emisión
                                    @if ($ordenarPor === 'issue_date')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th role="button" wire:click="ordenar('due_date')">
                                    Vence
                                    @if ($ordenarPor === 'due_date')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th class="text-end" role="button" wire:click="ordenar('total')">
                                    Total
                                    @if ($ordenarPor === 'total')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th class="text-end" role="button" wire:click="ordenar('balance_due')">
                                    Debe
                                    @if ($ordenarPor === 'balance_due')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th>Estado</th>
                                <th class="text-end" style="width: 170px;">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                        @forelse ($facturas as $f)

                            @php
                                /*
                                 | El estado que se enseña NO siempre es el
                                 | guardado: una factura enviada cuya fecha
                                 | ya pasó se lee como vencida aunque en la
                                 | base siga diciendo "enviada".
                                 |
                                 | El accessor display_status del modelo lo resuelve en
                                 | un solo sitio.
                                 */
                                $estadoVisible = $f->display_status;

                                $franja = match ($estadoVisible->color()) {
                                    'green'  => 'fila-ok',
                                    'yellow' => 'fila-warn',
                                    'red'    => 'fila-bad',
                                    'blue'   => 'fila-info',
                                    default  => 'fila-mute',
                                };
                            @endphp

                            <tr wire:key="comp-{{ $f->id }}" class="fila-estado {{ $franja }}">

                                <td>
                                    <a href="{{ route('finanzas.facturacion.show', $f) }}"
                                       class="doc-numero">{{ $f->invoice_number }}</a>
                                </td>

                                <td>
                                    <div class="fw-medium">{{ $f->customer?->name ?? '—' }}</div>
                                    <div class="small text-secondary">{{ $f->customer?->customer_number }}</div>
                                </td>

                                <td class="small">{{ $f->issue_date?->format('d/m/Y') }}</td>

                                <td class="small">
                                    {{ $f->due_date?->format('d/m/Y') ?? '—' }}

                                    @if ($f->isOverdue())
                                        <div class="text-danger fw-medium">
                                            <i class="bi bi-clock-history"></i>
                                            {{ $f->days_overdue }} días
                                        </div>
                                    @endif
                                </td>

                                <td class="text-end monto">${{ number_format((float) $f->total, 2) }}</td>

                                <td class="text-end fw-semibold monto">
                                    @if ((float) $f->balance_due > 0)
                                        <span class="{{ $f->isOverdue() ? 'text-danger' : '' }}">
                                            ${{ number_format((float) $f->balance_due, 2) }}
                                        </span>
                                    @else
                                        <span class="text-success">Pagada</span>
                                    @endif
                                </td>

                                <td>
                                    <x-ui.badge :color="$estadoVisible->color()"
                                                :label="$estadoVisible->label()" />
                                </td>

                                <td class="text-end">
                                    @include('livewire.invoices.partials.acciones', ['f' => $f])
                                </td>

                            </tr>

                        @empty
                            <tr>
                                <td colspan="8">
                                    @include('livewire.invoices.partials.vacio')
                                </td>
                            </tr>
                        @endforelse
                        </tbody>

                    </table>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             B · TARJETAS
        ═══════════════════════════════════════════════════ --}}
        <div x-show="vista === 'tarjetas'" x-cloak>

            <div class="card-body border-bottom py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap small">
                    <span class="text-secondary">Ordenar por:</span>
                    @foreach ([
                        'issue_date'     => 'Emisión',
                        'due_date'       => 'Vence',
                        'balance_due'    => 'Debe',
                        'total'          => 'Total',
                        'invoice_number' => 'Número',
                    ] as $col => $nombre)
                        <button class="btn btn-sm {{ $ordenarPor === $col ? 'btn-primary' : 'btn-outline-secondary' }} py-0"
                                wire:click="ordenar('{{ $col }}')">
                            {{ $nombre }}
                            @if ($ordenarPor === $col)
                                <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill"></i>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="lista-tarjetas">
                @forelse ($facturas as $f)

                    @php
                        $estadoVisible = $f->display_status;

                        $tf = match ($estadoVisible->color()) {
                            'green'  => 'tf-ok',
                            'yellow' => 'tf-warn',
                            'red'    => 'tf-bad',
                            'blue'   => 'tf-info',
                            default  => '',
                        };
                    @endphp

                    <div class="tarjeta-fila {{ $tf }}" wire:key="tarj-{{ $f->id }}">

                        <div class="tf-barra"></div>

                        <div class="tf-cuerpo">
                            <div class="tf-titulo">
                                <a href="{{ route('finanzas.facturacion.show', $f) }}"
                                   class="doc-numero">{{ $f->invoice_number }}</a>

                                <x-ui.badge :color="$estadoVisible->color()"
                                            :label="$estadoVisible->label()" />

                                @if ($f->isOverdue())
                                    <span class="badge text-bg-danger">
                                        <i class="bi bi-clock-history"></i>
                                        {{ $f->days_overdue }} días de atraso
                                    </span>
                                @endif
                            </div>

                            <div class="tf-cliente">{{ $f->customer?->name ?? '—' }}</div>

                            <div class="tf-meta mt-1">
                                <span><i class="bi bi-person-badge"></i> {{ $f->customer?->customer_number }}</span>
                                <span><i class="bi bi-calendar3"></i> {{ $f->issue_date?->format('d/m/Y') }}</span>
                                <span>
                                    <i class="bi bi-hourglass"></i>
                                    Vence {{ $f->due_date?->format('d/m/Y') ?? 'sin fecha' }}
                                </span>
                                @if ((float) $f->amount_paid > 0)
                                    <span>
                                        <i class="bi bi-cash-coin"></i>
                                        Pagado ${{ number_format((float) $f->amount_paid, 2) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="tf-lado">
                            <div class="tf-total {{ $f->isOverdue() ? 'text-danger' : '' }}">
                                @if ((float) $f->balance_due > 0)
                                    ${{ number_format((float) $f->balance_due, 2) }}
                                @else
                                    <span class="text-success">Pagada</span>
                                @endif
                            </div>

                            <div class="small text-secondary">
                                de ${{ number_format((float) $f->total, 2) }}
                            </div>

                            <div class="mt-2">
                                @include('livewire.invoices.partials.acciones', ['f' => $f])
                            </div>
                        </div>

                    </div>

                @empty
                    @include('livewire.invoices.partials.vacio')
                @endforelse
            </div>
        </div>

        @if ($facturas->hasPages())
            <div class="card-footer">
                {{ $facturas->links() }}
            </div>
        @endif

    </div>

</div>
