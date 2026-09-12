{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DE LA FACTURA
    ═══════════════════════════════════════════════════════════════════════

    Dos usos en la misma pantalla:

      EN PANTALLA   la factura, más la barra de acciones, los adjuntos,
                    los pagos recibidos y el desglose interno.

      AL IMPRIMIR   solo la factura. Todo lo marcado con la clase
                    "no-imprimir" desaparece.
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
         deshabilitado no explica por qué lo está; uno que no está no
         genera la pregunta.
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-imprimir">

        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('finanzas.facturacion.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>

            <div>
                <h4 class="mb-0">
                    Factura {{ $invoice->invoice_number }}
                    <x-ui.badge :color="$invoice->display_status->color()"
                                :label="$invoice->display_status->label()" />
                </h4>

                <small class="text-secondary">
                    {{ $invoice->customer?->name }}
                    @if ($invoice->isOverdue())
                        · <span class="text-danger">
                            {{ $invoice->days_overdue }}
                            {{ $invoice->days_overdue === 1 ? 'día' : 'días' }} de atraso
                        </span>
                    @endif
                </small>
            </div>
        </div>

        <div class="btn-group">

            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Imprimir
            </button>

            @if ($invoice->isEditable())
                @can('invoices.update')
                    <a href="{{ route('finanzas.facturacion.edit', $invoice) }}"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i> Corregir
                    </a>
                @endcan
            @endif

            @if ($invoice->status !== \App\Enums\InvoiceStatus::Void)
                @can('invoices.send')
                    <button class="btn btn-primary" wire:click="marcarEnviada">
                        <i class="bi bi-send me-1"></i>
                        {{ $invoice->sent_at ? 'Reenviar' : 'Marcar como enviada' }}
                    </button>
                @endcan
            @endif

            {{--
                Anular tiene permiso propio porque consume el numero para
                siempre. El RoleSeeder solo se lo da a contabilidad.
            --}}
            @if ($invoice->status !== \App\Enums\InvoiceStatus::Void)
                @can('invoices.void')
                    <button class="btn btn-outline-danger" wire:click="confirmar('anular')">
                        <i class="bi bi-x-octagon me-1"></i> Anular
                    </button>
                @endcan
            @endif

        </div>
    </div>

    {{-- ─────────────────────────────────────────────────────────────
         AVISOS DE ESTADO
    ───────────────────────────────────────────────────────────── --}}

    @if ($invoice->status === \App\Enums\InvoiceStatus::Void)
        <div class="alert alert-secondary no-imprimir">
            <strong><i class="bi bi-x-octagon me-1"></i> Factura anulada</strong>
            el {{ $invoice->voided_at?->format('d/m/Y') }}.
            <div class="mt-1">Motivo: {{ $invoice->void_reason }}</div>
            <div class="small text-secondary mt-2">
                El número {{ $invoice->invoice_number }} queda consumido y no se reutiliza.
                Eso es lo correcto: un hueco en la numeración es lo que busca una auditoría.
            </div>
        </div>
    @endif

    @if ($invoice->estimate)
        <div class="alert alert-info no-imprimir">
            <i class="bi bi-file-earmark-text me-1"></i>
            Emitida a partir del presupuesto
            <a href="{{ route('comercial.presupuestos.show', $invoice->estimate) }}">
                {{ $invoice->estimate->estimate_number }}
            </a>.
        </div>
    @endif

    <div class="row g-3">

        {{-- ═════════════════════════════════════════════════════
             EL DOCUMENTO
        ═════════════════════════════════════════════════════ --}}
        <div class="col-12 col-xl-8">
            <div class="card documento">
                <div class="card-body p-4">

                    {{-- ENCABEZADO --}}
                    <div class="row mb-4">

                        <div class="col-7">
                            <h5 class="mb-1">{{ $invoice->company?->name }}</h5>
                            <div class="small text-secondary">
                                {{ $invoice->company?->legal_name }}<br>
                                @if ($invoice->company?->address_line1)
                                    {{ $invoice->company->address_line1 }}<br>
                                @endif
                                {{ collect([$invoice->company?->city, $invoice->company?->state, $invoice->company?->zip])->filter()->implode(', ') }}<br>
                                @if ($invoice->company?->phone) {{ $invoice->company->phone }} @endif
                                @if ($invoice->company?->email) · {{ $invoice->company->email }} @endif
                            </div>
                        </div>

                        <div class="col-5 text-end">
                            <div class="text-uppercase text-secondary small">
                                {{ $invoice->type?->label() === 'Venta' ? 'Invoice' : $invoice->type?->label() }}
                            </div>
                            <div class="fs-4 fw-semibold">{{ $invoice->invoice_number }}</div>

                            <table class="table table-sm table-borderless mb-0 mt-2">
                                <tr>
                                    <td class="text-secondary text-end py-0">Emisión</td>
                                    <td class="text-end py-0">{{ $invoice->issue_date?->format('d/m/Y') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-secondary text-end py-0">Vence</td>
                                    <td class="text-end py-0">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td>
                                </tr>
                                @if ($invoice->terms)
                                    <tr>
                                        <td class="text-secondary text-end py-0">Términos</td>
                                        <td class="text-end py-0">{{ $invoice->terms }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>

                    </div>

                    {{--
                        EL PERÍODO DE SERVICIO (RB-023)

                        Va destacado y arriba de las líneas. En las
                        facturas viejas ese dato lo metían en el campo
                        TRACKING#, que es donde cabía. Aquí tiene su sitio
                        propio, porque sin él el cliente no sabe qué mes
                        está pagando.
                    --}}
                    @if ($invoice->service_period_start)
                        <div class="border rounded p-2 mb-3 bg-body-tertiary">
                            <strong class="small text-uppercase text-secondary">Período facturado:</strong>
                            {{ $invoice->service_period_start->format('d/m/Y') }}
                            —
                            {{ $invoice->service_period_end?->format('d/m/Y') }}
                        </div>
                    @endif

                    {{-- BILL TO / SHIP TO (RB-035) --}}
                    <div class="row mb-4">

                        <div class="col-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Facturar a</div>
                            <div class="fw-semibold">{{ $invoice->customer?->name }}</div>
                            <div class="small">
                                {{-- El armado de la línea vive en el modelo:
                                     ver HasDocumentAddresses::addressToLine(). --}}
                                {{ \App\Models\Invoice::addressToLine($invoice->bill_to) }}
                            </div>
                            @if ($invoice->customer?->primary_email)
                                <div class="small text-secondary">{{ $invoice->customer->primary_email }}</div>
                            @endif
                        </div>

                        {{--
                            ENTREGAR EN — se imprime SIEMPRE, aunque sea la
                            misma dirección.

                            Es como vienen las facturas del cliente y es lo
                            correcto en un documento fiscal: el bloque que
                            falta se lee como un dato omitido, no como
                            "coincide con el de arriba".

                            En la BASE ship_to sigue siendo null cuando no
                            se pidió otro destino. El porqué está en el
                            trait HasDocumentAddresses.
                        --}}
                        <div class="col-6">
                            <div class="small text-uppercase text-secondary fw-semibold mb-1">Entregar en</div>
                            <div class="small">
                                {{ \App\Models\Invoice::addressToLine($invoice->printableShipTo()) }}
                            </div>
                            @unless ($invoice->shipsElsewhere())
                                <div class="small text-secondary fst-italic">Misma dirección de facturación</div>
                            @endunless
                        </div>

                    </div>

                    {{-- LAS LÍNEAS, YA AGRUPADAS --}}
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Descripción</th>
                                <th style="width: 100px;">Fecha</th>
                                <th class="text-end" style="width: 80px;">Cant.</th>
                                <th class="text-end" style="width: 110px;">Precio</th>
                                <th class="text-end" style="width: 110px;">Importe</th>
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

                                            Solo en pantalla, nunca impreso
                                            (RB-007: el desglose es
                                            interno).

                                            Está aquí porque quien revisa la
                                            factura por dentro necesita ver
                                            de dónde salieron los $2,750 y
                                            por qué el 7% se calculó solo
                                            sobre una parte.
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

                                    <td class="small">
                                        {{ $renglon->service_date?->format('d/m/Y') ?? '' }}
                                    </td>

                                    <td class="text-end">
                                        {{ rtrim(rtrim(number_format((float) $renglon->quantity, 2), '0'), '.') }}
                                    </td>
                                    <td class="text-end">${{ number_format((float) $renglon->unit_price, 2) }}</td>
                                    <td class="text-end fw-semibold">${{ number_format((float) $renglon->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    {{-- LOS TOTALES --}}
                    <div class="row">
                        <div class="col-md-7">
                            @if ($invoice->notes)
                                <div class="small text-uppercase text-secondary fw-semibold mb-1">Notas</div>
                                <p class="small">{{ $invoice->notes }}</p>
                            @endif

                            @if ($invoice->expected_payment_method)
                                <div class="small text-secondary">
                                    Forma de pago prevista: {{ $invoice->expected_payment_method->label() }}
                                </div>
                            @endif
                        </div>

                        <div class="col-md-5">
                            <table class="table table-sm table-borderless mb-0">

                                <tr>
                                    <td class="text-secondary">Subtotal</td>
                                    <td class="text-end">${{ number_format((float) $invoice->subtotal, 2) }}</td>
                                </tr>

                                @if ((float) $invoice->discount_amount > 0)
                                    <tr>
                                        <td class="text-secondary">Descuento</td>
                                        <td class="text-end">−${{ number_format((float) $invoice->discount_amount, 2) }}</td>
                                    </tr>
                                @endif

                                <tr>
                                    <td class="text-secondary">
                                        Sales tax ({{ number_format((float) $invoice->tax_rate, 2) }}%)
                                        @if ($invoice->tax_exempt)
                                            <span class="badge text-bg-info">Exento</span>
                                        @endif
                                    </td>
                                    <td class="text-end">${{ number_format((float) $invoice->tax_amount, 2) }}</td>
                                </tr>

                                @if ((float) $invoice->credit_card_fee > 0)
                                    <tr>
                                        <td class="text-secondary">
                                            Credit card fee ({{ number_format((float) $invoice->credit_card_fee_percent, 2) }}%)
                                        </td>
                                        <td class="text-end">${{ number_format((float) $invoice->credit_card_fee, 2) }}</td>
                                    </tr>
                                @endif

                                @if ((float) $invoice->deposit_applied > 0)
                                    <tr>
                                        <td class="text-secondary">Depósito aplicado</td>
                                        <td class="text-end">−${{ number_format((float) $invoice->deposit_applied, 2) }}</td>
                                    </tr>
                                @endif

                                <tr class="border-top">
                                    <td class="fw-semibold pt-2">Total</td>
                                    <td class="fw-semibold text-end pt-2">
                                        ${{ number_format((float) $invoice->total, 2) }}
                                    </td>
                                </tr>

                                @if ((float) $invoice->amount_paid > 0)
                                    <tr>
                                        <td class="text-success">Pagado</td>
                                        <td class="text-end text-success">
                                            −${{ number_format((float) $invoice->amount_paid, 2) }}
                                        </td>
                                    </tr>
                                @endif

                                <tr class="border-top">
                                    <td class="fs-5 fw-semibold pt-2">Saldo</td>
                                    <td class="fs-5 fw-semibold text-end pt-2
                                               {{ (float) $invoice->balance_due > 0 ? 'text-danger' : 'text-success' }}">
                                        ${{ number_format((float) $invoice->balance_due, 2) }}
                                    </td>
                                </tr>

                            </table>

                            <div class="small text-secondary mt-2 no-imprimir">
                                Impuesto calculado sobre
                                ${{ number_format((float) $invoice->taxable_base, 2) }}
                                (el transporte no paga sales tax en Florida).
                            </div>
                        </div>
                    </div>

                    @if ($invoice->footer_terms || $invoice->company?->invoice_footer_terms)
                        <hr>
                        <p class="small text-secondary mb-0">
                            {{ $invoice->footer_terms ?: $invoice->company?->invoice_footer_terms }}
                        </p>
                    @endif

                </div>
            </div>
        </div>

        {{-- ═════════════════════════════════════════════════════
             COLUMNA DERECHA · TODO LO QUE NO SE IMPRIME
        ═════════════════════════════════════════════════════ --}}
        <div class="col-12 col-xl-4 no-imprimir">

            {{-- PAGOS RECIBIDOS --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">Pagos recibidos</h6>
                </div>
                <div class="card-body">
                    @forelse ($invoice->payments as $pago)
                        <div class="d-flex justify-content-between align-items-start mb-2"
                             wire:key="pago-{{ $pago->id }}">
                            <div>
                                <div class="fw-semibold">{{ $pago->method?->label() }}</div>
                                <div class="small text-secondary">
                                    {{ $pago->received_at?->format('d/m/Y') }}
                                    @if ($pago->reference) · {{ $pago->reference }} @endif
                                </div>
                            </div>
                            <span class="fw-semibold text-success">
                                ${{ number_format((float) $pago->pivot->amount, 2) }}
                            </span>
                        </div>
                    @empty
                        <div class="text-secondary small text-center py-2">
                            Todavía no se ha cobrado nada de esta factura.
                            <div class="mt-1">La pantalla de cobros llega en el paso siguiente.</div>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- ADJUNTOS (RB-034) --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-paperclip me-1"></i> Documentos adjuntos
                    </h6>
                </div>

                <div class="card-body">

                    {{-- Lista --}}
                    @forelse ($invoice->documents as $documento)
                        <div class="d-flex justify-content-between align-items-start border-bottom py-2"
                             wire:key="doc-{{ $documento->id }}">

                            <div class="me-2" style="min-width: 0;">
                                <div class="fw-semibold text-truncate">{{ $documento->name }}</div>
                                <div class="small text-secondary">
                                    {{ $documento->category?->label() }}
                                    · {{ $documento->readable_size }}

                                    @if ($documento->attach_to_invoice)
                                        <span class="badge text-bg-primary">Viaja con la factura</span>
                                    @else
                                        <span class="badge text-bg-secondary">Solo interno</span>
                                    @endif
                                </div>
                            </div>

                            <div class="btn-group btn-group-sm flex-shrink-0">
                                <button class="btn btn-outline-secondary"
                                        wire:click="descargar({{ $documento->id }})"
                                        title="Descargar">
                                    <i class="bi bi-download"></i>
                                </button>

                                @can('invoices.update')
                                    <button class="btn btn-outline-danger"
                                            wire:click="quitarArchivo({{ $documento->id }})"
                                            title="Quitar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            </div>

                        </div>
                    @empty
                        <div class="text-secondary small text-center py-2">
                            Sin documentos adjuntos.
                        </div>
                    @endforelse

                    {{-- Subir uno nuevo --}}
                    <div class="mt-3 pt-3 border-top">

                        <label class="form-label small">Adjuntar documento</label>

                        <input type="file"
                               class="form-control form-control-sm @error('archivo') is-invalid @enderror"
                               wire:model="archivo">
                        @error('archivo') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        {{--
                            wire:loading con target: solo se muestra
                            mientras SUBE ESTE archivo, no en cualquier
                            acción de la pantalla.
                        --}}
                        <div wire:loading wire:target="archivo" class="small text-secondary mt-1">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                            Subiendo archivo…
                        </div>

                        <select class="form-select form-select-sm mt-2" wire:model="categoriaArchivo">
                            @foreach ($categorias as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>

                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox"
                                   id="viaja-con-factura" wire:model="viajaConLaFactura">
                            <label class="form-check-label small" for="viaja-con-factura">
                                Enviárselo al cliente con la factura
                            </label>
                        </div>

                        <div class="form-text">
                            Desmárcalo para papeles internos, como la autorización de
                            tarjeta firmada: esa no sale de la oficina.
                        </div>

                        @can('invoices.update')
                            <button class="btn btn-sm btn-primary w-100 mt-2"
                                    wire:click="subirArchivo"
                                    wire:loading.attr="disabled">
                                <i class="bi bi-upload me-1"></i> Adjuntar
                            </button>
                        @endcan

                    </div>

                </div>
            </div>

            {{-- DATOS INTERNOS --}}
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0">Datos internos</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 small">

                        <div class="col-6">
                            <div class="text-secondary">Tipo</div>
                            <div>{{ $invoice->type?->label() }}</div>
                        </div>

                        <div class="col-6">
                            <div class="text-secondary">Emitida por</div>
                            <div>{{ $invoice->createdBy?->name ?? '—' }}</div>
                        </div>

                        <div class="col-6">
                            <div class="text-secondary">Enviada</div>
                            <div>{{ $invoice->sent_at?->format('d/m/Y H:i') ?? 'Todavía no' }}</div>
                        </div>

                        <div class="col-6">
                            <div class="text-secondary">Empresa emisora</div>
                            <div>
                                <span class="badge rounded-pill"
                                      style="background-color: {{ $invoice->company?->brand_color ?: '#334155' }}">
                                    {{ $invoice->company?->code }}
                                </span>
                            </div>
                        </div>

                        {{--
                            EL CERTIFICADO DE EXENCIÓN (RB-015)

                            Si la factura va exenta, aquí se ve CUÁL
                            certificado lo justifica. Si dice "sin
                            certificado", hay un problema: el impuesto no
                            se cobró y no hay nada que lo respalde ante el
                            estado.
                        --}}
                        @if ($invoice->tax_exempt)
                            <div class="col-12">
                                <div class="text-secondary">Certificado de exención</div>
                                @if ($invoice->exemptionCertificate)
                                    <div class="text-success">
                                        <i class="bi bi-patch-check me-1"></i>
                                        {{ $invoice->exemptionCertificate->certificate_number }}
                                        (vence {{ $invoice->exemptionCertificate->valid_until?->format('d/m/Y') }})
                                    </div>
                                @else
                                    <div class="text-danger">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Sin certificado adjunto. El impuesto no se cobró y no hay
                                        nada que lo respalde ante el estado.
                                    </div>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         CONFIRMACIÓN DE ANULACIÓN

         Modal dibujado con una condición de PHP en vez del JavaScript
         de Bootstrap. Motivo: Livewire vuelve a pintar este pedazo de
         página cada vez que algo cambia, y un modal abierto por
         JavaScript se queda colgado cuando eso pasa.
    ───────────────────────────────────────────────────────────── --}}
    @if ($confirmando === 'anular')
        <div class="modal fade show d-block no-imprimir" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Anular la factura {{ $invoice->invoice_number }}</h5>
                        <button type="button" class="btn-close" wire:click="cancelarConfirmacion"></button>
                    </div>

                    <div class="modal-body">

                        <p>
                            La factura no se borra: queda marcada como anulada, con su número
                            gastado y este motivo escrito.
                        </p>

                        <label class="form-label">
                            ¿Por qué se anula? <span class="text-danger">*</span>
                        </label>

                        <textarea class="form-control @error('motivoAnulacion') is-invalid @enderror"
                                  rows="3"
                                  placeholder="Ej: Error en la dirección de entrega, se reemplaza por la factura 1361."
                                  wire:model="motivoAnulacion"></textarea>

                        @error('motivoAnulacion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <div class="form-text">
                            Dentro de dos años, cuando alguien pregunte por qué el número
                            {{ $invoice->invoice_number }} no cobró nada, la respuesta tiene que
                            estar aquí y no en la memoria de nadie.
                        </div>

                        @if ($invoice->estimate)
                            <div class="alert alert-info small mt-3 mb-0">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                El presupuesto {{ $invoice->estimate->estimate_number }} volverá a
                                quedar como "Aceptada", para poder corregirlo y facturarlo de nuevo.
                            </div>
                        @endif

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="cancelarConfirmacion">
                            Cancelar
                        </button>
                        <button class="btn btn-danger" wire:click="anular">
                            <i class="bi bi-x-octagon me-1"></i> Anular la factura
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         ESTILOS DE IMPRESIÓN

         @media print le habla solo a la impresora. En pantalla no cambia
         nada.
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

            /* La columna del documento pasa a ocupar el ancho completo. */
            .col-xl-8 {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100% !important;
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
