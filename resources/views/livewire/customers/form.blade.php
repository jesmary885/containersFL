{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL CLIENTE — crear y editar
    ═══════════════════════════════════════════════════════════════════════

    Mismo diseño que el formulario de presupuesto: barra de pasos arriba,
    tira de contexto que recuerda lo ya decidido, y un solo pie con los
    botones que aplican al paso donde estás.

    ── POR QUÉ SE PARTIÓ EN TRES ──

    Era una pantalla sola con cuatro bloques apilados. Con dos
    direcciones y tres contactos se iba a metro y medio de scroll, y el
    botón de guardar quedaba al final, a ciegas.

    El corte no es arbitrario: cada paso responde una pregunta.

      1 · QUIÉN ES         quién llamó
      2 · DÓNDE Y QUIÉN    a dónde se le lleva y con quién se habla
      3 · CONDICIONES      cómo se le cobra y qué papeles tiene

    El paso 1 es lo único obligatorio de verdad. Los otros dos se pueden
    dejar vacíos y completar otro día, que es exactamente el caso real:
    llamó pidiendo precio y todavía no dio dirección.
--}}
<div>

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $customerId ? 'Editar cliente' : 'Nuevo cliente' }}
                @if ($numero)
                    <span class="text-secondary fw-normal font-monospace fs-6">{{ $numero }}</span>
                @endif
            </h4>
            <small class="text-secondary">
                @if ($customerId)
                    Los cambios se aplican a los documentos nuevos. Los ya emitidos conservan su copia.
                @else
                    El número se asigna solo al guardar.
                @endif
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">

            <span class="leyenda-obligatorio">
                <strong>*</strong> Campo obligatorio
            </span>

            <a href="{{ $customerId
                        ? route('comercial.clientes.show', $customerId)
                        : route('comercial.clientes.index') }}"
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

    @if (session('aviso'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('aviso') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

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

    {{-- ─────────────────────────────────────────────────────────────
         LA BARRA DE PASOS
    ───────────────────────────────────────────────────────────── --}}
    <div class="ps-barra">

        <button type="button"
                class="ps-paso {{ $paso === 1 ? 'ps-activo' : 'ps-hecho' }}"
                wire:click="irAlPaso(1)">
            <span class="ps-bolita">{{ $paso > 1 ? '✓' : '1' }}</span>
            <span class="ps-texto">Quién es</span>
        </button>

        <span class="ps-sep"></span>

        <button type="button"
                class="ps-paso {{ $paso === 2 ? 'ps-activo' : ($paso > 2 ? 'ps-hecho' : '') }}"
                wire:click="irAlPaso(2)">
            <span class="ps-bolita">{{ $paso > 2 ? '✓' : '2' }}</span>
            <span class="ps-texto">Dónde y con quién</span>
        </button>

        <span class="ps-sep"></span>

        <button type="button"
                class="ps-paso {{ $paso === 3 ? 'ps-activo' : '' }}"
                wire:click="irAlPaso(3)">
            <span class="ps-bolita">3</span>
            <span class="ps-texto">Condiciones y documentos</span>
        </button>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         LA TIRA DE CONTEXTO

         Lo que hace que partir el formulario no moleste. Sin ella hay
         que volver al paso 1 cada vez que quieres comprobar a quién
         estás registrando.
    ───────────────────────────────────────────────────────────── --}}
    @if ($paso > 1)
        @php $ctx = $this->resumen; @endphp

        <div class="ps-tira">

            <div class="ps-tira-dato">
                <span class="ps-tira-k">Cliente</span>
                <span class="ps-tira-v {{ $ctx['nombre'] ? '' : 'ps-falta' }}">
                    {{ $ctx['nombre'] ?? 'Falta' }}
                </span>
            </div>

            <span class="ps-tira-sep"></span>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">Tipo</span>
                <span class="ps-tira-v">{{ $ctx['tipo'] ?? '—' }}</span>
            </div>

            <span class="ps-tira-sep"></span>

            <div class="ps-tira-dato">
                <span class="ps-tira-k">Contacto</span>
                <span class="ps-tira-v">{{ $ctx['contacto'] ?? '—' }}</span>
            </div>

            @if ($paso === 3)
                <span class="ps-tira-sep"></span>

                <div class="ps-tira-dato">
                    <span class="ps-tira-k">Dirección</span>
                    <span class="ps-tira-v">{{ $ctx['direccion'] ?? '—' }}</span>
                </div>

                <div class="ps-tira-dato">
                    <span class="ps-tira-k">Contactos</span>
                    <span class="ps-tira-v">{{ $ctx['contactos'] }}</span>
                </div>
            @endif

            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto"
                    wire:click="irAlPaso(1)">
                <i class="bi bi-pencil me-1"></i>Cambiar
            </button>

        </div>
    @endif

    <form wire:submit.prevent="guardar">

        {{-- ═════════════════════════════════════════════════════════
             PASO 1 · QUIÉN ES
        ═════════════════════════════════════════════════════════ --}}
        @if ($paso === 1)

            <div class="card mb-3 seccion seccion-cliente">

                <div class="card-header">
                    <h6 class="seccion-titulo">
                        <span class="paso-num">1</span>
                        <i class="bi bi-person-vcard"></i>
                        <span>Quién es</span>
                    </h6>
                </div>

                <div class="card-body">
                    <div class="row g-3">

                        {{--
                            El tipo va de primero y como botones, no como select.
                            Cambia qué campos se piden debajo, y un select obliga a
                            desplegar para ver que hay dos opciones.
                        --}}
                        <div class="col-12">
                            <label class="form-label">Tipo de cliente</label>
                            <div class="btn-group d-block" role="group">
                                @foreach ($tipos as $valor => $etiqueta)
                                    <input type="radio"
                                           class="btn-check"
                                           id="tipo{{ $valor }}"
                                           value="{{ $valor }}"
                                           wire:model.live="type">
                                    <label class="btn btn-outline-primary" for="tipo{{ $valor }}">
                                        <i class="bi bi-{{ $valor === 'business' ? 'building' : 'person' }} me-1"></i>
                                        {{ $etiqueta }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        @if ($this->esEmpresa())

                            <div class="col-12 col-md-8">
                                <label class="form-label">Razón social <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control @error('company_name') is-invalid @enderror"
                                       placeholder="Ej: Homestead Construction LLC"
                                       wire:model.blur="company_name">
                                @error('company_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                        @else

                            <div class="col-12 col-md-4">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text"
                                       class="form-control @error('first_name') is-invalid @enderror"
                                       wire:model.blur="first_name">
                                @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Apellido</label>
                                <input type="text"
                                       class="form-control @error('last_name') is-invalid @enderror"
                                       wire:model.blur="last_name">
                                @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                        @endif

                        <div class="col-12 col-md-4">
                            <label class="form-label">Nombre a mostrar</label>
                            <input type="text"
                                   class="form-control @error('display_name') is-invalid @enderror"
                                   placeholder="{{ $this->nombreCompuesto() ?: 'Se arma solo' }}"
                                   wire:model.blur="display_name">
                            @error('display_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Solo si se le conoce por un alias distinto. Si se deja vacío se arma solo.
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        {{--
                            EL TELÉFONO Y EL CORREO

                            Los dos son componentes propios. El teléfono
                            descarta las letras y se formatea solo; el
                            correo trae el desplegable con los dominios
                            de siempre.

                            Están explicados en
                            resources/views/components/ui/.
                        --}}
                        <div class="col-12 col-md-4">
                            <label class="form-label">Teléfono</label>

                            <x-ui.telefono model="primary_phone"
                                           :value="$primary_phone"
                                           :invalid="$errors->has('primary_phone')" />

                            @error('primary_phone')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <div class="form-text">Solo números. El formato se pone solo.</div>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Correo</label>

                            <x-ui.correo model="primary_email"
                                         :value="$primary_email"
                                         :invalid="$errors->has('primary_email')" />

                            @error('primary_email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label">Idioma</label>
                            <select class="form-select" wire:model="preferred_locale">
                                <option value="en">English</option>
                                <option value="es">Español</option>
                            </select>
                            <div class="form-text">En el que se le manda el documento.</div>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label">¿Cómo llegó?</label>
                            <select class="form-select" wire:model="source">
                                <option value="">—</option>
                                @foreach ($origenes as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- ───── SUNBIZ, SOLO EMPRESAS ───── --}}
                        @if ($this->esEmpresa())

                            <div class="col-12"><hr class="my-1"></div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="sunbiz"
                                           wire:model.live="sunbiz_verified">
                                    <label class="form-check-label" for="sunbiz">
                                        Verificada en Sunbiz
                                    </label>
                                </div>
                                <div class="form-text">
                                    El registro de empresas de Florida. Se comprueba antes de darle crédito
                                    o de procesarle una tarjeta.
                                </div>
                            </div>

                            @if ($sunbiz_verified)
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Número de documento</label>
                                    <input type="text"
                                           class="form-control @error('sunbiz_document_number') is-invalid @enderror"
                                           placeholder="Ej: L21000123456"
                                           wire:model.blur="sunbiz_document_number">
                                    @error('sunbiz_document_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text">Queda registrado quién verificó y cuándo.</div>
                                </div>
                            @endif

                        @endif

                    </div>
                </div>

            </div>

        @endif

        {{-- ═════════════════════════════════════════════════════════
             PASO 2 · DÓNDE Y CON QUIÉN
        ═════════════════════════════════════════════════════════ --}}
        @if ($paso === 2)

            {{-- ───── DIRECCIONES ───── --}}
            <div class="card mb-3 seccion seccion-direccion">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="seccion-titulo mb-0">
                        <span class="paso-num">2</span>
                        <i class="bi bi-geo-alt"></i>
                        <span>Direcciones</span>
                    </h6>

                    <button type="button" class="btn btn-sm btn-outline-primary"
                            wire:click="agregarDireccion">
                        <i class="bi bi-plus-lg me-1"></i> Agregar dirección
                    </button>
                </div>

                <div class="card-body">

                    <div class="alert alert-light border py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Una dirección guardada aquí se copia sola al presupuesto y a la factura.
                        Sin ella hay que teclearla entera cada vez.
                    </div>

                    @foreach ($direcciones as $i => $direccion)

                        {{--
                            wire:key por uid y no por número de fila.

                            Con el número, borrar la dirección del medio hace
                            que la de abajo herede su sitio, y los campos que
                            llevan estado propio en el navegador —teléfono,
                            correo— se quedan con los datos de la borrada.

                            Está explicado largo en Customers\Form.php, donde
                            se crea el uid.
                        --}}
                        <div class="border rounded p-3 mb-3 {{ $direccion['is_default_billing'] ? 'border-primary' : '' }}"
                             wire:key="dir-{{ $direccion['uid'] ?? $i }}">

                            <div class="row g-2">

                                <div class="col-12 col-md-3">
                                    <label class="form-label small">Etiqueta</label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           placeholder="Oficina, Obra, Yarda..."
                                           wire:model.blur="direcciones.{{ $i }}.label">
                                </div>

                                <div class="col-12 col-md-9">
                                    <label class="form-label small">
                                        Calle y número <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                           class="form-control form-control-sm @error('direcciones.'.$i.'.line1') is-invalid @enderror"
                                           wire:model.blur="direcciones.{{ $i }}.line1">
                                    @error('direcciones.'.$i.'.line1')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label small">Referencia, suite o unidad</label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           wire:model.blur="direcciones.{{ $i }}.line2">
                                </div>

                                {{--
                                    EL ESTADO VA PRIMERO Y LA CIUDAD DESPUÉS.

                                    Al revés de como estaba, y a propósito:
                                    elegir el estado deja la lista de ciudades
                                    con las de ese estado y nada más. Florida
                                    tiene doscientas y pico; el país entero
                                    tiene miles.

                                    Por eso el select del estado lleva .live y
                                    no .blur: sin el .live, la lista de ciudades
                                    no se entera de que cambiaste de estado
                                    hasta que sales del campo.
                                --}}
                                <div class="col-12 col-md-4">
                                    <label class="form-label small">Estado</label>
                                    <select class="form-select form-select-sm @error('direcciones.'.$i.'.state') is-invalid @enderror"
                                            wire:model.live="direcciones.{{ $i }}.state">
                                        <option value="">— Elegir —</option>
                                        @foreach (\App\Support\UsPlaces::estadosParaSelect() as $cod => $nombreEstado)
                                            <option value="{{ $cod }}">{{ $nombreEstado }}</option>
                                        @endforeach
                                    </select>
                                    @error('direcciones.'.$i.'.state')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{--
                                    LA CIUDAD: ESCRIBE Y FILTRA

                                    Es un campo de texto normal con una lista
                                    pegada. Se escriben dos letras y se ve lo que
                                    coincide; se elige con el mouse o con las
                                    flechas.

                                    ── POR QUÉ NO ES UN SELECT ──

                                    Porque un select obliga: si el cliente vive
                                    en un pueblo que no está en la lista, no hay
                                    forma de guardarlo. Así se sugiere sin
                                    obligar, que es lo que hace falta.

                                    Sin estado elegido sugiere las de todo el
                                    país, para que escribir "Homestead" funcione
                                    aunque nadie haya tocado el estado todavía.
                                --}}
                                @php
                                    $ciudadesSugeridas = ($direccion['state'] ?? '')
                                        ? \App\Support\UsPlaces::ciudadesDe($direccion['state'])
                                        : \App\Support\UsPlaces::todasLasCiudades();
                                @endphp

                                <div class="col-12 col-md-5">
                                    <label class="form-label small">Ciudad</label>
                                    <input type="text"
                                           list="ciudades-{{ $i }}"
                                           autocomplete="off"
                                           class="form-control form-control-sm"
                                           placeholder="Escriba dos letras y elija"
                                           wire:model.blur="direcciones.{{ $i }}.city">

                                    <datalist id="ciudades-{{ $i }}">
                                        @foreach ($ciudadesSugeridas as $ciudadSugerida)
                                            <option value="{{ $ciudadSugerida }}"></option>
                                        @endforeach
                                    </datalist>

                                    @if (($direccion['state'] ?? '') && count($ciudadesSugeridas))
                                        <div class="form-text">
                                            {{ count($ciudadesSugeridas) }} ciudades de
                                            {{ \App\Support\UsPlaces::ESTADOS[$direccion['state']] ?? $direccion['state'] }}.
                                            Si la suya no está, escríbala igual.
                                        </div>
                                    @endif
                                </div>

                                <div class="col-12 col-md-3">
                                    <label class="form-label small">ZIP</label>
                                    <input type="text"
                                           inputmode="numeric"
                                           maxlength="10"
                                           class="form-control form-control-sm"
                                           placeholder="33030"
                                           wire:model.blur="direcciones.{{ $i }}.zip">
                                </div>

                            </div>

                            {{--
                                LAS DOS MARCAS

                                Son botones y no casillas porque son excluyentes:
                                marcar una apaga la de las demás. Dos direcciones
                                de facturación no significan nada.

                                ── EL ARREGLO ──

                                Antes se encendían y no se podían apagar. Ahora
                                pulsar la que está encendida la apaga. El texto de
                                ayuda de abajo lo dice, porque un botón que hace
                                dos cosas distintas según cómo esté merece
                                explicarse.
                            --}}
                            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">

                                <div class="d-flex flex-wrap gap-2">

                                    <button type="button"
                                            class="btn btn-sm btn-{{ $direccion['is_default_billing'] ? 'primary' : 'outline-secondary' }}"
                                            wire:click="marcarFacturacion({{ $i }})"
                                            title="{{ $direccion['is_default_billing']
                                                        ? 'Pulse otra vez para quitarle la marca'
                                                        : 'Marcar como dirección de facturación' }}">
                                        <i class="bi bi-receipt me-1"></i> Facturación
                                        @if ($direccion['is_default_billing'])
                                            <i class="bi bi-x-circle ms-1 opacity-75"></i>
                                        @endif
                                    </button>

                                    <button type="button"
                                            class="btn btn-sm btn-{{ $direccion['is_default_shipping'] ? 'primary' : 'outline-secondary' }}"
                                            wire:click="marcarEnvio({{ $i }})"
                                            title="{{ $direccion['is_default_shipping']
                                                        ? 'Pulse otra vez para quitarle la marca'
                                                        : 'Marcar como dirección de entrega' }}">
                                        <i class="bi bi-truck me-1"></i> Entrega
                                        @if ($direccion['is_default_shipping'])
                                            <i class="bi bi-x-circle ms-1 opacity-75"></i>
                                        @endif
                                    </button>

                                </div>

                                @if (count($direcciones) > 1)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            wire:click="quitarDireccion({{ $i }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif

                            </div>

                        </div>

                    @endforeach

                    <div class="form-text">
                        <i class="bi bi-lightbulb me-1"></i>
                        Un botón azul se puede volver a pulsar para quitarle la marca.
                        Si no marca ninguna, el sistema usa la primera dirección para facturar.
                    </div>

                </div>

            </div>

            {{-- ───── CONTACTOS ───── --}}
            <div class="card mb-3 seccion seccion-notas">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="seccion-titulo mb-0">
                        <span class="paso-num">3</span>
                        <i class="bi bi-people"></i>
                        <span>Contactos</span>
                    </h6>

                    <button type="button" class="btn btn-sm btn-outline-primary"
                            wire:click="agregarContacto">
                        <i class="bi bi-plus-lg me-1"></i> Agregar contacto
                    </button>
                </div>

                <div class="card-body">

                    @if (empty($contactos))

                        <div class="text-center py-4 text-secondary">
                            <i class="bi bi-person-lines-fill fs-3 d-block mb-2 opacity-50"></i>
                            <div class="small mb-3">
                                Sin contactos. Hacen falta cuando en la empresa hay una persona para
                                operaciones y otra para pagos.
                            </div>
                        </div>

                        {{--
                            SIN CONTACTOS, ¿A QUIÉN SE LE AVISA?

                            Es la primera pregunta de cualquiera que llega a
                            esta tarjeta vacía, y merece contestarse aquí en
                            vez de tener que preguntarla.

                            Al teléfono y al correo del paso 1. Es lo que pidió
                            Denisse el 14 de agosto: el sistema usa los modos de
                            comunicación que estén archivados para ese cliente.

                            Los contactos son para el otro caso: la empresa
                            donde el de la obra no es el que paga.
                        --}}
                        <div class="alert alert-light border py-2 small mb-0">
                            <i class="bi bi-megaphone me-1"></i>
                            <strong>Sin contactos, los avisos van al paso 1.</strong>
                            Al teléfono y al correo que escribió ahí, que es lo normal para una
                            persona natural.

                            @if (! $primary_phone && ! $primary_email)
                                <div class="text-danger mt-1">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    Pero no escribió ninguno de los dos. Así no hay por dónde
                                    avisarle a este cliente cuando deba.
                                </div>
                            @endif
                        </div>

                    @else

                        <div class="alert alert-light border py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            El aviso de cobranza va a <strong>todos</strong> los contactos que lo tengan
                            encendido, no solo al principal.
                        </div>

                        @foreach ($contactos as $i => $contacto)

                            <div class="border rounded p-3 mb-3 {{ $contacto['is_primary'] ? 'border-primary' : '' }}"
                                 wire:key="cont-{{ $contacto['uid'] ?? $i }}">

                                <div class="row g-2">

                                    <div class="col-12 col-md-4">
                                        <label class="form-label small">
                                            Nombre <span class="text-danger">*</span>
                                        </label>
                                        <input type="text"
                                               class="form-control form-control-sm @error('contactos.'.$i.'.name') is-invalid @enderror"
                                               wire:model.blur="contactos.{{ $i }}.name">
                                        @error('contactos.'.$i.'.name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 col-md-2">
                                        <label class="form-label small">Cargo</label>
                                        <input type="text"
                                               class="form-control form-control-sm"
                                               placeholder="Pagos, Obra..."
                                               wire:model.blur="contactos.{{ $i }}.role">
                                    </div>

                                    <div class="col-12 col-md-3">
                                        <label class="form-label small">Correo</label>

                                        <x-ui.correo :model="'contactos.'.$i.'.email'"
                                                     :value="$contacto['email']"
                                                     :campo-key="$contacto['uid'] ?? $i"
                                                     size="sm"
                                                     :invalid="$errors->has('contactos.'.$i.'.email')" />

                                        @error('contactos.'.$i.'.email')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 col-md-3">
                                        <label class="form-label small">Teléfono</label>

                                        <x-ui.telefono :model="'contactos.'.$i.'.phone'"
                                                       :value="$contacto['phone']"
                                                       :campo-key="$contacto['uid'] ?? $i"
                                                       size="sm"
                                                       :invalid="$errors->has('contactos.'.$i.'.phone')" />

                                        @error('contactos.'.$i.'.phone')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                </div>

                                <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-3">

                                    <div class="d-flex flex-wrap align-items-center gap-3">

                                        <button type="button"
                                                class="btn btn-sm btn-{{ $contacto['is_primary'] ? 'primary' : 'outline-secondary' }}"
                                                wire:click="marcarContactoPrincipal({{ $i }})"
                                                title="{{ $contacto['is_primary']
                                                            ? 'Pulse otra vez para quitarle la marca'
                                                            : 'Marcar como contacto principal' }}">
                                            <i class="bi bi-star{{ $contacto['is_primary'] ? '-fill' : '' }} me-1"></i>
                                            Principal
                                            @if ($contacto['is_primary'])
                                                <i class="bi bi-x-circle ms-1 opacity-75"></i>
                                            @endif
                                        </button>

                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                   id="fact{{ $i }}"
                                                   wire:model="contactos.{{ $i }}.notify_invoices">
                                            <label class="form-check-label small" for="fact{{ $i }}">
                                                Recibe facturas
                                            </label>
                                        </div>

                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                   id="cobr{{ $i }}"
                                                   wire:model="contactos.{{ $i }}.notify_reminders">
                                            <label class="form-check-label small" for="cobr{{ $i }}">
                                                Recibe cobranza
                                            </label>
                                        </div>

                                    </div>

                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            wire:click="quitarContacto({{ $i }})">
                                        <i class="bi bi-trash"></i>
                                    </button>

                                </div>

                            </div>

                        @endforeach

                        <div class="form-text">
                            <i class="bi bi-lightbulb me-1"></i>
                            "Principal" también se puede quitar pulsándolo otra vez.
                        </div>

                    @endif

                </div>

            </div>

        @endif

        {{-- ═════════════════════════════════════════════════════════
             PASO 3 · CONDICIONES Y DOCUMENTOS
        ═════════════════════════════════════════════════════════ --}}
        @if ($paso === 3)

            {{-- ───── CONDICIONES COMERCIALES ───── --}}
            <div class="card mb-3 seccion seccion-datos">

                <div class="card-header">
                    <h6 class="seccion-titulo mb-0">
                        <span class="paso-num">4</span>
                        <i class="bi bi-sliders"></i>
                        <span>Condiciones comerciales</span>
                    </h6>
                </div>

                <div class="card-body">

                    <div class="alert alert-light border py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Son tres permisos, no tres etiquetas: definen qué deja hacer el sistema
                        con este cliente.
                    </div>

                    <div class="row g-3">

                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       id="tarjeta" wire:model="allow_credit_card">
                                <label class="form-check-label" for="tarjeta">Puede pagar con tarjeta</label>
                            </div>
                            <div class="form-text">
                                Apagado, la pantalla de pagos <strong>no deja</strong> cobrarle con
                                tarjeta. Encendido, además hace falta el formulario de autorización
                                firmado. El recargo es del 3,5%.
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       id="retencion" wire:model="credit_hold">
                                <label class="form-check-label" for="retencion">En retención de crédito</label>
                            </div>
                            <div class="form-text">
                                Marca al cliente al que no se le vende a crédito.
                                <strong>Hoy solo avisa</strong>: sale en rojo en el listado, pero el
                                sistema todavía no impide facturarle.
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       id="activo" wire:model="is_active">
                                <label class="form-check-label" for="activo">Cliente activo</label>
                            </div>
                            <div class="form-text">
                                Al apagarlo deja de aparecer al crear documentos nuevos.
                                Los viejos no se tocan.
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notas internas</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      rows="3"
                                      placeholder="Lo que hay que saber antes de atenderlo. No se imprime en ningún documento."
                                      wire:model.blur="notes"></textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        {{--
                            LA EXENCIÓN DE IMPUESTO NO SE MARCA AQUÍ

                            Y conviene decirlo en pantalla, porque es la primera
                            pregunta de quien busca la casilla y no la encuentra.
                        --}}
                        <div class="col-12">
                            <div class="alert alert-warning py-2 small mb-0">
                                <i class="bi bi-patch-check me-1"></i>
                                <strong>¿Y la exención de impuesto?</strong>
                                No se marca a mano. El cliente queda exento solo cuando se le registra
                                un certificado vigente, y eso se hace desde su ficha, después de
                                guardar. Sin el papel, si el estado audita, el 7% lo termina pagando
                                la empresa.
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            {{-- ───── DOCUMENTOS ───── --}}
            <div class="card mb-3 seccion seccion-entrega">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="seccion-titulo mb-0">
                        <span class="paso-num">5</span>
                        <i class="bi bi-paperclip"></i>
                        <span>Documentos del cliente</span>
                    </h6>

                    <button type="button" class="btn btn-sm btn-outline-primary"
                            wire:click="agregarAdjunto">
                        <i class="bi bi-plus-lg me-1"></i> Adjuntar documento
                    </button>
                </div>

                <div class="card-body">

                    <div class="alert alert-light border py-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Los papeles que hay que tener del cliente y mantener al día: contrato,
                        certificado de exención, autorización de tarjeta firmada.
                        <strong>La fecha de vencimiento es lo importante</strong>: es la que
                        permite avisar antes de que se venza, en vez de enterarse el día que
                        hace falta.
                    </div>

                    {{-- ── LOS QUE YA ESTÁN GUARDADOS ── --}}
                    @if ($documentos->isNotEmpty())

                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0">

                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Tipo</th>
                                        <th>Vence</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>

                                <tbody>
                                @foreach ($documentos as $doc)

                                    @php
                                        $vencido  = $doc->expires_at && $doc->expires_at->isPast();
                                        $porVencer = $doc->expires_at
                                                     && ! $vencido
                                                     && $doc->expires_at->lte(now()->addDays(30));
                                    @endphp

                                    <tr wire:key="doc-{{ $doc->id }}"
                                        class="fila-estado {{ $vencido ? 'fila-bad' : ($porVencer ? 'fila-warn' : 'fila-mute') }}">

                                        <td>
                                            <div class="fw-medium">{{ $doc->name }}</div>
                                            <div class="small text-secondary">
                                                {{ $doc->readable_size }}
                                                @if ($doc->notes) · {{ $doc->notes }} @endif
                                            </div>
                                        </td>

                                        <td class="small">{{ $doc->category?->label() }}</td>

                                        <td class="small">
                                            @if ($doc->expires_at)
                                                {{ $doc->expires_at->format('d/m/Y') }}
                                                @if ($vencido)
                                                    <div class="text-danger fw-medium">
                                                        <i class="bi bi-exclamation-triangle"></i> Vencido
                                                    </div>
                                                @elseif ($porVencer)
                                                    <div class="text-warning fw-medium">Por vencer</div>
                                                @endif
                                            @else
                                                <span class="text-secondary">No vence</span>
                                            @endif
                                        </td>

                                        <td class="text-end">

                                            @if ($documentoPorBorrar === $doc->id)

                                                <div class="d-inline-flex align-items-center gap-2">
                                                    <small class="text-secondary">¿Eliminar?</small>
                                                    <button type="button" class="btn btn-sm btn-danger"
                                                            wire:click="borrarDocumento">Sí</button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                            wire:click="cancelarBorrarDocumento">No</button>
                                                </div>

                                            @else

                                                <div class="acciones">
                                                    <a href="{{ route('documentos.descargar', $doc) }}"
                                                       class="acc acc-ver" title="Descargar">
                                                        <i class="bi bi-download"></i>
                                                    </a>

                                                    <button type="button"
                                                            class="acc acc-borrar acc-separado"
                                                            wire:click="pedirBorrarDocumento({{ $doc->id }})"
                                                            title="Eliminar">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>

                                            @endif

                                        </td>

                                    </tr>

                                @endforeach
                                </tbody>

                            </table>
                        </div>

                    @endif

                    {{-- ── LOS QUE SE ESTÁN SUBIENDO ── --}}
                    @forelse ($adjuntos as $i => $adjunto)

                        <div class="border rounded p-3 mb-3" wire:key="adj-{{ $i }}">

                            <div class="row g-2 align-items-end">

                                <div class="col-12 col-md-5">
                                    <label class="form-label small">Archivo</label>
                                    <input type="file"
                                           class="form-control form-control-sm @error('adjuntos.'.$i.'.archivo') is-invalid @enderror"
                                           wire:model="adjuntos.{{ $i }}.archivo">
                                    @error('adjuntos.'.$i.'.archivo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror

                                    <div wire:loading wire:target="adjuntos.{{ $i }}.archivo"
                                         class="form-text text-primary">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Subiendo...
                                    </div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <label class="form-label small">Qué es</label>
                                    <select class="form-select form-select-sm"
                                            wire:model.live="adjuntos.{{ $i }}.category">
                                        @foreach ($categorias as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-8 col-md-3">
                                    <label class="form-label small">Vence</label>
                                    <input type="date"
                                           class="form-control form-control-sm @error('adjuntos.'.$i.'.expires_at') is-invalid @enderror"
                                           wire:model="adjuntos.{{ $i }}.expires_at">
                                    @error('adjuntos.'.$i.'.expires_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-4 col-md-1 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            wire:click="quitarAdjunto({{ $i }})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small">Nota</label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           placeholder="Opcional. Ej: firmado por Carlos, falta el sello"
                                           wire:model.blur="adjuntos.{{ $i }}.notes">
                                </div>

                                {{--
                                    ADJUNTAR EL PDF NO EXIME DE IMPUESTO

                                    Esta es la confusión que ya pasó una vez, y
                                    es tan razonable que va a volver a pasar:
                                    subes el certificado, ves la palabra
                                    "Certificado de exención" y das por hecho
                                    que el cliente quedó exento.

                                    No queda. El archivo es la foto del papel; lo
                                    que apaga el 7% es el registro del
                                    certificado, con su número y sus fechas, que
                                    se hace desde la ficha.

                                    El aviso sale solo cuando se elige esa
                                    categoría, que es justo el momento en que se
                                    está cometiendo el error.
                                --}}
                                @if (($adjunto['category'] ?? '') === \App\Enums\DocumentCategory::TaxExemption->value)
                                    <div class="col-12">
                                        <div class="alert alert-warning py-2 small mb-0">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                            <strong>Ojo: subir este PDF no lo deja exento de impuesto.</strong>
                                            Guarda la copia del papel y nada más. Para que el sistema
                                            deje de cobrarle el 7% hay que <strong>registrar el
                                            certificado</strong> —con su número, su año y sus fechas—
                                            desde la ficha del cliente, después de guardar.
                                        </div>
                                    </div>
                                @endif

                            </div>

                        </div>

                    @empty

                        @if ($documentos->isEmpty())
                            <div class="text-center py-4 text-secondary">
                                <i class="bi bi-folder2-open fs-3 d-block mb-2 opacity-50"></i>
                                <div class="small">
                                    Sin documentos. Se suben al guardar la ficha.
                                </div>
                            </div>
                        @endif

                    @endforelse

                    <div class="form-text">
                        Se aceptan PDF, imágenes, Word y Excel, hasta 10 MB cada uno.
                    </div>

                </div>

            </div>

        @endif

        {{-- ─────────────────────────────────────────────────────────
             EL PIE DE NAVEGACIÓN

             Un solo sitio con los botones, y solo los que aplican al
             paso. "Guardar" está en los tres a propósito: es la salida
             de emergencia de quien tiene que atender el teléfono a mitad
             de carga.
        ───────────────────────────────────────────────────────────── --}}
        <div class="ps-pie">

            <div>
                @if ($paso > 1)
                    <button type="button" class="btn btn-outline-secondary"
                            wire:click="pasoAnterior">
                        <i class="bi bi-arrow-left me-1"></i> Atrás
                    </button>
                @else
                    <a href="{{ $customerId
                                ? route('comercial.clientes.show', $customerId)
                                : route('comercial.clientes.index') }}"
                       class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                @endif
            </div>

            <div class="ps-pie-medio">
                @if ($errors->any())
                    <span class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        {{ $errors->count() === 1
                            ? 'Falta 1 dato'
                            : 'Faltan '.$errors->count().' datos' }}
                    </span>
                @else
                    Paso {{ $paso }} de {{ \App\Livewire\Customers\Form::PASOS }}
                @endif

                <div wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1"></span> Guardando...
                </div>
            </div>

            <div class="d-flex gap-2">

                @if ($paso < \App\Livewire\Customers\Form::PASOS)
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-save me-1"></i> Guardar
                    </button>

                    <button type="button" class="btn btn-primary"
                            wire:click="siguientePaso">
                        Siguiente <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @else
                    <button type="submit" class="btn btn-success" wire:loading.attr="disabled">
                        <i class="bi bi-check-lg me-1"></i>
                        {{ $customerId ? 'Guardar cambios' : 'Registrar cliente' }}
                    </button>
                @endif

            </div>

        </div>

    </form>

</div>
