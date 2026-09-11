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
    x-data va en la raíz del componente para que el observador siga vivo
    cuando Livewire vuelve a pintar la pantalla.

    Lo que hace: mirar si la tarjeta de totales está a la vista. Si no lo
    está, aparece la barra de abajo. Nada más.
--}}
<div class="con-barra-totales"
     x-data="{ totalesVisibles: true }"
     x-init="
        $nextTick(() => {
            const tarjeta = $refs.tarjetaTotales;
            if (!tarjeta) return;

            new IntersectionObserver(
                ([e]) => { totalesVisibles = e.isIntersecting },
                { threshold: 0.2 }
            ).observe(tarjeta);
        })
     ">

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

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

        Dos pantallas acá y una tercera que no es este formulario:

          1 · QUIÉN Y CUÁNDO   cliente, uso, fechas, términos, direcciones
          2 · QUÉ LLEVA        conceptos, grupos, notas, totales
          3 · REVISAR          la ficha, con el documento armado

        El 3 sale deshabilitado hasta que se le da a Procesar, y entonces
        deja de ser este formulario: revisar es leer el documento como lo
        va a ver el cliente, y eso ya existe en show.blade.php. Pintarlo
        también acá sería mantener dos veces la misma plantilla.
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
                class="ps-paso {{ $paso === 2 ? 'ps-activo' : '' }}"
                wire:click="irAlPaso(2)">
            <span class="ps-bolita">2</span>
            <span class="ps-texto">{{ __('estimates.step_what') }}</span>
        </button>

        <span class="ps-sep"></span>

        {{--
            EL PASO 3 ES NAVEGABLE SI EL DOCUMENTO YA EXISTE.

            Un presupuesto que ya pasó por Procesar tiene su documento
            armado. Volver al paso 2 a comprobar un precio y querer
            regresar no debería obligar a procesar de nuevo: no se
            cambió nada.

            Si es nuevo, o sigue en borrador, sale deshabilitado: no hay
            documento que revisar todavía, y el único camino es el botón
            Procesar de abajo.
        --}}
        @if ($this->puedeIrARevisar)
            <a href="{{ route('comercial.presupuestos.show', $estimateId) }}"
               class="ps-paso ps-hecho"
               title="{{ __('estimates.step_review_go') }}">
                <span class="ps-bolita">3</span>
                <span class="ps-texto">{{ __('estimates.step_review') }}</span>
            </a>
        @else
            <button type="button" class="ps-paso" disabled
                    title="{{ __('estimates.step_review_locked') }}">
                <span class="ps-bolita">3</span>
                <span class="ps-texto">{{ __('estimates.step_review') }}</span>
            </button>
        @endif
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
            <div class="col-12 {{ $paso === 2 ? 'col-xl-8' : '' }}">

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

                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        wire:click="quitarCliente">
                                    {{ __('common.change') }}
                                </button>
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

                            {{-- ───── TIPO DE USO ───── --}}
                            <div class="col-12">
                                <label class="form-label">
                                    {{ __('estimates.use_type') }} <span class="text-danger">*</span>
                                </label>

                                <div class="d-flex gap-3">
                                    @foreach ($tiposDeUso as $valor => $etiqueta)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   id="uso-{{ $valor }}"
                                                   value="{{ $valor }}"
                                                   wire:model.live="use_type">
                                            <label class="form-check-label" for="uso-{{ $valor }}">
                                                {{ $etiqueta }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>

                                {{--
                                    El aviso de exportación, escrito para
                                    el vendedor y no para el programador.
                                --}}
                                @if ($use_type === 'export')
                                    <div class="alert alert-info mt-3 mb-0">
                                        <strong>
                                            <i class="bi bi-globe-americas me-1"></i>
                                            {{ __('estimates.export_notice') }}
                                        </strong>
                                        <ul class="mb-0 mt-2 small">
                                            <li>{{ __('estimates.export_no_tax') }}</li>
                                            <li>{{ __('estimates.export_needs_cert') }}</li>
                                            <li>{{ __('estimates.export_no_delivery') }}</li>
                                        </ul>
                                    </div>
                                @endif
                            </div>

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

                {{-- ─────────────────────────────────────────────
                     5 · NOTAS
                ───────────────────────────────────────────── --}}
                <div class="card mb-3 seccion seccion-notas">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">5</span>
                            <i class="bi bi-sticky"></i>
                            <span>{{ __('estimates.section_notes') }}</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('estimates.doc_notes') }}</label>
                                <textarea class="form-control" rows="3"
                                          placeholder="{{ __('estimates.doc_notes_ph') }}"
                                          wire:model="notes"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('estimates.footer_terms') }}</label>
                                <textarea class="form-control" rows="3"
                                          placeholder="{{ __('estimates.footer_terms_ph') }}"
                                          wire:model="footer_terms"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                @endif {{-- fin paso 2 --}}

            </div>

            {{-- ═════════════════════════════════════════════════════
                 COLUMNA DERECHA · LOS TOTALES

                 Solo en el paso 2. En el paso 1 no hay conceptos
                 todavía: un panel de totales en $0.00 al lado de los
                 datos del cliente no informa de nada y ocupa un tercio
                 de la pantalla.
            ═════════════════════════════════════════════════════ --}}
            @if ($paso === 2)
            <div class="col-12 col-xl-4">
                {{--
                    panel-pegajoso y no position-sticky de Bootstrap.

                    La clase de Bootstrap ya estaba puesta y no hacía
                    nada: `position: sticky` se rompe en silencio si
                    algún contenedor de más arriba tiene overflow
                    distinto de visible, y el layout de AdminLTE lo trae
                    en varios sitios para que el sidebar no desborde.

                    No da error. El elemento simplemente se comporta como
                    si la propiedad no existiera.

                    La clase nueva vive en sistema.css y arregla las dos
                    cosas: apaga ese overflow y le pone al panel una
                    altura máxima con scroll propio, para que con quince
                    líneas cargadas siga entrando en la pantalla.
                --}}
                <div class="panel-pegajoso">

                    {{--
                        ═══════════════════════════════════════════════
                        EL PANEL DE TOTALES
                        ═══════════════════════════════════════════════

                        ── POR QUÉ NO USA .card DE BOOTSTRAP ──

                        Porque la palabra "Totales" te salía en blanco
                        sobre blanco. El encabezado tenía que ser oscuro,
                        pero AdminLTE define .card-header con la misma
                        fuerza que nuestra regla, y cuando dos reglas
                        pesan igual gana la que el navegador lee de
                        último. Según qué archivo cargue primero, ganaba
                        una u otra. El texto quedaba blanco y el fondo
                        también.

                        Este panel tiene marcado propio y clases propias.
                        No comparte ni un nombre con Bootstrap, así que no
                        hay nada con qué pelearse.

                        ── CÓMO ESTÁ ARMADO ──

                        Tres zonas, de arriba abajo:

                          1. Cabecera oscura: dice qué es esto.
                          2. Los ajustes que SE TOCAN: descuento, tax,
                             exento, tarjeta.
                          3. El desglose que SALE: subtotal, bases,
                             impuesto, y el total en una banda verde.

                        Lo que se toca arriba, lo que sale abajo. Cuando
                        están mezclados, la gente intenta escribir encima
                        del total.

                        x-ref es la marca que busca el observador de la
                        raíz. Cuando esta tarjeta sale de la pantalla,
                        aparece la barra de totales de abajo.
                    --}}
                    <div class="panel-totales" x-ref="tarjetaTotales">

                        <div class="pt-cabecera">
                            <i class="bi bi-calculator-fill"></i>
                            <span>{{ __('common.totals') }}</span>
                        </div>

                        {{-- ── 1 · LO QUE SE TOCA ── --}}
                        <div class="pt-ajustes">
                            <div class="row g-2">

                                <div class="col-6">
                                    <label class="form-label small mb-1">{{ __('estimates.discount_field') }}</label>
                                    <input type="number" step="0.01" min="0"
                                           class="form-control form-control-sm text-end @error('discount_amount') is-invalid @enderror"
                                           wire:model.live.debounce.500ms="discount_amount">
                                    @error('discount_amount')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-6">
                                    <label class="form-label small mb-1">{{ __('estimates.tax_rate_field') }}</label>
                                    <input type="number" step="0.01" min="0" max="100"
                                           class="form-control form-control-sm text-end @error('tax_rate') is-invalid @enderror"
                                           wire:model.live.debounce.500ms="tax_rate"
                                           @disabled($tax_exempt)>
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
                        </div>

                        {{-- ── 2 · LO QUE SALE ── --}}
                        <div class="pt-desglose">

                            <div class="pt-fila">
                                <span class="pt-etiqueta">{{ __('common.subtotal') }}</span>
                                <span class="pt-valor">
                                    ${{ number_format($this->totales['subtotal'], 2) }}
                                </span>
                            </div>

                            @if ($this->totales['discount_amount'] > 0)
                                <div class="pt-fila pt-resta">
                                    <span class="pt-etiqueta">{{ __('common.discount') }}</span>
                                    <span class="pt-valor">
                                        −${{ number_format($this->totales['discount_amount'], 2) }}
                                    </span>
                                </div>
                            @endif

                            {{--
                                Las dos bases van juntas y en su propio
                                bloque, porque cuentan la misma historia:
                                de todo lo cotizado, esto paga impuesto y
                                esto no.

                                Es el renglón que más preguntas evita.
                                Sobre qué monto se calcula el 7% casi
                                nunca es el total, y cuando el cliente
                                pregunta hay que poder responderlo sin
                                sacar la calculadora.
                            --}}
                            <div class="pt-bases">
                                <div class="pt-fila pt-menor">
                                    <span class="pt-etiqueta">
                                        <i class="bi bi-dot"></i> {{ __('common.taxable_base') }}
                                    </span>
                                    <span class="pt-valor">
                                        ${{ number_format($this->totales['taxable_base'], 2) }}
                                    </span>
                                </div>

                                @if ($this->totales['non_taxable_base'] > 0)
                                    <div class="pt-fila pt-menor">
                                        <span class="pt-etiqueta">
                                            <i class="bi bi-dot"></i> {{ __('estimates.non_taxable_freight') }}
                                        </span>
                                        <span class="pt-valor">
                                            ${{ number_format($this->totales['non_taxable_base'], 2) }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="pt-fila">
                                <span class="pt-etiqueta">
                                    {{ __('common.sales_tax') }}
                                    <span class="pt-chip">
                                        {{ number_format($tax_exempt ? 0 : $tax_rate, 2) }}%
                                    </span>
                                </span>
                                <span class="pt-valor">
                                    ${{ number_format($this->totales['tax_amount'], 2) }}
                                </span>
                            </div>

                            @if ($this->totales['credit_card_fee'] > 0)
                                <div class="pt-fila">
                                    <span class="pt-etiqueta">
                                        {{ __('common.card_surcharge') }}
                                        <span class="pt-chip">
                                            {{ number_format($credit_card_fee_percent, 2) }}%
                                        </span>
                                    </span>
                                    <span class="pt-valor">
                                        ${{ number_format($this->totales['credit_card_fee'], 2) }}
                                    </span>
                                </div>
                            @endif

                        </div>

                        {{--
                            ── 3 · LA BANDA DEL TOTAL ──

                            Es el número que va a mirar el cliente y el
                            que va a mirar quien cotiza, así que se lleva
                            todo el peso visual del panel: banda verde,
                            texto grande, moneda separada.

                            Verde y no azul porque en este sistema el
                            verde es dinero cerrado. El mismo criterio que
                            en los contadores del listado.
                        --}}
                        <div class="pt-total">
                            <span class="pt-total-label">{{ __('common.total') }}</span>
                            <span class="pt-total-valor">
                                <span class="pt-moneda">$</span>{{ number_format($this->totales['total'], 2) }}
                            </span>
                        </div>

                        @if ($tax_exempt)
                            <div class="pt-nota">
                                <i class="bi bi-patch-check-fill"></i>
                                <span>{{ __('estimates.exempt_note') }}</span>
                            </div>
                        @endif

                    </div>

                    {{--
                        Los botones se fueron al pie de navegación.

                        Estaban acá Y en una barra abajo, los dos juegos
                        con "Guardar borrador" y "Procesar". Dos botones
                        iguales en la misma pantalla obligan a pararse a
                        pensar si de verdad hacen lo mismo.

                        Lo que sí se queda es el resumen de errores: es
                        donde el usuario tiene la vista cuando revisa los
                        números.
                    --}}
                    <div class="card">
                        <div class="card-body py-2">
                            <x-ui.errores class="small mb-0 py-2" />
                            <div class="leyenda-obligatorio">
                                <strong>*</strong> {{ __('common.required_field') }}
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        {{-- ═════════════════════════════════════════════════════════
             LA BARRA DE TOTALES DE ABAJO

             Aparece cuando la tarjeta de totales de la derecha no se ve
             en pantalla, y desaparece cuando vuelve a verse.

             ── POR QUÉ HAY DOS SITIOS CON LOS TOTALES ──

             Porque el panel pegajoso de la derecha depende de que ninguna
             regla del layout de AdminLTE rompa el `position: sticky`, y
             eso ya falló una vez. Esta barra usa `position: fixed`, que
             se calcula contra la ventana del navegador y no contra los
             contenedores de la página: es mucho más difícil de romper.

             Los botones van aquí también. Si estás abajo cargando la
             línea ocho, no deberías tener que subir ni para ver el total
             ni para guardar.

             x-cloak evita que la barra se vea un instante antes de que
             Alpine arranque.
        ═════════════════════════════════════════════════════════ --}}
        @if ($paso === 2)
        <div class="barra-totales"
             x-show="!totalesVisibles"
             x-transition.opacity
             x-cloak>

            <div class="bt-linea justify-content-between">

                <div class="bt-linea">

                    <div class="bt-dato bt-oculto-movil">
                        <span class="bt-etiqueta">{{ __('common.subtotal') }}</span>
                        <span class="bt-valor">
                            ${{ number_format($this->totales['subtotal'], 2) }}
                        </span>
                    </div>

                    {{--
                        La base gravable es el dato que más preguntas
                        evita: sobre qué monto se calcula el 7%, que casi
                        nunca es el total.
                    --}}
                    <div class="bt-dato bt-oculto-movil">
                        <span class="bt-etiqueta">{{ __('common.taxable_base') }}</span>
                        <span class="bt-valor">
                            ${{ number_format($this->totales['taxable_base'], 2) }}
                        </span>
                    </div>

                    <div class="bt-dato bt-oculto-movil">
                        <span class="bt-etiqueta">
                            {{ __('common.tax') }} {{ number_format($tax_exempt ? 0 : $tax_rate, 2) }}%
                        </span>
                        <span class="bt-valor">
                            ${{ number_format($this->totales['tax_amount'], 2) }}
                        </span>
                    </div>

                    @if ($this->totales['credit_card_fee'] > 0)
                        <div class="bt-dato bt-oculto-movil">
                            <span class="bt-etiqueta">{{ __('common.card_surcharge') }}</span>
                            <span class="bt-valor">
                                ${{ number_format($this->totales['credit_card_fee'], 2) }}
                            </span>
                        </div>
                    @endif

                    <div class="bt-dato bt-total">
                        <span class="bt-etiqueta">{{ __('common.total') }}</span>
                        <span class="bt-valor">
                            ${{ number_format($this->totales['total'], 2) }}
                        </span>
                    </div>

                </div>

            </div>
            @endif {{-- fin columna de totales --}}
        </div>

        @endif {{-- fin barra flotante de totales --}}

        {{--
            ═══════════════════════════════════════════════════════════════
            EL PIE DE NAVEGACIÓN
            ═══════════════════════════════════════════════════════════════

            Un solo sitio con los botones, y solo los que aplican al paso.

            Antes había dos juegos: uno en la columna derecha y otro en una
            barra abajo, los dos con "Guardar borrador" y "Procesar". Dos
            botones que hacen lo mismo en la misma pantalla obligan a
            pensar si de verdad hacen lo mismo.

            "Guardar borrador" está en los dos pasos a propósito: es la
            salida de emergencia de quien tiene que atender el teléfono a
            mitad de cotización.
        --}}
        <div class="ps-pie">

            <div>
                @if ($paso > 1)
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
                @elseif ($paso === 2)
                    {{ trans_choice('estimates.lines_count', count($lineas), ['count' => count($lineas)]) }}
                @else
                    {{ __('estimates.step_1_of', ['total' => \App\Livewire\Estimates\Form::PASOS + 1]) }}
                @endif

                <div wire:loading>
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    {{ __('common.saving') }}
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-save me-1"></i>{{ __('common.save_draft') }}
                </button>

                @if ($paso < 2)
                    <button type="button" class="btn btn-primary"
                            wire:click="siguientePaso">
                        {{ __('estimates.step_next') }}<i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @elseif ($this->puedeIrARevisar)
                    {{--
                        Ya procesado: el botón guarda los cambios y lleva
                        a la ficha. Se llama "Revisar y enviar" y no
                        "Procesar" porque es lo que hace, y porque el
                        usuario ya sabe que ese paso existe: acaba de
                        volver de él.
                    --}}
                    <button type="button" class="btn btn-success"
                            wire:click="guardar(false, true)">
                        {{ __('estimates.step_review') }}<i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @else
                    <button type="button" class="btn btn-success"
                            wire:click="guardar(false, true)">
                        <i class="bi bi-check2-circle me-1"></i>{{ __('estimates.process') }}
                    </button>
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
                </div>

                {{-- RESULTADOS --}}
                <div class="bu-lista">
                    @forelse ($this->resultadosContenedor as $unidad)
                        @php $usadaEnLinea = $this->contenedoresYaUsados[$unidad->id] ?? null; @endphp

                        <button type="button" class="bu-item"
                                wire:key="unidad-{{ $unidad->id }}"
                                @disabled($usadaEnLinea)
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
                    </span>
                    <button type="button" class="bu-btn-cerrar"
                            wire:click="cerrarBuscadorContenedor">
                        {{ __('common.close') }}
                    </button>
                </div>

            </div>
        </div>
    @endif

</div>
