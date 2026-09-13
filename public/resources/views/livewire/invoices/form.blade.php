{{--
    ═══════════════════════════════════════════════════════════════════════
    FORMULARIO DE FACTURA
    ═══════════════════════════════════════════════════════════════════════

    Misma estructura que el de presupuestos —documento a la izquierda,
    totales a la derecha— con lo propio de una factura: tipo, período de
    servicio, método de pago esperado y depósito.
--}}
<div>

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0">
                @if ($invoiceId)
                    Corregir factura {{ $numero }}
                @else
                    Nueva factura
                @endif
            </h4>
            <small class="text-secondary">
                @if ($invoiceId)
                    Los cambios se guardan al presionar el botón de abajo.
                @else
                    El número se asigna al guardar y ya no se puede reutilizar.
                @endif
            </small>
        </div>

        <a href="{{ route('finanzas.facturacion.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver al listado
        </a>

    </div>

    {{--
        EL AVISO DE "YA SE LA MANDASTE AL CLIENTE"

        No bloquea nada: corregir una factura enviada es normal —el
        cliente llama a los diez minutos diciendo que el delivery era a
        otra dirección—. Pero quien lo haga tiene que saber que hay una
        copia distinta circulando por ahí.
    --}}
    @if ($yaEnviada)
        <div class="alert alert-warning">
            <i class="bi bi-envelope-exclamation me-1"></i>
            <strong>Esta factura ya se le envió al cliente.</strong>
            Si la modifica, el documento que él tiene dejará de coincidir
            con el del sistema. Vuelva a enviársela después de guardar.
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{--
        El resumen de errores de arriba.

        Livewire ya pinta el error debajo de cada campo, pero cuando el
        formulario es largo el usuario da a "Guardar", no ve nada, y cree
        que el botón está roto. En realidad el error está seiscientos
        píxeles más abajo.
    --}}

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong><i class="bi bi-exclamation-triangle me-1"></i> Faltan datos:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            {{-- ═══════════════════════════════════════════════════
                 COLUMNA IZQUIERDA
            ═══════════════════════════════════════════════════ --}}
            <div class="col-12 col-xl-8">

                {{-- 1 · CLIENTE --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">1 · Cliente</h6>
                    </div>

                    <div class="card-body">
                        @if ($customer_id)
                            <div class="d-flex justify-content-between align-items-center
                                        border rounded p-3 bg-body-tertiary">
                                <div>
                                    <div class="fw-semibold">{{ $clienteNombre }}</div>
                                    @if ($tax_exempt)
                                        <span class="badge text-bg-info mt-1">
                                            <i class="bi bi-patch-check me-1"></i> Exento de impuesto
                                        </span>
                                    @endif
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        wire:click="quitarCliente">
                                    Cambiar
                                </button>
                            </div>
                        @else
                            <label class="form-label">Buscar cliente</label>

                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text"
                                       class="form-control @error('customer_id') is-invalid @enderror"
                                       placeholder="Empresa, contacto, teléfono o número de cliente…"
                                       wire:model.live.debounce.300ms="buscarCliente">
                            </div>

                            @error('customer_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror

                            @if ($this->resultadosCliente->isNotEmpty())
                                <div class="list-group mt-2">
                                    @foreach ($this->resultadosCliente as $cliente)
                                        <button type="button"
                                                class="list-group-item list-group-item-action"
                                                wire:key="cliente-{{ $cliente->id }}"
                                                wire:click="seleccionarCliente({{ $cliente->id }})">

                                            <div class="d-flex justify-content-between">
                                                <span class="fw-semibold">{{ $cliente->name }}</span>
                                                <small class="text-secondary">{{ $cliente->customer_number }}</small>
                                            </div>

                                            <small class="text-secondary">
                                                {{ $cliente->primary_email ?: $cliente->primary_phone ?: 'Sin contacto registrado' }}
                                            </small>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (strlen(trim($buscarCliente)) >= 2)
                                <div class="text-secondary small mt-2">
                                    No se encontró ningún cliente con ese dato.
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- 2 · DATOS DEL DOCUMENTO --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">2 · Datos del documento</h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-6 col-md-3">
                                <label class="form-label">Tipo de factura</label>
                                <select class="form-select @error('type') is-invalid @enderror"
                                        wire:model.live="type">
                                    @foreach ($tipos as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Fecha de emisión</label>
                                <input type="date"
                                       class="form-control @error('issue_date') is-invalid @enderror"
                                       wire:model.live="issue_date">
                                @error('issue_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Términos</label>
                                {{--
                                    Al cambiar los términos se recalcula el
                                    vencimiento en pantalla, para que se vea
                                    el efecto antes de guardar.
                                --}}
                                <select class="form-select" wire:model.live="terms">
                                    <option value="Due on receipt">Due on receipt</option>
                                    <option value="Net 15">Net 15</option>
                                    <option value="Net 30">Net 30</option>
                                    <option value="Net 45">Net 45</option>
                                    <option value="Net 60">Net 60</option>
                                </select>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Vence</label>
                                <input type="date"
                                       class="form-control @error('due_date') is-invalid @enderror"
                                       wire:model="due_date">
                                @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{--
                                EL PERÍODO DE SERVICIO (RB-023)

                                Solo en las de renta, y ahí es obligatorio:
                                el invoice tiene que decir explícitamente
                                desde cuándo y hasta cuándo cubre.

                                En las facturas de RS Transport ese dato lo
                                metían a mano en el campo TRACKING#. Aquí
                                tiene su propio sitio.
                            --}}
                            @if ($type === 'rental')
                                <div class="col-12">
                                    <div class="border rounded p-3 bg-body-tertiary">
                                        <div class="fw-semibold small mb-2">
                                            <i class="bi bi-calendar-range me-1"></i>
                                            Período que cubre esta factura
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-6 col-md-3">
                                                <label class="form-label small">Desde</label>
                                                <input type="date"
                                                       class="form-control form-control-sm @error('service_period_start') is-invalid @enderror"
                                                       wire:model="service_period_start">
                                                @error('service_period_start')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="col-6 col-md-3">
                                                <label class="form-label small">Hasta</label>
                                                <input type="date"
                                                       class="form-control form-control-sm @error('service_period_end') is-invalid @enderror"
                                                       wire:model="service_period_end">
                                                @error('service_period_end')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-text mt-2">
                                            Sale impreso en la factura. Sin esto el cliente no sabe
                                            qué mes está pagando.
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($type === 'transport')
                                <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                        <i class="bi bi-truck me-1"></i>
                                        <strong>Factura de transporte.</strong>
                                        El transporte no lleva sales tax en Florida (RB-005),
                                        así que las líneas se desmarcaron solas. Si en esta misma
                                        factura va también un contenedor, márquelo a mano.
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>

                {{-- 3 · DIRECCIONES (RB-035) --}}
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">3 · Direcciones</h6>

                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox"
                                   id="envio-distinto" wire:model.live="envioDistinto">
                            <label class="form-check-label small" for="envio-distinto">
                                La entrega va a otra dirección
                            </label>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="{{ $envioDistinto ? 'col-md-6' : 'col-12' }}">
                                <div class="fw-semibold small text-uppercase text-secondary mb-2">
                                    Facturar a (BILL TO)
                                    <span class="text-danger">*</span>
                                </div>

                                <div class="row g-2">
                                    <div class="col-12">
                                        <input type="text"
                                               class="form-control form-control-sm @error('bill_to.line1') is-invalid @enderror"
                                               placeholder="Dirección línea 1"
                                               wire:model="bill_to.line1">
                                        @error('bill_to.line1')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="Dirección línea 2 (opcional)"
                                               wire:model="bill_to.line2">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="Ciudad" wire:model="bill_to.city">
                                    </div>
                                    <div class="col-3">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="FL" maxlength="2" wire:model="bill_to.state">
                                    </div>
                                    <div class="col-3">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="ZIP" wire:model="bill_to.zip">
                                    </div>
                                </div>
                            </div>

                            @if ($envioDistinto)
                                <div class="col-md-6">
                                    <div class="fw-semibold small text-uppercase text-secondary mb-2">
                                        Entregar en (SHIP TO)
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-12">
                                            <input type="text" class="form-control form-control-sm"
                                                   placeholder="Dirección línea 1"
                                                   wire:model="ship_to.line1">
                                        </div>
                                        <div class="col-12">
                                            <input type="text" class="form-control form-control-sm"
                                                   placeholder="Dirección línea 2 (opcional)"
                                                   wire:model="ship_to.line2">
                                        </div>
                                        <div class="col-6">
                                            <input type="text" class="form-control form-control-sm"
                                                   placeholder="Ciudad" wire:model="ship_to.city">
                                        </div>
                                        <div class="col-3">
                                            <input type="text" class="form-control form-control-sm"
                                                   placeholder="FL" maxlength="2" wire:model="ship_to.state">
                                        </div>
                                        <div class="col-3">
                                            <input type="text" class="form-control form-control-sm"
                                                   placeholder="ZIP" wire:model="ship_to.zip">
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </div>

                        <div class="form-text mt-2">
                            Se guardan como copia del día de hoy. Si el cliente se muda el año
                            que viene, esta factura seguirá mostrando dónde estaba hoy.
                        </div>
                    </div>
                </div>

                {{-- 4 · LAS LÍNEAS --}}
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">4 · Conceptos</h6>

                        <button type="button" class="btn btn-sm btn-primary" wire:click="agregarLinea">
                            <i class="bi bi-plus-lg me-1"></i> Agregar línea
                        </button>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">

                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 160px;">Concepto</th>
                                        <th>Descripción</th>
                                        <th style="width: 130px;">
                                            Fecha serv.
                                            <i class="bi bi-info-circle text-secondary"
                                               title="El día en que se prestó. En una factura semanal de viajes, cada línea es un día distinto."></i>
                                        </th>
                                        <th style="width: 70px;" class="text-end">Cant.</th>
                                        <th style="width: 100px;" class="text-end">Precio</th>
                                        <th style="width: 100px;" class="text-end">Importe</th>
                                        <th style="width: 50px;" class="text-center">
                                            Tax
                                            <i class="bi bi-info-circle text-secondary"
                                               title="Marcado = paga el 7%. El transporte nunca se marca (RB-005)."></i>
                                        </th>
                                        <th style="width: 70px;" class="text-center">
                                            Grupo
                                            <i class="bi bi-info-circle text-secondary"
                                               title="Las líneas con la misma etiqueta se imprimen como un solo renglón (RB-007)."></i>
                                        </th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {{--
                                        wire:key es obligatorio: es cómo
                                        Livewire sabe qué fila es cuál al
                                        agregar o borrar del medio. Sin él,
                                        borrar la fila 2 hace que el
                                        contenido de la 3 aparezca en la 2.
                                    --}}
                                    @foreach ($lineas as $i => $linea)
                                        <tr wire:key="linea-{{ $i }}">

                                            <td>
                                                <select class="form-select form-select-sm"
                                                        wire:model.live="lineas.{{ $i }}.product_id">
                                                    <option value="">— Libre —</option>
                                                    @foreach ($productos as $producto)
                                                        <option value="{{ $producto->id }}">
                                                            {{ $producto->name }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                @php
                                                    $productoElegido = $productos->firstWhere('id', $linea['product_id'] ?? null);
                                                @endphp

                                                @if ($productoElegido && $productoElegido->type->requiresContainer())
                                                    <select class="form-select form-select-sm mt-1"
                                                            wire:model="lineas.{{ $i }}.container_id">
                                                        <option value="">— Sin unidad asignada —</option>
                                                        @foreach ($contenedores as $contenedor)
                                                            <option value="{{ $contenedor->id }}">
                                                                {{ $contenedor->full_identifier }}
                                                                — {{ $contenedor->classification }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </td>

                                            <td>
                                                <input type="text"
                                                       class="form-control form-control-sm @error('lineas.'.$i.'.description') is-invalid @enderror"
                                                       placeholder="Lo que va a leer el cliente"
                                                       wire:model.blur="lineas.{{ $i }}.description">
                                            </td>

                                            <td>
                                                <input type="date"
                                                       class="form-control form-control-sm"
                                                       wire:model="lineas.{{ $i }}.service_date">
                                            </td>

                                            <td>
                                                <input type="number" step="0.01" min="0.01"
                                                       class="form-control form-control-sm text-end @error('lineas.'.$i.'.quantity') is-invalid @enderror"
                                                       wire:model.live.debounce.500ms="lineas.{{ $i }}.quantity">
                                            </td>

                                            <td>
                                                <input type="number" step="0.01" min="0"
                                                       class="form-control form-control-sm text-end @error('lineas.'.$i.'.unit_price') is-invalid @enderror"
                                                       wire:model.live.debounce.500ms="lineas.{{ $i }}.unit_price">
                                            </td>

                                            {{-- Calculado, no se escribe --}}
                                            <td class="text-end fw-semibold">
                                                ${{ number_format($this->importeLinea($i), 2) }}
                                            </td>

                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input"
                                                       wire:model.live="lineas.{{ $i }}.taxable">
                                            </td>

                                            <td>
                                                <input type="text" maxlength="20"
                                                       class="form-control form-control-sm text-center"
                                                       placeholder="—"
                                                       wire:model.live.debounce.600ms="lineas.{{ $i }}.grupo">
                                            </td>

                                            <td class="text-center">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger border-0"
                                                        wire:click="quitarLinea({{ $i }})"
                                                        title="Quitar línea">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>

                                        </tr>
                                    @endforeach
                                </tbody>

                            </table>
                        </div>
                    </div>

                    {{-- LOS TEXTOS DE LOS GRUPOS --}}
                    @if (count($grupos) > 0)
                        <div class="card-footer bg-body-tertiary">
                            <div class="fw-semibold small mb-2">
                                <i class="bi bi-collection me-1"></i>
                                Cómo se imprime cada grupo
                            </div>

                            <p class="text-secondary small">
                                Estas líneas se suman en un solo renglón para el cliente, pero
                                por dentro cada una conserva si paga impuesto. Es lo que permite
                                mostrar un precio consolidado y aun así cobrar el 7% solo sobre
                                el contenedor.
                            </p>

                            @foreach ($grupos as $grupo)
                                <div class="input-group input-group-sm mb-2" wire:key="grupo-{{ $grupo }}">
                                    <span class="input-group-text" style="min-width: 90px;">
                                        Grupo {{ $grupo }}
                                    </span>
                                    <input type="text" class="form-control"
                                           placeholder="Ej: Contenedor 40HC entregado en Homestead"
                                           wire:model.blur="gruposDescripcion.{{ $grupo }}">
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- 5 · NOTAS --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">5 · Notas</h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Notas del documento</label>
                                <textarea class="form-control" rows="3"
                                          placeholder="Aparecen impresas en la factura."
                                          wire:model="notes"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Condiciones al pie</label>
                                <textarea class="form-control" rows="3"
                                          placeholder="Si se deja vacío se usan las de la empresa."
                                          wire:model="footer_terms"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- ═══════════════════════════════════════════════════
                 COLUMNA DERECHA · TOTALES
            ═══════════════════════════════════════════════════ --}}
            <div class="col-12 col-xl-4">
                <div class="position-sticky" style="top: 1rem;">

                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Totales</h6>
                        </div>

                        <div class="card-body">

                            <div class="row g-2 mb-3">

                                <div class="col-12">
                                    <label class="form-label small">Método de pago esperado</label>
                                    <select class="form-select form-select-sm"
                                            wire:model.live="expected_payment_method">
                                        <option value="">— Sin definir —</option>
                                        @foreach ($metodosDePago as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">
                                        Al elegir tarjeta entra el recargo del
                                        {{ number_format($credit_card_fee_percent ?: 3.5, 2) }}% (RB-009).
                                    </div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Descuento ($)</label>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control form-control-sm text-end"
                                           wire:model.live.debounce.500ms="discount_amount">
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Sales tax (%)</label>
                                    <input type="number" step="0.01" min="0" max="100"
                                           class="form-control form-control-sm text-end"
                                           wire:model.live.debounce.500ms="tax_rate"
                                           @disabled($tax_exempt)>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small">
                                        Depósito aplicado ($)
                                        <i class="bi bi-info-circle text-secondary"
                                           title="El anticipo que el cliente ya entregó y se le descuenta del total."></i>
                                    </label>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control form-control-sm text-end"
                                           wire:model.live.debounce.500ms="deposit_applied">
                                </div>

                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               id="exento" wire:model.live="tax_exempt">
                                        <label class="form-check-label small" for="exento">
                                            Cliente exento de impuesto
                                        </label>
                                    </div>
                                </div>

                            </div>

                            <hr>

                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">Subtotal</span>
                                <span>${{ number_format($this->totales['subtotal'], 2) }}</span>
                            </div>

                            @if ($this->totales['discount_amount'] > 0)
                                <div class="d-flex justify-content-between mb-1 text-danger">
                                    <span>Descuento</span>
                                    <span>−${{ number_format($this->totales['discount_amount'], 2) }}</span>
                                </div>
                            @endif

                            {{--
                                El renglón que más preguntas evita: sobre qué
                                monto se calculó el 7%, que casi nunca es el
                                total.
                            --}}
                            <div class="d-flex justify-content-between mb-1 small text-secondary">
                                <span>Base gravable</span>
                                <span>${{ number_format($this->totales['taxable_base'], 2) }}</span>
                            </div>

                            @if ($this->totales['non_taxable_base'] > 0)
                                <div class="d-flex justify-content-between mb-1 small text-secondary">
                                    <span>No gravable (transporte)</span>
                                    <span>${{ number_format($this->totales['non_taxable_base'], 2) }}</span>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">
                                    Sales tax ({{ number_format($tax_exempt ? 0 : $tax_rate, 2) }}%)
                                </span>
                                <span>${{ number_format($this->totales['tax_amount'], 2) }}</span>
                            </div>

                            @if ($this->totales['credit_card_fee'] > 0)
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-secondary">
                                        Credit card fee ({{ number_format($credit_card_fee_percent, 2) }}%)
                                    </span>
                                    <span>${{ number_format($this->totales['credit_card_fee'], 2) }}</span>
                                </div>
                            @endif

                            @if ($this->totales['deposit_applied'] > 0)
                                <div class="d-flex justify-content-between mb-1 text-success">
                                    <span>Depósito aplicado</span>
                                    <span>−${{ number_format($this->totales['deposit_applied'], 2) }}</span>
                                </div>
                            @endif

                            <hr>

                            <div class="d-flex justify-content-between fs-5 fw-semibold">
                                <span>Total</span>
                                <span>${{ number_format($this->totales['total'], 2) }}</span>
                            </div>

                            @if ($tax_exempt)
                                <div class="alert alert-info small mt-3 mb-0">
                                    <i class="bi bi-patch-check me-1"></i>
                                    Al guardar se buscará el certificado de exención vigente del
                                    cliente y quedará adjunto como respaldo ante el estado.
                                </div>
                            @endif

                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body d-grid gap-2">

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>
                                @if ($invoiceId) Guardar cambios @else Emitir factura @endif
                            </button>

                             <x-ui.errores class="small mb-1 py-2" />

                            <div class="leyenda-obligatorio align-self-start mb-1">
                                <strong>*</strong> Campo obligatorio
                            </div>

                            <button type="button" class="btn btn-success" wire:click="guardar(true)">
                                <i class="bi bi-send me-1"></i>
                                Guardar y marcar como enviada
                            </button>

                            <a href="{{ route('finanzas.facturacion.index') }}"
                               class="btn btn-outline-secondary">
                                Cancelar
                            </a>

                            @unless ($invoiceId)
                                <div class="text-secondary small text-center pt-1">
                                    Al guardar se consume un número de la secuencia,
                                    aunque después la factura se anule.
                                </div>
                            @endunless

                            {{--
                                wire:loading muestra esto SOLO mientras el
                                servidor trabaja. Es la diferencia entre "no
                                pasó nada" y "está guardando": sin esto el
                                usuario vuelve a darle al botón, y en una
                                factura eso puede significar dos números
                                consumidos.
                            --}}
                            <div wire:loading class="text-center text-secondary small pt-2">
                                <span class="spinner-border spinner-border-sm me-1"></span>
                                Guardando…
                            </div>

                        </div>
                    </div>

                </div>
            </div>

        </div>
    </form>

</div>
