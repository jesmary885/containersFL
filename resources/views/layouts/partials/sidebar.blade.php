{{--
    ═══════════════════════════════════════════════════════════════════════
    MENÚ LATERAL
    ═══════════════════════════════════════════════════════════════════════

    Todo el texto sale de lang/es/nav.php y lang/en/nav.php.

    Si agregas un módulo: agrega la clave en LOS DOS archivos. Si falta
    en uno, Laravel imprime la clave cruda ("nav.reports") en pantalla.
    No da error, simplemente se ve mal, y eso es peor porque nadie se
    entera hasta que un usuario lo reporta.
--}}
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">

    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link logo-container">
            <img src="{{ Storage::url('logo/logo-Photoroom.png') }}"
                 alt="Containers FL"
                 class="logo-containers">
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>{{ __('nav.dashboard') }}</p>
                    </a>
                </li>

                {{-- ───── COMERCIAL ───── --}}
                <li class="nav-item {{ request()->routeIs('comercial.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-briefcase"></i>
                        <p>{{ __('nav.commercial') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('comercial.clientes.index') }}"
                               class="nav-link {{ request()->routeIs('comercial.clientes.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.customers') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('comercial.presupuestos.index') }}"
                               class="nav-link {{ request()->routeIs('comercial.presupuestos.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.estimates') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('comercial.ventas.index') }}"
                               class="nav-link {{ request()->routeIs('comercial.ventas.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.sales') }}</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- ───── OPERACIONES ───── --}}
                <li class="nav-item {{ request()->routeIs('operaciones.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-gear-wide-connected"></i>
                        <p>{{ __('nav.operations') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('operaciones.contenedores.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.containers') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.rentas.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.rentals') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.viajes.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.trips') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.choferes.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.drivers') }}</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{--
                    ───── COMPRAS ─────

                    ⚠️ Las tres rutas de aquí abajo apuntan a módulos de
                    Operaciones porque los de Compras todavía no existen.
                    Es un marcador de posición: "Proveedores" lleva a
                    Contenedores, "Compras" a Rentas y "Releases" a Viajes.

                    Cuando se creen los módulos hay que cambiar las tres.
                    Y ojo con la condición del menu-open: también mira
                    'operaciones.*', así que este bloque se abre cuando
                    estás en Operaciones. Se arregla solo cuando existan
                    las rutas propias.
                --}}
                <li class="nav-item {{ request()->routeIs('compras.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-cart3"></i>
                        <p>{{ __('nav.purchasing') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('operaciones.contenedores.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.suppliers') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.rentas.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.purchases') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.viajes.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.releases') }}</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- ───── INVENTARIO ───── --}}
                <li class="nav-item {{ request()->routeIs('inventario.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-box-seam"></i>
                        <p>{{ __('nav.inventory') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('inventario.insumos.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.supplies') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('inventario.camiones.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.trucks') }}</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- ───── FINANZAS ───── --}}
                <li class="nav-item {{ request()->routeIs('finanzas.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-cash-coin"></i>
                        <p>{{ __('nav.finance') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('finanzas.facturacion.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.invoicing') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('finanzas.pagos.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.payments') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.expenses') }}</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- ───── ADMINISTRACIÓN ───── --}}
                <li class="nav-item {{ request()->routeIs('administracion.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-shield-lock"></i>
                        <p>{{ __('nav.administration') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('administracion.usuarios.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.users') }}</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('administracion.roles.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>{{ __('nav.roles') }}</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="{{ route('reportes.index') }}" class="nav-link">
                        <i class="nav-icon bi bi-graph-up"></i>
                        <p>{{ __('nav.reports') }}</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('configuracion.index') }}" class="nav-link">
                        <i class="nav-icon bi bi-sliders"></i>
                        <p>{{ __('nav.settings') }}</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>
