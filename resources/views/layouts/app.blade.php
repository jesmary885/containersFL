<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @hasSection('title')
            @yield('title') - {{ config('app.name', 'Containers FL') }}
        @else
            {{ config('app.name', 'Containers FL') }}
        @endif
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary">
    <div class="app-wrapper">

        {{--
            ═══════════════════════════════════════════════════════════════
            LA BARRA DE ARRIBA
            ═══════════════════════════════════════════════════════════════

            Estaba en el gris de fábrica de AdminLTE: tres enlaces
            apagados en una barra blanca. No se distinguían entre sí y no
            se veía cuál era importante.

            Ahora son tres piezas con forma propia:

              EMPRESA   una pastilla del color de la marca. Es lo más
                        importante de esta barra y por eso es lo que más
                        pesa: emitir una factura desde la empresa
                        equivocada es el error más caro del sistema.

              IDIOMA    una pastilla con borde, gris hasta que la tocas.
                        Se cambia una vez y no se vuelve a mirar.

              USUARIO   un círculo con su inicial. Ocupa poco y dice
                        quién está adentro sin leer.

            La franja de color del borde de abajo sigue donde estaba, y
            sigue siendo la protección más barata contra ese mismo error.
            El color sale de companies.brand_color; el gris del ?: es el
            respaldo por si una empresa todavía no lo tiene configurado.
        --}}
        <nav class="app-header navbar navbar-expand barra-superior"
             style="border-bottom: 3px solid {{ $empresaActual?->brand_color ?: '#334155' }}">

            <div class="container-fluid">

                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link bs-hamburguesa" data-lte-toggle="sidebar" href="#" role="button"
                           title="Abrir o cerrar el menú">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center gap-2">

                    {{-- Selector de empresa. Va primero a propósito:
                         es lo que más importa de esta barra. --}}
                    @include('layouts.partials.company-switcher')

                    {{-- Selector de idioma. --}}
                    @include('layouts.partials.language-switcher')

                    {{-- ───── EL USUARIO ───── --}}
                    <li class="nav-item dropdown">

                        <a href="#" class="bs-usuario" data-bs-toggle="dropdown" role="button"
                           aria-expanded="false">

                            {{--
                                El círculo con la inicial.

                                Se saca del nombre en vez de pedir una foto:
                                nadie sube una foto, y un círculo vacío se ve
                                peor que una letra.
                            --}}
                            <span class="bs-avatar">
                                {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                            </span>

                            <span class="bs-usuario-texto d-none d-md-inline">
                                {{ auth()->user()->name }}
                            </span>

                            <i class="bi bi-chevron-down bs-chevron"></i>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 230px;">

                            <li>
                                <div class="px-3 py-2">
                                    <div class="fw-semibold">{{ auth()->user()->name }}</div>
                                    <div class="small text-secondary">{{ auth()->user()->email }}</div>
                                </div>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <li class="px-2 pb-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                        <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                                    </button>
                                </form>
                            </li>

                        </ul>
                    </li>

                </ul>
            </div>
        </nav>

        {{-- SIDEBAR --}}
        @include('layouts.partials.sidebar')

        {{-- CONTENIDO --}}
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">

                    {{--
                        AVISOS DEL SISTEMA

                        Aquí aterrizan los mensajes que dejan los controladores con
                        ->with('status', ...) o ->with('error', ...).

                        Sin este bloque, el cambio de empresa funcionaría pero en
                        silencio, y el usuario no tendría confirmación de que pasó
                        algo. Peor: si intentara entrar a una empresa sin permiso,
                        el mensaje de error no se vería en ninguna parte y parecería
                        que el botón está roto.
                    --}}

                    @if (session('status'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-1"></i>
                            {{ session('status') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    {{ $slot }}
                </div>
            </div>
        </main>

        <footer class="app-footer">
            <div class="float-end d-none d-sm-inline">Containers FL</div>
            <strong>&copy; {{ date('Y') }} Containers FL.</strong> Todos los derechos reservados.
        </footer>

    </div>

    {{--
        ═══════════════════════════════════════════════════════════════════
        LOS DOS CAMPOS QUE PIENSAN SOLOS
        ═══════════════════════════════════════════════════════════════════

        Aquí viven las funciones que usan <x-ui.telefono> y <x-ui.correo>.

        Van UNA VEZ en el layout, y no dentro de cada componente, por una
        razón muy concreta: el formulario de cliente puede tener cinco
        contactos, y cada uno lleva un teléfono y un correo. Si la
        función se declarara dentro del componente, el navegador estaría
        recibiendo la misma definición diez veces en la misma página.

        Van ANTES de @livewireScripts porque Alpine viene dentro de
        Livewire: hay que dejar el escuchador puesto antes de que Alpine
        arranque, o el evento pasa y nadie lo oye.
    --}}
    <script>
        document.addEventListener('alpine:init', () => {

            /* ═══════════════════════════════════════════════════════════
               EL TELÉFONO
               ═══════════════════════════════════════════════════════════ */
            Alpine.data('campoTelefono', (valorInicial, propiedad) => ({

                codigo: '+1',
                numero: '',

                init() {
                    this.desarmar(valorInicial);
                },

                /*
                 | Parte lo guardado en código de país y número.
                 |
                 | Se prueban los códigos de más largo a más corto: "+1"
                 | y "+1809" empiezan igual, y si se probara "+1" primero,
                 | un número dominicano quedaría como estadounidense con
                 | un 809 de más al principio.
                 */
                /* El texto tal como se guardaria ahora mismo. */
                armado() {
                    return this.numero.replace(/\D/g, '')
                        ? `${this.codigo} ${this.numero}`
                        : '';
                },

                desarmar(valor) {
                    this.numero = '';

                    if (!valor) return;

                    const codigos = [...this.$el.querySelectorAll('select option')]
                        .map(o => o.value)
                        .sort((a, b) => b.length - a.length);

                    const limpio = String(valor).trim();

                    for (const c of codigos) {
                        if (limpio.startsWith(c)) {
                            this.codigo = c;
                            this.numero = this.formatear(limpio.slice(c.length), c);
                            return;
                        }
                    }

                    /*
                     | Sin código al principio: es un número viejo, de
                     | antes de este campo. Se asume +1, que es de donde
                     | vienen todos.
                     */
                    this.codigo = '+1';
                    this.numero = this.formatear(limpio, '+1');
                },

                /* Deja solo dígitos y los acomoda. */
                formatear(texto, codigo) {
                    const d = String(texto).replace(/\D/g, '');

                    if (codigo === '+1') {
                        // El 1 de más que a veces se teclea delante.
                        const n = (d.length === 11 && d[0] === '1') ? d.slice(1) : d;

                        if (n.length <= 3)  return n;
                        if (n.length <= 6)  return `(${n.slice(0,3)}) ${n.slice(3)}`;

                        return `(${n.slice(0,3)}) ${n.slice(3,6)}-${n.slice(6,10)}`;
                    }

                    // Otros países: grupos de tres, que se lee mejor que la tira.
                    return d.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
                },

                alEscribir(evento) {
                    const antes = evento.target.value;
                    const despues = this.formatear(antes, this.codigo);

                    this.numero = despues;

                    /*
                     | Si el texto no cambió al formatear, el cursor se
                     | queda donde estaba. Solo se lo manda al final
                     | cuando hubo que reacomodar, que es cuando el
                     | navegador lo perdería igual.
                     */
                    if (antes !== despues) {
                        this.$nextTick(() => {
                            evento.target.setSelectionRange(despues.length, despues.length);
                        });
                    }
                },

                alCambiarPais() {
                    this.numero = this.formatear(this.numero, this.codigo);
                    this.guardar();
                },

                /* Sin dígitos no hay teléfono: se guarda vacío, no "+1". */
                guardar() {
                    this.$wire.set(propiedad, this.armado());
                },
            }));

            /* ═══════════════════════════════════════════════════════════
               EL CORREO
               ═══════════════════════════════════════════════════════════ */
            Alpine.data('campoCorreo', (valorInicial, propiedad, dominios) => ({

                usuario: '',
                dominio: dominios[0],
                otroDominio: '',

                init() {
                    this.desarmar(valorInicial);
                },

                desarmar(valor) {
                    if (!valor || !String(valor).includes('@')) {
                        this.usuario = valor ? String(valor).trim() : '';
                        return;
                    }

                    const partes = String(valor).trim().split('@');

                    this.usuario = partes[0];

                    const d = partes.slice(1).join('@').toLowerCase();

                    if (dominios.includes(d)) {
                        this.dominio = d;
                    } else {
                        /*
                         | Dominio propio de una empresa. Se elige "Otro"
                         | y se rellena el campo, para que editar un
                         | cliente viejo no obligue a volver a escribirlo.
                         */
                        this.dominio = '__otro__';
                        this.otroDominio = d;
                    }
                },

                compuesto() {
                    const u = this.usuario.trim();

                    if (!u) return '';

                    const d = this.dominio === '__otro__'
                        ? this.otroDominio.trim().replace(/^@/, '')
                        : this.dominio;

                    return d ? `${u}@${d}` : '';
                },

                /*
                 | Si alguien pega el correo entero en el campo del
                 | nombre —que es lo que pasa nueve de cada diez veces—
                 | se parte solo en vez de quedar como "carlos@gmail.com"
                 | delante de otro "@gmail.com".
                 */
                alEscribirUsuario() {
                    if (this.usuario.includes('@')) {
                        this.desarmar(this.usuario);
                    }
                },

                alEscribirDominio() {
                    this.otroDominio = this.otroDominio.replace(/^@/, '');
                },

                alCambiarDominio() {
                    this.guardar();
                },

                guardar() {
                    this.$wire.set(propiedad, this.compuesto());
                },
            }));

        });
    </script>

    @livewireScripts
</body>

</html>
