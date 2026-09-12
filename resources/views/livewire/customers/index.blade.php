{{--
    ═══════════════════════════════════════════════════════════════════════
    LISTADO DE CLIENTES — con las dos formas de ver la tabla
    ═══════════════════════════════════════════════════════════════════════

    Mismo diseño que el listado de presupuestos, entrada por entrada:
    contadores con chip de color, selector de vista, filtros en la
    cabecera de la tarjeta, y las acciones y el estado vacío en partials
    compartidos.

    ── DOS FORMAS DE VER LO MISMO ──

    LISTA      filas densas con franja de color. La que más filas mete
               en pantalla. Para revisar la cartera de un vistazo.

    TARJETAS   cada cliente en su propia tarjeta. Se ven menos de golpe
               pero cada uno entra solo por los ojos. Para buscar uno
               concreto, y en tablet.

    Las dos muestran EXACTAMENTE los mismos datos y tienen las mismas
    acciones. Lo único que cambia es cómo se leen.

    ── LOS CINCO CONTADORES ──

    Son botones que filtran. Tres de ellos no son estadísticas, son
    listas de trabajo:

      SIN DIRECCIÓN   cada uno obliga a teclear la dirección entera en
                      cada documento que se le haga.
      PAPELES         tiene un documento vencido o a punto de vencer.
      EN RETENCIÓN    no se le vende a crédito.

    ── POR QUÉ NO TOCA EL COMPONENTE PHP ──

    El selector de vista vive en Alpine. Las dos tablas se pintan y solo
    se muestra una, así que cambiar de vista es instantáneo y no cuesta
    ni una consulta a la base.
--}}
<div x-data="{
        /*
         | La vista elegida se guarda en el navegador de cada usuario.
         |
         | Va en el navegador y no en la base porque es una preferencia
         | personal de cómo mirar una lista, no un dato del negocio. La
         | misma persona puede querer tarjetas en su tablet y lista en el
         | escritorio, y así cada equipo recuerda lo suyo.
         |
         | El try es por si el navegador tiene el almacenamiento bloqueado
         | —pasa en modo privado de algunos—. En ese caso arranca en lista
         | y no se recuerda, que es molesto pero no rompe nada.
         */
        vista: (() => {
            try { return localStorage.getItem('vistaClientes') || 'compacta' }
            catch (e) { return 'compacta' }
        })(),

        recordar(v) {
            this.vista = v;
            try { localStorage.setItem('vistaClientes', v) } catch (e) {}
        },
     }">

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Clientes</h4>
            <small class="text-secondary">
                Se registran una sola vez y se reutilizan en cotizaciones, ventas, rentas y viajes.
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">

            <div class="selector-vista" title="Cómo ver la lista">
                <button type="button" x-on:click="recordar('compacta')"
                        :class="vista === 'compacta' && 'activo'">
                    <i class="bi bi-list"></i> Lista
                </button>
                <button type="button" x-on:click="recordar('tarjetas')"
                        :class="vista === 'tarjetas' && 'activo'">
                    <i class="bi bi-grid-1x2"></i> Tarjetas
                </button>
            </div>

            @can('customers.create')
                <a href="{{ route('comercial.clientes.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Nuevo cliente
                </a>
            @endcan

        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         AVISOS
    ───────────────────────────────────────────────────────────── --}}
    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         LOS CONTADORES

         Los que están en cero quedan en blanco y gris a propósito, para
         que los que sí tienen algo resalten por contraste.
    ───────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-3 col-xl">
            <button type="button" class="kpi kpi-info" wire:click="limpiarFiltros">
                <span class="kpi-icono"><i class="bi bi-people"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Activos</span>
                    <span class="kpi-valor d-block">{{ $resumen['activos'] }}</span>
                    <span class="kpi-pie d-block">Disponibles para cotizar</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3 col-xl">
            <button type="button"
                    class="kpi {{ $resumen['sinDireccion'] > 0 ? 'kpi-warn' : 'kpi-apagado' }} {{ $marca === 'sin_direccion' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('sin_direccion')">
                <span class="kpi-icono"><i class="bi bi-geo-alt"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Sin dirección</span>
                    <span class="kpi-valor d-block">{{ $resumen['sinDireccion'] }}</span>
                    <span class="kpi-pie d-block">Hay que teclearla cada vez</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3 col-xl">
            <button type="button"
                    class="kpi {{ $resumen['papeles'] > 0 ? 'kpi-bad' : 'kpi-apagado' }} {{ $marca === 'papeles' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('papeles')">
                <span class="kpi-icono"><i class="bi bi-paperclip"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Papeles al día</span>
                    <span class="kpi-valor d-block">{{ $resumen['papeles'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['papeles'] > 0 ? 'Vencidos o por vencer' : 'Todo en regla' }}
                    </span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3 col-xl">
            <button type="button"
                    class="kpi kpi-apagado {{ $marca === 'exentos' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('exentos')">
                <span class="kpi-icono"><i class="bi bi-patch-check"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Exentos de tax</span>
                    <span class="kpi-valor d-block">{{ $resumen['exentos'] }}</span>
                    <span class="kpi-pie d-block">Con certificado vigente</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-3 col-xl">
            <button type="button"
                    class="kpi {{ $resumen['retenidos'] > 0 ? 'kpi-warn' : 'kpi-apagado' }} {{ $marca === 'retenidos' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('retenidos')">
                <span class="kpi-icono"><i class="bi bi-hand-thumbs-down"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">En retención</span>
                    <span class="kpi-valor d-block">{{ $resumen['retenidos'] }}</span>
                    <span class="kpi-pie d-block">No se les vende a crédito</span>
                </span>
            </button>
        </div>

    </div>

    {{-- ─────────────────────────────────────────────────────────────
         LA TABLA
    ───────────────────────────────────────────────────────────── --}}
    <div class="card">

        {{-- FILTROS --}}
        <div class="card-header">
            <div class="row g-2 align-items-center">

                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>
                        <input type="search" class="form-control"
                               placeholder="Nombre, número, teléfono, correo o un contacto…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="tipo">
                        <option value="">Todos los tipos</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="estado">
                        <option value="activos">Activos</option>
                        <option value="inactivos">Desactivados</option>
                        <option value="">Todos</option>
                    </select>
                </div>

                <div class="col-12 col-md-2 text-end">
                    @if ($buscar || $tipo || $marca || $estado !== 'activos' || $ordenarPor !== 'created_at')
                        <button class="btn btn-outline-secondary w-100" wire:click="limpiarFiltros">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </button>
                    @endif
                </div>

            </div>
        </div>

        {{--
            A partir de aquí, la misma información dos veces.

            Se calcula una sola vez lo que comparten —el color de la
            franja— y cada vista lo pinta a su manera.
        --}}

        {{-- ═══════════════════════════════════════════════════
             A · COMPACTA
        ═══════════════════════════════════════════════════ --}}
        <div x-show="vista === 'compacta'">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabla-compacta">

                        <thead>
                            <tr>
                                <th role="button" wire:click="ordenar('customer_number')" style="width: 120px;">
                                    Número
                                    @if ($ordenarPor === 'customer_number')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th role="button" wire:click="ordenar('display_name')">
                                    Cliente
                                    @if ($ordenarPor === 'display_name')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th>Contacto</th>
                                <th class="text-center">Marcas</th>
                                <th role="button" wire:click="ordenar('created_at')" style="width: 110px;">
                                    Registrado
                                    @if ($ordenarPor === 'created_at')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th class="text-end" style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                        @forelse ($clientes as $cliente)

                            @php
                                /*
                                 | La franja dice, sin leer, qué le pasa a esa
                                 | ficha:
                                 |   rojo   papel vencido o por vencer
                                 |   ámbar  sin dirección, o en retención
                                 |   verde  exento con certificado vigente
                                 |   gris   nada que reportar
                                 */
                                $franja = match (true) {
                                    $cliente->documentos_alerta_count > 0 => 'fila-bad',
                                    $cliente->credit_hold                 => 'fila-warn',
                                    $cliente->addresses_count === 0       => 'fila-warn',
                                    (bool) $cliente->tax_exempt           => 'fila-ok',
                                    default                               => 'fila-mute',
                                };
                            @endphp

                            <tr wire:key="comp-{{ $cliente->id }}"
                                class="fila-estado {{ $franja }} {{ $cliente->is_active ? '' : 'opacity-50' }}">

                                <td>
                                    <a href="{{ route('comercial.clientes.show', $cliente) }}"
                                       class="doc-numero">{{ $cliente->customer_number }}</a>
                                </td>

                                <td>
                                    <div class="fw-medium">{{ $cliente->name }}</div>
                                    <div class="small text-secondary">
                                        {{ $cliente->type?->label() }}

                                        @if ($cliente->addresses_count === 0)
                                            · <span class="text-warning">sin dirección</span>
                                        @elseif ($cliente->addresses_count > 1)
                                            · {{ $cliente->addresses_count }} direcciones
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    @if ($cliente->primary_phone)
                                        <div class="small">
                                            <i class="bi bi-telephone text-secondary me-1"></i>{{ $cliente->primary_phone }}
                                        </div>
                                    @endif

                                    @if ($cliente->primary_email)
                                        <div class="small text-secondary">
                                            <i class="bi bi-envelope me-1"></i>{{ $cliente->primary_email }}
                                        </div>
                                    @endif

                                    @if (! $cliente->primary_phone && ! $cliente->primary_email)
                                        @if ($cliente->contacts_count > 0)
                                            <small class="text-secondary">
                                                {{ $cliente->contacts_count }}
                                                {{ $cliente->contacts_count === 1 ? 'contacto' : 'contactos' }}
                                            </small>
                                        @else
                                            <small class="text-warning">
                                                <i class="bi bi-exclamation-triangle me-1"></i> Sin forma de contacto
                                            </small>
                                        @endif
                                    @endif
                                </td>

                                <td class="text-center">
                                    @include('livewire.customers.partials.marcas', ['c' => $cliente])
                                </td>

                                <td class="small text-secondary">
                                    {{ $cliente->created_at?->format('d/m/Y') }}
                                </td>

                                <td class="text-end">
                                    @include('livewire.customers.partials.acciones', ['c' => $cliente])
                                </td>

                            </tr>

                        @empty
                            <tr>
                                <td colspan="6">
                                    @include('livewire.customers.partials.vacio')
                                </td>
                            </tr>
                        @endforelse
                        </tbody>

                    </table>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             B · TARJETAS
        ═══════════════════════════════════════════════════ --}}
        <div x-show="vista === 'tarjetas'" x-cloak>

            {{-- Sin encabezado de tabla no hay dónde ordenar: va acá --}}
            <div class="card-body border-bottom py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap small">
                    <span class="text-secondary">Ordenar por:</span>
                    @foreach (['created_at' => 'Más reciente', 'display_name' => 'Nombre', 'customer_number' => 'Número'] as $col => $nombre)
                        <button class="btn btn-sm {{ $ordenarPor === $col ? 'btn-primary' : 'btn-outline-secondary' }} py-0"
                                wire:click="ordenar('{{ $col }}')">
                            {{ $nombre }}
                            @if ($ordenarPor === $col)
                                <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill"></i>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="lista-tarjetas">
                @forelse ($clientes as $cliente)

                    @php
                        $tf = match (true) {
                            $cliente->documentos_alerta_count > 0 => 'tf-bad',
                            $cliente->credit_hold                 => 'tf-warn',
                            $cliente->addresses_count === 0       => 'tf-warn',
                            (bool) $cliente->tax_exempt           => 'tf-ok',
                            default                               => '',
                        };
                    @endphp

                    <div class="tarjeta-fila {{ $tf }} {{ $cliente->is_active ? '' : 'opacity-50' }}"
                         wire:key="tarj-{{ $cliente->id }}">

                        <div class="tf-barra"></div>

                        <div class="tf-cuerpo">
                            <div class="tf-titulo">
                                <a href="{{ route('comercial.clientes.show', $cliente) }}"
                                   class="doc-numero">{{ $cliente->customer_number }}</a>

                                @include('livewire.customers.partials.marcas', ['c' => $cliente])
                            </div>

                            <div class="tf-cliente">{{ $cliente->name }}</div>

                            <div class="tf-meta mt-1">
                                <span><i class="bi bi-tag"></i> {{ $cliente->type?->label() }}</span>

                                @if ($cliente->primary_phone)
                                    <span><i class="bi bi-telephone"></i> {{ $cliente->primary_phone }}</span>
                                @endif

                                @if ($cliente->primary_email)
                                    <span><i class="bi bi-envelope"></i> {{ $cliente->primary_email }}</span>
                                @endif

                                <span>
                                    <i class="bi bi-geo-alt"></i>
                                    {{ $cliente->addresses_count === 0
                                        ? 'Sin dirección'
                                        : $cliente->addresses_count.' '.($cliente->addresses_count === 1 ? 'dirección' : 'direcciones') }}
                                </span>
                            </div>
                        </div>

                        <div class="tf-lado">
                            @include('livewire.customers.partials.acciones', ['c' => $cliente])
                        </div>

                    </div>

                @empty
                    @include('livewire.customers.partials.vacio')
                @endforelse
            </div>
        </div>

        @if ($clientes->hasPages())
            <div class="card-footer">
                {{ $clientes->links() }}
            </div>
        @endif

    </div>

</div>
