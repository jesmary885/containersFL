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

    {{--
        LA BARRA DE PASOS, TAMBIÉN ACÁ

        Esta pantalla ES el paso 3. Sin la barra, darle a Procesar se
        siente como haber salido del formulario y aterrizado en otro
        sitio; con ella se lee como lo que es: la última etapa de lo
        mismo.

        Solo mientras esté por revisar. Una vez enviado, el presupuesto
        ya no es un formulario a medias y la barra estorbaría.
    --}}
    @if ($estimate->status->isPendingReview())
        <div class="ps-barra no-imprimir">
            <a href="{{ route('comercial.presupuestos.edit', $estimate) }}" class="ps-paso ps-hecho">
                <span class="ps-bolita">✓</span>
                <span class="ps-texto">{{ __('estimates.step_who') }}</span>
            </a>
            <span class="ps-sep"></span>
            <a href="{{ route('comercial.presupuestos.edit', $estimate) }}" class="ps-paso ps-hecho">
                <span class="ps-bolita">✓</span>
                <span class="ps-texto">{{ __('estimates.step_what') }}</span>
            </a>
            <span class="ps-sep"></span>
            <span class="ps-paso ps-activo">
                <span class="ps-bolita">3</span>
                <span class="ps-texto">{{ __('estimates.step_review') }}</span>
            </span>
        </div>
    @endif

    @if ($estimate->status->isPendingReview())
        <div class="alert alert-primary d-flex align-items-start gap-2 no-imprimir">
            <i class="bi bi-eye fs-5"></i>
            <div>
                <strong>{{ __('estimates.review_title') }}</strong>
                <div class="small">{{ __('estimates.review_text') }}</div>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         BARRA DE ACCIONES

         Cada botón aparece solo cuando tiene sentido. Un botón
         deshabilitado no explica por qué está deshabilitado; un botón
         que no está no genera la pregunta.
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-imprimir">

        {{--
            LOS DOS BOTONES DE VOLVER

            Se acaba de guardar: lo que se quiere casi siempre es seguir
            tocando ESTE presupuesto, no irse a la lista. La flecha vuelve
            a la edición.

            Ir al listado es la otra intención y tiene su propio botón,
            con su propio icono. Antes las dos cosas colgaban de la misma
            flecha y ganaba la menos frecuente.

            Cuando el presupuesto ya no es editable —aceptado, convertido,
            vencido— no hay a dónde volver a editar: ahí la flecha sí va
            al listado, que es la única salida que queda.
        --}}
        <div class="d-flex align-items-center gap-3">

            <div class="btn-group">
                @if ($estimate->isEditable())
                    <a href="{{ route('comercial.presupuestos.edit', $estimate) }}"
                       class="btn btn-outline-secondary"
                       title="Seguir editando este presupuesto">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                @else
                    <a href="{{ route('comercial.presupuestos.index') }}"
                       class="btn btn-outline-secondary"
                       title="Volver al listado">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                @endif

                <a href="{{ route('comercial.presupuestos.index') }}"
                   class="btn btn-outline-secondary"
                   title="Todos los presupuestos">
                    <i class="bi bi-list-ul"></i>
                </a>
            </div>

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

        {{--
            ═══════════════════════════════════════════════════════════════
            LA BARRA DE ACCIONES, POR ETAPA
            ═══════════════════════════════════════════════════════════════

            Antes estaban todas siempre. Se podía imprimir, duplicar y
            convertir en factura un borrador a medio armar, y "El cliente
            aceptó" convivía con un documento que el cliente no había
            recibido nunca.

            Ahora hay dos momentos y cada uno enseña lo suyo:

              POR REVISAR   solo mirar, volver a editar, y enviar. Nada
                            más. Es el momento de comprobar, no de
                            operar.

              ENVIADO       ya salió: imprimir, duplicar, registrar la
                            respuesta del cliente, convertir en factura.

            Un botón que no está no genera la pregunta de por qué está
            deshabilitado.
        --}}
        <div class="btn-group">

            @if ($estimate->status->isPendingReview())

                {{-- ── ETAPA 1 · REVISAR ── --}}

                @can('estimates.update')
                    <a href="{{ route('comercial.presupuestos.edit', $estimate) }}"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-pencil me-1"></i> {{ __('estimates.back_to_edit') }}
                    </a>
                @endcan

                @can('estimates.send')
                    <button class="btn btn-success" wire:click="marcarEnviado">
                        <i class="bi bi-envelope-arrow-up me-1"></i> {{ __('estimates.send_now') }}
                    </button>
                @endcan

            @else

                {{-- ── ETAPA 2 · YA SALIÓ ── --}}

                @if ($estimate->status->isOut())
                    <button class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Imprimir
                    </button>
                @endif

                @if ($estimate->isEditable())
                    @can('estimates.update')
                        <a href="{{ route('comercial.presupuestos.edit', $estimate) }}"
                           class="btn btn-outline-secondary">
                            <i class="bi bi-pencil me-1"></i> Editar
                        </a>
                    @endcan
                @endif

                @if ($estimate->status->isOut())
                    @can('estimates.create')
                        <button class="btn btn-outline-secondary" wire:click="duplicar">
                            <i class="bi bi-files me-1"></i> Duplicar
                        </button>
                    @endcan
                @endif

                @if ($estimate->status === \App\Enums\EstimateStatus::Draft)
                    @can('estimates.send')
                        <button class="btn btn-primary" wire:click="marcarEnviado">
                            <i class="bi bi-send me-1"></i> Marcar como enviado
                        </button>
                    @endcan
                @endif

                {{--
                    "El cliente aceptó" y "Rechazó" NO van acá.

                    La respuesta del cliente no se conoce en el mismo
                    segundo en que se manda el correo: llega días
                    después, por teléfono. Un botón que dice "el cliente
                    aceptó" al lado de un documento que se acaba de
                    enviar invita a pulsarlo por inercia, y entonces el
                    estado del presupuesto deja de significar nada.

                    Se registra desde el LISTADO, que es donde se está
                    cuando el cliente llama. Los métodos marcarAceptado()
                    y marcarRechazado() del componente siguen ahí para
                    cuando se enganchen desde allá.
                --}}

                {{--
                    El permiso es de FACTURAS, no de presupuestos: lo que
                    hace este boton es emitir una factura. Un vendedor que
                    cotiza pero no factura no tiene por que verlo.
                --}}
                @if ($estimate->status->canConvert())
                    @can('invoices.create')
                        <button class="btn btn-warning" wire:click="confirmar('convertir')">
                            <i class="bi bi-receipt me-1"></i> Convertir en factura
                        </button>
                    @endcan
                @endif

            @endif

            @if ($estimate->status->is(\App\Enums\EstimateStatus::Rejected, \App\Enums\EstimateStatus::Expired))
                @can('estimates.update')
                    <button class="btn btn-outline-primary" wire:click="reabrir">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reabrir
                    </button>
                @endcan
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
                        {{-- El armado de la línea vive en el modelo: ver
                             HasDocumentAddresses::addressToLine(). --}}
                        {{ \App\Models\Estimate::addressToLine($estimate->bill_to) }}
                    </div>
                    @if ($estimate->customer?->primary_email)
                        <div class="small text-secondary">{{ $estimate->customer->primary_email }}</div>
                    @endif
                </div>

                {{--
                    ENTREGAR EN — se imprime SIEMPRE, aunque sea la misma.

                    Es como vienen las facturas del cliente y es lo correcto
                    en un documento que se manda afuera: nadie debería tener
                    que deducir a dónde iba la mercancía por el hecho de que
                    falte el bloque.

                    Cuando no se pidió otro destino, printableShipTo()
                    devuelve la de facturación. En la BASE ship_to sigue
                    siendo null: el porqué está explicado en el trait.
                --}}
                <div class="col-6">
                    <div class="small text-uppercase text-secondary fw-semibold mb-1">Entregar en</div>
                    <div class="small">
                        {{ \App\Models\Estimate::addressToLine($estimate->printableShipTo()) }}
                    </div>
                    @unless ($estimate->shipsElsewhere())
                        <div class="small text-secondary fst-italic">Misma dirección de facturación</div>
                    @endunless
                </div>

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
                                    EL PLAZO DE LA RENTA — sí se imprime.

                                    Sin él, el cliente lee "$850.00" y no
                                    tiene forma de saber si es el total o la
                                    mensualidad. Es la pregunta que llega por
                                    teléfono al día siguiente.

                                    El importe de la línea es la MENSUALIDAD.
                                    El compromiso total va aparte y en gris:
                                    es informativo, no es lo que se cobra hoy.
                                --}}
                                {{--
                                    EL DETALLE DE LA MODIFICACIÓN — sí se
                                    imprime, y va aquí y no en la
                                    descripción por una razón de formato:
                                    "dos puertas laterales, ventana con
                                    reja, pintura, piso de madera" dentro
                                    del renglón haría que la fila de
                                    importes ocupara cuatro líneas y la
                                    tabla dejara de leerse.
                                --}}
                                @if ($renglon->work_details)
                                    <div class="small text-secondary mt-1"
                                         style="white-space: pre-line; padding-left: .75rem; border-left: 2px solid #e2e8f0;">{{ $renglon->work_details }}</div>
                                @endif

                                {{--
                                    EL PLAZO Y EL TAX DE LA RENTA

                                    Se imprime el mes CON su tax porque es
                                    lo que va a decir cada factura. El tax
                                    de una renta se cobra por factura
                                    mensual, no una vez al firmar: en el
                                    Excel que lleva la empresa, una renta de
                                    $150 a 4 meses tiene la columna TAX en
                                    $10.50, que es el 7% de UN mes.

                                    El compromiso del plazo va debajo y en
                                    gris. Es informativo: no es lo que se
                                    cobra hoy ni lo que suma este
                                    presupuesto.
                                --}}
                                @if ($renglon->rental_months)
                                    @php
                                        $tasaR = $estimate->tax_exempt ? 0 : (float) $estimate->tax_rate;
                                        $taxR  = $renglon->rental_taxable ? round($renglon->monthly_rate * $tasaR / 100, 2) : 0.0;
                                    @endphp

                                    <div class="small">
                                        <i class="bi bi-calendar-range me-1"></i>
                                        ${{ number_format($renglon->monthly_rate + $taxR, 2) }}/mes
                                        @if ($taxR > 0)
                                            <span class="text-secondary">
                                                (${{ number_format($renglon->monthly_rate, 2) }}
                                                + ${{ number_format($taxR, 2) }} tax)
                                            </span>
                                        @endif
                                        ·
                                        {{ trans_choice('estimates.months_short', $renglon->rental_months, ['count' => $renglon->rental_months]) }}
                                    </div>
                                    <div class="small text-secondary">
                                        Compromiso del plazo:
                                        ${{ number_format(($renglon->monthly_rate + $taxR) * $renglon->rental_months, 2) }}
                                        — se factura mes a mes, una factura por mes.
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
