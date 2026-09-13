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


    {{-- ============================================================
         INDICADORES PRINCIPALES
    ============================================================= --}}

    <div class="row g-3 mb-4">

        {{-- Ventas --}}
        <div class="col-xl-3 col-md-6">

            <div class="small-box text-bg-primary">

                <div class="inner">

                    <h3>
                        ${{ number_format($indicadores['ventas_mes'], 0) }}
                    </h3>

                    <p>Ventas del mes</p>

                </div>

                <i class="small-box-icon bi bi-graph-up-arrow"></i>

                <a href="{{ route('comercial.ventas.index') }}"
                   class="small-box-footer">

                    Ver ventas
                    <i class="bi bi-arrow-right-circle"></i>

                </a>

            </div>

        </div>


        {{-- Cuentas por cobrar --}}
        <div class="col-xl-3 col-md-6">

            <div class="small-box text-bg-danger">

                <div class="inner">

                    <h3>
                        ${{ number_format($indicadores['por_cobrar'], 0) }}
                    </h3>

                    <p>Por cobrar</p>

                </div>

                <i class="small-box-icon bi bi-cash-stack"></i>

                <a href="{{ route('finanzas.pagos.index') }}"
                   class="small-box-footer">

                    Ver pagos
                    <i class="bi bi-arrow-right-circle"></i>

                </a>

            </div>

        </div>


        {{-- Rentas --}}
        <div class="col-xl-3 col-md-6">

            <div class="small-box text-bg-success">

                <div class="inner">

                    <h3>
                        {{ $indicadores['rentas_activas'] }}
                    </h3>

                    <p>Rentas activas</p>

                </div>

                <i class="small-box-icon bi bi-arrow-repeat"></i>

                <a href="{{ route('operaciones.rentas.index') }}"
                   class="small-box-footer">

                    Ver rentas
                    <i class="bi bi-arrow-right-circle"></i>

                </a>

            </div>

        </div>


        {{-- Contenedores --}}
        <div class="col-xl-3 col-md-6">

            <div class="small-box text-bg-info">

                <div class="inner">

                    <h3>
                        {{ $indicadores['contenedores'] }}
                    </h3>

                    <p>Contenedores disponibles</p>

                </div>

                <i class="small-box-icon bi bi-box-seam"></i>

                <a href="{{ route('operaciones.contenedores.index') }}"
                   class="small-box-footer">

                    Ver contenedores
                    <i class="bi bi-arrow-right-circle"></i>

                </a>

            </div>

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


    {{-- ============================================================
         RESUMEN OPERATIVO
    ============================================================= --}}

    <div class="row g-3">

        <div class="col-lg-6">

            <div class="card">

                <div class="card-header">

                    <h3 class="card-title">

                        <i class="bi bi-truck me-1"></i>

                        Operación

                    </h3>

                </div>

                <div class="card-body">

                    <div class="row text-center">

                        <div class="col-4">

                            <div class="fs-3 fw-semibold">
                                {{ $indicadores['viajes_mes'] }}
                            </div>

                            <div class="text-muted">
                                Viajes
                            </div>

                        </div>

                        <div class="col-4">

                            <div class="fs-3 fw-semibold">
                                {{ $indicadores['rentas_activas'] }}
                            </div>

                            <div class="text-muted">
                                Rentas
                            </div>

                        </div>

                        <div class="col-4">

                            <div class="fs-3 fw-semibold">
                                {{ $indicadores['contenedores'] }}
                            </div>

                            <div class="text-muted">
                                Contenedores
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-lg-6">

            <div class="card">

                <div class="card-header">

                    <h3 class="card-title">

                        <i class="bi bi-people me-1"></i>

                        Clientes

                    </h3>

                </div>

                <div class="card-body">

                    <div class="d-flex align-items-center my-2">

                        <div class="display-6 fw-semibold me-3">

                            {{ $indicadores['clientes'] }}

                        </div>

                        <div>

                            <div class="text-muted">
                                Clientes registrados
                            </div>

                            <a
                                href="{{ route('comercial.clientes.index') }}"
                                class="small"
                            >
                                Administrar clientes
                                <i class="bi bi-arrow-right"></i>
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>
    


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