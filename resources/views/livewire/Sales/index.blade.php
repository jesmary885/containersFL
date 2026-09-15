<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0">Ventas</h4>
            <small class="text-secondary">
                Lo que se vendió, lo que costó y lo que quedó.
            </small>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary" wire:click="mesAnterior">
                <i class="bi bi-chevron-left"></i>
            </button>
            <span class="btn btn-outline-secondary disabled text-capitalize">
                {{ $titulo }}
            </span>
            <button type="button" class="btn btn-outline-secondary" wire:click="mesSiguiente">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>

    @php $t = $this->totales; @endphp

    {{--
        ═══════════════════════════════════════════════════════════════════
        EL AVISO DE QUÉ NO INCLUYE ESTE NÚMERO

        Va arriba y no en letra chica al final, a propósito.

        Un margen que se lee como final y no lo es hace tomar decisiones
        equivocadas: se baja un precio creyendo que hay holgura que en
        realidad se la come el chofer.
    --}}
    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle me-1"></i>
        Este margen descuenta <strong>el costo de las unidades</strong> y
        <strong>la comisión del vendedor</strong>. Todavía <strong>no descuenta el pago
        al chofer ni los gastos generales</strong>, porque esos dos módulos están en
        construcción. Cuando existan, este número bajará.
    </div>

    {{-- ═══════════ LOS NÚMEROS DEL MES ═══════════ --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-secondary">Vendido</div>
                    <div class="fs-4 fw-semibold">${{ number_format($t['vendido'], 2) }}</div>
                    <div class="small text-secondary">
                        {{ $t['unidades'] }}
                        {{ $t['unidades'] === 1 ? 'contenedor' : 'contenedores' }}
                        en {{ $t['facturas'] }}
                        {{ $t['facturas'] === 1 ? 'factura' : 'facturas' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-secondary">Costo de las unidades</div>
                    <div class="fs-4 fw-semibold">${{ number_format($t['costo'], 2) }}</div>
                    <div class="small text-secondary">
                        compra + recogida + arreglos
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-secondary">Comisiones</div>
                    <div class="fs-4 fw-semibold">${{ number_format($t['comision'], 2) }}</div>
                    <div class="small text-secondary">
                        lo pactado con el vendedor
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            {{--
                El resultado, destacado. Verde si queda dinero, rojo si no.

                Un margen negativo tiene que saltar a la vista: significa
                que esa unidad se vendió por debajo de lo que costó
                ponerla en la yarda.
            --}}
            <div class="card h-100 {{ $t['neto'] >= 0 ? 'text-bg-success' : 'text-bg-danger' }}">
                <div class="card-body">
                    <div class="small">Margen</div>
                    <div class="fs-4 fw-semibold">${{ number_format($t['neto'], 2) }}</div>
                    <div class="small">
                        @if ($t['porcentaje'] !== null)
                            {{ $t['porcentaje'] }}% sobre lo vendido
                        @else
                            sin ventas medibles este mes
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{--
        LAS VENTAS QUE NO SE PUEDEN MEDIR

        Las facturas emitidas antes de que el sistema guardara el costo.
        Se dicen aparte y no se suman: meterlas con un cero inflaría el
        margen y nadie sabría por qué.
    --}}
    @if ($t['sinCosto'] > 0)
        <div class="alert alert-warning py-2 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ $t['sinCosto'] }}
            {{ $t['sinCosto'] === 1 ? 'factura de este mes no entra' : 'facturas de este mes no entran' }}
            en el cálculo: se emitieron antes de que el sistema guardara el costo de la
            unidad. De esas no sabemos cuánto costó, y preferimos decirlo a inventar un
            cero que se leería como margen del 100%.
        </div>
    @endif

    {{-- ═══════════ FILTROS ═══════════ --}}
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">

            <div class="flex-grow-1" style="min-width: 220px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control"
                           placeholder="Número de factura o cliente…"
                           wire:model.live.debounce.300ms="buscar">
                </div>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       id="cobradas" wire:model.live="soloCobradas">
                <label class="form-check-label small" for="cobradas">
                    Solo las ya cobradas
                </label>
            </div>

            <div class="small text-secondary">
                Una venta facturada y no cobrada tiene margen en el papel y cero en el banco.
            </div>

        </div>
    </div>

    {{-- ═══════════ EL DETALLE ═══════════ --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Cliente</th>
                        <th>Unidades</th>
                        <th>Vendedor</th>
                        <th class="text-end">Vendido</th>
                        <th class="text-end">Costo</th>
                        <th class="text-end">Comisión</th>
                        <th class="text-end">Margen</th>
                        <th class="text-center">Cobro</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($facturas as $factura)
                    @php
                        $margen = $factura->margen_neto;
                        $pct    = $factura->margen_porcentaje;
                    @endphp

                    <tr wire:key="venta-{{ $factura->id }}">

                        <td>
                            <a href="{{ route('finanzas.facturacion.show', $factura) }}"
                               class="text-decoration-none fw-semibold">
                                {{ $factura->invoice_number }}
                            </a>
                            <div class="small text-secondary">
                                {{ $factura->issue_date?->format('d/m/Y') }}
                            </div>
                        </td>

                        <td>
                            {{ $factura->customer?->display_name
                               ?? $factura->customer?->company_name
                               ?? '—' }}
                        </td>

                        <td class="small">
                            @foreach ($factura->lineasDeVenta() as $linea)
                                <div>
                                    {{ $linea->container?->container_number
                                       ?? $linea->container?->internal_code
                                       ?? 'sin unidad' }}
                                </div>
                            @endforeach
                        </td>

                        <td class="small">
                            {{ $factura->salesperson?->name ?? '—' }}
                        </td>

                        <td class="text-end monto">
                            ${{ number_format($factura->ingreso_por_venta, 2) }}
                        </td>

                        <td class="text-end monto">
                            @if ($factura->costo_de_venta === null)
                                <span class="text-secondary" title="Factura anterior a que se guardara el costo">
                                    sin dato
                                </span>
                            @else
                                ${{ number_format($factura->costo_de_venta, 2) }}
                            @endif
                        </td>

                        <td class="text-end monto">
                            @if ((float) $factura->commission_amount > 0)
                                ${{ number_format((float) $factura->commission_amount, 2) }}
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>

                        <td class="text-end monto">
                            @if ($margen === null)
                                <span class="text-secondary">—</span>
                            @else
                                <span class="fw-semibold {{ $margen >= 0 ? 'text-success' : 'text-danger' }}">
                                    ${{ number_format($margen, 2) }}
                                </span>
                                @if ($pct !== null)
                                    <div class="small text-secondary">{{ $pct }}%</div>
                                @endif
                            @endif
                        </td>

                        <td class="text-center">
                            @if ((float) $factura->balance_due <= 0.01)
                                <span class="badge text-bg-success">Cobrada</span>
                            @else
                                <span class="badge text-bg-warning">
                                    Debe ${{ number_format((float) $factura->balance_due, 2) }}
                                </span>
                            @endif
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-4">
                            <i class="bi bi-graph-up d-block mb-2" style="font-size:1.6rem; color:#cbd5e1;"></i>
                            No hay ventas de contenedores en {{ $titulo }}.
                            <div class="small mt-1">
                                Aquí solo entran las facturas con al menos un renglón de venta de
                                contenedor. Una que solo cobra un delivery o una mora no es una venta.
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($facturas->hasPages())
            <div class="card-footer">
                {{ $facturas->links() }}
            </div>
        @endif
    </div>

</div>
