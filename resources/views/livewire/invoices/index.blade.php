{{--
    ═══════════════════════════════════════════════════════════════════════
    LISTADO DE FACTURAS
    ═══════════════════════════════════════════════════════════════════════

    Esta pantalla no es una lista de documentos: es una herramienta de
    cobranza. Un vendedor abre presupuestos para ver qué ofreció; alguien
    abre facturas para ver QUIÉN LE DEBE.

    Por eso los contadores de arriba son dinero y no cantidades, y por eso
    el filtro por defecto es "con saldo".

    ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──

      · Los contadores tienen color, icono y pie explicativo, y los
        cuatro se pueden pulsar para filtrar.

      · Los botones de acción dejaron de ser cuadritos grises idénticos.

      · Cada fila lleva una franja de color según su estado real: si la
        fecha ya pasó y queda saldo, la franja es roja aunque en la base
        siga diciendo "Enviada".

      · ⚠️ AVISO NUEVO: cuando el filtro "Con saldo" está encendido y hay
        facturas escondidas por él, la pantalla lo dice. Antes una factura
        recién emitida en cero podía desaparecer sin explicación.
--}}
<div>

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Facturación</h4>
            <small class="text-secondary">
                Documentos fiscales emitidos. No se borran: se anulan.
            </small>
        </div>

        <a href="{{ route('finanzas.facturacion.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nueva factura
        </a>

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

    {{-- ─────────────────────────────────────────────────────────────
         LOS CONTADORES

         Los cuatro responden preguntas distintas:

           Por cobrar   ¿cuánto me deben en total?
           Vencido      ¿cuánto de eso ya se pasó de fecha?
           Vencidas     ¿en cuántas facturas está repartido?
           Del mes      ¿cuánto facturé este mes?

         El tercero importa más de lo que parece: $40,000 en una sola
         factura de un cliente bueno es una conversación; los mismos
         $40,000 repartidos en treinta facturas de veinte clientes son
         un problema de cobranza.
    ───────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <button type="button" class="kpi kpi-info" wire:click="limpiarFiltros">
                <div class="kpi-label">
                    <i class="bi bi-cash-stack"></i> Por cobrar
                </div>
                <div class="kpi-valor monto">
                    ${{ number_format($resumen['porCobrar'], 2) }}
                </div>
                <div class="kpi-pie">Todo lo que tiene saldo</div>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['vencido'] > 0 ? 'kpi-bad' : 'kpi-apagado' }}"
                    wire:click="verVencidas">
                <div class="kpi-label">
                    <i class="bi bi-exclamation-octagon"></i> Vencido
                </div>
                <div class="kpi-valor monto">
                    ${{ number_format($resumen['vencido'], 2) }}
                </div>
                <div class="kpi-pie">
                    {{ $resumen['vencido'] > 0 ? 'Ya pasó la fecha de pago' : 'Nada atrasado' }}
                </div>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['vencidas'] > 0 ? 'kpi-warn' : 'kpi-apagado' }}"
                    wire:click="verVencidas">
                <div class="kpi-label">
                    <i class="bi bi-files"></i> Facturas vencidas
                </div>
                <div class="kpi-valor">{{ $resumen['vencidas'] }}</div>
                <div class="kpi-pie">
                    @if ($resumen['vencidas'] > 0)
                        En {{ $resumen['vencidas'] }}
                        {{ $resumen['vencidas'] === 1 ? 'documento' : 'documentos' }}
                    @else
                        Ninguna
                    @endif
                </div>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ok">
                <div class="kpi-label">
                    <i class="bi bi-graph-up-arrow"></i>
                    Facturado en {{ now()->translatedFormat('F') }}
                </div>
                <div class="kpi-valor monto">
                    ${{ number_format($resumen['delMes'], 2) }}
                </div>
                <div class="kpi-pie">Emitido este mes, sin las anuladas</div>
            </div>
        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         LA TABLA
    ───────────────────────────────────────────────────────────── --}}
    <div class="card">

        <div class="card-header">
            <div class="row g-2 align-items-center">

                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>

                        {{--
                            .live.debounce.400ms: manda lo que escribo al
                            servidor, pero espera 400 milisegundos a que
                            deje de teclear.

                            Sin el debounce, escribir "Homestead" son
                            nueve consultas. Con él, una.
                        --}}
                        <input type="search" class="form-control"
                               placeholder="Número, cliente o descripción de una línea…"
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
                        <option value="">Todos los tipos</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex align-items-center gap-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox"
                               id="solo-pendientes"
                               wire:model.live="soloPendientes">
                        <label class="form-check-label small" for="solo-pendientes">
                            Con saldo
                        </label>
                    </div>

                    @if ($buscar || $estado || $tipo)
                        <button class="btn btn-sm btn-outline-secondary" wire:click="limpiarFiltros"
                                title="Quitar los filtros">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    @endif
                </div>

            </div>
        </div>

        {{--
            ⚠️ EL AVISO DEL FILTRO ESCONDIDO

            "Con saldo" viene encendido a propósito: una empresa con dos
            años de operación tiene miles de facturas cobradas y ninguna
            de ellas es lo que se viene a mirar aquí.

            El problema es cuando ese filtro esconde algo que acabas de
            crear. Una factura recién emitida que quedó en cero —o una que
            se cobró completa hace un minuto— desaparece sin decir por
            qué, y la conclusión natural es "no se guardó".

            Este aviso solo aparece cuando de verdad hay algo escondido, y
            se apaga con un clic.
        --}}
        @if ($soloPendientes)
            @php
                $totalSinFiltro = \App\Models\Invoice::query()
                    ->search($buscar)
                    ->statusIs($estado)
                    ->when($tipo, fn ($q) => $q->where('type', $tipo))
                    ->count();

                $escondidas = $totalSinFiltro - $facturas->total();
            @endphp

            @if ($escondidas > 0)
                <div class="card-body border-bottom py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 small">
                        <i class="bi bi-funnel-fill text-secondary"></i>
                        <span class="text-secondary">
                            Hay {{ $escondidas }}
                            {{ $escondidas === 1 ? 'factura sin saldo que no se está mostrando' : 'facturas sin saldo que no se están mostrando' }}
                            (cobradas, anuladas o en cero).
                        </span>
                        <button class="btn btn-sm btn-outline-secondary py-0"
                                wire:click="$set('soloPendientes', false)">
                            Mostrarlas
                        </button>
                    </div>
                </div>
            @endif
        @endif

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead>
                        <tr>
                            <th role="button" wire:click="ordenar('invoice_number')">
                                Número
                                @if ($ordenarPor === 'invoice_number')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th>Cliente</th>
                            <th>Tipo</th>

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
                                Saldo
                                @if ($ordenarPor === 'balance_due')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th>Estado</th>
                            <th class="text-end" style="width: 120px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($facturas as $f)

                            {{--
                                La franja usa display_status y no status.

                                Si la fecha ya pasó y queda saldo, se pinta
                                de rojo aunque en la base siga diciendo
                                "Enviada". Así el listado dice la verdad sin
                                necesidad de un proceso que ande cambiando
                                estados de madrugada.
                            --}}
                            @php
                                $franja = match ($f->display_status->color()) {
                                    'green'  => 'fila-ok',
                                    'yellow' => 'fila-warn',
                                    'red'    => 'fila-bad',
                                    'blue'   => 'fila-info',
                                    default  => 'fila-mute',
                                };
                            @endphp

                            <tr wire:key="factura-{{ $f->id }}"
                                class="fila-estado {{ $franja }} {{ $f->status === \App\Enums\InvoiceStatus::Void ? 'opacity-50' : '' }}">

                                <td>
                                    <a href="{{ route('finanzas.facturacion.show', $f) }}"
                                       class="doc-numero">
                                        {{ $f->invoice_number }}
                                    </a>
                                </td>

                                <td>
                                    <div class="fw-medium">{{ $f->customer?->name ?? '—' }}</div>
                                    <div class="small text-secondary">
                                        {{ $f->customer?->customer_number }}
                                    </div>
                                </td>

                                <td class="small">{{ $f->type?->label() }}</td>

                                <td class="small">{{ $f->issue_date?->format('d/m/Y') }}</td>

                                <td class="small">
                                    {{ $f->due_date?->format('d/m/Y') ?? '—' }}

                                    @if ($f->isOverdue())
                                        <div class="text-danger fw-medium">
                                            <i class="bi bi-clock-history"></i>
                                            {{ $f->days_overdue }}
                                            {{ $f->days_overdue === 1 ? 'día' : 'días' }}
                                        </div>
                                    @endif
                                </td>

                                <td class="text-end monto">${{ number_format((float) $f->total, 2) }}</td>

                                <td class="text-end fw-semibold monto
                                           {{ (float) $f->balance_due > 0 ? 'text-danger' : 'text-success' }}">
                                    ${{ number_format((float) $f->balance_due, 2) }}
                                </td>

                                <td>
                                    <x-ui.badge
                                        :color="$f->display_status->color()"
                                        :label="$f->display_status->label()"
                                    />
                                </td>

                                <td class="text-end">
                                    <div class="acciones">

                                        <a href="{{ route('finanzas.facturacion.show', $f) }}"
                                           class="acc acc-ver" title="Ver">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        @if ($f->isEditable())
                                            <a href="{{ route('finanzas.facturacion.edit', $f) }}"
                                               class="acc acc-editar" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endif

                                        {{--
                                            El atajo a cobrar.

                                            Es la acción que más se hace
                                            desde esta pantalla: se mira
                                            quién debe y se registra el
                                            cobro. Sin este botón hay que
                                            ir al menú de Pagos y volver a
                                            buscar el cliente.

                                            Solo aparece si queda saldo:
                                            cobrar una factura pagada no
                                            existe.
                                        --}}
                                        @if ((float) $f->balance_due > 0
                                             && $f->status !== \App\Enums\InvoiceStatus::Void)
                                            <a href="{{ route('finanzas.pagos.create') }}?factura={{ $f->id }}"
                                               class="acc acc-copiar" title="Registrar un cobro de esta factura">
                                                <i class="bi bi-cash-coin"></i>
                                            </a>
                                        @endif

                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="vacio">
                                        <i class="bi bi-receipt"></i>

                                        @if ($buscar || $estado || $tipo)
                                            No hay facturas que coincidan con el filtro.
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-secondary"
                                                        wire:click="limpiarFiltros">
                                                    Quitar los filtros
                                                </button>
                                            </div>
                                        @elseif ($soloPendientes)
                                            No hay facturas con saldo pendiente.
                                            <div class="small mt-1">
                                                Puede que existan facturas cobradas, anuladas o en cero.
                                            </div>
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-secondary"
                                                        wire:click="$set('soloPendientes', false)">
                                                    Ver todas
                                                </button>
                                            </div>
                                        @else
                                            Todavía no hay facturas.
                                            <div class="mt-2">
                                                <a href="{{ route('finanzas.facturacion.create') }}"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus-lg me-1"></i> Emitir la primera
                                                </a>
                                            </div>
                                            <div class="small mt-2">
                                                O convertir un presupuesto desde
                                                <a href="{{ route('comercial.presupuestos.index') }}">Presupuestos</a>.
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

        @if ($facturas->hasPages())
            <div class="card-footer">
                {{ $facturas->links() }}
            </div>
        @endif

    </div>

</div>
