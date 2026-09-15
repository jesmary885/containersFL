<div>

    {{-- ============================================================
         HEADER
    ============================================================= --}}

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h3 class="mb-1 fw-semibold">
                Dashboard
            </h3>

            <span class="text-muted">
                Resumen general del sistema
            </span>
        </div>

        <div class="text-muted">
            <i class="bi bi-calendar3 me-1"></i>
            {{ now()->translatedFormat('d M Y') }}
        </div>

    </div>


    {{--
        ═══════════════════════════════════════════════════════════════
        LO QUE HAY QUE ATENDER HOY
        ═══════════════════════════════════════════════════════════════

        Tres avisos que hoy solo se descubren entrando módulo por módulo.
        Solo aparecen si hay algo que atender: un panel lleno de ceros
        enseña a no mirarlo.
    --}}
    @if ($atencion['releases_vencidos'] > 0 || $atencion['presupuestos_abiertos'] > 0 || $totalMorosos > 0)
        <div class="alert alert-warning d-flex flex-wrap gap-3 align-items-center">

            <strong><i class="bi bi-bell me-1"></i> Para hoy:</strong>

            @if ($totalMorosos > 0)
                <a href="{{ route('finanzas.facturacion.index') }}" class="text-decoration-none">
                    {{ $totalMorosos }}
                    {{ $totalMorosos === 1 ? 'factura vencida' : 'facturas vencidas' }}
                    por ${{ number_format($totalDeuda, 2) }}
                </a>
            @endif

            @if ($atencion['releases_vencidos'] > 0)
                <a href="{{ route('compras.compras.index') }}" class="text-decoration-none">
                    {{ $atencion['releases_vencidos'] }}
                    {{ $atencion['releases_vencidos'] === 1 ? 'release vencido' : 'releases vencidos' }}
                    — el depósito ya está cobrando almacenaje
                </a>
            @endif

            @if ($atencion['presupuestos_abiertos'] > 0)
                <a href="{{ route('comercial.presupuestos.index') }}" class="text-decoration-none">
                    {{ $atencion['presupuestos_abiertos'] }}
                    {{ $atencion['presupuestos_abiertos'] === 1 ? 'presupuesto abierto' : 'presupuestos abiertos' }}
                    — valen 3 días
                </a>
            @endif

        </div>
    @endif

    {{--
        ═══════════════════════════════════════════════════════════════════
        LOS INDICADORES · DOS ESTILOS
        ═══════════════════════════════════════════════════════════════════

        ── QUÉ ESTABA MAL ──

        Había CINCO tarjetas en una rejilla de cuatro columnas, así que la
        quinta quedaba sola en la fila de abajo, enorme y descolgada. No
        era un problema de gusto: una rejilla que no cierra se lee como un
        error de la pantalla.

        ── CÓMO SE ARREGLA ──

        No estirando la quinta ni encogiendo las otras, sino separando dos
        cosas que son distintas:

          · CUATRO TARJETAS DE DINERO — lo que hay, lo que entró, lo que
            falta cobrar y lo que se ganó. Cuatro en una fila de cuatro:
            cierra sola en cualquier pantalla.

          · UNA TIRA DE CONTADORES — disponibles, rentados, por retirar,
            clientes. Son cantidades, no dinero, y no merecen el mismo
            tamaño que un importe.

        El margen del mes es nuevo. Existe desde que la factura congela el
        costo de cada unidad, y es el número que contesta la única pregunta
        que el sistema no sabía contestar.

        ── LOS DOS ESTILOS ──

        Mismo contenido, dos pieles, como en los listados:

          TABLERO   sobrio, el de todo el sistema. Por defecto.
          COLOR     bloques de color, el de AdminLTE.

        La elección se guarda en el navegador de cada persona, igual que en
        Clientes. Es una preferencia de cómo mirar, no un dato del negocio.
    --}}
    <div x-data="{
            vista: (() => {
                try { return localStorage.getItem('vistaPanel') || 'tablero' }
                catch (e) { return 'tablero' }
            })(),

            recordar(v) {
                this.vista = v;
                try { localStorage.setItem('vistaPanel', v) } catch (e) {}
            },
         }">

        <div class="d-flex justify-content-end mb-2">
            <div class="selector-vista" title="Cómo ver el panel">
                <button type="button" x-on:click="recordar('tablero')"
                        :class="vista === 'tablero' && 'activo'">
                    <i class="bi bi-ui-checks-grid"></i> Tablero
                </button>
                <button type="button" x-on:click="recordar('color')"
                        :class="vista === 'color' && 'activo'">
                    <i class="bi bi-palette"></i> Color
                </button>
            </div>
        </div>

        {{-- ═══════════════ ESTILO A · TABLERO ═══════════════ --}}
        <div x-show="vista === 'tablero'">

            <div class="row g-3 mb-3">

                {{--
                    VALOR EN YARDA

                    Primero porque es el número con el que abre la demo:
                    el dinero inmovilizado en el patio.
                --}}
                <div class="col-6 col-xl-3">
                    <a href="{{ route('operaciones.contenedores.index') }}"
                       class="kpi kpi-apagado text-decoration-none">
                        <span class="kpi-icono"><i class="bi bi-box-seam"></i></span>
                        <span class="kpi-cuerpo">
                            <span class="kpi-label d-block">Valor en yarda</span>
                            <span class="kpi-valor d-block">
                                ${{ number_format($indicadores['valor_yarda'], 2) }}
                            </span>
                            <span class="kpi-pie d-block">
                                compra + recogida + arreglos
                            </span>
                        </span>
                    </a>
                </div>

                {{-- FACTURADO DEL MES --}}
                <div class="col-6 col-xl-3">
                    <a href="{{ route('finanzas.facturacion.index') }}"
                       class="kpi kpi-info text-decoration-none">
                        <span class="kpi-icono"><i class="bi bi-receipt"></i></span>
                        <span class="kpi-cuerpo">
                            <span class="kpi-label d-block">Facturado del mes</span>
                            <span class="kpi-valor d-block">
                                ${{ number_format($indicadores['ventas_mes'], 2) }}
                            </span>
                            <span class="kpi-pie d-block">
                                {{ now()->translatedFormat('F Y') }}
                            </span>
                        </span>
                    </a>
                </div>

                {{-- POR COBRAR --}}
                <div class="col-6 col-xl-3">
                    <a href="{{ route('finanzas.facturacion.index') }}"
                       class="kpi {{ $indicadores['por_cobrar'] > 0 ? 'kpi-bad' : 'kpi-apagado' }} text-decoration-none">
                        <span class="kpi-icono"><i class="bi bi-cash-stack"></i></span>
                        <span class="kpi-cuerpo">
                            <span class="kpi-label d-block">Por cobrar</span>
                            <span class="kpi-valor d-block">
                                ${{ number_format($indicadores['por_cobrar'], 2) }}
                            </span>
                            <span class="kpi-pie d-block">
                                {{ $totalMorosos > 0
                                    ? $totalMorosos.' '.($totalMorosos === 1 ? 'factura vencida' : 'facturas vencidas')
                                    : 'nada vencido' }}
                            </span>
                        </span>
                    </a>
                </div>

                {{--
                    MARGEN DEL MES

                    El número nuevo. Existe desde que la factura congela el
                    costo de cada unidad.

                    Verde si queda dinero, rojo si no: un margen negativo
                    significa que algo se vendió por debajo de lo que costó
                    ponerlo en la yarda, y eso tiene que saltar a la vista.
                --}}
                <div class="col-6 col-xl-3">
                    <a href="{{ route('comercial.ventas.index') }}"
                       class="kpi {{ $indicadores['margen_mes'] > 0 ? 'kpi-ok' : ($indicadores['margen_mes'] < 0 ? 'kpi-bad' : 'kpi-apagado') }} text-decoration-none">
                        <span class="kpi-icono"><i class="bi bi-graph-up-arrow"></i></span>
                        <span class="kpi-cuerpo">
                            <span class="kpi-label d-block">Margen del mes</span>
                            <span class="kpi-valor d-block">
                                ${{ number_format($indicadores['margen_mes'], 2) }}
                            </span>
                            <span class="kpi-pie d-block">
                                @if ($indicadores['margen_pct'] !== null)
                                    {{ $indicadores['margen_pct'] }}% sobre lo vendido
                                @else
                                    sin ventas medibles aún
                                @endif
                            </span>
                        </span>
                    </a>
                </div>

            </div>

            @include('livewire.partials.panel-contadores')

        </div>

        {{-- ═══════════════ ESTILO B · COLOR ═══════════════ --}}
        <div x-show="vista === 'color'" x-cloak>

            <div class="row g-3 mb-3">

                <div class="col-sm-6 col-xl-3">
                    <div class="small-box text-bg-dark">
                        <div class="inner">
                            <h3>${{ number_format($indicadores['valor_yarda'], 0) }}</h3>
                            <p>Valor en yarda</p>
                        </div>
                        <i class="small-box-icon bi bi-box-seam"></i>
                        <a href="{{ route('operaciones.contenedores.index') }}" class="small-box-footer">
                            Ver el inventario <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="small-box text-bg-primary">
                        <div class="inner">
                            <h3>${{ number_format($indicadores['ventas_mes'], 0) }}</h3>
                            <p>Facturado del mes</p>
                        </div>
                        <i class="small-box-icon bi bi-receipt"></i>
                        <a href="{{ route('finanzas.facturacion.index') }}" class="small-box-footer">
                            Ver facturas <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="small-box {{ $indicadores['por_cobrar'] > 0 ? 'text-bg-danger' : 'text-bg-secondary' }}">
                        <div class="inner">
                            <h3>${{ number_format($indicadores['por_cobrar'], 0) }}</h3>
                            <p>Por cobrar</p>
                        </div>
                        <i class="small-box-icon bi bi-cash-stack"></i>
                        <a href="{{ route('finanzas.facturacion.index') }}" class="small-box-footer">
                            Ver cobranza <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="small-box {{ $indicadores['margen_mes'] >= 0 ? 'text-bg-success' : 'text-bg-danger' }}">
                        <div class="inner">
                            <h3>${{ number_format($indicadores['margen_mes'], 0) }}</h3>
                            <p>Margen del mes</p>
                        </div>
                        <i class="small-box-icon bi bi-graph-up-arrow"></i>
                        <a href="{{ route('comercial.ventas.index') }}" class="small-box-footer">
                            Ver ventas <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

            </div>

            @include('livewire.partials.panel-contadores')

        </div>

    </div>

    {{-- ============================================================
         PAGOS PENDIENTES
    ============================================================= --}}

    <div class="card card-danger card-outline mb-4">

        <div class="card-header">

            <h3 class="card-title">

                <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>

                Pagos pendientes

            </h3>

            <div class="card-tools">

                <a
                    href="{{ route('finanzas.pagos.index') }}"
                    class="btn btn-sm btn-outline-danger"
                >
                    Ver todos
                </a>

            </div>

        </div>


        <div class="card-body">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>

                    <strong>
                        {{ $totalMorosos }}
                    </strong>

                    clientes con pagos pendientes

                </div>

                <div>

                    <strong class="text-danger fs-5">

                        ${{ number_format($totalDeuda, 2) }}

                    </strong>

                    por cobrar

                </div>

            </div>


            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead>

                        <tr>

                            <th>Cliente</th>

                            <th>Contenedor</th>

                            <th>Vencimiento</th>

                            <th class="text-end">Monto</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse ($pagosPendientes as $pago)

                            <tr>

                                <td>
                                    {{ $pago['cliente'] }}
                                </td>

                                <td>
                                    <span class="badge text-bg-secondary">
                                        {{ $pago['contenedor'] }}
                                    </span>
                                </td>

                                <td class="text-danger">

                                    <i class="bi bi-calendar-x me-1"></i>

                                    {{ $pago['vencido'] }}
                                    @if (! empty($pago['dias']))
                                        <div class="small text-danger">
                                            {{ $pago['dias'] }}
                                            {{ $pago['dias'] === 1 ? 'día' : 'días' }} de atraso
                                        </div>
                                    @endif

                                </td>

                                <td class="text-end fw-semibold">

                                    ${{ number_format($pago['monto'], 2) }}

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="4" class="text-center text-muted py-4">

                                    No existen pagos pendientes.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    

    {{-- ============================================================
         GRÁFICAS
    ============================================================= --}}

    <div class="row g-3 mb-4">

        {{-- Ventas --}}
        <div class="col-lg-8">

            <div class="card h-100">

                <div class="card-header">

                    <h3 class="card-title">

                        <i class="bi bi-bar-chart-line me-1"></i>

                        Ventas de los últimos 6 meses

                    </h3>

                </div>

                <div class="card-body">

                    <div style="height: 300px;">

                        <canvas id="ventasChart"></canvas>

                    </div>

                </div>

            </div>

        </div>


        {{-- Cuentas --}}
        <div class="col-lg-4">

            <div class="card h-100">

                <div class="card-header">

                    <h3 class="card-title">

                        <i class="bi bi-pie-chart me-1"></i>

                        Estado de cuentas

                    </h3>

                </div>

                <div class="card-body">

                    <div
                        style="
                            height: 300px;
                            display: flex;
                            justify-content: center;
                        "
                    >

                        <canvas id="cuentasChart"></canvas>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{--
        AQUÍ ESTABA "RESUMEN OPERATIVO".

        Enseñaba tres números —por retirar, rentas y disponibles— y una
        tarjeta de clientes. Los cuatro están ahora en la tira de
        contadores de arriba, que además los enlaza a su módulo.

        Dos sitios de la misma pantalla diciendo lo mismo no dan más
        información: dan más sitio donde mirar, y alargan la página hasta
        que las gráficas quedan fuera del primer vistazo.

        El bloque se fue. Nada se perdió.
    --}}

    {{-- ============================================================
         CHART.JS
    ============================================================= --}}

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            const ventasCanvas = document.getElementById('ventasChart');

            if (ventasCanvas && window.Chart) {

                new Chart(ventasCanvas, {

                    type: 'bar',

                    data: {

                        labels: @json($ventasMensuales['labels']),

                        datasets: [{
                            label: 'Ventas',

                            data: @json($ventasMensuales['data']),

                            borderWidth: 1,

                            borderRadius: 6
                        }]

                    },

                    options: {

                        responsive: true,

                        maintainAspectRatio: false,

                        plugins: {

                            legend: {
                                display: false
                            },

                            tooltip: {

                                callbacks: {

                                    label: function (context) {

                                        return '$' +
                                            Number(context.raw)
                                                .toLocaleString();

                                    }

                                }

                            }

                        },

                        scales: {

                            y: {

                                beginAtZero: true,

                                ticks: {

                                    callback: function (value) {

                                        return '$' +
                                            Number(value)
                                                .toLocaleString();

                                    }

                                }

                            }

                        }

                    }

                });

            }


            const cuentasCanvas =
                document.getElementById('cuentasChart');

            if (cuentasCanvas && window.Chart) {

                new Chart(cuentasCanvas, {

                    type: 'doughnut',

                    data: {

                        labels: @json($estadoCuentas['labels']),

                        datasets: [{

                            data: @json($estadoCuentas['data']),

                            borderWidth: 2

                        }]

                    },

                    options: {

                        responsive: true,

                        maintainAspectRatio: false,

                        plugins: {

                            legend: {

                                position: 'bottom'

                            }

                        }

                    }

                });

            }

        });

    </script>

</div>