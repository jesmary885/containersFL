{{--
    ═══════════════════════════════════════════════════════════════════════
    FACTURA — crear y editar
    ═══════════════════════════════════════════════════════════════════════

    Mismo asistente que el presupuesto: barra de pasos arriba, tira de
    contexto que recuerda lo ya decidido, y un solo pie con los botones
    que aplican al paso donde estás.

      1 · A QUIÉN Y CUÁNDO   cliente, fechas, términos, direcciones
      2 · QUÉ SE LE COBRA    los renglones
      3 · REVISAR Y EMITIR   el documento armado

    ── POR QUÉ LOS RENGLONES SE EDITAN EN UN MODAL ──

    Porque cada concepto pide lo suyo. Una renta no tiene cantidad —un
    renglón es un contenedor— y un viaje de transporte necesita su fecha
    de servicio. Una tabla con ocho columnas iguales para todos pide
    datos que no significan nada en la mitad de los casos.

    Lo que se edita en el modal es una copia. Solo al guardar se escribe
    sobre el renglón, así "Cancelar" cancela de verdad.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $invoiceId ? 'Editar factura' : 'Nueva factura' }}
                @if ($numero)
                    <span class="text-secondary fw-normal font-monospace fs-6">{{ $numero }}</span>
                @endif
            </h4>
            <small class="text-secondary">
                @if ($invoiceId)
                    Lo que se cambie aquí reemplaza el documento. Los pagos ya aplicados no se tocan.
                @else
                    El número se asigna solo al guardar, siguiendo la secuencia de la empresa.
                @endif
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="leyenda-obligatorio"><strong>*</strong> Campo obligatorio</span>

            <a href="{{ $invoiceId
                        ? route('finanzas.facturacion.show', $invoiceId)
                        : route('finanzas.facturacion.index') }}"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>

    </div>

    @if (session('error'))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
        </div>
    @endif

    {{--
        LA FACTURA YA ENVIADA

        Editar un documento que el cliente ya tiene en su correo no está
        prohibido, pero sí merece un aviso: la copia que él guarda y la
        que queda aquí van a decir cosas distintas.
    --}}
    @if ($yaEnviada)
        <div class="alert alert-warning">
            <i class="bi bi-envelope-exclamation me-1"></i>
            <strong>Esta factura ya se le envió al cliente.</strong>
            Si la cambia, vuelva a enviársela: la copia que él tiene seguirá diciendo lo de antes.
        </div>
    @endif

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('subir-al-inicio', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    </script>

    {{-- ───── LA BARRA DE PASOS ───── --}}
    <div class="ps-barra">

        <button type="button"
                class="ps-paso {{ $paso === 1 ? 'ps-activo' : 'ps-hecho' }}"
                wire:click="irAlPaso(1)">
            <span class="ps-bolita">{{ $paso > 1 ? '✓' : '1' }}</span>
            <span class="ps-texto">A quién y cuándo</span>
        </button>

        <span class="ps-sep"></span>

        <button type="button"
                class="ps-paso {{ $paso === 2 ? 'ps-activo' : ($paso > 2 ? 'ps-hecho' : '') }}"
                wire:click="irAlPaso(2)">
            <span class="ps-bolita">{{ $paso > 2 ? '✓' : '2' }}</span>
            <span class="ps-texto">Qué se le cobra</span>
        </button>

        <span class="ps-sep"></span>

        <button type="button"
                class="ps-paso {{ $paso === 3 ? 'ps-activo' : '' }}"
                wire:click="irAlPaso(3)">
            <span class="ps-bolita">3</span>
            <span class="ps-texto">Revisar y emitir</span>
        </button>

    </div>

    {{-- ───── LA TIRA DE CONTEXTO ───── --}}
    @if ($paso > 1)
        @php $ctx = $this->resumen; @endphp

        <div class="ps-tira">

            <div class="ps-tira-dato">
                <span class="ps-tira-k">Cliente</span>
                <span class="ps-tira-v {{ $ctx['cliente'] ? '' : 'ps-falta' }}">
                    {{ $ctx['cliente'] ?? 'Falta' }}
                </span>
            </div>

            <span class="ps-tira-sep"></span>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">Emisión</span>
                <span class="ps-tira-v">{{ $ctx['emision'] ?? '—' }}</span>
            </div>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">Vence</span>
                <span class="ps-tira-v">{{ $ctx['vence'] ?? '—' }}</span>
            </div>

            <span class="ps-tira-sep"></span>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">Factura a</span>
                <span class="ps-tira-v">{{ $ctx['direccion'] ?? '—' }}</span>
            </div>

            @if ($tax_exempt)
                <span class="badge bg-success-subtle text-success">Exento de tax</span>
            @endif

            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                    wire:click="irAlPaso(1)">
                <i class="bi bi-pencil me-1"></i>Cambiar
            </button>

        </div>
    @endif

    <form wire:submit.prevent="guardar">

        {{-- ═════════════════════════════════════════════════════════
             PASO 1 · A QUIÉN Y CUÁNDO
        ═════════════════════════════════════════════════════════ --}}
        @if ($paso === 1)

            <div class="card mb-3 seccion seccion-cliente">
                <div class="card-header">
                    <h6 class="seccion-titulo">
                        <span class="paso-num">1</span>
                        <i class="bi bi-person-vcard"></i>
                        <span>A quién se le factura</span>
                    </h6>
                </div>

                <div class="card-body">

                    @if (! $customer_id)

                        {{--
                            EL BUSCADOR

                            Busca por nombre, número, teléfono, correo y por los
                            contactos del cliente. Es normal que llamen diciendo
                            "soy Carlos, de la constructora" sin acordarse del
                            nombre de la empresa.
                        --}}
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>

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
                                    <button type="button"
                                            class="list-group-item list-group-item-action"
                                            wire:key="cli-{{ $c->id }}"
                                            wire:click="seleccionarCliente({{ $c->id }})">

                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-semibold">{{ $c->name }}</div>
                                                <div class="small text-secondary">
                                                    {{ $c->customer_number }}
                                                    @if ($c->primary_phone) · {{ $c->primary_phone }} @endif
                                                </div>
                                            </div>

                                            <div class="text-end">
                                                @if ($c->tax_exempt)
                                                    <span class="badge bg-success-subtle text-success">Exento</span>
                                                @endif
                                                @if ($c->credit_hold)
                                                    <span class="badge bg-warning-subtle text-warning-emphasis">Retenido</span>
                                                @endif
                                            </div>
                                        </div>
                                    </button>
                                @empty
                                    <div class="list-group-item text-secondary small">
                                        Nadie coincide con eso.
                                        <a href="{{ route('comercial.clientes.create') }}" target="_blank">
                                            Registrar un cliente nuevo
                                        </a>
                                    </div>
                                @endforelse
                            </div>
                        @endif

                    @else

                        <div class="d-flex justify-content-between align-items-center border rounded p-3">
                            <div>
                                <div class="fw-semibold fs-6">{{ $clienteNombre }}</div>
                                <div class="small text-secondary">
                                    @if ($tax_exempt)
                                        <span class="badge bg-success-subtle text-success">
                                            Exento de impuesto
                                        </span>
                                        Tiene certificado vigente: no se le cobra el 7%.
                                    @else
                                        Se le cobra el impuesto normal.
                                    @endif
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

            {{-- ───── FECHAS Y TÉRMINOS ───── --}}
            <div class="card mb-3 seccion seccion-datos">
                <div class="card-header">
                    <h6 class="seccion-titulo">
                        <span class="paso-num">2</span>
                        <i class="bi bi-calendar3"></i>
                        <span>Cuándo y bajo qué condiciones</span>
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-12 col-md-3">
                            <label class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror"
                                    wire:model.live="type">
                                @foreach ($tipos as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                El transporte nunca lleva impuesto en Florida.
                            </div>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label">Emisión <span class="text-danger">*</span></label>
                            <input type="date"
                                   class="form-control @error('issue_date') is-invalid @enderror"
                                   wire:model.live="issue_date">
                            @error('issue_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label">Términos de pago</label>
                            <input type="text"
                                   class="form-control @error('terms') is-invalid @enderror"
                                   placeholder="Net 30, Due on receipt..."
                                   wire:model.live.debounce.600ms="terms">
                            @error('terms') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Al escribirlos se recalcula el vencimiento.</div>
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label">Vence</label>
                            <input type="date"
                                   class="form-control @error('due_date') is-invalid @enderror"
                                   wire:model="due_date">
                            @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Se propone según los términos.</div>
                        </div>

                        {{--
                            EL PERÍODO DE SERVICIO

                            Obligatorio en las facturas de renta. Una factura
                            mensual que no dice qué mes cubre es una factura que
                            el cliente no puede comprobar, y la primera que
                            discute.
                        --}}
                        @if ($type === 'rental')
                            <div class="col-12">
                                <div class="alert alert-light border py-2 small mb-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Una factura de renta <strong>tiene que decir qué período cubre</strong>.
                                    El ciclo va del día de entrega al mismo día del mes siguiente,
                                    no del 1 al 30.
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Período desde <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('service_period_start') is-invalid @enderror"
                                       wire:model="service_period_start">
                                @error('service_period_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Hasta <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('service_period_end') is-invalid @enderror"
                                       wire:model="service_period_end">
                                @error('service_period_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- ───── LAS DIRECCIONES ───── --}}
            <div class="card mb-3 seccion seccion-direccion">
                <div class="card-header">
                    <h6 class="seccion-titulo">
                        <span class="paso-num">3</span>
                        <i class="bi bi-geo-alt"></i>
                        <span>Dónde se factura y dónde se entrega</span>
                    </h6>
                </div>

                <div class="card-body">

                    <div class="alert alert-light border py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Las dos se imprimen <strong>siempre</strong>, aunque sean la misma. Y quedan
                        congeladas: si el cliente se muda el año que viene, esta factura seguirá
                        diciendo dónde estaba hoy.
                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-lg-6">
                            <div class="fw-semibold small text-secondary mb-2">
                                <i class="bi bi-receipt me-1"></i> FACTURAR A
                            </div>

                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="text" class="form-control form-control-sm @error('bill_to.line1') is-invalid @enderror"
                                           placeholder="Calle y número" wire:model.blur="bill_to.line1">
                                    @error('bill_to.line1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <input type="text" class="form-control form-control-sm"
                                           placeholder="Suite, unidad (opcional)" wire:model.blur="bill_to.line2">
                                </div>
                                <div class="col-12 col-md-4">
                                    <select class="form-select form-select-sm" wire:model.live="bill_to.state">
                                        <option value="">Estado</option>
                                        @foreach (\App\Support\UsPlaces::estadosParaSelect() as $cod => $nom)
                                            <option value="{{ $cod }}">{{ $nom }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-5">
                                    <input type="text" list="ciudades-bill" autocomplete="off"
                                           class="form-control form-control-sm"
                                           placeholder="Ciudad" wire:model.blur="bill_to.city">
                                    <datalist id="ciudades-bill">
                                        @foreach (($bill_to['state'] ?? '')
                                            ? \App\Support\UsPlaces::ciudadesDe($bill_to['state'])
                                            : \App\Support\UsPlaces::todasLasCiudades() as $ciu)
                                            <option value="{{ $ciu }}"></option>
                                        @endforeach
                                    </datalist>
                                </div>
                                <div class="col-12 col-md-3">
                                    <input type="text" class="form-control form-control-sm"
                                           placeholder="ZIP" wire:model.blur="bill_to.zip">
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold small text-secondary">
                                    <i class="bi bi-truck me-1"></i> ENTREGAR EN
                                </span>

                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox"
                                           id="envioDistinto" wire:model.live="envioDistinto">
                                    <label class="form-check-label small" for="envioDistinto">
                                        Es otra dirección
                                    </label>
                                </div>
                            </div>

                            @if ($envioDistinto)
                                <div class="row g-2">
                                    <div class="col-12">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="Calle y número" wire:model.blur="ship_to.line1">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="Referencia (opcional)" wire:model.blur="ship_to.line2">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <select class="form-select form-select-sm" wire:model.live="ship_to.state">
                                            <option value="">Estado</option>
                                            @foreach (\App\Support\UsPlaces::estadosParaSelect() as $cod => $nom)
                                                <option value="{{ $cod }}">{{ $nom }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <input type="text" list="ciudades-ship" autocomplete="off"
                                               class="form-control form-control-sm"
                                               placeholder="Ciudad" wire:model.blur="ship_to.city">
                                        <datalist id="ciudades-ship">
                                            @foreach (($ship_to['state'] ?? '')
                                                ? \App\Support\UsPlaces::ciudadesDe($ship_to['state'])
                                                : \App\Support\UsPlaces::todasLasCiudades() as $ciu)
                                                <option value="{{ $ciu }}"></option>
                                            @endforeach
                                        </datalist>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="ZIP" wire:model.blur="ship_to.zip">
                                    </div>
                                </div>
                            @else
                                <div class="border rounded p-3 bg-body-tertiary small text-secondary">
                                    <i class="bi bi-arrow-left-right me-1"></i>
                                    Se entrega en la misma dirección de facturación.
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>

        @endif

        {{-- ═════════════════════════════════════════════════════════
             PASO 2 · QUÉ SE LE COBRA
        ═════════════════════════════════════════════════════════ --}}
        @if ($paso === 2)

            <div class="card mb-3 seccion seccion-lineas">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="seccion-titulo mb-0">
                        <span class="paso-num">4</span>
                        <i class="bi bi-list-ul"></i>
                        <span>Qué se le cobra</span>
                    </h6>

                    <button type="button" class="btn btn-sm btn-primary" wire:click="agregarLinea">
                        <i class="bi bi-plus-lg me-1"></i> Agregar concepto
                    </button>
                </div>

                <div class="card-body">

                    @error('lineas')
                        <div class="alert alert-danger py-2 small">{{ $message }}</div>
                    @enderror

                    @if (empty(array_filter($lineas, fn ($l) => filled($l['description'] ?? null))))
                        <div class="text-center py-4 text-secondary">
                            <i class="bi bi-receipt fs-3 d-block mb-2 opacity-50"></i>
                            <div class="small">
                                Todavía no hay nada que cobrar. Agregue el primer concepto.
                            </div>
                        </div>
                    @endif

                    {{--
                        AGRUPAR

                        Se marcan dos o más renglones y se pulsa el botón. La
                        letra la pone el sistema, y la descripción del grupo se
                        toma del renglón más caro: si se agrupan un contenedor de
                        $2.400 y su entrega de $150, el cliente tiene que leer
                        "contenedor", no "entrega".
                    --}}
                    @if (count(array_filter($seleccionadas)) > 0)
                        <div class="alert alert-info py-2 d-flex justify-content-between align-items-center">
                            <span class="small">
                                <i class="bi bi-check2-square me-1"></i>
                                {{ count(array_filter($seleccionadas)) }} renglones marcados
                            </span>
                            <button type="button" class="btn btn-sm btn-primary"
                                    wire:click="agruparSeleccionadas">
                                <i class="bi bi-boxes me-1"></i> Agruparlos como uno solo
                            </button>
                        </div>
                    @endif

                    @if ($avisoAgrupar)
                        <div class="alert alert-warning py-2 small">
                            <i class="bi bi-exclamation-triangle me-1"></i> {{ $avisoAgrupar }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">

                            <thead>
                                <tr>
                                    <th style="width: 36px;"></th>
                                    <th>Concepto</th>
                                    <th class="text-end" style="width: 90px;">Cant.</th>
                                    <th class="text-end" style="width: 130px;">Precio</th>
                                    <th class="text-center" style="width: 80px;">Tax</th>
                                    <th class="text-end" style="width: 130px;">Importe</th>
                                    <th class="text-end" style="width: 110px;"></th>
                                </tr>
                            </thead>

                            <tbody>
                            @foreach ($lineas as $i => $linea)

                                @continue (blank($linea['description'] ?? null) && ! $linea['product_id'])

                                <tr wire:key="lin-{{ $i }}">

                                    <td>
                                        <input type="checkbox" class="form-check-input"
                                               value="{{ $i }}"
                                               wire:model.live="seleccionadas.{{ $i }}"
                                               title="Marcar para agrupar con otros">
                                    </td>

                                    <td>
                                        <div class="fw-medium">{{ $linea['description'] ?: '—' }}</div>

                                        <div class="small text-secondary">
                                            @if ($linea['service_date'])
                                                <i class="bi bi-calendar3"></i>
                                                {{ \Carbon\Carbon::parse($linea['service_date'])->format('d/m/Y') }}
                                            @endif

                                            @if ($linea['grupo'])
                                                <span class="badge bg-primary-subtle text-primary">
                                                    <i class="bi bi-boxes"></i> Grupo {{ $linea['grupo'] }}
                                                </span>
                                                <button type="button"
                                                        class="btn btn-link btn-sm p-0 align-baseline text-secondary"
                                                        wire:click="desagrupar('{{ $linea['grupo'] }}')"
                                                        title="Deshacer este grupo">deshacer</button>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $linea['quantity'], 2), '0'), '.') }}</td>

                                    <td class="text-end monto">${{ number_format((float) $linea['unit_price'], 2) }}</td>

                                    <td class="text-center">
                                        @if ($linea['taxable'])
                                            <i class="bi bi-check-circle-fill text-success" title="Paga impuesto"></i>
                                        @else
                                            <span class="text-secondary" title="No paga impuesto">—</span>
                                        @endif
                                    </td>

                                    <td class="text-end fw-semibold monto">
                                        ${{ number_format($this->importeLinea($i), 2) }}
                                    </td>

                                    <td class="text-end">
                                        <div class="acciones">
                                            <button type="button" class="acc acc-editar"
                                                    wire:click="abrirLinea({{ $i }})" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="acc acc-borrar acc-separado"
                                                    wire:click="quitarLinea({{ $i }})" title="Quitar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>

                                </tr>

                            @endforeach
                            </tbody>

                        </table>
                    </div>

                    <div class="form-text mt-2">
                        <i class="bi bi-check2-square me-1"></i>
                        Marque dos o más renglones para <strong>imprimirlos como uno solo</strong>.
                        Es lo que se hace con un contenedor y su entrega: el cliente ve un precio
                        consolidado y el sistema sigue aplicando el impuesto solo a lo que
                        corresponde.
                    </div>

                    <div class="form-text">
                        <i class="bi bi-lightbulb me-1"></i>
                        El <strong>transporte no paga impuesto</strong> en Florida. El contenedor sí.
                        Por eso cada renglón lleva su propia marca de tax y no una sola para toda la
                        factura.
                    </div>

                </div>
            </div>

            {{-- El total, mientras se cargan renglones --}}
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary small">
                            {{ count($lineas) }} {{ count($lineas) === 1 ? 'renglón' : 'renglones' }}
                        </span>
                        <span class="fs-5 fw-semibold monto">
                            ${{ number_format($this->totales['total'], 2) }}
                        </span>
                    </div>
                </div>
            </div>

        @endif

        {{-- ═════════════════════════════════════════════════════════
             PASO 3 · REVISAR Y EMITIR
        ═════════════════════════════════════════════════════════ --}}
        @if ($paso === 3)

            @php $t = $this->totales; @endphp

            <div class="row g-3">

                <div class="col-12 col-xl-7">

                    {{-- ───── EL DOCUMENTO ───── --}}
                    <div class="card mb-3 seccion seccion-entrega">
                        <div class="card-header">
                            <h6 class="seccion-titulo mb-0">
                                <span class="paso-num">5</span>
                                <i class="bi bi-eye"></i>
                                <span>Así la va a ver el cliente</span>
                            </h6>
                        </div>

                        <div class="card-body">

                            <div class="row g-3 mb-3 small">
                                <div class="col-6">
                                    <div class="text-secondary">FACTURAR A</div>
                                    <div class="fw-semibold">{{ $clienteNombre }}</div>
                                    <div>{{ $bill_to['line1'] }}</div>
                                    @if ($bill_to['line2'])<div>{{ $bill_to['line2'] }}</div>@endif
                                    <div>
                                        {{ collect([$bill_to['city'], $bill_to['state']])->filter()->implode(', ') }}
                                        {{ $bill_to['zip'] }}
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="text-secondary">ENTREGAR EN</div>
                                    @if ($envioDistinto)
                                        <div>{{ $ship_to['line1'] }}</div>
                                        @if ($ship_to['line2'])<div>{{ $ship_to['line2'] }}</div>@endif
                                        <div>
                                            {{ collect([$ship_to['city'], $ship_to['state']])->filter()->implode(', ') }}
                                            {{ $ship_to['zip'] }}
                                        </div>
                                    @else
                                        <div class="text-secondary fst-italic">La misma de facturación</div>
                                    @endif
                                </div>
                            </div>

                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th class="text-end">Cant.</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-end">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($lineas as $i => $linea)
                                    @continue (blank($linea['description'] ?? null))
                                    <tr wire:key="rev-{{ $i }}">
                                        <td>
                                            {{ $linea['description'] }}
                                            @unless ($linea['taxable'])
                                                <span class="badge bg-light text-secondary border">sin tax</span>
                                            @endunless
                                        </td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $linea['quantity'], 2), '0'), '.') }}</td>
                                        <td class="text-end monto">${{ number_format((float) $linea['unit_price'], 2) }}</td>
                                        <td class="text-end monto">${{ number_format($this->importeLinea($i), 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>

                        </div>
                    </div>

                    {{-- ───── TEXTOS ───── --}}
                    <div class="card mb-3 seccion seccion-notas">
                        <div class="card-header">
                            <h6 class="seccion-titulo mb-0">
                                <i class="bi bi-chat-left-text"></i>
                                <span>Lo que se escribe en el documento</span>
                            </h6>
                        </div>
                        <div class="card-body">

                            <div class="mb-3">
                                <label class="form-label">Nota para el cliente</label>
                                <textarea class="form-control" rows="2"
                                          placeholder="Sale impresa en la factura."
                                          wire:model.blur="notes"></textarea>
                            </div>

                            <div>
                                <label class="form-label">Términos del pie</label>
                                <textarea class="form-control" rows="2"
                                          wire:model.blur="footer_terms"></textarea>
                            </div>

                        </div>
                    </div>

                </div>

                {{-- ───── LOS NÚMEROS ───── --}}
                <div class="col-12 col-xl-5">

                    <div class="card mb-3 seccion seccion-datos">
                        <div class="card-header">
                            <h6 class="seccion-titulo mb-0">
                                <span class="paso-num">6</span>
                                <i class="bi bi-calculator"></i>
                                <span>Las cuentas</span>
                            </h6>
                        </div>

                        <div class="card-body">

                            <div class="row g-2 mb-3">

                                <div class="col-6">
                                    <label class="form-label small">Descuento</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01"
                                               class="form-control @error('discount_amount') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="discount_amount">
                                    </div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Anticipo aplicado</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01"
                                               class="form-control @error('deposit_applied') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="deposit_applied">
                                    </div>
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Tasa de impuesto</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01"
                                               class="form-control @error('tax_rate') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="tax_rate"
                                               @disabled($tax_exempt)>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    @if ($tax_exempt)
                                        <div class="form-text text-success">
                                            Cliente exento: no se cobra.
                                        </div>
                                    @endif
                                </div>

                                <div class="col-6">
                                    <label class="form-label small">Cómo va a pagar</label>
                                    <select class="form-select form-select-sm" wire:model.live="expected_payment_method">
                                        <option value="">Sin decidir</option>
                                        @foreach ($metodosDePago as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>

                            {{--
                                EL RECARGO DE TARJETA

                                Aparece solo si se eligió tarjeta. Y con el aviso
                                del formulario firmado, porque ese papel es el
                                único que protege a la empresa si después el dueño
                                de la tarjeta reclama el cargo al banco.
                            --}}
                            @if ($credit_card_fee_percent > 0)
                                <div class="alert alert-warning py-2 small">
                                    <i class="bi bi-credit-card me-1"></i>
                                    <strong>Recargo de tarjeta del {{ rtrim(rtrim(number_format($credit_card_fee_percent, 2), '0'), '.') }}%.</strong>
                                    No se cobra hasta tener el formulario de autorización
                                    <strong>firmado</strong> por el cliente.
                                </div>
                            @endif

                            {{-- ───── EL DESGLOSE ───── --}}
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-secondary">Subtotal</td>
                                        <td class="text-end monto">${{ number_format($t['subtotal'], 2) }}</td>
                                    </tr>

                                    @if ($t['discount_amount'] > 0)
                                        <tr>
                                            <td class="text-secondary">Descuento</td>
                                            <td class="text-end monto text-danger">
                                                −${{ number_format($t['discount_amount'], 2) }}
                                            </td>
                                        </tr>
                                    @endif

                                    <tr>
                                        <td class="text-secondary">
                                            Impuesto
                                            @if ($t['taxable_base'] > 0)
                                                <div class="small">
                                                    sobre ${{ number_format($t['taxable_base'], 2) }}
                                                    @if ($t['non_taxable_base'] > 0)
                                                        · ${{ number_format($t['non_taxable_base'], 2) }} no paga
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end monto">${{ number_format($t['tax_amount'], 2) }}</td>
                                    </tr>

                                    @if ($t['credit_card_fee'] > 0)
                                        <tr>
                                            <td class="text-secondary">Recargo de tarjeta</td>
                                            <td class="text-end monto">${{ number_format($t['credit_card_fee'], 2) }}</td>
                                        </tr>
                                    @endif

                                    @if ($t['deposit_applied'] > 0)
                                        <tr>
                                            <td class="text-secondary">Anticipo</td>
                                            <td class="text-end monto text-danger">
                                                −${{ number_format($t['deposit_applied'], 2) }}
                                            </td>
                                        </tr>
                                    @endif

                                    <tr class="fw-bold border-top fs-5">
                                        <td>TOTAL</td>
                                        <td class="text-end monto">${{ number_format($t['total'], 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>

                        </div>
                    </div>

                </div>

            </div>

        @endif

        {{-- ───── EL PIE ───── --}}
        <div class="ps-pie">

            <div>
                @if ($paso > 1)
                    <button type="button" class="btn btn-outline-secondary" wire:click="pasoAnterior">
                        <i class="bi bi-arrow-left me-1"></i> Atrás
                    </button>
                @else
                    <a href="{{ $invoiceId
                                ? route('finanzas.facturacion.show', $invoiceId)
                                : route('finanzas.facturacion.index') }}"
                       class="btn btn-outline-secondary">Cancelar</a>
                @endif
            </div>

            <div class="ps-pie-medio">
                @if ($errors->any())
                    <span class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        {{ $errors->count() === 1 ? 'Falta 1 dato' : 'Faltan '.$errors->count().' datos' }}
                    </span>
                @else
                    Paso {{ $paso }} de {{ \App\Livewire\Invoices\Form::PASOS }}
                @endif

                <div wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1"></span> Guardando...
                </div>
            </div>

            <div class="d-flex gap-2">

                @if ($paso < \App\Livewire\Invoices\Form::PASOS)
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-save me-1"></i> Guardar borrador
                    </button>

                    <button type="button" class="btn btn-primary" wire:click="siguientePaso">
                        Siguiente <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @else
                    <button type="submit" class="btn btn-outline-success" wire:loading.attr="disabled">
                        <i class="bi bi-save me-1"></i> Guardar sin enviar
                    </button>

                    <button type="button" class="btn btn-success"
                            wire:click="guardar(true)" wire:loading.attr="disabled">
                        <i class="bi bi-send me-1"></i> Guardar y marcar enviada
                    </button>
                @endif

            </div>

        </div>

    </form>

    {{-- ═════════════════════════════════════════════════════════════
         EL EDITOR DE RENGLONES

         Dibujado a mano y no con el JavaScript de Bootstrap. Livewire
         repinta este pedazo cada vez que algo cambia, y un modal abierto
         por JavaScript se queda colgado: el fondo gris pegado y los
         clics bloqueados.
    ═════════════════════════════════════════════════════════════ --}}
    @if ($lineaEditando !== null)
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(15,23,42,.55);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $borradorEsNuevo ? 'Agregar concepto' : 'Editar concepto' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="cancelarLinea"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">

                            {{--
                                EL CONCEPTO VA PRIMERO

                                Elegirlo precarga el precio, si lleva impuesto y
                                el texto que lee el cliente. Todo lo de abajo
                                queda ya relleno y solo hay que ajustar.
                            --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label">Concepto</label>
                                <select class="form-select" wire:model.live="borrador.product_id">
                                    <option value="">— Escribir uno libre —</option>
                                    @foreach ($productos as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Al elegirlo se precargan precio, impuesto y descripción.
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Unidad</label>
                                <select class="form-select" wire:model="borrador.container_id">
                                    <option value="">— Ninguna —</option>
                                    @foreach ($contenedores as $c)
                                        <option value="{{ $c->id }}">
                                            {{ $c->full_identifier }} · {{ $c->size?->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Solo las disponibles: no se factura lo que no está en yarda.
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">
                                    Qué se le cobra <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control @error('borrador.description') is-invalid @enderror"
                                          rows="2"
                                          placeholder="El texto que el cliente va a leer en la factura"
                                          wire:model="borrador.description"></textarea>
                                @error('borrador.description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                                <input type="number" step="0.01"
                                       class="form-control @error('borrador.quantity') is-invalid @enderror"
                                       wire:model.live.debounce.400ms="borrador.quantity">
                                @error('borrador.quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label">Precio <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01"
                                           class="form-control @error('borrador.unit_price') is-invalid @enderror"
                                           wire:model.live.debounce.400ms="borrador.unit_price">
                                </div>
                                @error('borrador.unit_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Sale solo de la unidad elegida. Cámbielo si se negoció otro.
                                </div>
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label">Fecha del servicio</label>
                                <input type="date" class="form-control" wire:model="borrador.service_date">
                                <div class="form-text">
                                    Para el transporte: cada viaje es un día.
                                </div>
                            </div>


                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           id="borradorTaxable" wire:model="borrador.taxable">
                                    <label class="form-check-label" for="borradorTaxable">
                                        Este renglón paga impuesto
                                    </label>
                                </div>
                                <div class="form-text">
                                    El contenedor sí. El transporte <strong>nunca</strong>: en Florida
                                    el flete no paga sales tax.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="rn-desglose">
                                    <div class="rn-dg">
                                        <span class="rn-dg-k">Importe de este renglón</span>
                                        <span class="rn-dg-v">${{ number_format($this->importeBorrador, 2) }}</span>
                                        <span class="rn-dg-n">Cantidad × precio</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="cancelarLinea">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="guardarLinea">
                            <i class="bi bi-check-lg me-1"></i>
                            {{ $borradorEsNuevo ? 'Agregar' : 'Guardar cambios' }}
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
