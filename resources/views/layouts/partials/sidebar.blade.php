{{--
    ═══════════════════════════════════════════════════════════════════════
    MENÚ LATERAL
    ═══════════════════════════════════════════════════════════════════════

    Todo el texto sale de lang/es/nav.php y lang/en/nav.php.

    Si agregas un módulo: agrega la clave en LOS DOS archivos. Si falta
    en uno, Laravel imprime la clave cruda ("nav.reports") en pantalla.
    No da error, simplemente se ve mal, y eso es peor porque nadie se
    entera hasta que un usuario lo reporta.

    ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──

    1. EL MENÚ AHORA DICE LO MISMO QUE LA PANTALLA DE ROLES.

       Antes no coincidían. Roles maneja 21 módulos en 5 bloques
       (Comercial, Operaciones, Compras, Finanzas, Sistema) y el menú
       enseñaba 18 en 6, con nombres distintos.

       Eso tiene un costo real: quien reparte permisos marca "Depósitos"
       en Roles y después busca Depósitos en el menú y no está. O marca
       "Liquidación de choferes" y no sabe dónde va a aparecer.

       Ahora los bloques y los nombres son los mismos, en el mismo orden.

    2. SE ACABARON LOS ENLACES QUE MENTÍAN.

       El bloque Compras tenía tres entradas —Proveedores, Compras y
       Releases— que en realidad llevaban a Contenedores, Rentas y
       Viajes. Estaba puesto como parche y anotado como tal.

       Ahora cada entrada lleva a SU ruta, y cada ruta a su pantalla
       provisional. El usuario ve "Proveedores · en construcción", que
       es la verdad, en vez de aterrizar en Contenedores sin entender
       por qué.

    3. CAMIONES SE MUDÓ A OPERACIONES E INSUMOS A COMPRAS.

       Es donde los pone Roles. El bloque "Inventario" desapareció
       porque sus dos entradas se fueron a otros bloques.

    4. EL COLOR.

       Cada bloque tiene el suyo y cada submenú hereda ese color en su
       icono. Los círculos grises idénticos de antes no ayudaban a
       distinguir nada: quince puntos iguales uno debajo del otro.

       El color vive en resources/css/sidebar.css.

    ── EL @can NO ES LA SEGURIDAD ──

    Esconder el enlace es cortesía, no protección. La seguridad está en
    el `can:` de la ruta y en el `exigirPermiso()` de cada componente.
    Quien escriba la dirección a mano no entra igual; simplemente el
    menú deja de mentirle a los demás.
--}}
<aside class="app-sidebar sidebar-containers shadow" data-bs-theme="dark">

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

                {{-- ───── PANEL ───── --}}
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-speedometer2 ic-panel"></i>
                        <p>{{ __('nav.dashboard') }}</p>
                    </a>
                </li>

                {{-- ═════════════════════════════════════════════
                     COMERCIAL
                     Roles: customers · estimates · sales
                ═════════════════════════════════════════════ --}}
                @canany(['customers.view', 'estimates.view', 'sales.view'])
                    <li class="nav-item bloque-comercial {{ request()->routeIs('comercial.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon bi bi-briefcase-fill"></i>
                            <p>{{ __('nav.commercial') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">

                            @can('customers.view')
                                <li class="nav-item">
                                    <a href="{{ route('comercial.clientes.index') }}"
                                       class="nav-link {{ request()->routeIs('comercial.clientes.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-person-vcard"></i><p>{{ __('nav.customers') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('estimates.view')
                                <li class="nav-item">
                                    <a href="{{ route('comercial.presupuestos.index') }}"
                                       class="nav-link {{ request()->routeIs('comercial.presupuestos.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-file-earmark-text"></i><p>{{ __('nav.estimates') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('sales.view')
                                <li class="nav-item">
                                    <a href="{{ route('comercial.ventas.index') }}"
                                       class="nav-link {{ request()->routeIs('comercial.ventas.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-bag-check"></i><p>{{ __('nav.sales') }}</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                {{-- ═════════════════════════════════════════════
                     OPERACIONES
                     Roles: containers · rentals · trips · drivers · vehicles
                ═════════════════════════════════════════════ --}}
                @canany(['containers.view', 'rentals.view', 'trips.view', 'drivers.view', 'vehicles.view'])
                    <li class="nav-item bloque-operaciones {{ request()->routeIs('operaciones.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon bi bi-gear-wide-connected"></i>
                            <p>{{ __('nav.operations') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">

                            @can('containers.view')
                                <li class="nav-item">
                                    <a href="{{ route('operaciones.contenedores.index') }}"
                                       class="nav-link {{ request()->routeIs('operaciones.contenedores.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-box-seam"></i><p>{{ __('nav.containers') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('rentals.view')
                                <li class="nav-item">
                                    <a href="{{ route('operaciones.rentas.index') }}"
                                       class="nav-link {{ request()->routeIs('operaciones.rentas.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-calendar-check"></i><p>{{ __('nav.rentals') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('trips.view')
                                <li class="nav-item">
                                    <a href="{{ route('operaciones.viajes.index') }}"
                                       class="nav-link {{ request()->routeIs('operaciones.viajes.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-signpost-split"></i><p>{{ __('nav.trips') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('drivers.view')
                                <li class="nav-item">
                                    <a href="{{ route('operaciones.choferes.index') }}"
                                       class="nav-link {{ request()->routeIs('operaciones.choferes.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-person-badge"></i><p>{{ __('nav.drivers') }}</p>
                                    </a>
                                </li>
                            @endcan

                            {{-- Camiones vivía en "Inventario". En Roles está en Operaciones. --}}
                            @can('vehicles.view')
                                <li class="nav-item">
                                    <a href="{{ route('operaciones.camiones.index') }}"
                                       class="nav-link {{ request()->routeIs('operaciones.camiones.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-truck"></i><p>{{ __('nav.vehicles') }}</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                {{-- ═════════════════════════════════════════════
                     COMPRAS
                     Roles: suppliers · purchases · depots · parts

                     Las cuatro entradas llevan a su propia ruta. Todas
                     caen hoy en la pantalla provisional, y eso está
                     bien: dice "en construcción" en vez de llevarte a
                     otro módulo sin avisar.
                ═════════════════════════════════════════════ --}}
                @canany(['suppliers.view', 'purchases.view', 'depots.view', 'parts.view'])
                    <li class="nav-item bloque-compras {{ request()->routeIs('compras.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon bi bi-cart3"></i>
                            <p>{{ __('nav.purchasing') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">

                            @can('suppliers.view')
                                <li class="nav-item">
                                    <a href="{{ route('compras.proveedores.index') }}"
                                       class="nav-link {{ request()->routeIs('compras.proveedores.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-shop"></i><p>{{ __('nav.suppliers') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('purchases.view')
                                <li class="nav-item">
                                    <a href="{{ route('compras.compras.index') }}"
                                       class="nav-link {{ request()->routeIs('compras.compras.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-basket"></i><p>{{ __('nav.purchases') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('depots.view')
                                <li class="nav-item">
                                    <a href="{{ route('compras.depositos.index') }}"
                                       class="nav-link {{ request()->routeIs('compras.depositos.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-building"></i><p>{{ __('nav.depots') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('parts.view')
                                <li class="nav-item">
                                    <a href="{{ route('compras.insumos.index') }}"
                                       class="nav-link {{ request()->routeIs('compras.insumos.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-tools"></i><p>{{ __('nav.parts') }}</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                {{-- ═════════════════════════════════════════════
                     FINANZAS
                     Roles: invoices · payments · expenses · commissions · settlements
                ═════════════════════════════════════════════ --}}
                @canany(['invoices.view', 'payments.view', 'expenses.view', 'commissions.view', 'settlements.view'])
                    <li class="nav-item bloque-finanzas {{ request()->routeIs('finanzas.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon bi bi-cash-coin"></i>
                            <p>{{ __('nav.finance') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">

                            @can('invoices.view')
                                <li class="nav-item">
                                    <a href="{{ route('finanzas.facturacion.index') }}"
                                       class="nav-link {{ request()->routeIs('finanzas.facturacion.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-receipt"></i><p>{{ __('nav.invoicing') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('payments.view')
                                <li class="nav-item">
                                    <a href="{{ route('finanzas.pagos.index') }}"
                                       class="nav-link {{ request()->routeIs('finanzas.pagos.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-wallet2"></i><p>{{ __('nav.payments') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('expenses.view')
                                <li class="nav-item">
                                    <a href="{{ route('finanzas.gastos.index') }}"
                                       class="nav-link {{ request()->routeIs('finanzas.gastos.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-arrow-down-circle"></i><p>{{ __('nav.expenses') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('commissions.view')
                                <li class="nav-item">
                                    <a href="{{ route('finanzas.comisiones.index') }}"
                                       class="nav-link {{ request()->routeIs('finanzas.comisiones.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-percent"></i><p>{{ __('nav.commissions') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('settlements.view')
                                <li class="nav-item">
                                    <a href="{{ route('finanzas.liquidaciones.index') }}"
                                       class="nav-link {{ request()->routeIs('finanzas.liquidaciones.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-clipboard-check"></i><p>{{ __('nav.settlements') }}</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

                {{-- ═════════════════════════════════════════════
                     SISTEMA
                     Roles: reports · catalogs · settings · users

                     Antes se llamaba "Administración" y solo tenía
                     usuarios y roles, con Reportes y Configuración
                     sueltos al final del menú. Ahora es un bloque, como
                     en Roles.
                ═════════════════════════════════════════════ --}}
                @canany(['reports.view', 'catalogs.view', 'settings.view', 'users.view'])
                    <li class="nav-item bloque-sistema {{ request()->routeIs('sistema.*', 'administracion.*', 'reportes.*', 'configuracion.*') ? 'menu-open' : '' }}">
                        <a href="#" class="nav-link">
                            <i class="nav-icon bi bi-sliders"></i>
                            <p>{{ __('nav.system') }} <i class="nav-arrow bi bi-chevron-right"></i></p>
                        </a>
                        <ul class="nav nav-treeview">

                            @can('reports.view')
                                <li class="nav-item">
                                    <a href="{{ route('reportes.index') }}"
                                       class="nav-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-graph-up-arrow"></i><p>{{ __('nav.reports') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('catalogs.view')
                                <li class="nav-item">
                                    <a href="{{ route('sistema.catalogos.index') }}"
                                       class="nav-link {{ request()->routeIs('sistema.catalogos.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-list-columns-reverse"></i><p>{{ __('nav.catalogs') }}</p>
                                    </a>
                                </li>
                            @endcan

                            @can('settings.view')
                                <li class="nav-item">
                                    <a href="{{ route('configuracion.index') }}"
                                       class="nav-link {{ request()->routeIs('configuracion.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-gear"></i><p>{{ __('nav.settings') }}</p>
                                    </a>
                                </li>
                            @endcan

                            {{--
                                Usuarios y Roles comparten el permiso
                                `users.*` a propósito: quien puede crear
                                un usuario decide qué hace ese rol.
                            --}}
                            @can('users.view')
                                <li class="nav-item">
                                    <a href="{{ route('administracion.usuarios.index') }}"
                                       class="nav-link {{ request()->routeIs('administracion.usuarios.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-people"></i><p>{{ __('nav.users') }}</p>
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a href="{{ route('administracion.roles.index') }}"
                                       class="nav-link {{ request()->routeIs('administracion.roles.*') ? 'active' : '' }}">
                                        <i class="nav-icon bi bi-shield-lock"></i><p>{{ __('nav.roles') }}</p>
                                    </a>
                                </li>
                            @endcan

                        </ul>
                    </li>
                @endcanany

            </ul>
        </nav>
    </div>
</aside>
