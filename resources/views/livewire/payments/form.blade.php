{{--
    ═══════════════════════════════════════════════════════════════════════
    REGISTRAR UN PAGO
    ═══════════════════════════════════════════════════════════════════════

    ── ESTA VISTA NO EXISTÍA ──

    El componente `App\Livewire\Payments\Form` estaba escrito entero —560
    líneas con la aplicación a facturas, la autorización de tarjeta y las
    tres validaciones de RB-011 a RB-013— y su archivo de pantalla nunca
    se creó.

    Por eso el botón "Cobrar" reventaba con:

        View [livewire.payments.form] not found

    No era un error del botón ni de la ruta: era que al llegar, no había
    nada que dibujar.

    ── CÓMO ESTÁ ORGANIZADA ──

    Izquierda: el dinero —quién paga, cuánto, cómo y cuándo—.
    Derecha:   a qué facturas se aplica.

    Las dos cosas a la vez, porque un pago sin aplicar es dinero que
    entró y no bajó ninguna deuda: aparece cobrado en caja y el cliente
    sigue saliendo como moroso.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Registrar un pago</h4>
            <small class="text-secondary">
                Registro del cobro y su aplicación a las facturas del cliente.
            </small>
        </div>

        <a href="{{ route('finanzas.pagos.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>

    </div>

    @if (session('error'))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
        </div>
    @endif

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder registrar el pago." />

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            {{-- ═══════════════════════════════════════════════════
                 IZQUIERDA · EL DINERO
            ═══════════════════════════════════════════════════ --}}
            <div class="col-12 col-lg-5">

                {{-- ───── QUIÉN PAGA ───── --}}
                <div class="card mb-3 seccion seccion-cliente">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">1</span>
                            <i class="bi bi-person-vcard"></i>
                            <span>Cliente</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        @if (! $customer_id)

                            <div class="input-group">
                                <span class="input-group-text bg-body">
                                    <i class="bi bi-search text-secondary"></i>
                                </span>
                                <input type="search"
                                       class="form-control @error('customer_id') is-invalid @enderror"
                                       placeholder="Nombre, número, teléfono o un contacto…"
                                       wire:model.live.debounce.400ms="buscarCliente">
                            </div>

                            @error('customer_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror

                            @if (strlen($buscarCliente) >= 2)
                                <div class="list-group mt-2">
                                    @forelse ($this->resultadosCliente as $c)
                                        <button type="button" class="list-group-item list-group-item-action"
                                                wire:key="cli-{{ $c->id }}"
                                                wire:click="seleccionarCliente({{ $c->id }})">
                                            <div class="fw-semibold">{{ $c->name }}</div>
                                            <div class="small text-secondary">{{ $c->customer_number }}</div>
                                        </button>
                                    @empty
                                        <div class="list-group-item text-secondary small">
                                            Nadie coincide con eso.
                                        </div>
                                    @endforelse
                                </div>
                            @endif

                        @else

                            <div class="d-flex justify-content-between align-items-center border rounded p-3">
                                <div>
                                    <div class="fw-semibold">{{ $cliente?->name }}</div>
                                    <div class="small text-secondary">
                                        {{ $this->facturasPendientes->count() }} facturas con saldo
                                    </div>
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        wire:click="quitarCliente">
                                    <i class="bi bi-x-lg me-1"></i> Cambiar
                                </button>
                            </div>

                        @endif

                    </div>
                </div>

                {{-- ───── CUÁNTO Y CÓMO ───── --}}
                <div class="card mb-3 seccion seccion-datos">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">2</span>
                            <i class="bi bi-cash-coin"></i>
                            <span>Datos del cobro</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-6">
                                <label class="form-label">Monto <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           wire:model.live.debounce.500ms="amount">
                                </div>
                                @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Recibido el <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('received_at') is-invalid @enderror"
                                       wire:model="received_at">
                                @error('received_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">Forma de pago <span class="text-danger">*</span></label>
                                <select class="form-select @error('method') is-invalid @enderror"
                                        wire:model.live="method">
                                    @foreach ($metodos as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">
                                    Referencia
                                    @if (\App\Enums\PaymentMethod::tryFrom($method)?->requiresReference())
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <input type="text"
                                       class="form-control @error('reference') is-invalid @enderror"
                                       placeholder="Número de cheque, confirmación de Zelle, ID de Square…"
                                       wire:model.blur="reference">
                                @error('reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">
                                    Es lo que permite encontrar el pago en el banco si el cliente
                                    dice que ya pagó.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           id="esDeposito" wire:model.live="is_deposit">
                                    <label class="form-check-label" for="esDeposito">
                                        Es un depósito o anticipo
                                    </label>
                                </div>
                                <div class="form-text">
                                    Dinero recibido antes de facturar. Queda a favor del cliente
                                    hasta que se aplique.
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Nota</label>
                                <textarea class="form-control" rows="2"
                                          wire:model.blur="notes"></textarea>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ───── LA TARJETA ───── --}}
                @if ($method === \App\Enums\PaymentMethod::CreditCard->value)

                    <div class="card mb-3 seccion seccion-entrega">
                        <div class="card-header">
                            <h6 class="seccion-titulo">
                                <span class="paso-num">3</span>
                                <i class="bi bi-credit-card"></i>
                                <span>Autorización de la tarjeta</span>
                            </h6>
                        </div>

                        <div class="card-body">

                            {{--
                                POR QUÉ ESTO NO SE PUEDE SALTAR

                                Han tenido fraudes: se pasa la tarjeta, el dueño
                                real ve el cargo, lo reclama al banco, y los
                                fondos más las penalidades se los come la empresa.

                                El papel firmado es lo único que protege. Por eso
                                el sistema no deja cobrar sin él, en vez de
                                confiar en que alguien se acuerde.
                            --}}
                            <div class="alert alert-warning py-2 small">
                                <i class="bi bi-shield-exclamation me-1"></i>
                                <strong>Sin el formulario firmado no se cobra.</strong>
                                Si después el dueño de la tarjeta reclama el cargo al banco,
                                ese papel es lo único que respalda a la empresa.
                            </div>

                            {{--
                                ═══════════════════════════════════════════
                                LA FACTURA NO TRAE EL 3.5%
                                ═══════════════════════════════════════════

                                El caso: se emitió la factura sin saber cómo
                                iba a pagar el cliente, así que salió sin
                                recargo. Después llama y dice que paga con
                                tarjeta.

                                Antes esto no tenía salida. O se cobraba lo
                                que dice la factura y el 3.5% lo ponía la
                                empresa, o se cobraba de más de lo que dice
                                el documento, que es justo lo que provoca un
                                chargeback.

                                El recargo va en la FACTURA, no en el pago:
                                es el documento que el cliente recibe y el
                                importe que autoriza al firmar. Por eso aquí
                                no se ajusta nada en silencio — se enseñan
                                los números y se ofrece corregir.
                            --}}
                            @if (count($this->facturasSinRecargo) > 0)
                                <div class="alert alert-danger py-2 small">

                                    <div class="mb-2">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        <strong>
                                            @if (count($this->facturasSinRecargo) === 1)
                                                Esta factura se emitió sin el recargo de tarjeta.
                                            @else
                                                Estas facturas se emitieron sin el recargo de tarjeta.
                                            @endif
                                        </strong>
                                        El cliente dijo entonces que no sabía cómo iba a pagar.
                                    </div>

                                    <table class="table table-sm mb-2 bg-body rounded">
                                        <thead>
                                            <tr>
                                                <th>Factura</th>
                                                <th class="text-end">Dice hoy</th>
                                                <th class="text-end">Recargo</th>
                                                <th class="text-end">Pasaría a</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->facturasSinRecargo as $f)
                                                <tr wire:key="sinrec-{{ $f['id'] }}">
                                                    <td class="doc-numero">{{ $f['numero'] }}</td>
                                                    <td class="text-end monto">${{ number_format($f['saldo'], 2) }}</td>
                                                    <td class="text-end monto">
                                                        +${{ number_format($f['recargo'], 2) }}
                                                        <span class="text-secondary">
                                                            ({{ rtrim(rtrim(number_format($f['porcentaje'], 2), '0'), '.') }}%)
                                                        </span>
                                                    </td>
                                                    <td class="text-end monto fw-semibold">
                                                        ${{ number_format($f['nuevoSaldo'], 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <button type="button" class="btn btn-sm btn-danger"
                                            wire:click="agregarRecargoDeTarjeta"
                                            wire:loading.attr="disabled">
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Agregar el recargo y actualizar el cobro
                                    </button>

                                    <div class="mt-2">
                                        <strong>Después hay que reenviarle la factura al cliente</strong>,
                                        y el formulario de autorización tiene que firmarse por el
                                        importe nuevo. Si el papel dice un monto y en la tarjeta se
                                        pasa otro, el papel deja de proteger a la empresa.
                                    </div>

                                    <div class="mt-1 text-secondary">
                                        Si el cliente no acepta el recargo, cámbiele la forma de
                                        pago: en efectivo, cheque, Zelle o transferencia no se cobra.
                                    </div>

                                </div>
                            @endif

                            <div class="btn-group w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" id="authExistente"
                                       value="existing" wire:model.live="modoTarjeta">
                                <label class="btn btn-outline-primary" for="authExistente">
                                    Ya tiene una firmada
                                </label>

                                <input type="radio" class="btn-check" id="authNueva"
                                       value="new" wire:model.live="modoTarjeta">
                                <label class="btn btn-outline-primary" for="authNueva">
                                    Registrar una nueva
                                </label>
                            </div>

                            @if ($modoTarjeta === 'existing')

                                @forelse ($this->autorizacionesDisponibles as $auth)
                                    <div class="form-check border rounded p-2 mb-2" wire:key="auth-{{ $auth->id }}">
                                        <input class="form-check-input" type="radio"
                                               id="auth{{ $auth->id }}" value="{{ $auth->id }}"
                                               wire:model="credit_card_authorization_id">
                                        <label class="form-check-label w-100" for="auth{{ $auth->id }}">
                                            <span class="fw-semibold">{{ $auth->cardholder_name }}</span>
                                            <span class="small text-secondary d-block">
                                                {{ $auth->card_brand }} ····{{ $auth->last_four }}
                                                · vence {{ $auth->exp_month }}/{{ $auth->exp_year }}
                                            </span>
                                        </label>
                                    </div>
                                @empty
                                    <div class="alert alert-secondary py-2 small mb-0">
                                        Este cliente no tiene ninguna autorización firmada y vigente.
                                        Regístrela con la opción de al lado.
                                    </div>
                                @endforelse

                                @error('credit_card_authorization_id')
                                    <div class="text-danger small">{{ $message }}</div>
                                @enderror

                            @else

                                <div class="row g-2">

                                    <div class="col-12">
                                        <label class="form-label small">Nombre en la tarjeta <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm @error('nuevaAuthNombre') is-invalid @enderror"
                                               wire:model.blur="nuevaAuthNombre">
                                        @error('nuevaAuthNombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small">Marca</label>
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="Visa, Mastercard…" wire:model.blur="nuevaAuthMarca">
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small">Últimos 4 <span class="text-danger">*</span></label>
                                        <input type="text" maxlength="4" inputmode="numeric"
                                               class="form-control form-control-sm @error('nuevaAuthUltimos4') is-invalid @enderror"
                                               wire:model.blur="nuevaAuthUltimos4">
                                        @error('nuevaAuthUltimos4') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text">
                                            Solo cuatro dígitos. El número completo no se guarda nunca.
                                        </div>
                                    </div>

                                    <div class="col-4">
                                        <label class="form-label small">Mes <span class="text-danger">*</span></label>
                                        <input type="number" min="1" max="12"
                                               class="form-control form-control-sm @error('nuevaAuthMes') is-invalid @enderror"
                                               wire:model.blur="nuevaAuthMes">
                                        @error('nuevaAuthMes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-4">
                                        <label class="form-label small">Año <span class="text-danger">*</span></label>
                                        <input type="number" min="{{ now()->year }}"
                                               class="form-control form-control-sm @error('nuevaAuthAnio') is-invalid @enderror"
                                               wire:model.blur="nuevaAuthAnio">
                                        @error('nuevaAuthAnio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-4">
                                        <label class="form-label small">Monto autorizado</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" class="form-control"
                                                   wire:model.blur="nuevaAuthMontoAutorizado">
                                        </div>
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small">Firmada el <span class="text-danger">*</span></label>
                                        <input type="date"
                                               class="form-control form-control-sm @error('nuevaAuthFirmadaEl') is-invalid @enderror"
                                               wire:model="nuevaAuthFirmadaEl">
                                        @error('nuevaAuthFirmadaEl') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small">El papel firmado <span class="text-danger">*</span></label>
                                        <input type="file"
                                               class="form-control form-control-sm @error('nuevaAuthArchivo') is-invalid @enderror"
                                               wire:model="nuevaAuthArchivo">
                                        @error('nuevaAuthArchivo') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                        <div wire:loading wire:target="nuevaAuthArchivo" class="form-text text-primary">
                                            <span class="spinner-border spinner-border-sm me-1"></span> Subiendo…
                                        </div>
                                    </div>

                                </div>

                            @endif

                            {{--
                                LA PERSONA FÍSICA TIENE QUE ESTAR PRESENTE

                                Con empresas basta la verificación en Sunbiz. Con
                                una persona no: solo se acepta la tarjeta con el
                                cliente delante, en la yarda. Nada por teléfono.
                            --}}
                            {{--
                                Se pregunta por el tipo y no por un metodo del
                                modelo: `type` es una columna que existe seguro,
                                y esta vista no puede depender de que alguien
                                agregue un helper mas adelante.
                            --}}
                            @if ($cliente && $cliente->type?->value !== 'business')
                                <hr>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="presente" wire:model="clientePresenteEnYarda">
                                    <label class="form-check-label" for="presente">
                                        El cliente está presente en la yarda
                                    </label>
                                </div>
                                <div class="form-text">
                                    Con personas físicas la tarjeta solo se acepta en persona.
                                </div>
                            @endif

                        </div>
                    </div>

                @endif

            </div>

            {{-- ═══════════════════════════════════════════════════
                 DERECHA · A QUÉ FACTURAS SE APLICA
            ═══════════════════════════════════════════════════ --}}
            <div class="col-12 col-lg-7">

                <div class="card mb-3 seccion seccion-lineas">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="seccion-titulo mb-0">
                            <span class="paso-num">4</span>
                            <i class="bi bi-list-check"></i>
                            <span>Aplicación a facturas</span>
                        </h6>

                        @if ($customer_id && $this->facturasPendientes->isNotEmpty())
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        wire:click="aplicarAutomatico"
                                        title="Reparte el monto entre las facturas, la más vieja primero">
                                    <i class="bi bi-magic me-1"></i> Distribuir
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        wire:click="limpiarAplicaciones">
                                    Limpiar
                                </button>
                            </div>
                        @endif
                    </div>

                    <div class="card-body">

                        @if (! $customer_id)

                            <div class="text-center py-4 text-secondary">
                                <i class="bi bi-person-plus fs-3 d-block mb-2 opacity-50"></i>
                                <div class="small">Elija primero el cliente.</div>
                            </div>

                        @elseif ($this->facturasPendientes->isEmpty())

                            <div class="alert alert-success py-2 small mb-0">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                Este cliente no debe nada. El pago quedará como
                                <strong>anticipo a su favor</strong>.
                            </div>

                        @else

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">

                                    <thead>
                                        <tr>
                                            <th>Factura</th>
                                            <th>Vence</th>
                                            <th class="text-end">Debe</th>
                                            <th class="text-end" style="width: 150px;">Cuánto se le paga</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                    @foreach ($this->facturasPendientes as $f)

                                        <tr wire:key="fac-{{ $f->id }}"
                                            class="fila-estado {{ $f->isOverdue() ? 'fila-bad' : 'fila-mute' }}">

                                            <td>
                                                <a href="{{ route('finanzas.facturacion.show', $f) }}"
                                                   class="doc-numero" target="_blank">{{ $f->invoice_number }}</a>
                                            </td>

                                            <td class="small">
                                                {{ $f->due_date?->format('d/m/Y') ?? '—' }}
                                                @if ($f->isOverdue())
                                                    <div class="text-danger">
                                                        {{ $f->days_overdue }} días
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="text-end monto">
                                                ${{ number_format((float) $f->balance_due, 2) }}
                                            </td>

                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" step="0.01"
                                                           class="form-control text-end"
                                                           wire:model.live.debounce.500ms="aplicaciones.{{ $f->id }}">
                                                </div>
                                            </td>

                                        </tr>

                                    @endforeach
                                    </tbody>

                                </table>
                            </div>

                        @endif

                    </div>
                </div>

                {{-- ───── EL CUADRE ───── --}}
                <div class="card mb-3">
                    <div class="card-body">

                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-secondary">Monto del pago</td>
                                    <td class="text-end monto">${{ number_format((float) $amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-secondary">Aplicado a facturas</td>
                                    <td class="text-end monto">${{ number_format($this->totalAplicado, 2) }}</td>
                                </tr>
                                <tr class="fw-bold border-top">
                                    <td>Sin asignar</td>
                                    <td class="text-end monto {{ $this->saldoSinAsignar < 0 ? 'text-danger' : '' }}">
                                        ${{ number_format($this->saldoSinAsignar, 2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {{--
                            LOS DOS AVISOS DEL CUADRE

                            Se reparte MÁS de lo que entró: eso no se puede
                            guardar, porque estaría descontando dinero que
                            nadie pagó.

                            Sobra dinero: sí se puede, y queda a favor del
                            cliente. Pero conviene decirlo, porque la mayoría
                            de las veces es que se olvidó una factura.
                        --}}
                        @if ($this->saldoSinAsignar < -0.001)
                            <div class="alert alert-danger py-2 small mt-2 mb-0">
                                <div class="mb-2">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    <strong>El total aplicado supera el monto del pago
                                    en ${{ number_format(abs($this->saldoSinAsignar), 2) }}.</strong>
                                    No se puede aplicar a facturas más dinero del que se recibió.
                                </div>

                                {{--
                                    UN CARTEL QUE SOLO ACUSA NO SIRVE.

                                    El sistema sabe cuánto suma lo repartido. Si
                                    el monto y el reparto no cuadran, ofrecer el
                                    arreglo cuesta un botón y resuelve el caso más
                                    común: que el monto se haya quedado sin
                                    escribir o se tecleara mal.
                                --}}
                                <button type="button" class="btn btn-sm btn-danger"
                                        wire:click="usarSumaComoMonto">
                                    <i class="bi bi-arrow-left-circle me-1"></i>
                                    Fijar el monto en ${{ number_format($this->totalAplicado, 2) }}
                                </button>

                                <span class="ms-2">
                                    o corrija los importes de la tabla.
                                </span>
                            </div>
                        @elseif ($this->saldoSinAsignar > 0.001 && $customer_id)
                            <div class="alert alert-info py-2 small mt-2 mb-0">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                Quedan ${{ number_format($this->saldoSinAsignar, 2) }} sin asignar.
                                Se guardarán como saldo a favor del cliente.
                            </div>
                        @endif

                    </div>
                </div>

            </div>

        </div>

        {{-- ───── EL PIE ───── --}}
        <div class="ps-pie">

            <div>
                <a href="{{ route('finanzas.pagos.index') }}" class="btn btn-outline-secondary">
                    Cancelar
                </a>
            </div>

            <div class="ps-pie-medio">
                @if ($errors->any())
                    <span class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        {{ $errors->count() === 1 ? 'Falta 1 dato' : 'Faltan '.$errors->count().' datos' }}
                    </span>
                @endif

                <div wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1"></span> Guardando…
                </div>
            </div>

            <div>
                <button type="submit" class="btn btn-success" wire:loading.attr="disabled"
                        @disabled($this->saldoSinAsignar < -0.001)>
                    <i class="bi bi-check-lg me-1"></i> Registrar el pago
                </button>
            </div>

        </div>

    </form>

</div>
