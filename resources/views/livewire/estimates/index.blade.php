{{--
    ═══════════════════════════════════════════════════════════════════════
    LISTADO DE PRESUPUESTOS — con tres formas de ver la tabla
    ═══════════════════════════════════════════════════════════════════════

    ── DOS FORMAS DE VER LO MISMO ──

    LISTA      filas densas con franja de color. La que más filas mete
               en pantalla. Para revisar la cartera de un vistazo.

    TARJETAS   cada presupuesto en su propia tarjeta, con el importe
               grande. Se ven menos de golpe pero cada uno entra solo
               por los ojos. Para buscar uno concreto, y en tablet.

    Las dos muestran EXACTAMENTE los mismos datos y tienen las mismas
    acciones. Lo único que cambia es cómo se leen.

    La elección se recuerda en el navegador de cada usuario.

    ── POR QUÉ NO TOCA EL COMPONENTE PHP ──

    El selector vive en Alpine. Las dos tablas se pintan y solo se
    muestra una, así que cambiar de vista es instantáneo y no cuesta ni
    una consulta a la base.

    Las acciones y el estado vacío están en partials/, compartidos por
    las dos. Si mañana hay que agregar un botón, se agrega una vez.
--}}
<div x-data="{
        /*
         | La vista elegida se guarda en el navegador de cada usuario.
         |
         | Va en el navegador y no en la base porque es una preferencia
         | personal de cómo mirar una lista, no un dato del negocio. La
         | misma persona puede querer tarjetas en su tablet y lista en el
         | escritorio, y así cada equipo recuerda lo suyo.
         |
         | El try es por si el navegador tiene el almacenamiento bloqueado
         | —pasa en modo privado de algunos—. En ese caso arranca en lista
         | y no se recuerda, que es molesto pero no rompe nada.
         */
        vista: (() => {
            try { return localStorage.getItem('vistaPresupuestos') || 'compacta' }
            catch (e) { return 'compacta' }
        })(),

        recordar(v) {
            this.vista = v;
            try { localStorage.setItem('vistaPresupuestos', v) } catch (e) {}
        },
     }">

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Presupuestos</h4>
            <small class="text-secondary">
                Cotizaciones enviadas a clientes. Al aceptarse se convierten en factura.
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">

            {{--
                EL SELECTOR DE VISTA

                Se queda como función del sistema, no como prueba: las dos
                sirven para cosas distintas y la misma persona quiere una u
                otra según lo que esté haciendo.

                Compacta para revisar la cartera de un vistazo; tarjetas
                para cuando estás buscando uno concreto o trabajas en
                tablet.
            --}}
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

            @can('estimates.create')
            <a href="{{ route('comercial.presupuestos.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Nuevo presupuesto
            </a>
            @endcan

        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         AVISOS
    ───────────────────────────────────────────────────────────── --}}
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
         LOS CUATRO CONTADORES

         Rehechos: ahora el color va en el fondo de la tarjeta y en el
         chip del icono, no en una barrita fina.

         La razón del cambio es práctica. La versión anterior pintaba el
         color con un pseudo-elemento sobre un <button>, y eso lo borra
         cualquier reset de Tailwind o de AdminLTE sin avisar. Un fondo y
         un chip son propiedades normales sobre elementos normales: no hay
         nada que se pueda perder.

         Los que están en cero quedan en blanco y gris a propósito, para
         que los que sí tienen algo resalten por contraste.
    ───────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <button type="button" class="kpi kpi-info" wire:click="limpiarFiltros">
                <span class="kpi-icono"><i class="bi bi-folder2-open"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Abiertos</span>
                    <span class="kpi-valor d-block">{{ $resumen['abiertos'] }}</span>
                    <span class="kpi-pie d-block">Enviados y aceptados</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-ok">
                <span class="kpi-icono"><i class="bi bi-cash-stack"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">En la calle</span>
                    <span class="kpi-valor d-block">
                        ${{ number_format($resumen['montoAbierto'], 2) }}
                    </span>
                    <span class="kpi-pie d-block">Cotizado y sin cerrar</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['aceptados'] > 0 ? 'kpi-ok' : 'kpi-apagado' }}"
                    wire:click="$set('estado', '{{ \App\Enums\EstimateStatus::Accepted->value }}')">
                <span class="kpi-icono"><i class="bi bi-check2-circle"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Sin facturar</span>
                    <span class="kpi-valor d-block">{{ $resumen['aceptados'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['aceptados'] > 0 ? 'Listos para convertir' : 'Nada pendiente' }}
                    </span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3">
            <button type="button"
                    class="kpi {{ $resumen['porVencer'] > 0 ? 'kpi-bad' : 'kpi-apagado' }}"
                    wire:click="$set('estado', '{{ \App\Enums\EstimateStatus::Expired->value }}')">
                <span class="kpi-icono"><i class="bi bi-clock-history"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Vencidos</span>
                    <span class="kpi-valor d-block">{{ $resumen['porVencer'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['porVencer'] > 0 ? 'El precio ya no se respeta' : 'Ninguno vencido' }}
                    </span>
                </span>
            </button>
        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         LA TABLA
    ───────────────────────────────────────────────────────────── --}}
    <div class="card">

        {{-- FILTROS --}}
        <div class="card-header">
            <div class="row g-2 align-items-center">

                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>
                        <input type="search" class="form-control"
                               placeholder="Número, cliente o descripción de una línea…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-8 col-md-4">
                    <select class="form-select" wire:model.live="estado">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-4 col-md-2 text-end">
                    @if ($buscar || $estado)
                        <button class="btn btn-outline-secondary w-100" wire:click="limpiarFiltros">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </button>
                    @endif
                </div>

            </div>
        </div>

        {{--
            A partir de aquí, la misma información tres veces.

            Se calcula una sola vez lo que comparten —color de la franja,
            si está vencido— y cada vista lo pinta a su manera.
        --}}

        {{-- ═══════════════════════════════════════════════════
             A · COMPACTA
             Filas densas, franja de color al inicio. La que más
             filas mete en pantalla.
        ═══════════════════════════════════════════════════ --}}
        <div x-show="vista === 'compacta'">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabla-compacta">

                        <thead>
                            <tr>
                                <th role="button" wire:click="ordenar('estimate_number')">
                                    Número
                                    @if ($ordenarPor === 'estimate_number')
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
                                <th role="button" wire:click="ordenar('valid_until')">
                                    Vence
                                    @if ($ordenarPor === 'valid_until')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th class="text-end" role="button" wire:click="ordenar('total')">
                                    Total
                                    @if ($ordenarPor === 'total')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th>Estado</th>
                                <th class="text-end" style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($presupuestos as $p)
                                @php
                                    $franja = match ($p->status->color()) {
                                        'green'  => 'fila-ok',
                                        'yellow' => 'fila-warn',
                                        'red'    => 'fila-bad',
                                        'blue'   => 'fila-info',
                                        default  => 'fila-mute',
                                    };
                                @endphp

                                <tr wire:key="comp-{{ $p->id }}" class="fila-estado {{ $franja }}">

                                    <td>
                                        <a href="{{ route('comercial.presupuestos.show', $p) }}"
                                           class="doc-numero">{{ $p->estimate_number }}</a>
                                    </td>

                                    <td>
                                        <div class="fw-medium">{{ $p->customer?->name ?? '—' }}</div>
                                        <div class="small text-secondary">{{ $p->customer?->customer_number }}</div>
                                    </td>

                                    <td class="small">{{ $p->issue_date?->format('d/m/Y') }}</td>

                                    <td class="small">
                                        @if ($p->valid_until)
                                            {{ $p->valid_until->format('d/m/Y') }}
                                            @if ($p->isExpired())
                                                <div class="text-danger fw-medium">
                                                    <i class="bi bi-clock-history"></i> Vencido
                                                </div>
                                            @elseif ($p->days_left !== null && $p->days_left <= 1)
                                                <div class="text-warning fw-medium">
                                                    Vence {{ $p->days_left === 0 ? 'hoy' : 'mañana' }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-secondary">Sin límite</span>
                                        @endif
                                    </td>

                                    <td class="text-end fw-semibold monto">
                                        ${{ number_format((float) $p->total, 2) }}
                                    </td>

                                    <td>
                                        <x-ui.badge :color="$p->status->color()" :label="$p->status->label()" />
                                        @if ($p->converted_invoice_id && $p->invoice)
                                            <div class="small mt-1">
                                                <a href="{{ route('finanzas.facturacion.show', $p->invoice) }}"
                                                   class="text-decoration-none">
                                                    <i class="bi bi-receipt"></i> {{ $p->invoice->invoice_number }}
                                                </a>
                                            </div>
                                        @endif
                                    </td>

                                    <td class="text-end">
                                        @include('livewire.estimates.partials.acciones', ['p' => $p])
                                    </td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        @include('livewire.estimates.partials.vacio')
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
             Cada fila es una tarjeta con aire. Se leen menos
             filas de golpe pero cada una entra sola.
        ═══════════════════════════════════════════════════ --}}
        <div x-show="vista === 'tarjetas'" x-cloak>

            {{-- Sin encabezado de tabla no hay dónde ordenar: va acá --}}
            <div class="card-body border-bottom py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap small">
                    <span class="text-secondary">Ordenar por:</span>
                    @foreach (['estimate_number' => 'Número', 'issue_date' => 'Emisión', 'valid_until' => 'Vence', 'total' => 'Total'] as $col => $nombre)
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
                @forelse ($presupuestos as $p)
                    @php
                        $tf = match ($p->status->color()) {
                            'green'  => 'tf-ok',
                            'yellow' => 'tf-warn',
                            'red'    => 'tf-bad',
                            'blue'   => 'tf-info',
                            default  => '',
                        };
                    @endphp

                    <div class="tarjeta-fila {{ $tf }}" wire:key="tarj-{{ $p->id }}">

                        <div class="tf-barra"></div>

                        <div class="tf-cuerpo">
                            <div class="tf-titulo">
                                <a href="{{ route('comercial.presupuestos.show', $p) }}"
                                   class="doc-numero">{{ $p->estimate_number }}</a>

                                <x-ui.badge :color="$p->status->color()" :label="$p->status->label()" />

                                @if ($p->isExpired())
                                    <span class="badge text-bg-danger">
                                        <i class="bi bi-clock-history"></i> Vencido
                                    </span>
                                @elseif ($p->days_left !== null && $p->days_left <= 1)
                                    <span class="badge text-bg-warning">
                                        Vence {{ $p->days_left === 0 ? 'hoy' : 'mañana' }}
                                    </span>
                                @endif

                                @if ($p->converted_invoice_id && $p->invoice)
                                    <a href="{{ route('finanzas.facturacion.show', $p->invoice) }}"
                                       class="badge text-bg-success text-decoration-none">
                                        <i class="bi bi-receipt"></i> {{ $p->invoice->invoice_number }}
                                    </a>
                                @endif
                            </div>

                            <div class="tf-cliente">{{ $p->customer?->name ?? '—' }}</div>

                            <div class="tf-meta mt-1">
                                <span><i class="bi bi-person-badge"></i> {{ $p->customer?->customer_number }}</span>
                                <span><i class="bi bi-calendar3"></i> {{ $p->issue_date?->format('d/m/Y') }}</span>
                                <span>
                                    <i class="bi bi-hourglass"></i>
                                    {{ $p->valid_until?->format('d/m/Y') ?? 'Sin límite' }}
                                </span>
                            </div>
                        </div>

                        <div class="tf-lado">
                            <div class="tf-total">${{ number_format((float) $p->total, 2) }}</div>
                            <div class="mt-2">
                                @include('livewire.estimates.partials.acciones', ['p' => $p])
                            </div>
                        </div>

                    </div>
                @empty
                    @include('livewire.estimates.partials.vacio')
                @endforelse
            </div>
        </div>

        @if ($presupuestos->hasPages())
            <div class="card-footer">
                {{ $presupuestos->links() }}
            </div>
        @endif

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         CONFIRMACIÓN DE BORRADO

         Modal dibujado a mano en vez de usar el JavaScript de Bootstrap.
         Livewire vuelve a pintar este pedazo cada vez que algo cambia, y
         un modal abierto por JavaScript se queda colgado cuando eso pasa.
    ───────────────────────────────────────────────────────────── --}}
    @if ($porBorrar)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,.55);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">¿Eliminar el presupuesto?</h5>
                        <button type="button" class="btn-close" wire:click="cancelarBorrado"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-0">
                            Se va a eliminar el presupuesto y todas sus líneas.
                            Esta acción no se puede deshacer.
                        </p>
                        <p class="text-secondary small mt-2 mb-0">
                            El número consumido no se reutiliza: el siguiente
                            presupuesto seguirá la numeración donde iba.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" wire:click="cancelarBorrado">
                            Cancelar
                        </button>
                        <button class="btn btn-danger" wire:click="borrar">
                            <i class="bi bi-trash me-1"></i> Sí, eliminar
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
