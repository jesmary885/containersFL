{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL CLIENTE
    ═══════════════════════════════════════════════════════════════════════

    ── EL AVISO DE LA EMPRESA ACTIVA ──

    El cliente es compartido entre las dos compañías, pero sus documentos
    no: presupuestos, facturas y rentas llevan el filtro de la empresa
    activa. Así que el historial de abajo es "lo que este cliente tiene
    CON esta empresa".

    Eso está dicho en pantalla a propósito. Sin decirlo, alguien en RST
    abre la ficha, ve tres facturas y concluye que el sistema perdió las
    de FLCHR.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3 gap-2">

        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="mb-0 fw-semibold">{{ $customer->name }}</h4>

                <span class="text-secondary font-monospace small">{{ $customer->customer_number }}</span>

                @unless ($customer->is_active)
                    <span class="badge bg-secondary-subtle text-secondary">Desactivado</span>
                @endunless

                @if ($customer->credit_hold)
                    <span class="badge bg-danger-subtle text-danger">En retención de crédito</span>
                @endif
            </div>

            <small class="text-secondary">
                {{ $customer->type?->label() }}

                @if ($customer->source)
                    · llegó por {{ $customer->source }}
                @endif

                · registrado el {{ $customer->created_at?->format('d/m/Y') }}
            </small>
        </div>

        {{--
            ── POR QUÉ HAY DOS BOTONES Y NO UNO ──

            Había uno solo que decía "Volver" y llevaba al listado. El
            problema es que "volver" significa otra cosa: significa la
            pantalla de la que vengo.

            Y de aquí se llega por dos caminos distintos:

              · desde el listado, pulsando un cliente
              · desde el formulario, después de guardar

            Al que acaba de guardar, "Volver" le suena a deshacer o a
            regresar al formulario, y termina en una lista que no pidió.

            Ahora cada cosa dice lo que hace. "Atrás" es el navegador de
            verdad —la pantalla anterior, sea cual sea— y "Todos los
            clientes" es el listado, dicho con todas sus letras.
        --}}
        <div class="d-flex flex-wrap gap-2">

            <button type="button" class="btn btn-outline-secondary"
                    onclick="history.back()"
                    title="La pantalla de la que viene">
                <i class="bi bi-arrow-left me-1"></i> Atrás
            </button>

            <a href="{{ route('comercial.clientes.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-list-ul me-1"></i> Todos los clientes
            </a>

            @can('customers.create')
                <a href="{{ route('comercial.clientes.create') }}" class="btn btn-outline-success">
                    <i class="bi bi-person-plus me-1"></i> Otro cliente
                </a>
            @endcan

            @can('customers.update')
                <a href="{{ route('comercial.clientes.edit', $customer) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Editar
                </a>
            @endcan
        </div>

    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ───── AVISOS QUE BLOQUEAN TRABAJO ───── --}}
    @if ($customer->addresses->isEmpty())
        <div class="alert alert-warning">
            <i class="bi bi-geo-alt-fill me-1"></i>
            <strong>Este cliente no tiene ninguna dirección guardada.</strong>
            Hay que teclearla entera en cada presupuesto y en cada factura que se le haga.
            @can('customers.update')
                <a href="{{ route('comercial.clientes.edit', $customer) }}" class="alert-link">
                    Agregar una ahora
                </a>.
            @endcan
        </div>
    @endif

    {{-- ───── NÚMEROS ───── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3">
            <div class="kpi {{ $totales['debe'] > 0 ? 'kpi-warn' : 'kpi-ok' }}">
                <div class="kpi-label"><i class="bi bi-cash-stack"></i> Debe hoy</div>
                <div class="kpi-valor monto">${{ number_format($totales['debe'], 2) }}</div>
                <div class="kpi-pie">Saldo de facturas abiertas</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-apagado">
                <div class="kpi-label"><i class="bi bi-receipt"></i> Facturado</div>
                <div class="kpi-valor monto">${{ number_format($totales['facturado'], 2) }}</div>
                <div class="kpi-pie">{{ $totales['facturas'] }} facturas</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi kpi-apagado">
                <div class="kpi-label"><i class="bi bi-file-earmark-text"></i> Presupuestos</div>
                <div class="kpi-valor">{{ $totales['presupuestos'] }}</div>
                <div class="kpi-pie">Cotizaciones hechas</div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="kpi {{ $customer->tax_exempt ? 'kpi-ok' : 'kpi-apagado' }}">
                <div class="kpi-label"><i class="bi bi-percent"></i> Impuesto</div>
                <div class="kpi-valor fs-5">
                    {{ $customer->tax_exempt ? 'Exento' : 'Paga 7%' }}
                </div>
                {{--
                    El pie del contador tiene que decir QUE HACER, no solo
                    constatar. "Sin certificado vigente" es un diagnostico;
                    "hay que registrarlo" es una instruccion, y es lo que la
                    persona necesita leer.
                --}}
                <div class="kpi-pie">
                    @if ($certificadoVigente)
                        Exento hasta el {{ $certificadoVigente->valid_until?->format('d/m/Y') }}
                    @else
                        Falta registrar su certificado
                    @endif
                </div>
            </div>
        </div>

    </div>

    <div class="row g-3">

        {{-- ═════════════════════════════════════════════════════════
             COLUMNA IZQUIERDA · DATOS
        ═════════════════════════════════════════════════════════ --}}
        <div class="col-12 col-lg-5">

            {{-- ───── CONTACTO PRINCIPAL ───── --}}
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-telephone me-1"></i> Cómo contactarlo</span>
                </div>
                <div class="card-body">

                    {{--
                        ── EL TELÉFONO Y EL CORREO DE LA FICHA SON UN
                           CONTACTO, NO UN DATO SUELTO ──

                        Antes salían arriba, en gris, sin nombre y sin
                        explicación, como si fueran un encabezado. Y los
                        contactos agregados salían debajo en cuadros con
                        etiquetas de "Facturas" y "Cobranza".

                        Eso hacía pensar que solo los de abajo recibían los
                        avisos, cuando es al revés: si no hay ninguno, el
                        aviso va justo a estos dos.

                        Ahora es un cuadro más, el primero, marcado como
                        principal. Se lee como lo que es: la forma normal de
                        localizar a este cliente.
                    --}}
                    @if ($customer->primary_phone || $customer->primary_email)

                        <div class="border border-primary rounded p-2 mb-2">

                            <div class="fw-semibold">
                                {{ $customer->name }}
                                <span class="badge bg-primary-subtle text-primary ms-1">Principal</span>
                            </div>

                            <small class="text-secondary d-block">
                                {{ $customer->type?->label() }} · de la ficha
                            </small>

                            @if ($customer->primary_phone)
                                <div class="small mt-1">
                                    <i class="bi bi-telephone text-secondary me-2"></i>{{ $customer->primary_phone }}
                                </div>
                            @endif

                            @if ($customer->primary_email)
                                <div class="small">
                                    <i class="bi bi-envelope text-secondary me-2"></i>{{ $customer->primary_email }}
                                </div>
                            @endif

                            <div class="mt-1">
                                <span class="badge bg-light text-dark border">Facturas</span>
                                <span class="badge bg-light text-dark border">Cobranza</span>
                            </div>
                        </div>

                    @endif

                    @foreach ($customer->contacts as $contacto)

                        <div class="border rounded p-2 mb-2">
                            <div class="fw-semibold">
                                {{ $contacto->name }}
                                @if ($contacto->is_primary)
                                    <i class="bi bi-star-fill text-warning small" title="Principal entre los contactos"></i>
                                @endif
                            </div>

                            @if ($contacto->role)
                                <small class="text-secondary d-block">{{ $contacto->role }}</small>
                            @else
                                <small class="text-secondary d-block">Contacto</small>
                            @endif

                            <div class="small mt-1">
                                @if ($contacto->phone)
                                    <div><i class="bi bi-telephone text-secondary me-2"></i>{{ $contacto->phone }}</div>
                                @endif
                                @if ($contacto->email)
                                    <div><i class="bi bi-envelope text-secondary me-2"></i>{{ $contacto->email }}</div>
                                @endif
                            </div>

                            <div class="mt-1">
                                @if ($contacto->notify_invoices)
                                    <span class="badge bg-light text-dark border">Facturas</span>
                                @endif
                                @if ($contacto->notify_reminders)
                                    <span class="badge bg-light text-dark border">Cobranza</span>
                                @endif
                            </div>
                        </div>

                    @endforeach

                    {{--
                        El aviso de "sin forma de contacto" solo sale cuando
                        de verdad NO hay ninguna: ni en la ficha ni en los
                        contactos. Un cliente con su teléfono cargado nunca
                        tiene que ver este cartel.
                    --}}
                    @if (! $customer->primary_phone
                         && ! $customer->primary_email
                         && $customer->contacts->isEmpty())

                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>No hay por dónde contactar a este cliente.</strong>
                            Sin teléfono ni correo, el aviso de cobranza no se le puede mandar.
                        </div>

                    @else

                        <div class="form-text mt-2">
                            <i class="bi bi-megaphone me-1"></i>
                            Los avisos van a <strong>todos</strong> los de esta lista que tengan la
                            etiqueta puesta, empezando por el principal.
                        </div>

                    @endif

                </div>
            </div>

            {{-- ───── DIRECCIONES ───── --}}
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <span class="fw-semibold"><i class="bi bi-geo-alt me-1"></i> Direcciones</span>
                </div>
                <div class="card-body">

                    @forelse ($customer->addresses as $direccion)

                        <div class="border rounded p-2 mb-2">

                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    @if ($direccion->label)
                                        <div class="fw-semibold small">{{ $direccion->label }}</div>
                                    @endif

                                    <div class="small">{{ $direccion->line1 }}</div>

                                    @if ($direccion->line2)
                                        <div class="small text-secondary">{{ $direccion->line2 }}</div>
                                    @endif

                                    <div class="small text-secondary">
                                        {{ collect([$direccion->city, $direccion->state])->filter()->implode(', ') }}
                                        {{ $direccion->zip }}
                                    </div>
                                </div>

                                <div class="text-end">
                                    @if ($direccion->is_default_billing)
                                        <span class="badge bg-primary-subtle text-primary d-block mb-1">Facturación</span>
                                    @endif
                                    @if ($direccion->is_default_shipping)
                                        <span class="badge bg-primary-subtle text-primary d-block">Entrega</span>
                                    @endif
                                </div>
                            </div>

                        </div>

                    @empty

                        <div class="text-secondary small">Sin direcciones registradas.</div>

                    @endforelse

                </div>
            </div>

            {{-- ───── NOTAS ───── --}}
            @if ($customer->notes)
                <div class="card mb-3">
                    <div class="card-header bg-white">
                        <span class="fw-semibold"><i class="bi bi-sticky me-1"></i> Notas internas</span>
                    </div>
                    <div class="card-body">
                        <div class="small" style="white-space: pre-line;">{{ $customer->notes }}</div>
                    </div>
                </div>
            @endif

        </div>

        {{-- ═════════════════════════════════════════════════════════
             COLUMNA DERECHA · CERTIFICADOS E HISTORIAL
        ═════════════════════════════════════════════════════════ --}}
        <div class="col-12 col-lg-7">

            {{-- ───── CERTIFICADOS DE EXENCIÓN ───── --}}
            <div class="card mb-3">

                {{--
                    LAS DOS TARJETAS SE CONFUNDEN, ASÍ QUE CADA UNA DICE
                    EN UNA LÍNEA PARA QUÉ ES.

                    Esta decide si se le cobra impuesto. La de abajo solo
                    guarda archivos. Sin ese renglón, los dos títulos se
                    parecen demasiado.
                --}}
                <div class="card-header bg-white d-flex justify-content-between align-items-start">
                    <span>
                        <span class="fw-semibold">
                            <i class="bi bi-file-earmark-check me-1"></i> Certificados de exención
                        </span>
                        <small class="text-secondary d-block">
                            Decide si se le cobra el 7% de impuesto.
                        </small>
                    </span>

                    @can('customers.update')
                        <button class="btn btn-sm btn-outline-primary flex-shrink-0"
                                wire:click="abrirCertificado">
                            <i class="bi bi-plus-lg me-1"></i> Registrar
                        </button>
                    @endcan
                </div>

                <div class="card-body">

                    {{--
                        LA EXPLICACION, EN CRISTIANO

                        Esta seccion es la que mas se pregunta, asi que
                        conviene que se explique sola en vez de que haya
                        que preguntarle a alguien.
                    --}}
                    <div class="alert alert-light border py-2 small">
                        <div class="mb-1">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Que es esto.</strong>
                            Florida cobra 7% sobre el valor del contenedor. Un cliente que
                            <em>revende</em> no lo paga, pero solo si tiene su Annual Resale
                            Certificate al dia. Registrarlo aqui es lo unico que hace que el
                            sistema deje de cobrarle ese 7%.
                        </div>

                        <div class="mb-1">
                            <i class="bi bi-calendar-x me-1"></i>
                            <strong>Vence el 31 de diciembre</strong>, siempre, sin importar
                            cuando se emitio. Cada ano hay que registrar el nuevo.
                        </div>

                        <div class="mb-0">
                            <i class="bi bi-shield-check me-1"></i>
                            <strong>Por que se guarda el numero y no solo una casilla.</strong>
                            La factura anota <em>cual</em> certificado justificaba la exencion
                            el dia que se emitio. Si manana el estado audita, la marca "exento"
                            no prueba nada; el papel si. Y las facturas viejas conservan su
                            respaldo aunque el certificado ya se haya vencido.
                        </div>
                    </div>

                    @forelse ($customer->certificates as $certificado)

                        @php
                            $vigente = $certificado->id === $certificadoVigente?->id;
                            $diasRestantes = $certificado->valid_until
                                ? (int) now()->startOfDay()->diffInDays($certificado->valid_until, false)
                                : null;
                        @endphp

                        <div class="border rounded p-2 mb-2 {{ $vigente ? 'border-success' : '' }}">

                            <div class="d-flex justify-content-between align-items-start gap-2">

                                <div>
                                    <div class="fw-semibold font-monospace">
                                        {{ $certificado->certificate_number }}
                                    </div>
                                    <small class="text-secondary d-block">
                                        Año {{ $certificado->issued_year }} ·
                                        {{ $certificado->valid_from?->format('d/m/Y') }}
                                        a
                                        {{ $certificado->valid_until?->format('d/m/Y') }}
                                    </small>

                                    @if ($vigente && $diasRestantes !== null && $diasRestantes <= 45)
                                        <small class="text-warning d-block">
                                            <i class="bi bi-clock me-1"></i>
                                            Vence en {{ $diasRestantes }}
                                            {{ $diasRestantes === 1 ? 'día' : 'días' }}
                                        </small>
                                    @endif
                                </div>

                                <div class="text-end">
                                    @if ($vigente)
                                        <span class="badge bg-success-subtle text-success">Vigente</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            {{ $certificado->status?->label() ?? $certificado->status }}
                                        </span>
                                    @endif

                                    @can('customers.update')
                                        <div class="mt-1">
                                            <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                    wire:click="abrirCertificado({{ $certificado->id }})"
                                                    title="Registrar el del año siguiente">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>

                                            @if ($vigente)
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2"
                                                        wire:click="pedirRevocar({{ $certificado->id }})"
                                                        title="Revocar">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endcan
                                </div>

                            </div>

                            {{-- La confirmación, en línea y con el motivo escrito. --}}
                            @if ($revocando === $certificado->id)
                                <div class="alert alert-warning py-2 mt-2 mb-0 small">
                                    <div class="mb-2">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        El cliente dejará de estar exento y sus facturas nuevas
                                        llevarán el 7%. Las ya emitidas conservan este certificado
                                        como respaldo.
                                    </div>
                                    <button class="btn btn-sm btn-danger" wire:click="revocar">
                                        Sí, revocar
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" wire:click="cancelarRevocar">
                                        Cancelar
                                    </button>
                                </div>
                            @endif

                        </div>

                    @empty

                        <div class="text-secondary small">
                            Sin certificados. Este cliente paga el 7% sobre el valor del contenedor.
                        </div>

                    @endforelse

                </div>

            </div>

            {{-- ───── DOCUMENTOS DEL CLIENTE ─────

                 Los papeles que hay que tener del cliente y mantener
                 vigentes: contrato, autorizacion de tarjeta firmada,
                 certificado de exportacion.

                 La tabla `documents` existia desde el principio, con
                 categoria y fecha de vencimiento, y no habia ninguna
                 pantalla que la leyera ni que escribiera en ella.

                 ── POR QUE VA APARTE DE LOS CERTIFICADOS ──

                 El certificado de exencion no es solo un archivo: es un
                 registro con numero, ano y vigencia del que depende si
                 se le cobra impuesto o no. Tiene su propia tabla y su
                 propio observador.

                 Esto otro son archivos. Se suben, se bajan y se
                 vigila que no se venzan. Mezclarlos daria a entender
                 que subir un PDF exime de impuesto, y no es asi.
            ───────────────────────────────────────────── --}}
            <div class="card mb-3">

                <div class="card-header bg-white d-flex justify-content-between align-items-start">
                    <span>
                        <span class="fw-semibold">
                            <i class="bi bi-paperclip me-1"></i> Documentos del cliente
                        </span>
                        <small class="text-secondary d-block">
                            El archivador. Guarda copias, no decide nada.
                        </small>
                    </span>

                    @can('customers.update')
                        <a href="{{ route('comercial.clientes.edit', $customer) }}"
                           class="btn btn-sm btn-outline-primary flex-shrink-0">
                            <i class="bi bi-plus-lg me-1"></i> Adjuntar
                        </a>
                    @endcan
                </div>

                <div class="card-body">

                    {{--
                        EL PUENTE ENTRE EL ARCHIVO Y EL CERTIFICADO

                        Ya pasó, y es tan razonable que va a volver a pasar:
                        alguien sube el PDF con la categoría "Certificado de
                        exención", ve el nombre y da por hecho que el cliente
                        quedó exento.

                        No quedó. El archivo es la foto del papel. Lo que apaga
                        el 7% es el registro de arriba, con número y fechas.

                        Este aviso sale SOLO en ese caso concreto —hay PDF y no
                        hay certificado vigente— y trae el botón que resuelve el
                        problema. No es un cartel que se ignora: es el que
                        aparece justo cuando hace falta.
                    --}}
                    @php
                        $pdfDeExencion = $documentos->first(
                            fn ($d) => $d->category === \App\Enums\DocumentCategory::TaxExemption
                        );
                    @endphp

                    @if ($pdfDeExencion && ! $certificadoVigente)
                        <div class="alert alert-warning py-2 small">
                            <div class="mb-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <strong>Este cliente tiene el PDF del certificado pero sigue pagando el 7%.</strong>
                            </div>

                            <div class="mb-2">
                                Subir el archivo guarda la copia del papel, nada más. El sistema no
                                lee el PDF: para dejar de cobrarle el impuesto hay que registrar el
                                certificado con su número, su año y sus fechas de vigencia.
                            </div>

                            @can('customers.update')
                                <button class="btn btn-sm btn-warning" wire:click="abrirCertificado">
                                    <i class="bi bi-patch-check me-1"></i> Registrar el certificado ahora
                                </button>
                            @endcan
                        </div>
                    @endif

                    @forelse ($documentos as $doc)

                        @php
                            $vencido   = $doc->expires_at && $doc->expires_at->isPast();
                            $porVencer = $doc->expires_at
                                         && ! $vencido
                                         && $doc->expires_at->lte(now()->addDays(30));
                        @endphp

                        <div class="border rounded p-2 mb-2 {{ $vencido ? 'border-danger' : ($porVencer ? 'border-warning' : '') }}"
                             wire:key="doc-{{ $doc->id }}">

                            <div class="d-flex justify-content-between align-items-start gap-2">

                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $doc->name }}</div>

                                    <div class="small text-secondary">
                                        {{ $doc->category?->label() }} · {{ $doc->readable_size }}
                                        @if ($doc->uploadedBy)
                                            · subido por {{ $doc->uploadedBy->name }}
                                        @endif
                                    </div>

                                    @if ($doc->notes)
                                        <div class="small text-secondary fst-italic">{{ $doc->notes }}</div>
                                    @endif
                                </div>

                                <div class="text-end flex-shrink-0">

                                    @if ($doc->expires_at)
                                        <div class="small">
                                            Vence {{ $doc->expires_at->format('d/m/Y') }}
                                        </div>

                                        @if ($vencido)
                                            <span class="badge bg-danger-subtle text-danger">
                                                <i class="bi bi-exclamation-triangle"></i> Vencido
                                            </span>
                                        @elseif ($porVencer)
                                            <span class="badge bg-warning-subtle text-warning-emphasis">
                                                Por vencer
                                            </span>
                                        @else
                                            <span class="badge bg-success-subtle text-success">Vigente</span>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">No vence</span>
                                    @endif

                                    <div class="mt-1">
                                        <a href="{{ route('documentos.descargar', $doc) }}"
                                           class="btn btn-sm btn-outline-secondary" title="Descargar">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>

                                </div>

                            </div>

                        </div>

                    @empty

                        <div class="text-center py-3 text-secondary">
                            <i class="bi bi-folder2-open fs-3 d-block mb-2 opacity-50"></i>
                            <div class="small">
                                Sin documentos adjuntos. El contrato firmado y la autorizacion de
                                tarjeta se guardan aqui, con su fecha de vencimiento.
                            </div>
                        </div>

                    @endforelse

                </div>

            </div>

            {{-- ───── HISTORIAL ───── --}}
            <div class="card mb-3">

                <div class="card-header bg-white">
                    <span class="fw-semibold">
                        <i class="bi bi-clock-history me-1"></i> Qué le hemos hecho a este cliente
                    </span>

                    {{--
                        SE LLAMABA "HISTORIAL" Y NADIE SABIA DE QUE.

                        Ahora el titulo dice que es y el subtitulo dice de donde
                        sale. Son sus ultimas facturas y sus ultimos
                        presupuestos, para no tener que ir a buscarlo a los dos
                        listados por separado.

                        En un cliente recien creado sale vacio, y eso es
                        correcto: todavia no se le ha hecho nada.
                    --}}
                    <small class="text-secondary d-block">
                        Sus últimas facturas y presupuestos, para no tener que buscarlo en
                        cada listado.

                        @if ($empresaActiva)
                            Solo lo de <strong>{{ $empresaActiva->code }}</strong>:
                            cambie de empresa arriba para ver lo de la otra.
                        @endif
                    </small>
                </div>

                <div class="card-body">

                    {{-- FACTURAS --}}
                    <div class="fw-semibold small text-secondary mb-2">Últimas facturas</div>

                    @forelse ($facturas as $factura)

                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">

                            <div>
                                @can('invoices.view')
                                    <a href="{{ route('finanzas.facturacion.show', $factura) }}"
                                       class="fw-semibold text-decoration-none font-monospace">
                                        {{ $factura->invoice_number }}
                                    </a>
                                @else
                                    <span class="fw-semibold font-monospace">{{ $factura->invoice_number }}</span>
                                @endcan

                                <small class="text-secondary d-block">
                                    {{ $factura->issue_date?->format('d/m/Y') }}
                                </small>
                            </div>

                            <div class="text-end">
                                <div class="monto">${{ number_format($factura->total, 2) }}</div>

                                @if ($factura->balance_due > 0)
                                    <small class="text-warning">
                                        debe ${{ number_format($factura->balance_due, 2) }}
                                    </small>
                                @else
                                    <small class="text-success">pagada</small>
                                @endif
                            </div>

                        </div>

                    @empty

                        <div class="text-secondary small mb-3">
                            Todavía no se le ha facturado nada con esta empresa.
                        </div>

                    @endforelse

                    {{-- PRESUPUESTOS --}}
                    <div class="fw-semibold small text-secondary mt-4 mb-2">Últimos presupuestos</div>

                    @forelse ($presupuestos as $presupuesto)

                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">

                            <div>
                                @can('estimates.view')
                                    <a href="{{ route('comercial.presupuestos.show', $presupuesto) }}"
                                       class="fw-semibold text-decoration-none font-monospace">
                                        {{ $presupuesto->estimate_number }}
                                    </a>
                                @else
                                    <span class="fw-semibold font-monospace">{{ $presupuesto->estimate_number }}</span>
                                @endcan

                                <small class="text-secondary d-block">
                                    {{ $presupuesto->issue_date?->format('d/m/Y') }}
                                </small>
                            </div>

                            <div class="text-end">
                                <div class="monto">${{ number_format($presupuesto->total, 2) }}</div>
                                <small class="text-secondary">
                                    {{ $presupuesto->status?->label() }}
                                </small>
                            </div>

                        </div>

                    @empty

                        <div class="text-secondary small">
                            Todavía no se le ha cotizado nada con esta empresa.
                        </div>

                    @endforelse

                </div>

            </div>

        </div>

    </div>

    {{-- ═════════════════════════════════════════════════════════════
         EL MODAL DEL CERTIFICADO
    ═════════════════════════════════════════════════════════════ --}}
    @if ($modalCertificado)

        <div class="modal d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $renovandoDesde ? 'Renovar certificado' : 'Registrar certificado' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="cerrarCertificado"></button>
                    </div>

                    <div class="modal-body">

                        <div class="row g-3">

                            <div class="col-12 col-md-8">
                                <label class="form-label">
                                    Número del certificado <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control @error('certificate_number') is-invalid @enderror"
                                       wire:model.blur="certificate_number">
                                @error('certificate_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Año <span class="text-danger">*</span></label>
                                <input type="number"
                                       class="form-control @error('issued_year') is-invalid @enderror"
                                       wire:model.blur="issued_year">
                                @error('issued_year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Vigente desde <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('valid_from') is-invalid @enderror"
                                       wire:model.blur="valid_from">
                                @error('valid_from')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label class="form-label">Vigente hasta <span class="text-danger">*</span></label>
                                <input type="date"
                                       class="form-control @error('valid_until') is-invalid @enderror"
                                       wire:model.blur="valid_until">
                                @error('valid_until')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    El de Florida vence siempre el 31 de diciembre.
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notas</label>
                                <textarea class="form-control" rows="2"
                                          wire:model.blur="certNotas"></textarea>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" wire:click="cerrarCertificado">
                            Cancelar
                        </button>
                        <button class="btn btn-primary" wire:click="guardarCertificado">
                            <i class="bi bi-check-lg me-1"></i> Guardar certificado
                        </button>
                    </div>

                </div>
            </div>
        </div>

    @endif

</div>
