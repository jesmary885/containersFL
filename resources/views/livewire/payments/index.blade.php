{{--
    ═══════════════════════════════════════════════════════════════════════
    LISTADO DE PAGOS
    ═══════════════════════════════════════════════════════════════════════

    Dinero que ya entró. Se registra el cobro y se aplica a una o varias
    facturas.

    ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──

      · Los tres contadores tienen color, icono y pie, y se pueden pulsar.

      · ⚠️ APARECIÓ LA COLUMNA DE ACCIONES. Antes no había ninguna: la
        única forma de abrir un pago era hacer clic en su número, y eso
        no se ve como enlace hasta que pasas el mouse por encima. Con
        quince filas es incómodo; con un usuario nuevo es invisible.

      · Los pagos con dinero sin aplicar llevan franja ámbar. Ese dinero
        está cobrado pero no descontado de ninguna factura: el cliente
        ya pagó y el sistema sigue diciendo que debe.
--}}
<div>

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Pagos</h4>
            <small class="text-secondary">
                Dinero que ya entró. Se registra el cobro y se aplica a una o varias facturas.
            </small>
        </div>

        @can('payments.create')
        <a href="{{ route('finanzas.pagos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Registrar cobro
        </a>
        @endcan

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

         El del medio es el importante y por eso va en ámbar: dinero
         cobrado que todavía no se descontó de ninguna factura.

         Mientras esté ahí, el cliente ya pagó y el sistema sigue
         diciendo que debe. Se le manda un recordatorio de cobranza a
         alguien que no debe nada, y esa llamada cuesta más que el
         minuto que toma aplicar el pago.
    ───────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">

        <div class="col-12 col-lg-4">
            <div class="kpi kpi-ok">
                <div class="kpi-label">
                    <i class="bi bi-wallet2"></i>
                    Cobrado en {{ now()->translatedFormat('F') }}
                </div>
                <div class="kpi-valor monto">
                    ${{ number_format($resumen['delMes'], 2) }}
                </div>
                <div class="kpi-pie">Solo los pagos completados</div>
            </div>
        </div>

        <div class="col-6 col-lg-4">
            <button type="button"
                    class="kpi {{ $resumen['sinAplicar'] > 0 ? 'kpi-warn' : 'kpi-apagado' }}"
                    wire:click="verSinAplicar">
                <div class="kpi-label">
                    <i class="bi bi-hourglass-split"></i> Sin aplicar
                </div>
                <div class="kpi-valor monto">
                    ${{ number_format($resumen['sinAplicar'], 2) }}
                </div>
                <div class="kpi-pie">
                    @if ($resumen['sinAplicarCant'] > 0)
                        En {{ $resumen['sinAplicarCant'] }}
                        {{ $resumen['sinAplicarCant'] === 1 ? 'pago' : 'pagos' }} ·
                        el cliente ya pagó
                    @else
                        Todo aplicado
                    @endif
                </div>
            </button>
        </div>

        <div class="col-6 col-lg-4">
            <button type="button"
                    class="kpi {{ $resumen['conProblema'] > 0 ? 'kpi-bad' : 'kpi-apagado' }}"
                    wire:click="$set('estado', '{{ \App\Enums\PaymentStatus::Disputed->value }}')">
                <div class="kpi-label">
                    <i class="bi bi-exclamation-triangle"></i> Con problema
                </div>
                <div class="kpi-valor">{{ $resumen['conProblema'] }}</div>
                <div class="kpi-pie">
                    {{ $resumen['conProblema'] > 0 ? 'Fallidos o en disputa' : 'Ninguno' }}
                </div>
            </button>
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
                        <input type="search" class="form-control"
                               placeholder="Número de pago, referencia o cliente…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <select class="form-select" wire:model.live="metodo">
                        <option value="">Todos los métodos</option>
                        @foreach ($metodos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="estado">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-2 d-flex align-items-center gap-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox"
                               id="solo-sin-aplicar"
                               wire:model.live="soloSinAplicar">
                        <label class="form-check-label small" for="solo-sin-aplicar">
                            Sin aplicar
                        </label>
                    </div>

                    @if ($buscar || $metodo || $estado || $soloSinAplicar)
                        <button class="btn btn-sm btn-outline-secondary" wire:click="limpiarFiltros"
                                title="Quitar los filtros">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    @endif
                </div>

            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead>
                        <tr>
                            <th role="button" wire:click="ordenar('payment_number')">
                                Número
                                @if ($ordenarPor === 'payment_number')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th>Cliente</th>
                            <th>Método</th>

                            <th role="button" wire:click="ordenar('received_at')">
                                Recibido
                                @if ($ordenarPor === 'received_at')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th class="text-end" role="button" wire:click="ordenar('amount')">
                                Monto
                                @if ($ordenarPor === 'amount')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th class="text-end" role="button" wire:click="ordenar('unapplied_amount')">
                                Sin aplicar
                                @if ($ordenarPor === 'unapplied_amount')
                                    <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                @endif
                            </th>

                            <th>Estado</th>
                            <th class="text-end" style="width: 90px;">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($pagos as $p)

                            @php
                                $sinAplicar = (float) $p->unapplied_amount > 0.001;

                                $roto = in_array($p->status, [
                                    \App\Enums\PaymentStatus::Failed,
                                    \App\Enums\PaymentStatus::Refunded,
                                ], true);

                                /*
                                 | El dinero sin aplicar manda sobre el
                                 | estado.
                                 |
                                 | Un pago "completado" con saldo sin
                                 | aplicar está a medias: entró el dinero
                                 | pero no se descontó de ninguna factura.
                                 | Pintarlo de verde escondería justo lo
                                 | que hay que resolver.
                                 */
                                $franja = $roto
                                    ? 'fila-mute'
                                    : ($sinAplicar ? 'fila-warn' : match ($p->status->color()) {
                                        'green'  => 'fila-ok',
                                        'yellow' => 'fila-warn',
                                        'red'    => 'fila-bad',
                                        'blue'   => 'fila-info',
                                        default  => 'fila-mute',
                                    });
                            @endphp

                            <tr wire:key="pago-{{ $p->id }}"
                                class="fila-estado {{ $franja }} {{ $roto ? 'opacity-50' : '' }}">

                                <td>
                                    <a href="{{ route('finanzas.pagos.show', $p) }}" class="doc-numero">
                                        {{ $p->payment_number }}
                                    </a>
                                    @if ($p->is_deposit)
                                        <span class="badge text-bg-secondary">Anticipo</span>
                                    @endif
                                </td>

                                <td>
                                    <div class="fw-medium">{{ $p->customer?->name ?? '—' }}</div>
                                    @if ($p->reference)
                                        <div class="small text-secondary">Ref. {{ $p->reference }}</div>
                                    @endif
                                </td>

                                <td class="small">{{ $p->method->label() }}</td>

                                <td class="small">{{ $p->received_at?->format('d/m/Y') }}</td>

                                <td class="text-end fw-semibold monto">
                                    ${{ number_format((float) $p->amount, 2) }}
                                </td>

                                <td class="text-end monto">
                                    @if ($sinAplicar)
                                        <span class="text-warning fw-semibold">
                                            ${{ number_format((float) $p->unapplied_amount, 2) }}
                                        </span>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>

                                <td>
                                    <x-ui.badge :color="$p->status->color()" :label="$p->status->label()" />
                                </td>

                                <td class="text-end">
                                    <div class="acciones">
                                        <a href="{{ route('finanzas.pagos.show', $p) }}"
                                           class="acc acc-ver" title="Ver el pago y a qué facturas se aplicó">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        {{--
                                            El atajo de "aplicar lo que
                                            sobró".

                                            Lleva a la ficha del pago, que
                                            es donde se reparte el dinero
                                            entre facturas. Solo aparece
                                            cuando queda algo por aplicar:
                                            es la acción pendiente de esa
                                            fila.
                                        --}}
                                        @if ($sinAplicar && ! $roto)
                                            <a href="{{ route('finanzas.pagos.show', $p) }}"
                                               class="acc acc-editar" title="Aplicar el saldo a una factura">
                                                <i class="bi bi-arrow-right-circle"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="vacio">
                                        <i class="bi bi-wallet2"></i>

                                        @if ($buscar || $metodo || $estado || $soloSinAplicar)
                                            No hay pagos que coincidan con el filtro.
                                            <div class="mt-2">
                                                <button class="btn btn-sm btn-outline-secondary"
                                                        wire:click="limpiarFiltros">
                                                    Quitar los filtros
                                                </button>
                                            </div>
                                        @else
                                            Todavía no hay pagos registrados.
                                            <div class="mt-2">
                                                <a href="{{ route('finanzas.pagos.create') }}"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="bi bi-plus-lg me-1"></i> Registrar el primero
                                                </a>
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

        @if ($pagos->hasPages())
            <div class="card-footer">
                {{ $pagos->links() }}
            </div>
        @endif

    </div>

</div>
