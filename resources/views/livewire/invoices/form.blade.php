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

    {{--
        ───── ENCABEZADO ─────

        no-imprimir: esto es la pantalla, no el documento. Sin la clase
        salia impreso encima de la factura el titulo "Editar factura",
        la leyenda del asterisco y el boton Volver con su URL al lado.
    --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-imprimir">

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

            {{--
                SIEMPRE AL LISTADO.

                Antes, con la factura ya guardada, este boton llevaba a la
                ficha. Y la ficha es justo la pantalla que nos ahorramos al
                meter la vista previa aqui dentro: salir del formulario
                para caer en otra vista del mismo documento no es volver,
                es dar un rodeo.

                Volver es salir. El unico sitio que siempre existe y desde
                el que se llega a cualquier factura es el listado.
            --}}
            <a href="{{ route('finanzas.facturacion.index') }}"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al listado
            </a>
        </div>

    </div>

    @if ($guardada)
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-1"></i>
            <strong>Factura {{ $numero }} guardada.</strong>
            Puede imprimirla, corregirla o anularla desde abajo. El documento de la izquierda
            es el que quedó grabado.
        </div>
    @endif

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
            <span class="ps-texto">Cliente y condiciones</span>
        </button>

        <span class="ps-sep"></span>

        <button type="button"
                class="ps-paso {{ $paso === 2 ? 'ps-activo' : ($paso > 2 ? 'ps-hecho' : '') }}"
                wire:click="irAlPaso(2)">
            <span class="ps-bolita">{{ $paso > 2 ? '✓' : '2' }}</span>
            <span class="ps-texto">Conceptos</span>
        </button>

        <span class="ps-sep"></span>

        <button type="button"
                class="ps-paso {{ $paso === 3 ? 'ps-activo' : '' }}"
                wire:click="irAlPaso(3)">
            <span class="ps-bolita">3</span>
            <span class="ps-texto">Revisión y emisión</span>
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
                        <span>Cliente</span>
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

            {{--
                ───── QUIÉN VENDIÓ ─────

                Va aquí, en la factura, y no en un módulo aparte, porque en
                el Excel la comisión es una columna de la venta.

                Y es un TRABAJADOR, no un usuario del sistema: Miguelito
                vende desde 2024 y probablemente nunca ha abierto el
                sistema. Denisse teclea la factura, Miguelito la vendió.
                Quedan guardados los dos.

                Importa que esté aquí y no solo en el presupuesto: hay
                ventas que se cierran de boca y van directo a facturar sin
                pasar por cotización. Si el vendedor solo se pudiera poner
                en el presupuesto, esas comisiones se perderían.
            --}}
            <div class="card mb-3 seccion seccion-notas">
                <div class="card-header">
                    <h6 class="seccion-titulo">
                        <i class="bi bi-person-badge"></i>
                        <span>Comisión de venta</span>
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row g-3 align-items-start">

                        <div class="col-12 col-md-5">
                            <label class="form-label">Vendedor</label>
                            <select class="form-select @error('sold_by_employee_id') is-invalid @enderror"
                                    wire:model.live="sold_by_employee_id">
                                <option value="">— Sin comisión —</option>
                                @foreach ($vendedores as $v)
                                    <option value="{{ $v->id }}">
                                        {{ $v->name }}
                                        @if ($v->default_commission_amount !== null)
                                            · ${{ number_format((float) $v->default_commission_amount, 2) }}
                                        @elseif ($v->default_commission_percent !== null)
                                            · {{ rtrim(rtrim(number_format((float) $v->default_commission_percent, 2), '0'), '.') }}%
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('sold_by_employee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-7">
                            @if ($vendedores->isEmpty())
                                <div class="alert alert-warning py-2 small mb-0">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    No hay vendedores cargados.
                                    @can('users.create')
                                        <a href="{{ route('sistema.trabajadores.index') }}" target="_blank">
                                            Regístrelos en Trabajadores
                                        </a>
                                        o corra el seeder para traer los del Excel.
                                    @endcan
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>

            {{-- ───── FECHAS Y TÉRMINOS ───── --}}
            <div class="card mb-3 seccion seccion-datos">
                <div class="card-header">
                    <h6 class="seccion-titulo">
                        <span class="paso-num">2</span>
                        <i class="bi bi-calendar3"></i>
                        <span>Fechas y condiciones</span>
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        {{--
                            EL TIPO SE FUE DE AQUI.

                            Una factura no es "de renta" o "de venta" en su
                            cabecera: lo es por lo que lleva dentro, y puede
                            llevar las dos cosas.

                            La columna se sigue guardando; lo que ya no se hace
                            es preguntarla antes de saber que se va a cobrar.
                        --}}

                        <div class="col-6 col-md-4">
                            <label class="form-label">Emisión <span class="text-danger">*</span></label>
                            <input type="date"
                                   class="form-control @error('issue_date') is-invalid @enderror"
                                   wire:model.live="issue_date">
                            @error('issue_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-6 col-md-3">
                            {{--
                                LOS TÉRMINOS, EN DESPLEGABLE.

                                Como en presupuesto. Escribirlos a mano hacía
                                que "Net 30", "NET 30" y "net 30" fueran tres
                                términos distintos, y al reportar salen como
                                tres cosas.

                                Lo que se guarda es el término tal cual —"Net
                                30"— en los dos idiomas: es texto de un
                                documento legal, el cliente lo conoce así y su
                                contador espera verlo así.

                                Lo que cambia con el idioma es la explicación
                                que se lee dentro del desplegable.
                            --}}
                            <label class="form-label">Términos de pago</label>

                            <select class="form-select @error('terms') is-invalid @enderror"
                                    wire:model.live="termsSeleccion">
                                <option value="">— Sin especificar —</option>
                                @foreach ($terminosDePago as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                                <option value="{{ $terminoOtro }}">{{ __('invoices.terms_other') }}</option>
                            </select>

                            @error('terms')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            @if ($termsSeleccion === $terminoOtro)
                                <input type="text" maxlength="50" class="form-control mt-2"
                                       placeholder="{{ __('invoices.terms_other_ph') }}"
                                       wire:model.blur="termsOtro">
                            @endif
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label">Vence</label>
                            <input type="date"
                                   class="form-control @error('due_date') is-invalid @enderror"
                                   wire:model="due_date">
                            @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                        <span>Direcciones</span>
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

            {{--
                ═══════════════════════════════════════════════════════════
                4 · LOS CONCEPTOS
                ═══════════════════════════════════════════════════════════

                Es la misma zona del presupuesto, tarjeta por tarjeta.

                ── POR QUE SE FUE LA TABLA ──

                Porque le ensenaba las mismas siete columnas a todos los
                conceptos. En una renta pedia "Cant." —un renglon ES un
                contenedor, si hay dos se agrega otro renglon— y no tenia
                donde poner el plazo. En una entrega no tenia donde poner
                las millas. Los datos existian en la base y no habia
                casilla que los mostrara.

                Cada tarjeta ensena lo que ESE concepto tiene: la unidad,
                el plazo, el ZIP, las millas, el trabajo hecho.
            --}}
            <div class="card mb-3 seccion seccion-lineas">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h6 class="seccion-titulo mb-0">
                        <span class="paso-num">4</span>
                        <i class="bi bi-list-ul"></i>
                        <span>Conceptos</span>
                    </h6>

                    <div class="d-flex gap-2">
                        {{--
                            EL AGRUPADOR

                            Se marcan dos o mas casillas y este boton las
                            junta en un solo renglon impreso. La letra la
                            pone el sistema; el usuario ya no la escribe.
                        --}}
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                wire:click="agruparSeleccionadas"
                                @disabled(count($seleccionadas) < 2)>
                            <i class="bi bi-collection me-1"></i>
                            Agrupar
                            @if (count($seleccionadas) > 0)
                                ({{ count($seleccionadas) }})
                            @endif
                        </button>

                        <button type="button" class="btn btn-sm btn-primary" wire:click="agregarLinea">
                            <i class="bi bi-plus-lg me-1"></i> Agregar concepto
                        </button>
                    </div>
                </div>

                @if ($avisoAgrupar)
                    <div class="alert alert-warning small mb-0 rounded-0">
                        <i class="bi bi-info-circle me-1"></i>{{ $avisoAgrupar }}
                    </div>
                @endif

                <div class="card-body">

                    @error('lineas')
                        <div class="alert alert-danger py-2 small">{{ $message }}</div>
                    @enderror

                    <div class="cn-lista">

                        @forelse ($lineas as $i => $linea)
                            @php
                                $prod   = $productos->firstWhere('id', $linea['product_id'] ?? null);
                                $unidad = $contenedoresElegidos->get($linea['container_id'] ?? null);
                                $gr     = trim((string) ($linea['grupo'] ?? ''));
                                $pos    = array_search($gr, $grupos, true);
                                $color  = ($gr && $pos !== false) ? 'cn-g'.(($pos % 6) + 1) : '';
                                $imp    = $this->importeLinea($i);
                                $vacia  = ! $prod && blank($linea['description'] ?? null);
                            @endphp

                            <div class="cn-card {{ $color }} {{ $vacia ? 'cn-vacia' : '' }}"
                                 wire:key="cn-{{ $i }}">

                                <div class="cn-izq">
                                    <input type="checkbox" class="form-check-input"
                                           value="{{ $i }}" wire:model.live="seleccionadas">
                                    <span class="cn-num">{{ $i + 1 }}</span>
                                </div>

                                <div>
                                    <div class="cn-titulo">
                                        <span class="cn-concepto">
                                            {{ $prod?->display_name ?? 'Renglón libre' }}
                                        </span>

                                        @if ($gr && $pos !== false)
                                            <button type="button" class="cn-grupo"
                                                    title="Sacar del grupo {{ $gr }}"
                                                    wire:click="desagrupar('{{ $gr }}')">
                                                {{ $gr }}<i class="bi bi-x"></i>
                                            </button>
                                        @endif
                                    </div>

                                    <div class="cn-desc">
                                        {{ $linea['description'] ?: 'Sin descripción' }}
                                    </div>

                                    {{-- Los datos propios de cada concepto --}}
                                    <div class="cn-datos">
                                        @if ($unidad)
                                            <span class="cn-dato">
                                                <i class="bi bi-box-seam"></i>{{ $unidad->full_identifier }}
                                            </span>
                                        @endif

                                        @if (! empty($linea['rental_months']))
                                            <span class="cn-dato">
                                                <i class="bi bi-calendar-range"></i>
                                                {{ $linea['rental_months'] }} {{ $linea['rental_months'] == 1 ? 'mes' : 'meses' }}
                                                · ${{ number_format((float) $linea['unit_price'], 2) }}/mes
                                            </span>
                                        @endif

                                        @if (! empty($linea['delivery_zip']))
                                            <span class="cn-dato">
                                                <i class="bi bi-geo-alt"></i>{{ $linea['delivery_zip'] }}
                                            </span>
                                        @endif

                                        @if (! empty($linea['miles']))
                                            <span class="cn-dato">
                                                <i class="bi bi-signpost-split"></i>
                                                {{ rtrim(rtrim(number_format((float) $linea['miles'], 1), '0'), '.') }} mi
                                                × ${{ number_format((float) $linea['rate_per_mile'], 2) }}
                                            </span>
                                        @endif

                                        @if (! empty($linea['service_date']))
                                            <span class="cn-dato">
                                                <i class="bi bi-calendar3"></i>
                                                {{ \Carbon\Carbon::parse($linea['service_date'])->format('d/m/Y') }}
                                            </span>
                                        @endif

                                        @if ($prod && ! $prod->type->requiresContainer() && (float) $linea['quantity'] != 1)
                                            <span class="cn-dato">
                                                <i class="bi bi-x-lg"></i>{{ rtrim(rtrim(number_format((float) $linea['quantity'], 2), '0'), '.') }}
                                            </span>
                                        @endif
                                    </div>

                                    @if (! empty($linea['work_details']))
                                        <div class="cn-trabajo">{{ $linea['work_details'] }}</div>
                                    @endif
                                </div>

                                <div class="cn-der">
                                    <div class="cn-importe">
                                        <b>${{ number_format($imp, 2) }}</b>
                                        @if (! empty($linea['rental_months']))
                                            <small>al mes</small>
                                        @endif
                                        <span class="cn-tax {{ ! empty($linea['taxable']) ? 'cn-tax-si' : 'cn-tax-no' }}">
                                            {{ ! empty($linea['taxable']) ? 'paga tax' : 'sin tax' }}
                                        </span>
                                    </div>

                                    <div class="cn-acciones">
                                        <button type="button" class="cn-btn"
                                                wire:click="abrirLinea({{ $i }})" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="cn-btn cn-btn-borrar"
                                                wire:click="quitarLinea({{ $i }})" title="Quitar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-4">
                                <i class="bi bi-receipt d-block mb-2" style="font-size: 1.6rem; color: #cbd5e1;"></i>
                                Todavía no hay nada que cobrar. Agregue el primer concepto.
                            </div>
                        @endforelse
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

                {{--
                    EL PIE DE LOS GRUPOS

                    Solo sale si hay grupos de verdad —dos o más renglones
                    bajo la misma letra—. Aquí se ve el precio consolidado
                    que va a leer el cliente y se escribe el texto con el
                    que sale impreso.

                    Sin este pie, agrupar es un acto de fe: no se ve el
                    número hasta imprimir.
                --}}
                @if (count($grupos) > 0)
                    <div class="card-footer bg-body-tertiary">
                        <div class="fw-semibold small mb-2">
                            <i class="bi bi-collection me-1"></i> Renglones agrupados
                        </div>

                        <p class="text-secondary small">
                            Cada grupo se imprime como un solo renglón con el precio sumado.
                            Por dentro, cada línea conserva su propia marca de impuesto.
                        </p>

                        @foreach ($grupos as $grupo)
                            @php $datos = $this->resumenGrupos[$grupo] ?? ['total' => 0, 'lineas' => []]; @endphp

                            <div class="border rounded p-2 mb-2 bg-body" wire:key="grupo-{{ $grupo }}">

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="small">
                                        <span class="badge text-bg-primary me-1">{{ $grupo }}</span>
                                        Renglones {{ implode(', ', $datos['lineas']) }}
                                        <span class="text-secondary ms-2">
                                            El cliente ve
                                            <strong>${{ number_format($datos['total'], 2) }}</strong>
                                        </span>
                                    </div>

                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            wire:click="desagrupar('{{ $grupo }}')">
                                        <i class="bi bi-scissors me-1"></i> Deshacer
                                    </button>
                                </div>

                                <input type="text" class="form-control form-control-sm"
                                       placeholder="Texto que sale impreso para este grupo"
                                       wire:model.blur="gruposDescripcion.{{ $grupo }}">

                                <div class="form-text">
                                    Si se deja vacío se usa la descripción del renglón más caro.
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- El desglose, mientras se cargan conceptos --}}
            @php $t2 = $this->totales; @endphp

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-6 text-secondary small">
                            {{ count($lineas) }} {{ count($lineas) === 1 ? 'concepto' : 'conceptos' }}
                        </div>

                        <div class="col-12 col-md-6">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-secondary">Subtotal</td>
                                        <td class="text-end monto">${{ number_format($t2['subtotal'], 2) }}</td>
                                    </tr>

                                    @if ($t2['discount_amount'] > 0)
                                        <tr>
                                            <td class="text-secondary">Descuento</td>
                                            <td class="text-end monto text-danger">
                                                −${{ number_format($t2['discount_amount'], 2) }}
                                            </td>
                                        </tr>
                                    @endif

                                    <tr>
                                        <td class="text-secondary">Impuesto</td>
                                        <td class="text-end monto">${{ number_format($t2['tax_amount'], 2) }}</td>
                                    </tr>

                                    @if ($t2['credit_card_fee'] > 0)
                                        <tr>
                                            <td class="text-secondary">Recargo de tarjeta</td>
                                            <td class="text-end monto">${{ number_format($t2['credit_card_fee'], 2) }}</td>
                                        </tr>
                                    @endif

                                    <tr class="fw-bold border-top fs-5">
                                        <td>TOTAL</td>
                                        <td class="text-end monto">${{ number_format($t2['total'], 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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

                    {{--
                        ───── EL DOCUMENTO, COMO SE VA A IMPRIMIR ─────

                        Antes esto era un resumen; ahora es la factura. La
                        misma cabecera, las mismas dos direcciones, los mismos
                        renglones y el mismo pie que salen al imprimir.

                        La razón es sencilla: si la vista previa no es igual al
                        documento, nadie la mira. Se pulsa guardar y se revisa
                        después, que es justo cuando ya se envió.
                    --}}
                    <div class="card mb-3">
                        <div class="card-body doc-preview">

                            {{-- CABECERA --}}
                            <div class="d-flex justify-content-between align-items-start mb-4">
                                <div>
                                    <div class="fs-5 fw-bold">{{ $empresaActual?->legal_name }}</div>
                                    <div class="small text-secondary">
                                        {{ $empresaActual?->address_line1 }}<br>
                                        {{ collect([$empresaActual?->city, $empresaActual?->state])
                                            ->filter()->implode(', ') }} {{ $empresaActual?->zip }}<br>
                                        @if ($empresaActual?->phone) {{ $empresaActual->phone }} @endif
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="fs-4 fw-bold text-uppercase">Invoice</div>
                                    <div class="small">
                                        <div>
                                            <span class="text-secondary">N.º</span>
                                            <strong>{{ $numero ?: 'se asigna al guardar' }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-secondary">Emisión</span>
                                            {{ $issue_date
                                                ? \Carbon\Carbon::parse($issue_date)->format('d/m/Y') : '—' }}
                                        </div>
                                        <div>
                                            <span class="text-secondary">Pagar antes de</span>
                                            {{ $due_date
                                                ? \Carbon\Carbon::parse($due_date)->format('d/m/Y') : '—' }}
                                        </div>
                                        @if ($terms)
                                            <div class="text-secondary">{{ $terms }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{--
                                LAS DOS DIRECCIONES SE IMPRIMEN SIEMPRE,
                                aunque sean la misma. Está confirmado por la
                                factura real de RST.
                            --}}
                            <div class="row g-3 mb-4 small">
                                <div class="col-6">
                                    <div class="text-secondary text-uppercase" style="font-size:.7rem">
                                        Bill to
                                    </div>
                                    <div class="fw-semibold">{{ $clienteNombre }}</div>
                                    <div>{{ $bill_to['line1'] }}</div>
                                    @if ($bill_to['line2'])<div>{{ $bill_to['line2'] }}</div>@endif
                                    <div>
                                        {{ collect([$bill_to['city'], $bill_to['state']])->filter()->implode(', ') }}
                                        {{ $bill_to['zip'] }}
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="text-secondary text-uppercase" style="font-size:.7rem">
                                        Ship to
                                    </div>
                                    @if ($envioDistinto)
                                        <div>{{ $ship_to['line1'] }}</div>
                                        @if ($ship_to['line2'])<div>{{ $ship_to['line2'] }}</div>@endif
                                        <div>
                                            {{ collect([$ship_to['city'], $ship_to['state']])->filter()->implode(', ') }}
                                            {{ $ship_to['zip'] }}
                                        </div>
                                    @else
                                        <div class="fw-semibold">{{ $clienteNombre }}</div>
                                        <div>{{ $bill_to['line1'] }}</div>
                                        <div>
                                            {{ collect([$bill_to['city'], $bill_to['state']])->filter()->implode(', ') }}
                                            {{ $bill_to['zip'] }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- LOS RENGLONES --}}
                            <table class="table table-sm">
                                <thead>
                                    <tr class="border-bottom border-dark">
                                        <th>Descripción</th>
                                        <th class="text-end" style="width:70px;">Cant.</th>
                                        <th class="text-end" style="width:110px;">Precio</th>
                                        <th class="text-end" style="width:110px;">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse ($lineas as $i => $linea)
                                    @continue (blank($linea['description'] ?? null))
                                    <tr wire:key="rev-{{ $i }}">
                                        <td>
                                            {{ $linea['description'] }}
                                            @if ($linea['service_date'])
                                                <div class="small text-secondary">
                                                    {{ \Carbon\Carbon::parse($linea['service_date'])->format('d/m/Y') }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            {{ rtrim(rtrim(number_format((float) $linea['quantity'], 2), '0'), '.') }}
                                        </td>
                                        <td class="text-end monto">
                                            ${{ number_format((float) $linea['unit_price'], 2) }}
                                        </td>
                                        <td class="text-end monto">
                                            ${{ number_format($this->importeLinea($i), 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-secondary py-3">
                                            Sin conceptos. Vuelva al paso anterior.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>

                            {{-- LOS TOTALES, DEL LADO DERECHO COMO EN EL PAPEL --}}
                            <div class="row">
                                <div class="col-6">
                                    @if ($notes)
                                        <div class="small">
                                            <div class="text-secondary text-uppercase" style="font-size:.7rem">
                                                Notas
                                            </div>
                                            {{ $notes }}
                                        </div>
                                    @endif
                                </div>

                                <div class="col-6">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-secondary">Subtotal</td>
                                                <td class="text-end monto">${{ number_format($t['subtotal'], 2) }}</td>
                                            </tr>

                                            @if ($t['discount_amount'] > 0)
                                                <tr>
                                                    <td class="text-secondary">Descuento</td>
                                                    <td class="text-end monto">−${{ number_format($t['discount_amount'], 2) }}</td>
                                                </tr>
                                            @endif

                                            <tr>
                                                <td class="text-secondary">
                                                    Sales tax
                                                    @if ($t['non_taxable_base'] > 0)
                                                        <div class="small">
                                                            ${{ number_format($t['non_taxable_base'], 2) }} exento
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-end monto">${{ number_format($t['tax_amount'], 2) }}</td>
                                            </tr>

                                            @if ($t['credit_card_fee'] > 0)
                                                <tr>
                                                    <td class="text-secondary">Credit card fee</td>
                                                    <td class="text-end monto">${{ number_format($t['credit_card_fee'], 2) }}</td>
                                                </tr>
                                            @endif

                                            @if ($t['deposit_applied'] > 0)
                                                <tr>
                                                    <td class="text-secondary">Anticipo</td>
                                                    <td class="text-end monto">−${{ number_format($t['deposit_applied'], 2) }}</td>
                                                </tr>
                                            @endif

                                            <tr class="fw-bold border-top border-dark fs-5">
                                                <td>TOTAL</td>
                                                <td class="text-end monto">${{ number_format($t['total'], 2) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            @if ($footer_terms)
                                <div class="border-top mt-4 pt-2 small text-secondary">
                                    {{ $footer_terms }}
                                </div>
                            @endif

                        </div>
                    </div>

                    {{-- ───── TEXTOS ───── --}}
                    <div class="card mb-3 seccion seccion-notas">
                        <div class="card-header">
                            <h6 class="seccion-titulo mb-0">
                                <i class="bi bi-chat-left-text"></i>
                                <span>Textos del documento</span>
                            </h6>
                        </div>
                        <div class="card-body">

                            {{--
                                .live Y NO .blur

                                Con .blur el texto solo viaja cuando el campo
                                pierde el foco. Si se escribe y se pulsa un
                                botón directamente desde dentro del textarea,
                                ese aviso no siempre llega a tiempo: el texto
                                se ve en pantalla pero no entra ni en la
                                vista previa ni en lo que se guarda.

                                Es justo lo que pasó con los términos del
                                pie. Con .live la vista previa de al lado se
                                actualiza mientras se escribe, que además es
                                lo que uno espera de una vista previa.
                            --}}
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea class="form-control" rows="2"
                                          placeholder="Salen junto a los totales."
                                          wire:model.live.debounce.500ms="notes"></textarea>
                                <div class="form-text">
                                    Salen abajo a la izquierda, a la altura de los totales.
                                </div>
                            </div>

                            <div>
                                <label class="form-label">Términos del pie</label>
                                <textarea class="form-control" rows="2"
                                          placeholder="Condiciones, garantía, aviso de mora…"
                                          wire:model.live.debounce.500ms="footer_terms"></textarea>
                                <div class="form-text">
                                    Salen al final del todo, separados por una línea.
                                    Se precargan desde la ficha de la empresa.
                                </div>
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

                    {{--
                        ═══════════════════════════════════════════════════
                        LOS ADJUNTOS (RB-034)
                        ═══════════════════════════════════════════════════

                        Aquí, y no solo en la ficha, porque el momento de
                        adjuntar es justo antes de mandar el correo.

                        Antes había que guardar, salir a otra pantalla,
                        subir los papeles y volver a entrar para enviar.
                        Tres pantallas para una sola gestión.

                        no-imprimir: esto es la pantalla, no la factura.
                    --}}
                    <div class="card mb-3 no-imprimir">
                        <div class="card-header">
                            <h6 class="seccion-titulo mb-0">
                                <span class="paso-num">7</span>
                                <i class="bi bi-paperclip"></i>
                                <span>Documentos que van con la factura</span>
                            </h6>
                        </div>

                        <div class="card-body">

                            @if (! $invoiceId)
                                {{--
                                    LA ZONA SE VE, PERO NO DEJA SUBIR.

                                    Un adjunto se cuelga DE la factura:
                                    necesita su número para saber de quién
                                    es. Mientras no exista, no hay a qué
                                    colgarlo.

                                    Se enseña desactivada y con el motivo
                                    escrito, en vez de esconderla: si no se
                                    ve, nadie sabe que existe.
                                --}}
                                <div class="alert alert-secondary small mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Pulse <strong>Guardar sin enviar</strong> y aquí podrá adjuntar
                                    el Credit Card Authorization Form, el certificado de exportación
                                    o cualquier papel que tenga que viajar con la factura.
                                </div>
                            @else

                                {{-- LO QUE YA ESTÁ COLGADO --}}
                                @forelse ($this->documentos as $documento)
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
                                            <button type="button" class="btn btn-outline-secondary"
                                                    wire:click="descargar({{ $documento->id }})"
                                                    title="Descargar">
                                                <i class="bi bi-download"></i>
                                            </button>

                                            <button type="button" class="btn btn-outline-danger"
                                                    wire:click="quitarArchivo({{ $documento->id }})"
                                                    title="Quitar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>

                                    </div>
                                @empty
                                    <div class="text-secondary small text-center py-2">
                                        Todavía no hay nada adjunto.
                                    </div>
                                @endforelse

                                {{-- SUBIR UNO NUEVO --}}
                                <div class="mt-3 pt-3 border-top">

                                    <label class="form-label small">Adjuntar documento</label>

                                    <input type="file"
                                           class="form-control form-control-sm @error('archivo') is-invalid @enderror"
                                           wire:model="archivo">
                                    @error('archivo')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror

                                    {{--
                                        wire:target: el aviso sale mientras
                                        sube ESTE archivo, no en cualquier
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
                                        Desmárcalo para los papeles internos, como la autorización
                                        de tarjeta firmada: esa no sale de la oficina.
                                    </div>

                                    <button type="button" class="btn btn-sm btn-primary w-100 mt-2"
                                            wire:click="subirArchivo"
                                            wire:loading.attr="disabled">
                                        <i class="bi bi-upload me-1"></i> Adjuntar
                                    </button>

                                </div>
                            @endif

                        </div>
                    </div>

                </div>

            </div>

        @endif

        {{-- ───── EL PIE ───── --}}
        <div class="ps-pie">

            <div>
                {{--
                    Ya guardada, la flecha SACA de la pantalla. Antes
                    devolvía al paso 2 a editar los conceptos de una
                    factura que ya estaba emitida.
                --}}
                @if ($guardada && $paso === \App\Livewire\Invoices\Form::PASOS)
                    <a href="{{ route('finanzas.facturacion.index') }}"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Volver al listado
                    </a>
                @elseif ($paso > 1)
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
                    {{--
                        DESPUÉS DE GUARDAR SE QUEDA AQUÍ.

                        Antes saltaba a la ficha, y eso obligaba a mirar el
                        documento en una pantalla y corregirlo en otra. Ahora
                        la vista previa que ya se estaba mirando es el
                        documento guardado, y las acciones salen debajo.
                    --}}
                    {{--
                        ── EL BOTON DE ENVIAR SE QUEDA ──

                        Antes, al guardar sin enviar desaparecia. Y eso
                        rompia justo el camino para el que existe el paso:

                            guardar sin enviar -> adjuntar -> enviar

                        Se guardaba para poder adjuntar y, al volver, ya no
                        habia con que mandarlo. Habia que salir a la ficha.

                        Ahora "Guardar y enviar" esta en los dos estados.
                        Cambia solo la etiqueta: antes de guardar promete
                        las dos cosas; despues, solo la que falta.
                    --}}
                    @if ($guardada)
                        {{--
                            IMPRIME ESTA MISMA PANTALLA.

                            Antes llevaba a la ficha, que es justo la ventana
                            que nos ahorramos. Ahora el navegador imprime el
                            documento de arriba: el CSS de impresión esconde
                            todo lo demás.
                        --}}
                        <button type="button" class="btn btn-outline-primary"
                                onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Imprimir
                        </button>

                        <button type="submit" class="btn btn-outline-secondary"
                                wire:loading.attr="disabled">
                            <i class="bi bi-pencil me-1"></i> Corregir
                        </button>

                        <a href="{{ route('finanzas.facturacion.show', $invoiceId) }}"
                           class="btn btn-outline-danger">
                            <i class="bi bi-x-octagon me-1"></i> Anular
                        </a>

                        {{--
                            El que faltaba. Con los adjuntos ya subidos,
                            este es el boton que cierra la gestion.

                            Dice "Enviar por correo" y no "Guardar y
                            enviar" porque a estas alturas ya esta
                            guardada: repetir la palabra guardar hace
                            dudar de si se va a duplicar algo.
                        --}}
                        <button type="button" class="btn btn-success"
                                wire:click="guardar(true)" wire:loading.attr="disabled">
                            <i class="bi bi-envelope-check me-1"></i> Enviar por correo
                        </button>
                    @else
                        <button type="submit" class="btn btn-outline-success" wire:loading.attr="disabled">
                            <i class="bi bi-save me-1"></i> Guardar sin enviar
                        </button>

                        <button type="button" class="btn btn-success"
                                wire:click="guardar(true)" wire:loading.attr="disabled">
                            <i class="bi bi-envelope-check me-1"></i> Guardar y enviar por correo
                        </button>
                    @endif
                @endif

            </div>

        </div>

    </form>

    {{--
        ═══════════════════════════════════════════════════════════════════
        EL MODAL DEL RENGLÓN
        ═══════════════════════════════════════════════════════════════════

        El mismo del presupuesto. Cada concepto pide lo suyo y solo lo suyo.

          RENTA        unidad, mensualidad y plazo. NO pide cantidad: un
                       renglón es un contenedor. Si hay dos, hay dos
                       renglones.

          VENTA        unidad y precio. Tampoco cantidad, por lo mismo.

          ENTREGA      ZIP, millas y tarifa. El importe se calcula solo.

          REPARACIÓN   qué unidad y QUÉ SE LE HIZO. El detalle largo va en
                       su propio campo: el renglón corto entra en la tabla
                       de importes y el detalle se imprime debajo.

          LIBRE        descripción, cantidad y precio.

        ── POR QUÉ NO ES UN MODAL DE BOOTSTRAP ──

        Porque los de Bootstrap se abren y se cierran con su propio
        JavaScript, y cuando Livewire vuelve a dibujar la pantalla se
        quedan a medias: el fondo gris pegado y los clics bloqueados.

        Esto es HTML normal que aparece o no según una variable del
        componente. No hay nada que sincronizar.
    --}}
    @if ($lineaEditando !== null)
        @php
            $prodB     = $this->productoDelBorrador();
            $esRenta   = $prodB?->isRental() ?? false;
            $esVenta   = $prodB?->isSale() ?? false;
            $esEntrega = $prodB?->isDelivery() ?? false;
            $esRepair  = $prodB && $prodB->code === 'REPAIR';
            $pideUnid  = $prodB?->type->requiresContainer() ?? false;
            $unidadB   = $contenedoresElegidos->get($borrador['container_id'] ?? null)
                         ?? (! empty($borrador['container_id'])
                             ? \App\Models\Container::with(['size:id,name','condition:id,name','grade:id,name'])
                                 ->find($borrador['container_id'])
                             : null);
        @endphp

        <div class="rn-fondo" wire:key="editor-renglon"
             x-data x-on:keydown.escape.window="$wire.cancelarLinea()">

            <div class="rn-panel">

                <div class="rn-cabecera">
                    <span class="bu-icono"><i class="bi bi-pencil-square"></i></span>
                    <h6>
                        {{ $borradorEsNuevo ? 'Agregar concepto' : 'Renglón '.($lineaEditando + 1) }}
                        <span class="rn-sub">Cada concepto pide solo lo suyo</span>
                    </h6>
                    <button type="button" class="bu-cerrar" wire:click="cancelarLinea">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="rn-cuerpo">
                    <div class="rn-grid">

                        {{-- CONCEPTO --}}
                        <div class="rn-c12">
                            <label>Concepto</label>
                            <select class="form-select" wire:model.live="borrador.product_id">
                                <option value="">— Escribir uno libre —</option>
                                @foreach ($productos as $producto)
                                    <option value="{{ $producto->id }}">{{ $producto->display_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- ═══ LO PROPIO DEL CONCEPTO ═══ --}}

                        @if ($pideUnid)
                            <div class="rn-bloque">
                                <div class="rn-bloque-tit">
                                    <i class="bi bi-box-seam"></i>La unidad
                                </div>

                                <div class="rn-grid">
                                    <div class="{{ $esRenta ? 'rn-c6' : 'rn-c8' }}">
                                        <label>Contenedor <span class="rn-req">*</span></label>

                                        @if ($unidadB)
                                            <div class="rn-unidad">
                                                <span class="rn-unidad-id">
                                                    {{ $unidadB->full_identifier }}
                                                    <span class="rn-unidad-sub">{{ $unidadB->classification }}</span>
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        wire:click="quitarContenedor" title="Cambiar">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </div>
                                        @else
                                            <button type="button" class="btn btn-outline-primary w-100"
                                                    wire:click="abrirBuscadorContenedor({{ $lineaEditando }})">
                                                <i class="bi bi-search me-1"></i> Buscar unidad
                                            </button>
                                        @endif

                                        @error('borrador.container_id')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror

                                        {{--
                                            EL USO PREVISTO DE ESTA UNIDAD.

                                            Va por renglón y no en la cabecera:
                                            una factura puede llevar tres
                                            contenedores con tres destinos.
                                        --}}
                                        <div class="mt-2">
                                            <label class="form-label small">Uso previsto</label>
                                            <select class="form-select form-select-sm"
                                                    wire:model.live="borrador.use_type">
                                                <option value="">— Sin especificar —</option>
                                                @foreach ($tiposDeUso as $valor => $etiqueta)
                                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                                @endforeach
                                            </select>

                                            @if (($borrador['use_type'] ?? null) === 'export')
                                                <div class="alert alert-info py-2 small mt-2 mb-0">
                                                    <i class="bi bi-globe-americas me-1"></i>
                                                    Exportación: no lleva sales tax y necesita
                                                    certificado CSC.
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="rn-c4">
                                        <label>
                                            {{ $esRenta ? 'Mensualidad' : 'Precio' }}
                                            <span class="rn-req">*</span>
                                        </label>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control @error('borrador.unit_price') is-invalid @enderror"
                                               wire:model.live.debounce.400ms="borrador.unit_price">
                                        @if ($unidadB && $esVenta && $unidadB->list_price)
                                            <div class="rn-ayuda">Precio de lista: ${{ number_format((float) $unidadB->list_price, 2) }}</div>
                                        @endif
                                        @if ($unidadB && $esRenta && $unidadB->monthly_rate)
                                            <div class="rn-ayuda">Precio de lista: ${{ number_format((float) $unidadB->monthly_rate, 2) }}</div>
                                        @endif
                                        @error('borrador.unit_price')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    @if ($esRenta)
                                        <div class="rn-c2">
                                            <label>Meses <span class="rn-req">*</span></label>
                                            <input type="number" step="1" min="1" max="120"
                                                   class="form-control text-center @error('borrador.rental_months') is-invalid @enderror"
                                                   wire:model.live.debounce.400ms="borrador.rental_months">
                                            @error('borrador.rental_months')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{--
                                            EL DESGLOSE DE LA RENTA

                                            Tres números, cada uno contesta una
                                            pregunta distinta:

                                              · LO DE CADA MES  es lo que va a
                                                decir cada factura mensual, con
                                                su tax: el impuesto se cobra por
                                                factura, no una vez al firmar
                                                (RB-006 + RB-022).

                                              · EL PLAZO        cuántas de esas
                                                facturas van a llegar.

                                              · EL COMPROMISO   lo que el cliente
                                                acaba pagando en total. En gris:
                                                es informativo, no es lo que se
                                                cobra hoy ni lo que suma esta
                                                factura.

                                            Sin el desglose, "$850.00" en un
                                            renglón de renta es ambiguo: puede
                                            leerse como el total del contrato.
                                        --}}
                                        @php
                                            $mens  = (float) ($borrador['unit_price'] ?? 0);
                                            $meses = (int) ($borrador['rental_months'] ?? 0);
                                            $tasa  = $tax_exempt ? 0 : (float) $tax_rate;
                                            $taxM  = ! empty($borrador['taxable']) ? round($mens * $tasa / 100, 2) : 0.0;
                                        @endphp

                                        @if ($meses > 0 && $mens > 0)
                                            <div class="rn-c12">
                                                <div class="rn-desglose">
                                                    <div class="rn-dg">
                                                        <span class="rn-dg-k">Cada mes</span>
                                                        <span class="rn-dg-v">${{ number_format($mens + $taxM, 2) }}</span>
                                                        @if ($taxM > 0)
                                                            <span class="rn-dg-n">
                                                                ${{ number_format($mens, 2) }}
                                                                + ${{ number_format($taxM, 2) }} tax
                                                            </span>
                                                        @else
                                                            <span class="rn-dg-n">no paga impuesto</span>
                                                        @endif
                                                    </div>

                                                    <span class="rn-dg-x">×</span>

                                                    <div class="rn-dg">
                                                        <span class="rn-dg-k">El plazo</span>
                                                        <span class="rn-dg-v">{{ $meses }}</span>
                                                        <span class="rn-dg-n">facturas mensuales</span>
                                                    </div>

                                                    <span class="rn-dg-x">=</span>

                                                    <div class="rn-dg rn-dg-fin">
                                                        <span class="rn-dg-k">El compromiso</span>
                                                        <span class="rn-dg-v">${{ number_format(($mens + $taxM) * $meses, 2) }}</span>
                                                        <span class="rn-dg-n">informativo, no se cobra hoy</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="rn-c12">
                                            <div class="rn-ayuda">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Esta factura cobra un mes. El plazo queda registrado
                                                para saber cuántas quedan por emitir.
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($esEntrega)
                            <div class="rn-bloque">
                                <div class="rn-bloque-tit">
                                    <i class="bi bi-truck"></i>La entrega
                                </div>

                                <div class="rn-grid">
                                    <div class="rn-c4">
                                        <label>ZIP de destino <span class="rn-req">*</span></label>
                                        <input type="text" maxlength="10"
                                               class="form-control @error('borrador.delivery_zip') is-invalid @enderror"
                                               wire:model.blur="borrador.delivery_zip">
                                        @error('borrador.delivery_zip')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="rn-c3">
                                        <label>Millas <span class="rn-req">*</span></label>
                                        <input type="number" step="0.1" min="0"
                                               class="form-control text-end @error('borrador.miles') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="borrador.miles">
                                        @error('borrador.miles')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="rn-c3">
                                        <label>Tarifa por milla <span class="rn-req">*</span></label>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end @error('borrador.rate_per_mile') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="borrador.rate_per_mile">
                                        @error('borrador.rate_per_mile')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="rn-c2">
                                        <label>Importe</label>
                                        <input type="text" class="form-control text-end" readonly
                                               value="{{ number_format((float) ($borrador['unit_price'] ?? 0), 2) }}">
                                    </div>

                                    <div class="rn-c12">
                                        <div class="rn-ayuda">
                                            <i class="bi bi-info-circle me-1"></i>
                                            El importe se calcula: millas × tarifa. El transporte
                                            nunca paga sales tax en Florida (RB-005).
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($esRepair)
                            <div class="rn-bloque">
                                <div class="rn-bloque-tit">
                                    <i class="bi bi-tools"></i>La reparación
                                </div>

                                <div class="rn-grid">
                                    <div class="rn-c8">
                                        <label>Unidad reparada</label>

                                        @if ($unidadB)
                                            <div class="rn-unidad">
                                                <span class="rn-unidad-id">
                                                    {{ $unidadB->full_identifier }}
                                                    <span class="rn-unidad-sub">{{ $unidadB->classification }}</span>
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        wire:click="quitarContenedor">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        @else
                                            <button type="button" class="btn btn-outline-secondary w-100"
                                                    wire:click="abrirBuscadorContenedor({{ $lineaEditando }})">
                                                <i class="bi bi-search me-1"></i> Elegir unidad
                                            </button>
                                        @endif

                                        <div class="rn-ayuda">Opcional: puede ser una unidad del cliente.</div>
                                    </div>

                                    <div class="rn-c4">
                                        <label>Precio <span class="rn-req">*</span></label>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end @error('borrador.unit_price') is-invalid @enderror"
                                               wire:model.live.debounce.400ms="borrador.unit_price">
                                        @error('borrador.unit_price')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="rn-c12">
                                        <label>Trabajo realizado <span class="rn-req">*</span></label>
                                        <textarea rows="4"
                                                  class="form-control @error('borrador.work_details') is-invalid @enderror"
                                                  placeholder="Cambio de pisos, pintura de dos paneles, sustitución de manijas…"
                                                  wire:model.blur="borrador.work_details"></textarea>
                                        <div class="rn-ayuda">
                                            Se imprime debajo del renglón. Es lo que el cliente
                                            aprobó en el presupuesto.
                                        </div>
                                        @error('borrador.work_details')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ═══ LO COMÚN ═══ --}}

                        <div class="rn-c12">
                            <label>Descripción <span class="rn-req">*</span></label>
                            <input type="text"
                                   class="form-control @error('borrador.description') is-invalid @enderror"
                                   placeholder="El texto que el cliente va a leer en la factura"
                                   wire:model.blur="borrador.description">
                            <div class="rn-ayuda">
                                Se escribe sola desde el concepto y la unidad. Si la cambia a mano,
                                deja de reescribirse.
                            </div>
                            @error('borrador.description')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{--
                            CANTIDAD Y PRECIO — solo cuando el concepto no
                            los trajo ya resueltos arriba.

                            En renta, venta, entrega y reparación no salen:
                            el precio ya se pidió en su bloque y la cantidad
                            no significa nada. Un contenedor no viene en
                            cantidades.
                        --}}
                        @unless ($pideUnid || $esEntrega || $esRepair)
                            <div class="rn-c3">
                                <label>Cantidad <span class="rn-req">*</span></label>
                                <input type="number" step="0.01" min="0.01"
                                       class="form-control text-end @error('borrador.quantity') is-invalid @enderror"
                                       wire:model.live.debounce.400ms="borrador.quantity">
                                @error('borrador.quantity')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="rn-c4">
                                <label>Precio <span class="rn-req">*</span></label>
                                <input type="number" step="0.01" min="0"
                                       class="form-control text-end @error('borrador.unit_price') is-invalid @enderror"
                                       wire:model.live.debounce.400ms="borrador.unit_price">
                                @error('borrador.unit_price')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endunless

                        {{--
                            LA FECHA DEL SERVICIO

                            Es lo único que la factura pide y el presupuesto
                            no: cuándo se hizo lo que se está cobrando. En un
                            presupuesto todavía no ha pasado nada.
                        --}}
                        <div class="rn-c3">
                            <label>Fecha del servicio</label>
                            <input type="date" class="form-control" wire:model="borrador.service_date">
                        </div>

                        <div class="{{ ($pideUnid || $esEntrega || $esRepair) ? 'rn-c9' : 'rn-c5' }}">
                            <label>Impuesto</label>
                            <div class="form-check form-switch mt-1">
                                <input type="checkbox" class="form-check-input" role="switch"
                                       id="tax-borrador" wire:model.live="borrador.taxable">
                                <label class="form-check-label small" for="tax-borrador">
                                    {{ ! empty($borrador['taxable']) ? 'paga impuesto' : 'no paga impuesto' }}
                                </label>
                            </div>
                        </div>

                    </div>

                    <div class="rn-total">
                        <span>{{ $esRenta ? 'Importe mensual' : 'Importe del renglón' }}</span>
                        <b>${{ number_format($this->importeBorrador, 2) }}</b>
                    </div>
                </div>

                <div class="rn-pie">
                    <button type="button" class="bu-btn-cerrar" wire:click="cancelarLinea">
                        Cancelar
                    </button>

                    <div class="d-flex gap-2">
                        @unless ($borradorEsNuevo)
                            <button type="button" class="btn btn-outline-danger"
                                    wire:click="quitarLinea({{ $lineaEditando }})">
                                <i class="bi bi-trash me-1"></i>Quitar renglón
                            </button>
                        @endunless

                        <button type="button" class="btn btn-primary" wire:click="guardarLinea">
                            <i class="bi bi-check-lg me-1"></i>
                            {{ $borradorEsNuevo ? 'Agregar' : 'Guardar cambios' }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

    {{--
        ═══════════════════════════════════════════════════════════════════
        EL BUSCADOR DE UNIDADES
        ═══════════════════════════════════════════════════════════════════

        Una capa por encima del modal, a pantalla completa.

        Antes era una lista de 220 píxeles metida en media columna del
        propio modal. Con doscientos contenedores en yarda, elegir ahí es
        adivinar: no cabe la clasificación, no cabe el precio, y no hay
        sitio para avisar de nada.
    --}}
    @if ($lineaBuscandoContenedor !== null)
        <div class="bu-fondo bu-sobre-modal"
             wire:key="buscador-unidades"
             x-data
             x-on:keydown.escape.window="$wire.cerrarBuscadorContenedor()">

            <div class="bu-panel">

                <div class="bu-cabecera">
                    <span class="bu-icono"><i class="bi bi-box-seam"></i></span>
                    <h6 class="bu-titulo">
                        Unidad para el renglón {{ $lineaBuscandoContenedor + 1 }}
                        <span class="bu-sub">Solo las que están en yarda y libres</span>
                    </h6>
                    <button type="button" class="bu-cerrar"
                            wire:click="cerrarBuscadorContenedor" title="Cerrar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="bu-busqueda">
                    <div class="bu-campo">
                        <i class="bi bi-search"></i>
                        <input type="text" autofocus
                               placeholder="Número, código interno o medida…"
                               wire:model.live.debounce.300ms="buscarContenedor">
                    </div>
                    <p class="bu-ayuda">
                        <i class="bi bi-info-circle me-1"></i>
                        No salen las compradas que siguen en el depósito del proveedor:
                        no se factura lo que no se ha retirado (RB-019).
                    </p>
                </div>

                <div class="bu-lista">
                    @forelse ($this->resultadosContenedor as $unidad)
                        @php
                            $usadaEnLinea = $this->contenedoresYaUsados[$unidad->id] ?? null;

                            /*
                             | Ya ofrecida en un presupuesto abierto.
                             |
                             | No bloquea: la factura manda sobre la
                             | cotización. Pero conviene saber a quién hay
                             | que llamar para avisarle.
                             */
                            $yaCotizada = $this->cotizadasEnOtros[$unidad->id] ?? null;

                            /*
                             | YA FACTURADA. Esto SÍ bloquea.
                             |
                             | Un presupuesto es una oferta que puede no
                             | aceptarse nunca; una factura es dinero que se
                             | está cobrando. Ofrecer esa unidad otra vez es
                             | prometer algo que ya tiene dueño.
                             */
                            $yaFacturada = $this->comprometidasEnFacturas[$unidad->id] ?? null;

                            $bloqueada = $usadaEnLinea || $yaFacturada;
                        @endphp

                        <button type="button" class="bu-item"
                                wire:key="unidad-{{ $unidad->id }}"
                                @disabled($bloqueada)
                                wire:click="seleccionarContenedor({{ $unidad->id }})">

                            <div class="bu-item-datos">
                                <span class="bu-item-id">{{ $unidad->full_identifier }}</span>
                                @if ($unidad->is_export_eligible)
                                    <span class="bu-tag-export">apta para exportar</span>
                                @endif

                                <span class="bu-item-clase">
                                    {{ $unidad->classification ?: 'sin clasificar' }}
                                </span>

                                @if ($usadaEnLinea)
                                    <span class="bu-aviso-usada">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Ya está en el renglón {{ $usadaEnLinea }}
                                    </span>
                                @endif

                                @if ($yaFacturada)
                                    <span class="bu-aviso-usada">
                                        <i class="bi bi-lock-fill me-1"></i>
                                        Ya facturada en la {{ $yaFacturada->invoice_number }}
                                        @if ((float) $yaFacturada->balance_due > 0)
                                            · pendiente de cobro
                                        @else
                                            · cobrada
                                        @endif
                                    </span>
                                @endif

                                @if ($yaCotizada)
                                    <span class="bu-aviso-cotizada">
                                        <i class="bi bi-clock-history me-1"></i>
                                        Ofrecida en el presupuesto {{ $yaCotizada->estimate_number }}
                                        ({{ mb_strtolower($yaCotizada->status->label()) }})
                                    </span>
                                @endif
                            </div>

                            <div class="bu-item-precio">
                                @if ($unidad->list_price)
                                    <span class="bu-precio">${{ number_format((float) $unidad->list_price, 2) }}</span>
                                @else
                                    <span class="bu-precio-no">sin precio</span>
                                @endif

                                @if ($unidad->monthly_rate)
                                    <span class="bu-renta">
                                        ${{ number_format((float) $unidad->monthly_rate, 2) }}/mes
                                    </span>
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="bu-vacio">
                            <i class="bi bi-inbox"></i>
                            @if (trim($buscarContenedor) === '')
                                No hay unidades disponibles en este momento.
                            @else
                                Ninguna unidad coincide con «{{ $buscarContenedor }}».
                            @endif
                        </div>
                    @endforelse
                </div>

                <div class="bu-pie">
                    <span class="bu-conteo">
                        {{ $this->resultadosContenedor->count() }}
                        {{ $this->resultadosContenedor->count() === 1 ? 'unidad' : 'unidades' }}
                    </span>
                    <button type="button" class="bu-btn-cerrar"
                            wire:click="cerrarBuscadorContenedor">
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
