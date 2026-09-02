{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL PRESUPUESTO
    ═══════════════════════════════════════════════════════════════════════

    Dos usos en la misma pantalla:

      EN LA PANTALLA  el documento más una barra de acciones y el
                      desglose interno de los grupos.

      AL IMPRIMIR     solo el documento. La barra de acciones, el menú y
                      el desglose desaparecen.

    Eso lo hace el bloque @media print del final. Es un PDF de mentira
    —el navegador lo genera al imprimir— pero funciona hoy y sin
    instalar nada. El PDF de verdad, con el logo y la plantilla de cada
    empresa, va en el módulo de facturas.
--}}
<div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show no-imprimir">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show no-imprimir">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         BARRA DE ACCIONES

         Cada botón aparece solo cuando tiene sentido. Un botón
         deshabilitado no explica por qué está deshabilitado; un botón
         que no está no genera la pregunta.
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-imprimir">

        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('comercial.presupuestos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>

            <div>
                <h4 class="mb-0">
                    Presupuesto {{ $estimate->estimate_number }}
                    <x-ui.badge :color="$estimate->status->color()"
                                :label="$estimate->status->label()" />
                </h4>

                <small class="text-secondary">
                    {{ $estimate->customer?->name }}
                    @if ($estimate->isExpired())
                        · <span class="text-danger">Venció el {{ $estimate->valid_until->format('d/m/Y') }}</span>
                    @endif
                </small>
            </div>
        </div>

        <div class="btn-group">

            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Imprimir
            </button>

            @if ($estimate->isEditable())
                <a href="{{ route('comercial.presupuestos.edit', $estimate) }}"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i> Editar
                </a>
            @endif

            <button class="btn btn-outline-secondary" wire:click="duplicar">
                <i class="bi bi-files me-1"></i> Duplicar
            </button>

            @if ($estimate->status === \App\Enums\EstimateStatus::Draft)
                <button class="btn btn-primary" wire:click="marcarEnviado">
                    <i class="bi bi-send me-1"></i> Marcar como enviado
                </button>
            @endif

            @if ($estimate->status === \App\Enums\EstimateStatus::Sent)
                <button class="btn btn-success" wire:click="marcarAceptado">
                    <i class="bi bi-check-lg me-1"></i> El cliente aceptó
                </button>
                <button class="btn btn-outline-danger" wire:click="confirmar('rechazar')">
                    <i class="bi bi-x-lg me-1"></i> Rechazó
                </button>
            @endif

            @if ($estimate->status->canConvert())
                <button class="btn btn-warning" wire:click="confirmar('convertir')">
                    <i class="bi bi-receipt me-1"></i> Convertir en factura
                </button>
            @endif

            @if ($estimate->status->is(\App\Enums\EstimateStatus::Rejected, \App\Enums\EstimateStatus::Expired))
                <button class="btn btn-outline-primary" wire:click="reabrir">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reabrir
                </button>
            @endif

        </div>
    </div>

    {{-- Si ya se convirtió, se avisa arriba del todo --}}
    @if ($estimate->invoice)
        <div class="alert alert-success no-imprimir">
            <i class="bi bi-receipt me-1"></i>
            Este presupuesto se convirtió en la factura
            <strong>{{ $estimate->invoice->invoice_number }}</strong>
            el {{ $estimate->invoice->issue_date?->format('d/m/Y') }}.
            <span class="text-secondary">
                (La pantalla de facturas se construye en el siguiente paso.)
            </span>
        </div>
    @endif

    {{-- ═════════════════════════════════════════════════════════════
         EL DOCUMENTO
    ═════════════════════════════════════════════════════════════ --}}
    <div class="card documento">
        <div class="card-body p-4">

            {{-- ───── ENCABEZADO ───── --}}
            <div class="row mb-4">

                <div class="col-7">
                    <h5 class="mb-1">{{ $estimate->company?->name }}</h5>
                    <div class="small text-secondary">
                        {{ $estimate->company?->legal_name }}<br>
                        @if ($estimate->company?->address_line1)
                            {{ $estimate->company->address_line1 }}<br>
                        @endif
                        {{ collect([$estimate->company?->city, $estimate->company?->state, $estimate->company?->zip])->filter()->implode(', ') }}<br>
                        @if ($estimate->company?->phone) {{ $estimate->company->phone }} @endif
                        @if ($estimate->company?->email) · {{ $estimate->company->email }} @endif
                    </div>
                </div>

                <div class="col-5 text-end">
                    <div class="text-uppercase text-secondary small">Presupuesto</div>
                    <div class="fs-4 fw-semibold">{{ $estimate->estimate_number }}</div>

                    <table class="table table-sm table-borderless mb-0 mt-2">
                        <tr>
                            <td class="text-secondary text-end py-0">Emisión</td>
                            <td class="text-end py-0">{{ $estimate->issue_date?->format('d/m/Y') }}</td>
                        </tr>
                        @if ($estimate->valid_until)
                            <tr>
                                <td class="text-secondary text-end py-0">Válido hasta</td>
                                <td class="text-end py-0">{{ $estimate->valid_until->format('d/m/Y') }}</td>
                            </tr>
                        @endif
                        @if ($estimate->terms)
                            <tr>
                                <td class="text-secondary text-end py-0">Términos</td>
                                <td class="text-end py-0">{{ $estimate->terms }}</td>
                            </tr>
                        @endif
                    </table>
                </div>

            </div>

            {{-- ───── BILL TO / SHIP TO (RB-035) ───── --}}
            <div class="row mb-4">

                <div class="col-6">
                    <div class="small text-uppercase text-secondary fw-semibold mb-1">Facturar a</div>
                    <div class="fw-semibold">{{ $estimate->customer?->name }}</div>
                    <div class="small">
                        {{--
                            Se arma la dirección saltando las partes vacías.

                            Sin el filter(), un cliente sin "línea 2" saldría
                            con dos comas seguidas, que es de esas cosas que
                            nadie reporta pero todos notan en un documento
                            que se le manda a un cliente.

                            El 'label' se salta a propósito: es el nombre
                            interno de la dirección ("Oficina", "Yarda"),
                            no parte de la dirección misma.
                        --}}
                        {{ collect($estimate->bill_to ?? [])
                            ->except('label')
                            ->filter()
                            ->implode(', ') }}
                    </div>
                    @if ($estimate->customer?->primary_email)
                        <div class="small text-secondary">{{ $estimate->customer->primary_email }}</div>
                    @endif
                </div>

                @if ($estimate->ship_to)
                    <div class="col-6">
                        <div class="small text-uppercase text-secondary fw-semibold mb-1">Entregar en</div>
                        <div class="small">
                            {{ collect($estimate->ship_to)
                                ->except('label')
                                ->filter()
                                ->implode(', ') }}
                        </div>
                    </div>
                @endif

            </div>

            {{-- ───── LAS LÍNEAS, YA AGRUPADAS ───── --}}
            <table class="table table-sm">
                <thead class="table-light">
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Descripción</th>
                        <th class="text-end" style="width: 90px;">Cant.</th>
                        <th class="text-end" style="width: 120px;">Precio</th>
                        <th class="text-end" style="width: 120px;">Importe</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($renglones as $renglon)
                        <tr>
                            <td>{{ $loop->iteration }}</td>

                            <td>
                                {{ $renglon->description }}

                                @if ($renglon->container)
                                    <div class="small text-secondary">
                                        Unidad: {{ $renglon->container->full_identifier }}
                                    </div>
                                @endif

                                {{--
                                    EL DESGLOSE INTERNO.

                                    Solo se ve en pantalla, nunca impreso
                                    (RB-007: el desglose es interno).

                                    Está aquí porque quien revisa el
                                    presupuesto por dentro necesita ver de
                                    dónde salieron los $2,750 y por qué el
                                    7% se calculó solo sobre una parte.
                                --}}
                                @if ($renglon->detalle)
                                    <div class="small text-secondary mt-1 ps-3 border-start no-imprimir">
                                        @foreach ($renglon->detalle as $sub)
                                            <div>
                                                {{ $sub->description }}
                                                — ${{ number_format((float) $sub->amount, 2) }}
                                                @if ($sub->taxable)
                                                    <span class="text-success">(paga tax)</span>
                                                @else
                                                    <span>(sin tax)</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <td class="text-end">{{ rtrim(rtrim(number_format((float) $renglon->quantity, 2), '0'), '.') }}</td>
                            <td class="text-end">${{ number_format((float) $renglon->unit_price, 2) }}</td>
                            <td class="text-end fw-semibold">${{ number_format((float) $renglon->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- ───── LOS TOTALES ───── --}}
            <div class="row">
                <div class="col-md-7">
                    @if ($estimate->notes)
                        <div class="small text-uppercase text-secondary fw-semibold mb-1">Notas</div>
                        <p class="small">{{ $estimate->notes }}</p>
                    @endif
                </div>

                <div class="col-md-5">
                    <table class="table table-sm table-borderless mb-0">

                        <tr>
                            <td class="text-secondary">Subtotal</td>
                            <td class="text-end">${{ number_format((float) $estimate->subtotal, 2) }}</td>
                        </tr>

                        @if ((float) $estimate->discount_amount > 0)
                            <tr>
                                <td class="text-secondary">Descuento</td>
                                <td class="text-end">−${{ number_format((float) $estimate->discount_amount, 2) }}</td>
                            </tr>
                        @endif

                        <tr>
                            <td class="text-secondary">
                                Sales tax ({{ number_format((float) $estimate->tax_rate, 2) }}%)
                                @if ($estimate->tax_exempt)
                                    <span class="badge text-bg-info">Exento</span>
                                @endif
                            </td>
                            <td class="text-end">${{ number_format((float) $estimate->tax_amount, 2) }}</td>
                        </tr>

                        @if ((float) $estimate->credit_card_fee > 0)
                            <tr>
                                <td class="text-secondary">
                                    Credit card fee ({{ number_format((float) $estimate->credit_card_fee_percent, 2) }}%)
                                </td>
                                <td class="text-end">${{ number_format((float) $estimate->credit_card_fee, 2) }}</td>
                            </tr>
                        @endif

                        <tr class="border-top">
                            <td class="fs-5 fw-semibold pt-2">Total</td>
                            <td class="fs-5 fw-semibold text-end pt-2">
                                ${{ number_format((float) $estimate->total, 2) }}
                            </td>
                        </tr>

                    </table>

                    {{--
                        La base gravable, solo en pantalla.

                        Es el dato que evita la pregunta "¿por qué el
                        impuesto no es el 7% del total?". No se imprime
                        porque al cliente se le da un precio consolidado.
                    --}}
                    <div class="small text-secondary mt-2 no-imprimir">
                        Impuesto calculado sobre
                        ${{ number_format((float) $estimate->taxable_base, 2) }}
                        (el transporte no paga sales tax en Florida).
                    </div>
                </div>
            </div>

            {{-- ───── PIE ───── --}}
            @if ($estimate->footer_terms || $estimate->company?->invoice_footer_terms)
                <hr>
                <p class="small text-secondary mb-0">
                    {{ $estimate->footer_terms ?: $estimate->company?->invoice_footer_terms }}
                </p>
            @endif

        </div>
    </div>

    {{-- ─────────────────────────────────────────────────────────────
         DATOS INTERNOS

         Todo lo que el sistema sabe y el cliente no ve.
    ───────────────────────────────────────────────────────────── --}}
    <div class="card mt-3 no-imprimir">
        <div class="card-header">
            <h6 class="card-title mb-0">Datos internos</h6>
        </div>
        <div class="card-body">
            <div class="row g-3 small">

                <div class="col-6 col-md-3">
                    <div class="text-secondary">Tipo de uso</div>
                    <div>{{ $estimate->use_type?->label() ?? '—' }}</div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="text-secondary">Vendedor</div>
                    <div>{{ $estimate->salesperson?->name ?? '— Sin asignar —' }}</div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="text-secondary">Enviado</div>
                    <div>{{ $estimate->sent_at?->format('d/m/Y H:i') ?? 'Todavía no' }}</div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="text-secondary">Empresa emisora</div>
                    <div>
                        <span class="badge rounded-pill"
                              style="background-color: {{ $estimate->company?->brand_color ?: '#334155' }}">
                            {{ $estimate->company?->code }}
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────────────────────
         CONFIRMACIONES
    ───────────────────────────────────────────────────────────── --}}
    @if ($confirmando === 'convertir')
        <div class="modal fade show d-block no-imprimir" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Convertir en factura</h5>
                        <button type="button" class="btn-close" wire:click="cancelarConfirmacion"></button>
                    </div>

                    <div class="modal-body">
                        <p>
                            Se va a emitir una factura con los mismos conceptos y el mismo
                            total: <strong>${{ number_format((float) $estimate->total, 2) }}</strong>.
                        </p>

                        <p class="small text-secondary mb-0">
                            La factura tomará el siguiente número de la secuencia de facturas
                            —no la de presupuestos— y nacerá en borrador para que pueda
                            revisarla antes de enviarla.
                        </p>

                        <div class="alert alert-warning small mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Una vez emitida, la factura ya no se borra: se anula. Y este
                            presupuesto queda bloqueado para edición.
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="cancelarConfirmacion">
                            Cancelar
                        </button>
                        <button class="btn btn-warning" wire:click="convertirEnFactura">
                            <i class="bi bi-receipt me-1"></i> Emitir factura
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

    @if ($confirmando === 'rechazar')
        <div class="modal fade show d-block no-imprimir" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">¿El cliente rechazó el presupuesto?</h5>
                        <button type="button" class="btn-close" wire:click="cancelarConfirmacion"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-0">
                            El presupuesto queda guardado con estado "Rechazada". No se
                            borra: sigue en el historial del cliente y se puede reabrir si
                            vuelve más adelante.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="cancelarConfirmacion">
                            Cancelar
                        </button>
                        <button class="btn btn-danger" wire:click="marcarRechazado">
                            Sí, lo rechazó
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         ESTILOS DE IMPRESIÓN

         @media print le habla solo a la impresora. En la pantalla no
         cambia nada.

         Lo que hace: esconde el menú lateral, la barra de arriba, el pie
         del sistema y todo lo marcado con la clase "no-imprimir", y le
         quita el borde y la sombra al recuadro del documento para que en
         el papel se vea como un papel y no como una tarjeta de una web.
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

            .documento {
                border: 0 !important;
                box-shadow: none !important;
            }

            /* Que las líneas no se corten a la mitad entre dos hojas. */
            table, tr, td, th {
                page-break-inside: avoid;
            }
        }
    </style>

</div>
