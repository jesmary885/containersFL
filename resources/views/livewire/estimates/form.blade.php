{{--
    ═══════════════════════════════════════════════════════════════════════
    FORMULARIO DE PRESUPUESTO
    ═══════════════════════════════════════════════════════════════════════

    La pantalla está partida en dos columnas:

      IZQUIERDA (ancha)  el documento: cliente, líneas, notas
      DERECHA (angosta)  los totales y los botones

    En pantallas chicas la derecha baja debajo de la izquierda.

    ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──

      · TODO el texto sale de lang/es/estimates.php y lang/en/estimates.php.
        No queda ni una palabra escrita a mano en este archivo.

      · DESAPARECIÓ la sección "4 · Entrega". Tenía un solo ZIP, unas
        millas y una tarifa para todo el documento, y eso no alcanza:
        un presupuesto de tres contenedores puede ir a tres direcciones
        distintas (RB-049). Ahora cada línea de entrega lleva los suyos,
        en una fila que aparece debajo cuando el concepto es una entrega.

      · Se fue el selector de depósito. El pickup es depósito → yarda,
        lo paga FLCHR y nunca se le cotiza al cliente (RB-031). Y dónde
        está un contenedor ya es un dato del contenedor (RB-048).

      · Las secciones se renumeraron: Conceptos pasó de 5 a 4, y Notas
        de 6 a 5.

      · Apareció "Forma de pago prevista". El presupuesto ya calculaba
        el 3.5% de recargo pero no guardaba con qué iba a pagar, así que
        al reimprimirlo no había forma de saber de dónde salía ese número.

    ── LOS COMENTARIOS SIGUEN EN ESPAÑOL ──

    A propósito. Son para quien programa, no para quien usa el sistema.
    Traducirlos no le sirve a nadie y duplica el trabajo de mantenerlos.
--}}
{{--
    LA RAÍZ DEL COMPONENTE

    Aquí vivía un observador de Alpine que miraba si la tarjeta de totales
    de la derecha seguía a la vista, para encender una barra flotante
    abajo con los mismos números.

    Se fue junto con las dos cosas que vigilaba. Los totales ahora van
    debajo de los conceptos, como en facturación: siempre a la vista y sin
    nada que sincronizar.
--}}
<div>

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    {{--
        no-imprimir: esto es la pantalla, no el documento. Ver la regla
        en el bloque @media print de ajustes.css.
    --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-imprimir">

        <div>
            <h4 class="mb-0">
                @if ($estimateId)
                    {{ __('estimates.title_edit', ['number' => $numero]) }}
                @else
                    {{ __('estimates.title_new') }}
                @endif
            </h4>
            <small class="text-secondary">
                {{ $estimateId ? __('estimates.subtitle_edit') : __('estimates.subtitle_new') }}
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">
            {{--
                LA LEYENDA DE LOS OBLIGATORIOS

                Va arriba, junto al botón de volver, porque es lo primero
                que se mira al entrar y porque desde ahí se ve sin hacer
                scroll.

                El asterisco rojo se usa hace treinta años en formularios
                y casi todo el mundo lo entiende, pero "casi" no alcanza:
                la persona que carga presupuestos ocho horas al día no
                tiene por qué adivinar una convención.
            --}}
            <span class="leyenda-obligatorio">
                <strong>*</strong> {{ __('common.required_field') }}
            </span>

            {{-- Volver es salir: siempre al listado. --}}
            <a href="{{ route('comercial.presupuestos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> {{ __('common.back_to_list') }}
            </a>
        </div>

    </div>

    @if (session('error'))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-circle-fill me-1"></i> {{ session('warning') }}
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
        </div>
    @endif

    {{--
        El resumen de arriba.

        El componente trae dentro el escuchador que hace saltar la
        pantalla al primer campo en rojo, así que con ponerlo alcanza.
    --}}
    <x-ui.errores id="resumen-errores" :titulo="__('estimates.errors_title')" />

    {{--
        Al cambiar de paso la pantalla vuelve arriba.

        Sin esto, quien está al final del paso 1 le da a Siguiente y
        aparece a media altura del paso 2, con la barra de pasos fuera de
        vista. Parece que no pasó nada.
    --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('subir-al-inicio', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    </script>

    {{--
        ═══════════════════════════════════════════════════════════════════
        LA BARRA DE PASOS
        ═══════════════════════════════════════════════════════════════════

        Tres pantallas, las tres dentro de este formulario:

          1 · QUIÉN Y CUÁNDO   cliente, uso, fechas, términos, direcciones
          2 · QUÉ LLEVA        conceptos, grupos y el total
          3 · REVISAR Y ENVIAR el documento como lo va a ver el cliente

        ── QUÉ CAMBIÓ ──

        El paso 3 era un enlace que salía de aquí y llevaba a la ficha.
        Eso obligaba a revisar en una pantalla y corregir en otra: se veía
        un precio raro, había que volver, cambiarlo, y procesar otra vez
        para volver a mirarlo.

        Ahora los tres pasos se navegan igual, como en facturación. Hacia
        atrás es libre; hacia adelante valida lo que queda en medio.
    --}}
    <div class="ps-barra">
        <button type="button"
                class="ps-paso {{ $paso === 1 ? 'ps-activo' : 'ps-hecho' }}"
                wire:click="irAlPaso(1)">
            <span class="ps-bolita">{{ $paso > 1 ? '✓' : '1' }}</span>
            <span class="ps-texto">{{ __('estimates.step_who') }}</span>
        </button>

        <span class="ps-sep"></span>

        <button type="button"
                class="ps-paso {{ $paso === 2 ? 'ps-activo' : ($paso > 2 ? 'ps-hecho' : '') }}"
                wire:click="irAlPaso(2)">
            <span class="ps-bolita">{{ $paso > 2 ? '✓' : '2' }}</span>
            <span class="ps-texto">{{ __('estimates.section_lines') }}</span>
        </button>

        <span class="ps-sep"></span>

        <button type="button"
                class="ps-paso {{ $paso === 3 ? 'ps-activo' : '' }}"
                wire:click="irAlPaso(3)">
            <span class="ps-bolita">3</span>
            <span class="ps-texto">{{ __('estimates.step_review') }}</span>
        </button>
    </div>


    {{--
        LA TIRA DE CONTEXTO

        Es lo que hace que partir el formulario no moleste. Sin ella hay
        que volver al paso 1 cada vez que hace falta comprobar a qué
        cliente se está cotizando.

        Va en TEXTO y no en inputs a propósito: un dato que se lee se
        revisa; un dato dentro de una caja de texto se ignora. Ahí es
        donde se detectan los errores de dedo.
    --}}
    @if ($paso === 2)
        @php $ctx = $this->resumenPaso1; @endphp

        <div class="ps-tira">
            <div class="ps-tira-dato">
                <span class="ps-tira-k">{{ __('estimates.the_customer') }}</span>
                <span class="ps-tira-v {{ $ctx['cliente'] ? '' : 'ps-falta' }}">
                    {{ $ctx['cliente'] ?? __('common.missing') }}
                </span>
            </div>

            <span class="ps-tira-sep"></span>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">{{ __('estimates.use_short') }}</span>
                <span class="ps-tira-v">{{ $ctx['uso'] ?? '—' }}</span>
            </div>

            <span class="ps-tira-sep"></span>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">{{ __('estimates.issue_short') }}</span>
                <span class="ps-tira-v">{{ $ctx['emision'] ?? '—' }}</span>
            </div>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">{{ __('estimates.valid_short') }}</span>
                <span class="ps-tira-v">{{ $ctx['validez'] ?? '—' }}</span>
            </div>

            <span class="ps-tira-sep"></span>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">
                    {{ $ctx['distinta'] ? __('estimates.ship_short') : __('estimates.bill_short') }}
                </span>
                <span class="ps-tira-v {{ $ctx['entrega'] ? '' : 'ps-falta' }}">
                    {{ $ctx['entrega'] ?? __('common.missing') }}
                </span>
            </div>

            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                    wire:click="irAlPaso(1)">
                <i class="bi bi-pencil me-1"></i>{{ __('common.change') }}
            </button>
        </div>
    @endif

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            {{-- ═════════════════════════════════════════════════════
                 COLUMNA IZQUIERDA
            ═════════════════════════════════════════════════════ --}}
            {{--
                En el paso 1 la columna ocupa el ancho completo: no hay
                panel de totales al lado que le robe un tercio. Las
                direcciones, que eran una pila de inputs estrechos,
                respiran.
            --}}
            {{--
                Una sola columna a ancho completo en los tres pasos.

                Antes el paso 2 se partía en 8 y 4 para dejarle sitio al
                panel de totales de la derecha. Ese panel ya no está: los
                conceptos, que es donde se trabaja, se quedan con toda la
                pantalla.
            --}}
            <div class="col-12">

                {{-- ═══════════ PASO 1 · QUIÉN Y CUÁNDO ═══════════ --}}
                @if ($paso === 1)

                {{-- ─────────────────────────────────────────────
                     1 · EL CLIENTE
                ───────────────────────────────────────────── --}}
                <div class="card mb-3 seccion seccion-cliente">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">1</span>
                            <i class="bi bi-person-vcard"></i>
                            <span>{{ __('estimates.section_customer') }}</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        @if ($customer_id)
                            <div class="d-flex justify-content-between align-items-center
                                        border rounded p-3 bg-body-tertiary">
                                <div>
                                    <div class="fw-semibold">{{ $clienteNombre }}</div>
                                    @if ($tax_exempt)
                                        <span class="badge text-bg-info mt-1">
                                            <i class="bi bi-patch-check me-1"></i>
                                            {{ __('estimates.tax_exempt_badge') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="d-flex gap-2">
                                    {{--
                                        EL OJITO.

                                        Abre la ficha del cliente encima del
                                        documento, de solo lectura. Antes
                                        había que irse al módulo de Clientes
                                        a mirar una nota, y volver
                                        significaba empezar el presupuesto
                                        otra vez.
                                    --}}
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            wire:click="abrirFichaCliente"
                                            title="Ver la ficha del cliente sin salir de aquí">
                                        <i class="bi bi-eye"></i>
                                    </button>

                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            wire:click="quitarCliente">
                                        {{ __('common.change') }}
                                    </button>
                                </div>
                            </div>
                        @else
                            <label class="form-label">
                                {{ __('estimates.search_customer') }} <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text"
                                       class="form-control @error('customer_id') is-invalid @enderror"
                                       placeholder="{{ __('estimates.search_customer_ph') }}"
                                       wire:model.live.debounce.300ms="buscarCliente">
                            </div>

                            @error('customer_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
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
                                                {{ $cliente->primary_email
                                                    ?: $cliente->primary_phone
                                                    ?: __('estimates.no_contact') }}
                                            </small>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (strlen(trim($buscarCliente)) >= 2)
                                <div class="text-secondary small mt-2">
                                    {{ __('estimates.customer_not_found') }}
                                </div>
                            @endif
                        @endif

                    </div>
                </div>

                {{-- ─────────────────────────────────────────────
                     2 · DATOS DEL DOCUMENTO
                ───────────────────────────────────────────── --}}
                <div class="card mb-3 seccion seccion-datos">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">2</span>
                            <i class="bi bi-file-text"></i>
                            <span>{{ __('estimates.section_document') }}</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            {{--
                                wire:model.live y no wire:model a secas.

                                Sin .live, el servidor no se entera del
                                cambio hasta que se guarda, y "Válido
                                hasta" no se puede recalcular en el
                                momento.
                            --}}
                            <div class="col-6 col-md-3">
                                <label class="form-label">
                                    {{ __('estimates.issue_date') }} <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       class="form-control @error('issue_date') is-invalid @enderror"
                                       wire:model.live="issue_date">
                                @error('issue_date')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">
                                    {{ __('estimates.valid_until') }}
                                    <i class="bi bi-info-circle text-secondary"
                                       title="{{ __('estimates.valid_until_hint') }}"></i>
                                </label>
                                <input type="date"
                                       class="form-control @error('valid_until') is-invalid @enderror"
                                       wire:model.live="valid_until">
                                @error('valid_until')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('estimates.valid_until_help') }}</div>
                            </div>

                            {{--
                                TÉRMINOS DE PAGO

                                Lista cerrada para poder reportar por
                                término, con "Otro" como salida para el
                                caso raro.

                                Lo que se guarda es siempre la propiedad
                                $terms, nunca el valor "__otro__" del
                                desplegable: ese es una instrucción para
                                la pantalla, no un término de pago.
                            --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label">{{ __('estimates.terms') }}</label>

                                <select class="form-select @error('termsSeleccion') is-invalid @enderror"
                                        wire:model.live="termsSeleccion">
                                    @foreach ($terminosDePago as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                    <option value="{{ $terminoOtro }}">{{ __('estimates.terms_other') }}</option>
                                </select>

                                @if ($termsSeleccion === $terminoOtro)
                                    <input type="text" maxlength="50"
                                           class="form-control mt-2 @error('termsOtro') is-invalid @enderror"
                                           placeholder="{{ __('estimates.terms_other_ph') }}"
                                           wire:model.blur="termsOtro">
                                    @error('termsOtro')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">{{ __('estimates.terms_other_help') }}</div>
                                @endif
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">{{ __('common.salesperson') }}</label>
                                <select class="form-select" wire:model="salesperson_id">
                                    <option value="">{{ __('common.not_assigned') }}</option>
                                    @foreach ($vendedores as $vendedor)
                                        <option value="{{ $vendedor->id }}">{{ $vendedor->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('estimates.salesperson_help') }}</div>
                            </div>

                            {{--
                                ───── FORMA DE PAGO PREVISTA ─────

                                Campo nuevo. El presupuesto ya sumaba el
                                3.5% de recargo por tarjeta, pero no
                                guardaba con qué iba a pagar el cliente.

                                Al reimprimir el documento seis meses
                                después no había forma de explicar de
                                dónde salía ese recargo. Y al convertirlo
                                en factura, la factura sí tiene el campo,
                                así que había que volver a preguntarlo.

                                Al elegir "Tarjeta de crédito" se enciende
                                solo el interruptor del panel de totales.
                            --}}
                            <div class="col-12 col-md-6">
                                <label class="form-label">
                                    {{ __('estimates.payment_method') }}
                                    <i class="bi bi-info-circle text-secondary"
                                       title="{{ __('estimates.payment_method_hint') }}"></i>
                                </label>
                                <select class="form-select @error('expected_payment_method') is-invalid @enderror"
                                        wire:model.live="expected_payment_method">
                                    <option value="">{{ __('estimates.payment_method_unknown') }}</option>
                                    @foreach ($formasDePago as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('expected_payment_method')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            {{--
                                ───── EL USO SALIÓ DE AQUÍ ─────

                                Preguntaba una sola vez para qué se va a usar el
                                contenedor. Y un presupuesto puede llevar varios,
                                con destinos distintos: uno para almacenaje, otro
                                para obra, otro para exportación.

                                Preguntándolo arriba había que elegir uno solo y
                                los demás quedaban mal. Y no es decorativo:
                                RB-056 dice que en exportación solo entra el
                                Cargo Worthy, así que el uso decide qué unidades
                                se pueden ofrecer — por unidad, no por documento.

                                Ahora se pregunta en cada concepto.
                            --}}

                        </div>
                    </div>
                </div>

                {{-- ─────────────────────────────────────────────
                     3 · DIRECCIONES
                ───────────────────────────────────────────── --}}
                <div class="card mb-3 seccion seccion-direccion">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">3</span>
                            <i class="bi bi-geo-alt"></i>
                            <span>{{ __('estimates.section_addresses') }}</span>
                        </h6>

                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox"
                                   id="envio-distinto"
                                   wire:model.live="envioDistinto">
                            <label class="form-check-label small" for="envio-distinto">
                                {{ __('estimates.ship_elsewhere') }}
                            </label>
                        </div>
                    </div>

                    <div class="card-body">

                        <p class="text-secondary small">{!! __('estimates.addresses_intro') !!}</p>

                        {{--
                            ⚠️ EL AVISO QUE FALTABA

                            El sistema hace dos cosas solo al elegir el
                            cliente: copia su dirección fiscal, y si además
                            tiene una dirección de entrega distinta
                            guardada, la copia también y enciende el
                            interruptor.

                            Las dos son correctas, pero pasaban en silencio.
                            El usuario elige un cliente, se le llenan seis
                            campos que no escribió, se le enciende un
                            interruptor que no tocó y aparece una segunda
                            columna. Sin una línea que lo explique, eso no
                            se lee como ayuda: se lee como que el sistema
                            hizo algo raro.

                            Un sistema que actúa solo tiene que decir qué
                            hizo y de dónde lo sacó.
                        --}}
                        @if ($customer_id && $direccionesDelCliente > 0)
                            <div class="alert alert-info py-2 small">
                                <i class="bi bi-magic me-1"></i>
                                {!! __('estimates.autofilled', ['name' => e($clienteNombre)]) !!}

                                @if ($envioDistinto)
                                    {!! __('estimates.autofilled_ship') !!}
                                @endif

                                {{ __('estimates.autofilled_note') }}
                            </div>
                        @endif

                        <div class="row g-3">

                            <div class="{{ $envioDistinto ? 'col-md-6' : 'col-12' }}">
                                <div class="fw-semibold small text-uppercase text-secondary mb-2">
                                    {{ __('estimates.bill_to_label') }}
                                </div>

                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label small mb-1">
                                            {{ __('common.address') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               class="form-control form-control-sm @error('bill_to.line1') is-invalid @enderror"
                                               placeholder="{{ __('estimates.street_ph') }}"
                                               wire:model.blur="bill_to.line1">
                                        @error('bill_to.line1')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label small mb-1">
                                            {{ __('common.address_line2') }}
                                            <span class="text-secondary">{{ __('common.optional') }}</span>
                                        </label>
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="{{ __('estimates.line2_bill_ph') }}"
                                               wire:model.blur="bill_to.line2">
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small mb-1">
                                            {{ __('common.city') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               class="form-control form-control-sm @error('bill_to.city') is-invalid @enderror"
                                               wire:model.blur="bill_to.city">
                                        @error('bill_to.city')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-3">
                                        <label class="form-label small mb-1">
                                            {{ __('common.state') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" maxlength="2"
                                               class="form-control form-control-sm text-uppercase @error('bill_to.state') is-invalid @enderror"
                                               wire:model.blur="bill_to.state">
                                        @error('bill_to.state')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-3">
                                        <label class="form-label small mb-1">
                                            {{ __('common.zip') }} <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               class="form-control form-control-sm @error('bill_to.zip') is-invalid @enderror"
                                               wire:model.blur="bill_to.zip">
                                        @error('bill_to.zip')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            @if ($envioDistinto)
                                <div class="col-md-6">
                                    <div class="fw-semibold small text-uppercase text-secondary mb-2">
                                        {{ __('estimates.ship_to_label') }}
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label small mb-1">
                                                {{ __('common.address') }} <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                   class="form-control form-control-sm @error('ship_to.line1') is-invalid @enderror"
                                                   placeholder="{{ __('estimates.street_ph') }}"
                                                   wire:model.blur="ship_to.line1">
                                            @error('ship_to.line1')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small mb-1">
                                                {{ __('common.address_line2') }}
                                                <span class="text-secondary">{{ __('common.optional') }}</span>
                                            </label>
                                            <input type="text" class="form-control form-control-sm"
                                                   placeholder="{{ __('estimates.line2_ship_ph') }}"
                                                   wire:model.blur="ship_to.line2">
                                        </div>

                                        <div class="col-6">
                                            <label class="form-label small mb-1">
                                                {{ __('common.city') }} <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                   class="form-control form-control-sm @error('ship_to.city') is-invalid @enderror"
                                                   wire:model.blur="ship_to.city">
                                            @error('ship_to.city')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-3">
                                            <label class="form-label small mb-1">
                                                {{ __('common.state') }} <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" maxlength="2"
                                                   class="form-control form-control-sm text-uppercase @error('ship_to.state') is-invalid @enderror"
                                                   wire:model.blur="ship_to.state">
                                            @error('ship_to.state')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-3">
                                            <label class="form-label small mb-1">
                                                {{ __('common.zip') }} <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                   class="form-control form-control-sm @error('ship_to.zip') is-invalid @enderror"
                                                   wire:model.blur="ship_to.zip">
                                            @error('ship_to.zip')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </div>

                        {{--
                            LA CASILLA DE GUARDAR EN LA FICHA

                            Existe porque la pantalla de clientes todavía
                            no está hecha: hoy no hay ningún sitio donde
                            cargarle la dirección a nadie.

                            Sin esto, el cliente número 40 seguiría
                            obligando a teclear la dirección completa en su
                            documento número 15.

                            Se enciende sola cuando el cliente no tiene
                            ninguna dirección guardada. Si ya tiene,
                            arranca apagada: una entrega puntual no debería
                            cambiarle la dirección fiscal a nadie.
                        --}}
                        @if ($customer_id)
                            <div class="border rounded p-2 mt-3 bg-body-tertiary">

                                @if ($direccionesDelCliente === 0)
                                    <div class="small text-warning-emphasis mb-2">
                                        <i class="bi bi-info-circle-fill me-1"></i>
                                        {{ __('estimates.no_saved_address') }}
                                    </div>
                                @endif

                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox"
                                           id="guardar-direccion"
                                           wire:model="guardarDireccionEnCliente">
                                    <label class="form-check-label small" for="guardar-direccion">
                                        {{ __('estimates.save_to_customer') }}
                                        <span class="text-secondary">
                                            {{ __('estimates.save_to_customer_x') }}
                                        </span>
                                    </label>
                                </div>

                            </div>
                        @endif

                        <div class="form-text mt-2">
                            {{ __('estimates.addresses_frozen') }}
                        </div>
                    </div>
                </div>

                @endif {{-- fin paso 1 --}}

                {{-- ═══════════ PASO 2 · QUÉ LLEVA ═══════════ --}}
                @if ($paso === 2)

                {{-- ─────────────────────────────────────────────
                     4 · LAS LÍNEAS

                     Antes era la sección 5. Subió porque desapareció la
                     sección "Entrega" que estaba acá arriba.
                ───────────────────────────────────────────── --}}
                <div class="card mb-3 seccion seccion-lineas">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">4</span>
                            <i class="bi bi-list-ul"></i>
                            <span>{{ __('estimates.section_lines') }}</span>
                        </h6>

                        <div class="d-flex gap-2">
                            {{--
                                EL AGRUPADOR

                                Se marcan dos o más casillas y este botón
                                las junta en un solo renglón impreso. El
                                sistema pone la etiqueta (A, B, C…); el
                                usuario ya no la escribe.
                            --}}
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    wire:click="agruparSeleccionadas"
                                    @disabled(count($seleccionadas) < 2)>
                                <i class="bi bi-collection me-1"></i>
                                {{ __('estimates.group_selected') }}
                                @if (count($seleccionadas) > 0)
                                    ({{ count($seleccionadas) }})
                                @endif
                            </button>

                            <button type="button" class="btn btn-sm btn-primary" wire:click="agregarLinea">
                                <i class="bi bi-plus-lg me-1"></i> {{ __('estimates.add_line') }}
                            </button>
                        </div>
                    </div>

                    @if ($avisoAgrupar)
                        <div class="alert alert-warning small mb-0 rounded-0">
                            <i class="bi bi-info-circle me-1"></i>{{ $avisoAgrupar }}
                        </div>
                    @endif

                    {{--
                        EL AVISO DE EXPORTACIÓN

                        Antes vivía en la sección Entrega, que ya no
                        existe. Se movió acá arriba porque es donde el
                        usuario está por agregar la línea de entrega que
                        casi seguro no debería agregar (RB-017).
                    --}}
                    @if ($use_type === 'export')
                        <div class="alert alert-warning small mb-0 rounded-0">
                            <i class="bi bi-globe-americas me-1"></i>
                            {{ __('estimates.delivery_export') }}
                        </div>
                    @endif

                    <div class="card-body">

                        {{--
                            ═══════════════════════════════════════════════════
                            LOS RENGLONES, COMO TARJETAS
                            ═══════════════════════════════════════════════════

                            Aquí solo se LEE. Para escribir está el modal.

                            La tabla editable anterior le enseñaba las mismas
                            ocho columnas a todos los conceptos. En una renta
                            de contenedor pedía "Cant." —un renglón ES un
                            contenedor, si hay dos se agrega otro renglón— y
                            no había dónde contar qué se le hizo al contenedor
                            en una modificación. El formato no daba para las
                            dos cosas.

                            Cada tarjeta enseña lo que ese concepto tiene:
                            la unidad, el plazo, las millas, el trabajo.
                        --}}
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
                                                {{ $prod?->display_name ?? __('estimates.free_line') }}
                                            </span>

                                            @if ($gr && $pos !== false)
                                                <button type="button" class="cn-grupo"
                                                        title="{{ __('estimates.remove_from_group', ['group' => $gr]) }}"
                                                        wire:click="sacarDelGrupo({{ $i }})">
                                                    {{ $gr }}<i class="bi bi-x"></i>
                                                </button>
                                            @endif
                                        </div>

                                        <div class="cn-desc">
                                            {{ $linea['description'] ?: __('estimates.line_empty') }}
                                        </div>

                                        {{-- Los datos propios del concepto --}}
                                        <div class="cn-datos">
                                            @if ($unidad)
                                                <span class="cn-dato">
                                                    <i class="bi bi-box-seam"></i>{{ $unidad->full_identifier }}
                                                </span>
                                            @endif

                                            @if (! empty($linea['rental_months']))
                                                <span class="cn-dato">
                                                    <i class="bi bi-calendar-range"></i>
                                                    {{ trans_choice('estimates.months_short', $linea['rental_months'], ['count' => $linea['rental_months']]) }}
                                                    · ${{ number_format((float) $linea['unit_price'], 2) }}/{{ __('estimates.month_abbr') }}
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
                                                    {{ rtrim(rtrim(number_format((float) $linea['miles'], 1), '0'), '.') }}
                                                    {{ __('estimates.miles_abbr') }} × ${{ number_format((float) $linea['rate_per_mile'], 2) }}
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
                                                <small>{{ __('estimates.per_month') }}</small>
                                            @endif
                                            <span class="cn-tax {{ ! empty($linea['taxable']) ? 'cn-tax-si' : 'cn-tax-no' }}">
                                                {{ ! empty($linea['taxable']) ? __('estimates.pays_tax') : __('estimates.no_tax') }}
                                            </span>
                                        </div>

                                        <div class="cn-acciones">
                                            <button type="button" class="cn-btn"
                                                    wire:click="abrirLinea({{ $i }})"
                                                    title="{{ __('common.edit') }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="cn-btn cn-btn-borrar"
                                                    wire:click="quitarLinea({{ $i }})"
                                                    title="{{ __('estimates.remove_line') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-secondary py-4">
                                    <i class="bi bi-list-ul d-block mb-2" style="font-size: 1.6rem; color: #cbd5e1;"></i>
                                    {{ __('estimates.no_lines') }}
                                </div>
                            @endforelse
                        </div>

                        @error('lineas')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                    </div>

                    @if (count($grupos) > 0)
                        <div class="card-footer bg-body-tertiary">
                            <div class="fw-semibold small mb-2">
                                <i class="bi bi-collection me-1"></i>
                                {{ __('estimates.groups_title') }}
                            </div>

                            <p class="text-secondary small">{{ __('estimates.groups_intro') }}</p>

                            @foreach ($grupos as $grupo)
                                @php $datos = $this->resumenGrupos[$grupo] ?? ['total' => 0, 'lineas' => []]; @endphp

                                <div class="border rounded p-2 mb-2 bg-body" wire:key="grupo-{{ $grupo }}">

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="small">
                                            <span class="badge text-bg-primary me-1">{{ $grupo }}</span>
                                            {{ __('estimates.group_lines', ['lines' => implode(', ', $datos['lineas'])]) }}
                                            <span class="text-secondary ms-2">
                                                {{ __('estimates.customer_sees') }}
                                                <strong>${{ number_format($datos['total'], 2) }}</strong>
                                            </span>
                                        </div>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                wire:click="desagrupar('{{ $grupo }}')">
                                            <i class="bi bi-scissors me-1"></i> {{ __('common.undo') }}
                                        </button>
                                    </div>

                                    <input type="text" class="form-control form-control-sm"
                                           placeholder="{{ __('estimates.group_text_ph') }}"
                                           wire:model.blur="gruposDescripcion.{{ $grupo }}">

                                    <div class="form-text">{{ __('estimates.group_text_help') }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{--
                    ═══════════════════════════════════════════════════════
                    EL DESGLOSE, MIENTRAS SE CARGAN CONCEPTOS
                    ═══════════════════════════════════════════════════════

                    ── QUÉ SE FUE DE AQUÍ ──

                    El panel pegajoso de la derecha y la barra flotante de
                    abajo. Eran dos sitios distintos enseñando los mismos
                    números, y el de la derecha se comía un tercio de la
                    pantalla justo donde se cargan los renglones.

                    Ahora es lo mismo que en facturación: los conceptos
                    ocupan el ancho completo y el total va debajo, que es
                    donde uno está mirando cuando termina de cargar.

                    Los campos que SE TOCAN —descuento, tasa, exento,
                    tarjeta— se fueron al paso 3, junto a la vista previa.
                    Ahí es donde se decide, con el documento delante.
                --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-6 text-secondary small">
                                {{ trans_choice('estimates.lines_count', count($lineas), ['count' => count($lineas)]) }}
                            </div>

                            <div class="col-12 col-md-6">
                                @php $t2 = $this->totales; @endphp

                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-secondary">{{ __('common.subtotal') }}</td>
                                            <td class="text-end monto">${{ number_format($t2['subtotal'], 2) }}</td>
                                        </tr>

                                        @if ($t2['discount_amount'] > 0)
                                            <tr>
                                                <td class="text-secondary">{{ __('common.discount') }}</td>
                                                <td class="text-end monto text-danger">
                                                    −${{ number_format($t2['discount_amount'], 2) }}
                                                </td>
                                            </tr>
                                        @endif

                                        <tr>
                                            <td class="text-secondary">
                                                {{ __('common.sales_tax') }}
                                                @if ($t2['non_taxable_base'] > 0)
                                                    <div class="small">
                                                        ${{ number_format($t2['non_taxable_base'], 2) }}
                                                        {{ __('estimates.non_taxable_freight') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end monto">${{ number_format($t2['tax_amount'], 2) }}</td>
                                        </tr>

                                        @if ($t2['credit_card_fee'] > 0)
                                            <tr>
                                                <td class="text-secondary">{{ __('common.card_surcharge') }}</td>
                                                <td class="text-end monto">${{ number_format($t2['credit_card_fee'], 2) }}</td>
                                            </tr>
                                        @endif

                                        <tr class="fw-bold border-top fs-5">
                                            <td>{{ __('common.total') }}</td>
                                            <td class="text-end monto">${{ number_format($t2['total'], 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                @endif {{-- fin paso 2 --}}

                {{-- ═══════════ PASO 3 · REVISAR Y ENVIAR ═══════════

                     Antes esto no existía en el formulario: el paso 3 era
                     un enlace que sacaba de aquí y llevaba a la ficha.

                     Ahora es la misma zona de facturación. La vista previa
                     ES el documento —la misma cabecera, las mismas dos
                     direcciones, los mismos renglones y el mismo pie que
                     salen al imprimir— y los ajustes de dinero están al
                     lado, para poder corregir mirando el resultado.

                     La razón es sencilla: si la vista previa no es igual
                     al documento, nadie la mira. Se pulsa guardar y se
                     revisa después, que es justo cuando ya se envió.
                ═══════════════════════════════════════════════ --}}
                @if ($paso === 3)

                @php $t = $this->totales; @endphp

                <div class="row g-3">

                    <div class="col-12 col-xl-7">

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
                                        <div class="fs-4 fw-bold text-uppercase">Estimate</div>
                                        <div class="small">
                                            <div>
                                                <span class="text-secondary">N.º</span>
                                                <strong>{{ $numero ?: __('estimates.number_on_save') }}</strong>
                                            </div>
                                            <div>
                                                <span class="text-secondary">{{ __('estimates.issue_short') }}</span>
                                                {{ $issue_date ? \Carbon\Carbon::parse($issue_date)->format('d/m/Y') : '—' }}
                                            </div>
                                            <div>
                                                <span class="text-secondary">{{ __('estimates.valid_short') }}</span>
                                                {{ $valid_until ? \Carbon\Carbon::parse($valid_until)->format('d/m/Y') : '—' }}
                                            </div>
                                            @if ($terms)
                                                <div class="text-secondary">{{ $terms }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{--
                                    LAS DOS DIRECCIONES SE IMPRIMEN SIEMPRE,
                                    aunque sean la misma. Está confirmado por
                                    el documento real de RST.
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

                                {{--
                                    LOS RENGLONES, YA AGRUPADOS

                                    Aquí se ve lo que de verdad va a leer el
                                    cliente: los renglones de un grupo salen
                                    como UNO SOLO con el precio sumado. Es la
                                    única pantalla donde se comprueba que el
                                    agrupamiento quedó como se quería.
                                --}}
                                <table class="table table-sm">
                                    <thead>
                                        <tr class="border-bottom border-dark">
                                            <th>{{ __('estimates.col_description') }}</th>
                                            <th class="text-end" style="width:70px;">{{ __('estimates.col_qty') }}</th>
                                            <th class="text-end" style="width:110px;">{{ __('estimates.col_price') }}</th>
                                            <th class="text-end" style="width:110px;">{{ __('estimates.col_amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @php
                                        $yaPintados = [];
                                        $hayAlgo    = false;
                                    @endphp

                                    @foreach ($lineas as $i => $linea)
                                        @php
                                            $gr = trim((string) ($linea['grupo'] ?? ''));
                                            $esGrupo = $gr !== '' && in_array($gr, $grupos, true);
                                        @endphp

                                        @continue (blank($linea['description'] ?? null))
                                        @continue ($esGrupo && in_array($gr, $yaPintados, true))

                                        @php
                                            $hayAlgo = true;
                                            if ($esGrupo) { $yaPintados[] = $gr; }
                                            $datos = $esGrupo
                                                ? ($this->resumenGrupos[$gr] ?? ['total' => 0])
                                                : null;
                                            $texto = $esGrupo
                                                ? (trim((string) ($gruposDescripcion[$gr] ?? '')) ?: $linea['description'])
                                                : $linea['description'];
                                            $importe = $esGrupo ? $datos['total'] : $this->importeLinea($i);
                                        @endphp

                                        <tr wire:key="rev-{{ $i }}">
                                            <td>
                                                {{ $texto }}

                                                @if (! $esGrupo && ! empty($linea['work_details']))
                                                    <div class="small text-secondary">{{ $linea['work_details'] }}</div>
                                                @endif

                                                @if (! $esGrupo && ! empty($linea['rental_months']))
                                                    <div class="small text-secondary">
                                                        {{ __('estimates.the_term') }}:
                                                        {{ $linea['rental_months'] }}
                                                        {{ __('estimates.month_abbr') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                {{ $esGrupo ? 1 : rtrim(rtrim(number_format((float) $linea['quantity'], 2), '0'), '.') }}
                                            </td>
                                            <td class="text-end monto">${{ number_format($importe, 2) }}</td>
                                            <td class="text-end monto">${{ number_format($importe, 2) }}</td>
                                        </tr>
                                    @endforeach

                                    @if (! $hayAlgo)
                                        <tr>
                                            <td colspan="4" class="text-center text-secondary py-3">
                                                {{ __('estimates.no_lines') }}
                                            </td>
                                        </tr>
                                    @endif
                                    </tbody>
                                </table>

                                {{-- LOS TOTALES, DEL LADO DERECHO COMO EN EL PAPEL --}}
                                <div class="row">
                                    <div class="col-6">
                                        @if ($notes)
                                            <div class="small">
                                                <div class="text-secondary text-uppercase" style="font-size:.7rem">
                                                    {{ __('estimates.doc_notes') }}
                                                </div>
                                                {{ $notes }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="col-6">
                                        <table class="table table-sm mb-0">
                                            <tbody>
                                                <tr>
                                                    <td class="text-secondary">{{ __('common.subtotal') }}</td>
                                                    <td class="text-end monto">${{ number_format($t['subtotal'], 2) }}</td>
                                                </tr>

                                                @if ($t['discount_amount'] > 0)
                                                    <tr>
                                                        <td class="text-secondary">{{ __('common.discount') }}</td>
                                                        <td class="text-end monto">−${{ number_format($t['discount_amount'], 2) }}</td>
                                                    </tr>
                                                @endif

                                                <tr>
                                                    <td class="text-secondary">
                                                        {{ __('common.sales_tax') }}
                                                        @if ($t['non_taxable_base'] > 0)
                                                            <div class="small">
                                                                ${{ number_format($t['non_taxable_base'], 2) }}
                                                                {{ __('estimates.non_taxable_freight') }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="text-end monto">${{ number_format($t['tax_amount'], 2) }}</td>
                                                </tr>

                                                @if ($t['credit_card_fee'] > 0)
                                                    <tr>
                                                        <td class="text-secondary">{{ __('common.card_surcharge') }}</td>
                                                        <td class="text-end monto">${{ number_format($t['credit_card_fee'], 2) }}</td>
                                                    </tr>
                                                @endif

                                                <tr class="fw-bold border-top border-dark fs-5">
                                                    <td>{{ __('common.total') }}</td>
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
                                    <span>{{ __('estimates.section_notes') }}</span>
                                </h6>
                            </div>
                            <div class="card-body">

                                {{--
                                    .live y no .blur, por lo mismo que en
                                    facturación: con .blur el texto solo viaja
                                    cuando el campo pierde el foco, y al
                                    pulsar un botón desde dentro del textarea
                                    eso no siempre llega a tiempo.
                                --}}
                                <div class="mb-3">
                                    <label class="form-label">{{ __('estimates.doc_notes') }}</label>
                                    <textarea class="form-control" rows="2"
                                              placeholder="{{ __('estimates.doc_notes_ph') }}"
                                              wire:model.live.debounce.500ms="notes"></textarea>
                                </div>

                                <div>
                                    <label class="form-label">{{ __('estimates.footer_terms') }}</label>
                                    <textarea class="form-control" rows="2"
                                              placeholder="{{ __('estimates.footer_terms_ph') }}"
                                              wire:model.live.debounce.500ms="footer_terms"></textarea>
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
                                    <span>{{ __('common.totals') }}</span>
                                </h6>
                            </div>

                            <div class="card-body">

                                <div class="row g-2 mb-3">

                                    <div class="col-6">
                                        <label class="form-label small">{{ __('estimates.discount_field') }}</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0"
                                                   class="form-control @error('discount_amount') is-invalid @enderror"
                                                   wire:model.live.debounce.500ms="discount_amount">
                                        </div>
                                        @error('discount_amount')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small">{{ __('estimates.tax_rate_field') }}</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" min="0" max="100"
                                                   class="form-control @error('tax_rate') is-invalid @enderror"
                                                   wire:model.live.debounce.500ms="tax_rate"
                                                   @disabled($tax_exempt)>
                                            <span class="input-group-text">%</span>
                                        </div>
                                        @if ($tax_exempt)
                                            <div class="form-text text-success">
                                                {{ __('estimates.exempt_note') }}
                                            </div>
                                        @endif
                                        @error('tax_rate')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   id="exento" wire:model.live="tax_exempt">
                                            <label class="form-check-label small" for="exento">
                                                {{ __('estimates.customer_exempt') }}
                                            </label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   id="tarjeta" wire:model.live="pagaConTarjeta">
                                            <label class="form-check-label small" for="tarjeta">
                                                {{ __('estimates.pays_with_card', [
                                                    'percent' => number_format($credit_card_fee_percent, 2),
                                                ]) }}
                                            </label>
                                        </div>
                                    </div>

                                </div>

                                {{--
                                    EL RECARGO DE TARJETA

                                    Sale solo si se marcó tarjeta. Y con el
                                    aviso del formulario firmado, porque ese
                                    papel es lo único que protege a la empresa
                                    si después el dueño de la tarjeta reclama
                                    el cargo al banco (RB-011).
                                --}}
                                @if ($credit_card_fee_percent > 0)
                                    <div class="alert alert-warning py-2 small">
                                        <i class="bi bi-credit-card me-1"></i>
                                        <strong>{{ __('common.card_surcharge') }}
                                        {{ rtrim(rtrim(number_format($credit_card_fee_percent, 2), '0'), '.') }}%.</strong>
                                        {{ __('estimates.card_needs_form') }}
                                    </div>
                                @endif

                                {{-- ───── EL DESGLOSE ───── --}}
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-secondary">{{ __('common.subtotal') }}</td>
                                            <td class="text-end monto">${{ number_format($t['subtotal'], 2) }}</td>
                                        </tr>

                                        @if ($t['discount_amount'] > 0)
                                            <tr>
                                                <td class="text-secondary">{{ __('common.discount') }}</td>
                                                <td class="text-end monto text-danger">
                                                    −${{ number_format($t['discount_amount'], 2) }}
                                                </td>
                                            </tr>
                                        @endif

                                        <tr>
                                            <td class="text-secondary">
                                                {{ __('common.sales_tax') }}
                                                @if ($t['taxable_base'] > 0)
                                                    <div class="small">
                                                        {{ __('common.taxable_base') }}:
                                                        ${{ number_format($t['taxable_base'], 2) }}
                                                        @if ($t['non_taxable_base'] > 0)
                                                            · ${{ number_format($t['non_taxable_base'], 2) }}
                                                            {{ __('estimates.non_taxable_freight') }}
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end monto">${{ number_format($t['tax_amount'], 2) }}</td>
                                        </tr>

                                        @if ($t['credit_card_fee'] > 0)
                                            <tr>
                                                <td class="text-secondary">{{ __('common.card_surcharge') }}</td>
                                                <td class="text-end monto">${{ number_format($t['credit_card_fee'], 2) }}</td>
                                            </tr>
                                        @endif

                                        <tr class="fw-bold border-top fs-5">
                                            <td>{{ __('common.total') }}</td>
                                            <td class="text-end monto">${{ number_format($t['total'], 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                            </div>
                        </div>

                    </div>

                </div>

                @endif {{-- fin paso 3 --}}

            </div>

        </div>


        {{--
            ═══════════════════════════════════════════════════════════════
            EL PIE DE NAVEGACIÓN
            ═══════════════════════════════════════════════════════════════

            Un solo sitio con los botones, y solo los que aplican al paso.
            Es el mismo pie de facturación.

            "Guardar borrador" está en los pasos 1 y 2 a propósito: es la
            salida de emergencia de quien tiene que atender el teléfono a
            mitad de cotización.

            En el paso 3 cambian: antes de guardar, "Guardar sin enviar" y
            "Guardar y enviar"; después de guardar, "Imprimir", "Corregir"
            y "Ver ficha", sin cambiar de pantalla.
        --}}
        <div class="ps-pie">

            <div>
                {{--
                    ───── EL BOTON DE LA IZQUIERDA ─────

                    Tres casos, y el del medio es el que estaba mal.

                      PASO 1            "Cancelar": no hay nada detras.

                      PASOS 2 y 3       "Atras": vuelve al paso anterior
                                        para seguir editando.

                      YA GUARDADO       "Volver al listado".

                    ── QUE PASABA ──

                    Despues de guardar, la flecha seguia diciendo "Atras" y
                    llevaba al paso 2. O sea: acabas de cerrar el
                    presupuesto y el sistema te devuelve a editarle los
                    conceptos, como si no hubiera pasado nada.

                    Una vez guardado, el trabajo terminado. Si hay que
                    cambiar algo esta el boton "Corregir"; para eso es. La
                    flecha tiene que sacarte, no meterte otra vez.
                --}}
                @if ($guardada && $paso === \App\Livewire\Estimates\Form::PASOS)
                    <a href="{{ route('comercial.presupuestos.index') }}"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>{{ __('common.back_to_list') }}
                    </a>
                @elseif ($paso > 1)
                    <button type="button" class="btn btn-outline-secondary"
                            wire:click="pasoAnterior">
                        <i class="bi bi-arrow-left me-1"></i>{{ __('estimates.step_back') }}
                    </button>
                @else
                    <a href="{{ route('comercial.presupuestos.index') }}"
                       class="btn btn-outline-secondary">
                        {{ __('common.cancel') }}
                    </a>
                @endif
            </div>

            <div class="ps-pie-medio">
                @if ($errors->any())
                    <span class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        {{ $errors->count() === 1
                            ? __('estimates.missing_one')
                            : __('estimates.missing_many', ['count' => $errors->count()]) }}
                    </span>
                @else
                    {{ __('estimates.step_n_of', [
                        'paso'  => $paso,
                        'total' => \App\Livewire\Estimates\Form::PASOS,
                    ]) }}
                @endif

                <div wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    {{ __('common.saving') }}
                </div>
            </div>

            <div class="d-flex gap-2">

                @if ($paso < \App\Livewire\Estimates\Form::PASOS)
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-save me-1"></i>{{ __('common.save_draft') }}
                    </button>

                    <button type="button" class="btn btn-primary"
                            wire:click="siguientePaso">
                        {{ __('estimates.step_next') }}<i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @else
                    {{--
                        DESPUÉS DE GUARDAR SE QUEDA AQUÍ.

                        Antes saltaba a la ficha. Ahora la vista previa que
                        ya se estaba mirando es el documento guardado, y
                        las acciones salen debajo.

                        Imprimir usa window.print() sobre esta misma
                        pantalla: el CSS de impresión esconde todo lo demás.
                    --}}
                    @if ($guardada)
                        <button type="button" class="btn btn-outline-primary"
                                onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>{{ __('common.print') }}
                        </button>

                        <button type="submit" class="btn btn-outline-secondary"
                                wire:loading.attr="disabled">
                            <i class="bi bi-pencil me-1"></i>{{ __('common.fix') }}
                        </button>

                        <a href="{{ route('comercial.presupuestos.show', $estimateId) }}"
                           class="btn btn-outline-secondary">
                            <i class="bi bi-file-earmark-text me-1"></i>{{ __('common.view_record') }}
                        </a>

                        <button type="button" class="btn btn-success"
                                wire:click="guardar(true, true)" wire:loading.attr="disabled">
                            <i class="bi bi-envelope-check me-1"></i>{{ __('estimates.save_and_send') }}
                        </button>
                    @else
                        <button type="button" class="btn btn-outline-success"
                                wire:click="guardar(false, true)" wire:loading.attr="disabled">
                            <i class="bi bi-save me-1"></i>{{ __('estimates.save_no_send') }}
                        </button>

                        <button type="button" class="btn btn-success"
                                wire:click="guardar(true, true)" wire:loading.attr="disabled">
                            <i class="bi bi-envelope-check me-1"></i>{{ __('estimates.save_and_send') }}
                        </button>
                    @endif
                @endif

            </div>

        </div>


    </form>

    {{-- ═════════════════════════════════════════════════════════════
         EL BUSCADOR DE UNIDADES

         Se pinta como una capa encima de todo, no como un modal de
         Bootstrap. Es a propósito: los modales de Bootstrap se abren y
         se cierran con JavaScript propio, y cuando Livewire vuelve a
         dibujar la pantalla se quedan a medias —el fondo gris pegado,
         el modal invisible pero bloqueando los clics—.

         Esto es HTML normal que aparece o no según una variable del
         componente. No hay nada que sincronizar.
    ═════════════════════════════════════════════════════════════ --}}
    {{--
        ═══════════════════════════════════════════════════════════════════
        EL MODAL DEL RENGLÓN
        ═══════════════════════════════════════════════════════════════════

        Cada concepto pide lo suyo y solo lo suyo.

          RENTA        unidad, mensualidad y plazo. NO pide cantidad: un
                       renglón es un contenedor. Si hay dos, hay dos
                       renglones.

          VENTA        unidad y precio. Tampoco cantidad, por lo mismo.

          ENTREGA      ZIP, millas y tarifa. El importe se calcula solo.

          REPARACIÓN   qué unidad y QUÉ SE LE HIZO. El detalle largo va en
                       su propio campo: el renglón corto entra en la tabla
                       de importes y el detalle se imprime debajo.

          LIBRE        descripción, cantidad y precio.

        Los campos ocupan lo que necesitan. Un plazo en meses son dos
        dígitos y por eso mide dos columnas de doce, no media pantalla.
    --}}
    @if ($lineaEditando !== null)
        @php
            $prodB     = $this->productoDelBorrador();
            $esRenta   = $prodB?->isRental() ?? false;
            $esVenta   = $prodB?->isSale() ?? false;
            $esEntrega = $prodB?->isDelivery() ?? false;
            $esRepair  = $prodB && $prodB->code === 'REPAIR';
            $pideUnid  = $prodB?->type->requiresContainer() ?? false;
            $unidadB   = $contenedoresElegidos->get($borrador['container_id'] ?? null);
        @endphp

        <div class="rn-fondo" wire:key="editor-renglon"
             x-data x-on:keydown.escape.window="$wire.cancelarLinea()">

            <div class="rn-panel">

                <div class="rn-cabecera">
                    <span class="bu-icono"><i class="bi bi-pencil-square"></i></span>
                    <h6>
                        {{ $borradorEsNuevo ? __('estimates.line_new') : __('estimates.line_edit', ['n' => $lineaEditando + 1]) }}
                        <span class="rn-sub">{{ __('estimates.line_modal_sub') }}</span>
                    </h6>
                    <button type="button" class="bu-cerrar" wire:click="cancelarLinea">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="rn-cuerpo">
                    <div class="rn-grid">

                        {{-- CONCEPTO --}}
                        <div class="rn-c12">
                            <label>{{ __('estimates.col_concept') }}</label>
                            <select class="form-select" wire:model.live="borrador.product_id">
                                <option value="">{{ __('estimates.free_line') }}</option>
                                @foreach ($productos as $producto)
                                    <option value="{{ $producto->id }}">{{ $producto->display_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- ═══ LO PROPIO DEL CONCEPTO ═══ --}}

                        @if ($pideUnid)
                            <div class="rn-bloque">
                                <div class="rn-bloque-tit">
                                    <i class="bi bi-box-seam"></i>{{ __('estimates.block_unit') }}
                                </div>

                                <div class="rn-grid">
                                    <div class="{{ $esRenta ? 'rn-c6' : 'rn-c8' }}">
                                        <label>{{ __('estimates.the_unit') }} <span class="rn-req">*</span></label>

                                        @if ($unidadB)
                                            <div class="rn-unidad">
                                                <span class="rn-unidad-id">
                                                    {{ $unidadB->full_identifier }}
                                                    <span class="rn-unidad-sub">{{ $unidadB->classification }}</span>
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        wire:click="quitarContenedor">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </div>
                                        @else
                                            <button type="button" class="btn btn-outline-primary w-100"
                                                    wire:click="abrirBuscadorContenedor({{ $lineaEditando }})">
                                                <i class="bi bi-search me-1"></i> {{ __('estimates.find_unit') }}
                                            </button>
                                        @endif

                                        @error('borrador.container_id')
                                            <div class="text-danger small mt-1">{{ $message }}</div>
                                        @enderror

                                        {{--
                                            EL USO PREVISTO DE ESTA UNIDAD.

                                            Bajó de la cabecera del documento.
                                            Un presupuesto puede llevar tres
                                            contenedores con tres destinos, y
                                            arriba había que elegir uno solo.

                                            El documento hereda el uso de sus
                                            renglones: si alguno es de
                                            exportación, el documento lo es.
                                        --}}
                                        <div class="mt-2">
                                            <label class="form-label small">
                                                {{ __('estimates.use_type') }}
                                            </label>
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
                                                    {{ __('estimates.export_needs_cert') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="{{ $esRenta ? 'rn-c4' : 'rn-c4' }}">
                                        <label>
                                            {{ $esRenta ? __('estimates.monthly_rate') : __('estimates.col_price') }}
                                            <span class="rn-req">*</span>
                                        </label>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control @error('borrador.unit_price') is-invalid @enderror"
                                               wire:model.live.debounce.400ms="borrador.unit_price">
                                        @if ($unidadB && $esVenta && $unidadB->list_price)
                                            <div class="rn-ayuda">{{ __('common.list_price') }}: ${{ number_format((float) $unidadB->list_price, 2) }}</div>
                                        @endif
                                        @if ($unidadB && $esRenta && $unidadB->monthly_rate)
                                            <div class="rn-ayuda">{{ __('common.list_price') }}: ${{ number_format((float) $unidadB->monthly_rate, 2) }}</div>
                                        @endif
                                        @error('borrador.unit_price')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    @if ($esRenta)
                                        <div class="rn-c2">
                                            <label>{{ __('estimates.months_abbr') }} <span class="rn-req">*</span></label>
                                            <input type="number" step="1" min="1" max="120"
                                                   class="form-control text-center @error('borrador.rental_months') is-invalid @enderror"
                                                   wire:model.live.debounce.400ms="borrador.rental_months">
                                            @error('borrador.rental_months')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{--
                                            EL DESGLOSE DE LA RENTA

                                            Tres números y cada uno contesta una
                                            pregunta distinta:

                                              · LO DE CADA MES     es lo que va a
                                                decir cada factura. Con su tax,
                                                porque el tax se cobra por
                                                factura mensual, no una vez al
                                                firmar (RB-006 + RB-022).

                                              · EL PLAZO           cuántas de esas
                                                facturas van a llegar.

                                              · EL COMPROMISO      lo que el
                                                cliente acaba pagando en total.
                                                Va en gris: es informativo, no es
                                                lo que se cobra hoy ni lo que
                                                suma el presupuesto.

                                            Sin el desglose, "$850.00" en un
                                            renglón de renta es ambiguo: puede
                                            leerse como el total del contrato. Es
                                            la llamada del día siguiente.
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
                                                        <span class="rn-dg-k">{{ __('estimates.each_month') }}</span>
                                                        <span class="rn-dg-v">${{ number_format($mens + $taxM, 2) }}</span>
                                                        @if ($taxM > 0)
                                                            <span class="rn-dg-n">
                                                                ${{ number_format($mens, 2) }}
                                                                + ${{ number_format($taxM, 2) }} tax
                                                            </span>
                                                        @else
                                                            <span class="rn-dg-n">{{ __('estimates.no_tax_long') }}</span>
                                                        @endif
                                                    </div>

                                                    <span class="rn-dg-x">×</span>

                                                    <div class="rn-dg">
                                                        <span class="rn-dg-k">{{ __('estimates.the_term') }}</span>
                                                        <span class="rn-dg-v">{{ $meses }}</span>
                                                        <span class="rn-dg-n">{{ __('estimates.invoices_count') }}</span>
                                                    </div>

                                                    <span class="rn-dg-x">=</span>

                                                    <div class="rn-dg rn-dg-fin">
                                                        <span class="rn-dg-k">{{ __('estimates.commitment') }}</span>
                                                        <span class="rn-dg-v">${{ number_format(($mens + $taxM) * $meses, 2) }}</span>
                                                        <span class="rn-dg-n">{{ __('estimates.commitment_note') }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="rn-c12">
                                            <div class="rn-ayuda">
                                                <i class="bi bi-info-circle me-1"></i>{{ __('estimates.rental_row_help') }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($esEntrega)
                            <div class="rn-bloque">
                                <div class="rn-bloque-tit">
                                    <i class="bi bi-truck"></i>{{ __('estimates.block_delivery') }}
                                </div>

                                <div class="rn-grid">
                                    <div class="rn-c4">
                                        <label>{{ __('estimates.delivery_zip') }} <span class="rn-req">*</span></label>
                                        <input type="text" maxlength="10"
                                               class="form-control @error('borrador.delivery_zip') is-invalid @enderror"
                                               wire:model.blur="borrador.delivery_zip">
                                        @error('borrador.delivery_zip')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="rn-c3">
                                        <label>{{ __('estimates.miles') }} <span class="rn-req">*</span></label>
                                        <input type="number" step="0.1" min="0"
                                               class="form-control text-end @error('borrador.miles') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="borrador.miles">
                                        @error('borrador.miles')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="rn-c3">
                                        <label>{{ __('estimates.rate_per_mile') }} <span class="rn-req">*</span></label>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end @error('borrador.rate_per_mile') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="borrador.rate_per_mile">
                                        @error('borrador.rate_per_mile')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="rn-c2">
                                        <label>{{ __('estimates.col_amount') }}</label>
                                        <input type="text" class="form-control text-end" readonly
                                               value="{{ number_format((float) ($borrador['unit_price'] ?? 0), 2) }}">
                                    </div>

                                    <div class="rn-c12">
                                        <div class="rn-ayuda">
                                            <i class="bi bi-info-circle me-1"></i>{{ __('estimates.delivery_row_help') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($esRepair)
                            <div class="rn-bloque">
                                <div class="rn-bloque-tit">
                                    <i class="bi bi-tools"></i>{{ __('estimates.block_repair') }}
                                </div>

                                <div class="rn-grid">
                                    <div class="rn-c8">
                                        <label>{{ __('estimates.repair_unit') }}</label>

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
                                                <i class="bi bi-search me-1"></i> {{ __('estimates.repair_pick_unit') }}
                                            </button>
                                        @endif

                                        <div class="rn-ayuda">{{ __('estimates.repair_unit_help') }}</div>
                                    </div>

                                    <div class="rn-c4">
                                        <label>{{ __('estimates.col_price') }} <span class="rn-req">*</span></label>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control text-end @error('borrador.unit_price') is-invalid @enderror"
                                               wire:model.live.debounce.400ms="borrador.unit_price">
                                        @error('borrador.unit_price')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="rn-c12">
                                        <label>{{ __('estimates.work_details') }} <span class="rn-req">*</span></label>
                                        <textarea rows="4"
                                                  class="form-control @error('borrador.work_details') is-invalid @enderror"
                                                  placeholder="{{ __('estimates.work_details_ph') }}"
                                                  wire:model.blur="borrador.work_details"></textarea>
                                        <div class="rn-ayuda">{{ __('estimates.work_details_help') }}</div>
                                        @error('borrador.work_details')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- ═══ LO COMÚN ═══ --}}

                        <div class="rn-c12">
                            <label>{{ __('estimates.col_description') }} <span class="rn-req">*</span></label>
                            <input type="text"
                                   class="form-control @error('borrador.description') is-invalid @enderror"
                                   placeholder="{{ __('estimates.description_ph') }}"
                                   wire:model.blur="borrador.description">
                            <div class="rn-ayuda">{{ __('estimates.description_help') }}</div>
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
                                <label>{{ __('estimates.col_qty') }} <span class="rn-req">*</span></label>
                                <input type="number" step="0.01" min="0.01"
                                       class="form-control text-end @error('borrador.quantity') is-invalid @enderror"
                                       wire:model.live.debounce.400ms="borrador.quantity">
                                @error('borrador.quantity')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="rn-c4">
                                <label>{{ __('estimates.col_price') }} <span class="rn-req">*</span></label>
                                <input type="number" step="0.01" min="0"
                                       class="form-control text-end @error('borrador.unit_price') is-invalid @enderror"
                                       wire:model.live.debounce.400ms="borrador.unit_price">
                                @error('borrador.unit_price')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @endunless

                        <div class="{{ ($pideUnid || $esEntrega || $esRepair) ? 'rn-c12' : 'rn-c5' }}">
                            <label>{{ __('estimates.col_tax') }}</label>
                            <div class="form-check form-switch mt-1">
                                <input type="checkbox" class="form-check-input" role="switch"
                                       id="tax-borrador" wire:model.live="borrador.taxable">
                                <label class="form-check-label small" for="tax-borrador">
                                    {{ ! empty($borrador['taxable']) ? __('estimates.pays_tax_long') : __('estimates.no_tax_long') }}
                                </label>
                            </div>
                        </div>

                    </div>

                    <div class="rn-total">
                        <span>
                            {{ $esRenta ? __('estimates.monthly_amount') : __('estimates.line_amount') }}
                        </span>
                        <b>${{ number_format($this->importeBorrador, 2) }}</b>
                    </div>
                </div>

                <div class="rn-pie">
                    <button type="button" class="bu-btn-cerrar" wire:click="cancelarLinea">
                        {{ __('common.cancel') }}
                    </button>

                    <div class="d-flex gap-2">
                        @unless ($borradorEsNuevo)
                            <button type="button" class="btn btn-outline-danger"
                                    wire:click="quitarLinea({{ $lineaEditando }})">
                                <i class="bi bi-trash me-1"></i>{{ __('estimates.remove_line') }}
                            </button>
                        @endunless

                        <button type="button" class="btn btn-primary" wire:click="guardarLinea">
                            <i class="bi bi-check-lg me-1"></i>{{ __('common.save') }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    @endif

    @if ($lineaBuscandoContenedor !== null)
        <div class="bu-fondo bu-sobre-modal"
             wire:key="buscador-unidades"
             x-data
             x-on:keydown.escape.window="$wire.cerrarBuscadorContenedor()">

            <div class="bu-panel">

                {{-- CABECERA --}}
                <div class="bu-cabecera">
                    <span class="bu-icono"><i class="bi bi-box-seam"></i></span>
                    <h6 class="bu-titulo">
                        {{ __('estimates.unit_picker_title', ['line' => $lineaBuscandoContenedor + 1]) }}
                        <span class="bu-sub">{{ __('estimates.unit_picker_sub') }}</span>
                    </h6>
                    <button type="button" class="bu-cerrar"
                            wire:click="cerrarBuscadorContenedor"
                            title="{{ __('common.close') }}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                {{-- BUSQUEDA --}}
                <div class="bu-busqueda">
                    <div class="bu-campo">
                        <i class="bi bi-search"></i>
                        <input type="text" autofocus
                               placeholder="{{ __('estimates.unit_search_ph') }}"
                               wire:model.live.debounce.300ms="buscarContenedor">
                    </div>
                    <p class="bu-ayuda">
                        <i class="bi bi-info-circle me-1"></i>{{ __('estimates.unit_picker_help') }}
                    </p>

                    {{--
                        El aviso de exportación. No existía: el letrero de
                        la línea pedía certificado CSC y aquí salían todas
                        las unidades por igual.
                    --}}
                    @if (($borrador['use_type'] ?? null) === 'export')
                        <p class="bu-ayuda">
                            <i class="bi bi-globe-americas me-1"></i>
                            Esta línea es de <strong>exportación</strong>: solo se pueden
                            cotizar las unidades aptas y con el certificado CSC vigente.
                            Las demás salen en gris con el motivo.
                        </p>
                    @endif
                </div>

                {{-- RESULTADOS --}}
                <div class="bu-lista">
                    @forelse ($this->resultadosContenedor as $unidad)
                        @php
                            $usadaEnLinea = $this->contenedoresYaUsados[$unidad->id] ?? null;

                            /*
                             | Ya ofrecida en OTRO presupuesto abierto.
                             |
                             | No la bloquea: un presupuesto no reserva, es una
                             | cotizacion que vale tres dias y que el cliente
                             | puede no aceptar. Solo avisa, para que el vendedor
                             | sepa que esa unidad ya esta prometida y decida.
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

                            /*
                             | NO CALIFICA PARA EXPORTAR. Esto SÍ bloquea.
                             |
                             | Solo tiene valor cuando la línea es de
                             | exportación; fuera de ese caso la propiedad
                             | viene vacía y esto es null.
                             |
                             | Bloquear aquí importa tanto o más que en la
                             | factura: un presupuesto que cotiza una
                             | unidad que después la factura no acepta es
                             | una oferta que ya salió y no se puede
                             | cumplir.
                             */
                            $noExportable = $this->motivosNoExportable[$unidad->id] ?? null;

                            $bloqueada = $usadaEnLinea || $yaFacturada || $noExportable;
                        @endphp

                        <button type="button" class="bu-item"
                                wire:key="unidad-{{ $unidad->id }}"
                                @disabled($bloqueada)
                                wire:click="seleccionarContenedor({{ $unidad->id }})">

                            <div class="bu-item-datos">
                                <span class="bu-item-id">{{ $unidad->full_identifier }}</span>
                                @if ($unidad->is_export_eligible)
                                    <span class="bu-tag-export">{{ __('estimates.export_eligible') }}</span>
                                @endif

                                <span class="bu-item-clase">
                                    {{ $unidad->classification ?: __('estimates.unclassified') }}
                                </span>

                                @if ($usadaEnLinea)
                                    <span class="bu-aviso-usada">
                                        <i class="bi bi-exclamation-triangle me-1"></i>{{ __('estimates.already_in_line', ['line' => $usadaEnLinea]) }}
                                    </span>
                                @endif

                                {{--
                                    El aviso de "ya cotizada".

                                    Va despues del de "ya esta en la linea N"
                                    porque ese es un error de dedo dentro de este
                                    mismo documento —mas urgente— y este es una
                                    advertencia sobre otro documento.

                                    Se ensena aunque la unidad este deshabilitada:
                                    saber que ademas esta ofrecida a otro cliente
                                    sigue siendo util.
                                --}}
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

                                @if ($noExportable)
                                    <span class="bu-aviso-usada">
                                        <i class="bi bi-globe-americas me-1"></i>
                                        {{ $noExportable }}
                                    </span>
                                @endif

                                @if ($yaCotizada)
                                    <span class="bu-aviso-cotizada">
                                        <i class="bi bi-clock-history me-1"></i>{{ __('estimates.already_quoted', [
                                            'number' => $yaCotizada->estimate_number,
                                            'state'  => mb_strtolower($yaCotizada->status->label()),
                                        ]) }}
                                    </span>
                                @endif
                            </div>

                            <div class="bu-item-precio">
                                @if ($unidad->list_price)
                                    <span class="bu-precio">${{ number_format((float) $unidad->list_price, 2) }}</span>
                                @else
                                    <span class="bu-precio-no">{{ __('estimates.no_price') }}</span>
                                @endif

                                @if ($unidad->monthly_rate)
                                    <span class="bu-renta">
                                        {{ __('estimates.rent_per_month', [
                                            'amount' => number_format((float) $unidad->monthly_rate, 2),
                                        ]) }}
                                    </span>
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="bu-vacio">
                            <i class="bi bi-inbox"></i>
                            @if (trim($buscarContenedor) === '')
                                {{ __('estimates.no_units') }}
                            @else
                                {{ __('estimates.no_units_match', ['term' => $buscarContenedor]) }}
                            @endif
                        </div>
                    @endforelse
                </div>

                {{-- PIE --}}
                <div class="bu-pie">
                    <span class="bu-conteo">
                        {{ trans_choice('estimates.units_found', $this->resultadosContenedor->count(), [
                            'count' => $this->resultadosContenedor->count(),
                        ]) }}

                        {{-- En exportación, cuántas sirven de verdad. --}}
                        @if (($borrador['use_type'] ?? null) === 'export')
                            @php
                                $aptas = $this->resultadosContenedor->count()
                                         - count($this->motivosNoExportable);
                            @endphp
                            <span class="{{ $aptas > 0 ? 'text-muted' : 'text-danger fw-semibold' }}">
                                · {{ $aptas }} apta{{ $aptas === 1 ? '' : 's' }} para exportar
                            </span>
                        @endif
                    </span>
                    <button type="button" class="bu-btn-cerrar"
                            wire:click="cerrarBuscadorContenedor">
                        {{ __('common.close') }}
                    </button>
                </div>

            </div>
        </div>
    @endif


    @include('livewire.partials.ficha-cliente')

</div>
