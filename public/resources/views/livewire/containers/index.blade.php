{{--
    ═══════════════════════════════════════════════════════════════════════
    INVENTARIO DE CONTENEDORES
    ═══════════════════════════════════════════════════════════════════════

    Mismo diseño que Clientes y Presupuestos: contadores que filtran,
    selector de vista, filtros en la cabecera de la tarjeta.

    ── LOS CONTADORES NO SON ESTADÍSTICAS ──

    DISPONIBLES   lo que se puede vender HOY: en yarda y sin venta ni
                  renta encima. Es el único número que un vendedor
                  necesita saber antes de prometerle algo a un cliente.

    EN YARDA      lo que está físicamente en el patio, incluyendo lo ya
                  vendido que todavía no se ha retirado. La diferencia
                  entre este y el anterior es lo que está apartado.

    POR LLEGAR    comprado y no recibido. Es la cifra que el Excel no
                  distingue, y por eso el Excel dice 416 unidades que
                  físicamente no están.

    SIN PRECIO    disponible pero sin precio de lista. Cada uno de esos
                  obliga al vendedor a inventar un número por teléfono.
--}}
<div x-data="{
        vista: (() => {
            try { return localStorage.getItem('vistaContenedores') || 'compacta' }
            catch (e) { return 'compacta' }
        })(),
        recordar(v) {
            this.vista = v;
            try { localStorage.setItem('vistaContenedores', v) } catch (e) {}
        },
     }">

    {{-- ─────────────────────────────────────────────────────────────
         ENCABEZADO
    ───────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">Contenedores</h4>
            <small class="text-secondary">
                El inventario sale de las unidades, una por una. Nunca de un contador a mano.
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

            @can('containers.create')
                <a href="{{ route('operaciones.contenedores.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Nuevo contenedor
                </a>
            @endcan

        </div>

    </div>

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
    ───────────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-lg-4 col-xl">
            <button type="button"
                    class="kpi {{ $resumen['disponibles'] > 0 ? 'kpi-ok' : 'kpi-apagado' }} {{ $marca === 'disponibles' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('disponibles')"
                    title="En yarda y sin venta ni renta encima">
                <span class="kpi-icono"><i class="bi bi-box-seam"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Disponibles</span>
                    <span class="kpi-valor d-block">{{ $resumen['disponibles'] }}</span>
                    <span class="kpi-pie d-block">Se pueden vender hoy</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-4 col-xl">
            <div class="kpi kpi-info">
                <span class="kpi-icono"><i class="bi bi-cash-stack"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Valor en yarda</span>
                    <span class="kpi-valor d-block">${{ number_format($resumen['valor'], 2) }}</span>
                    <span class="kpi-pie d-block">Compra + recogida + arreglos</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-lg-4 col-xl">
            <button type="button"
                    class="kpi {{ $resumen['porLlegar'] > 0 ? 'kpi-warn' : 'kpi-apagado' }} {{ $marca === 'por_llegar' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('por_llegar')">
                <span class="kpi-icono"><i class="bi bi-truck"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Por llegar</span>
                    <span class="kpi-valor d-block">{{ $resumen['porLlegar'] }}</span>
                    <span class="kpi-pie d-block">Compradas y no recibidas</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-6 col-xl">
            <button type="button"
                    class="kpi kpi-apagado {{ $marca === 'exportables' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('exportables')"
                    title="Cargo Worthy con CSC vigente">
                <span class="kpi-icono"><i class="bi bi-globe-americas"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Exportables</span>
                    <span class="kpi-valor d-block">{{ $resumen['exportables'] }}</span>
                    <span class="kpi-pie d-block">Aptas para exportación</span>
                </span>
            </button>
        </div>

        <div class="col-6 col-lg-6 col-xl">
            <button type="button"
                    class="kpi {{ $resumen['sinPrecio'] > 0 ? 'kpi-bad' : 'kpi-apagado' }} {{ $marca === 'sin_precio' ? 'border-2' : '' }}"
                    wire:click="filtrarPor('sin_precio')">
                <span class="kpi-icono"><i class="bi bi-tag"></i></span>
                <span class="kpi-cuerpo">
                    <span class="kpi-label d-block">Sin precio</span>
                    <span class="kpi-valor d-block">{{ $resumen['sinPrecio'] }}</span>
                    <span class="kpi-pie d-block">
                        {{ $resumen['sinPrecio'] > 0 ? 'Hay que inventarlo al cotizar' : 'Todas con precio' }}
                    </span>
                </span>
            </button>
        </div>

    </div>

    {{--
        EL AVISO DE LA OTRA EMPRESA

        Los contenedores son de FLCHR (RB-001, RB-002). Quien está parado
        en RST ve cero, y sin este aviso eso se lee como "se perdió el
        inventario".
    --}}
    @if ($hayEnLaOtraEmpresa > 0)
        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill me-1"></i>
            <strong>{{ $empresa?->code }} no tiene contenedores propios.</strong>
            Hay {{ $hayEnLaOtraEmpresa }}
            {{ $hayEnLaOtraEmpresa === 1 ? 'unidad registrada' : 'unidades registradas' }}
            a nombre de la otra empresa. Cambie de empresa arriba para verlas.
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         LA TABLA
    ───────────────────────────────────────────────────────────── --}}
    <div class="card">

        {{-- FILTROS --}}
        <div class="card-header">
            <div class="row g-2 align-items-center">

                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-body">
                            <i class="bi bi-search text-secondary"></i>
                        </span>
                        <input type="search" class="form-control"
                               placeholder="Número, código interno, medida…"
                               wire:model.live.debounce.400ms="buscar">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="estado">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="medida">
                        <option value="">Toda medida</option>
                        @foreach ($medidas as $m)
                            <option value="{{ $m->id }}">{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select class="form-select" wire:model.live="grado">
                        <option value="">Toda calidad</option>
                        @foreach ($grados as $g)
                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-2 text-end">
                    @if ($this->hayFiltros)
                        <button class="btn btn-outline-secondary w-100" wire:click="limpiarFiltros">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </button>
                    @endif
                </div>

                {{-- La segunda fila solo aparece si hace falta afinar más --}}
                <div class="col-6 col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="condicion">
                        <option value="">Toda condición</option>
                        @foreach ($condiciones as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <select class="form-select form-select-sm" wire:model.live="ubicacion">
                        <option value="">Toda ubicación</option>
                        @foreach ($ubicaciones as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════
             A · COMPACTA
        ═══════════════════════════════════════════════════ --}}
        <div x-show="vista === 'compacta'">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabla-compacta">

                        <thead>
                            <tr>
                                <th role="button" wire:click="ordenar('container_number')">
                                    Unidad
                                    @if ($ordenarPor === 'container_number')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th>Qué es</th>
                                <th>Dónde está</th>
                                <th role="button" wire:click="ordenar('status')">
                                    Estado
                                    @if ($ordenarPor === 'status')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th class="text-end" role="button" wire:click="ordenar('list_price')">
                                    Precio
                                    @if ($ordenarPor === 'list_price')
                                        <i class="bi bi-caret-{{ $direccion === 'asc' ? 'up' : 'down' }}-fill small"></i>
                                    @endif
                                </th>
                                <th class="text-end">Costo</th>
                                <th class="text-end" style="width: 120px;">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                        @forelse ($unidades as $u)

                            @php
                                $franja = match ($u->status->color()) {
                                    'green'  => 'fila-ok',
                                    'yellow' => 'fila-warn',
                                    'red'    => 'fila-bad',
                                    'blue'   => 'fila-info',
                                    default  => 'fila-mute',
                                };
                            @endphp

                            <tr wire:key="comp-{{ $u->id }}" class="fila-estado {{ $franja }}">

                                <td>
                                    <a href="{{ route('operaciones.contenedores.show', $u) }}"
                                       class="doc-numero">{{ $u->full_identifier }}</a>

                                    @if ($u->container_number && $u->internal_code)
                                        <div class="small text-secondary">{{ $u->internal_code }}</div>
                                    @endif
                                </td>

                                <td>
                                    <div class="small">{{ $u->classification ?: '—' }}</div>

                                    @if ($u->is_export_eligible)
                                        <span class="badge bg-info-subtle text-info-emphasis">
                                            <i class="bi bi-globe-americas"></i> Exportable
                                        </span>
                                    @endif
                                </td>

                                <td class="small">
                                    {{ $u->location?->name ?? ($u->depot?->name ?? '—') }}
                                </td>

                                <td>
                                    <x-ui.badge :color="$u->status->color()" :label="$u->status->label()" />
                                </td>

                                <td class="text-end monto">
                                    @if ($u->list_price !== null)
                                        ${{ number_format((float) $u->list_price, 2) }}
                                    @else
                                        <span class="text-danger small">Sin precio</span>
                                    @endif

                                    @if ($u->monthly_rate !== null)
                                        <div class="small text-secondary">
                                            ${{ number_format((float) $u->monthly_rate, 2) }}/mes
                                        </div>
                                    @endif
                                </td>

                                <td class="text-end small text-secondary monto">
                                    ${{ number_format($u->total_cost, 2) }}
                                </td>

                                <td class="text-end">
                                    @include('livewire.containers.partials.acciones', ['u' => $u])
                                </td>

                            </tr>

                        @empty
                            <tr>
                                <td colspan="7">
                                    @include('livewire.containers.partials.vacio')
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

            <div class="card-body border-bottom py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap small">
                    <span class="text-secondary">Ordenar por:</span>
                    @foreach ([
                        'created_at'       => 'Más reciente',
                        'container_number' => 'Número',
                        'status'           => 'Estado',
                        'list_price'       => 'Precio',
                    ] as $col => $nombre)
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
                @forelse ($unidades as $u)

                    @php
                        $tf = match ($u->status->color()) {
                            'green'  => 'tf-ok',
                            'yellow' => 'tf-warn',
                            'red'    => 'tf-bad',
                            'blue'   => 'tf-info',
                            default  => '',
                        };
                    @endphp

                    <div class="tarjeta-fila {{ $tf }}" wire:key="tarj-{{ $u->id }}">

                        <div class="tf-barra"></div>

                        <div class="tf-cuerpo">
                            <div class="tf-titulo">
                                <a href="{{ route('operaciones.contenedores.show', $u) }}"
                                   class="doc-numero">{{ $u->full_identifier }}</a>

                                <x-ui.badge :color="$u->status->color()" :label="$u->status->label()" />

                                @if ($u->is_export_eligible)
                                    <span class="badge bg-info-subtle text-info-emphasis">
                                        <i class="bi bi-globe-americas"></i> Exportable
                                    </span>
                                @endif
                            </div>

                            <div class="tf-cliente">{{ $u->classification ?: 'Sin clasificar' }}</div>

                            <div class="tf-meta mt-1">
                                <span>
                                    <i class="bi bi-geo-alt"></i>
                                    {{ $u->location?->name ?? ($u->depot?->name ?? 'Sin ubicación') }}
                                </span>

                                @if ($u->received_at)
                                    <span><i class="bi bi-calendar3"></i> Recibido {{ $u->received_at->format('d/m/Y') }}</span>
                                @endif

                                <span><i class="bi bi-wallet2"></i> Costo ${{ number_format($u->total_cost, 2) }}</span>
                            </div>
                        </div>

                        <div class="tf-lado">
                            <div class="tf-total">
                                @if ($u->list_price !== null)
                                    ${{ number_format((float) $u->list_price, 2) }}
                                @else
                                    <span class="text-danger fs-6">Sin precio</span>
                                @endif
                            </div>

                            @if ($u->monthly_rate !== null)
                                <div class="small text-secondary">
                                    ${{ number_format((float) $u->monthly_rate, 2) }}/mes
                                </div>
                            @endif

                            <div class="mt-2">
                                @include('livewire.containers.partials.acciones', ['u' => $u])
                            </div>
                        </div>

                    </div>

                @empty
                    @include('livewire.containers.partials.vacio')
                @endforelse
            </div>
        </div>

        @if ($unidades->hasPages())
            <div class="card-footer">
                {{ $unidades->links() }}
            </div>
        @endif

    </div>

</div>
