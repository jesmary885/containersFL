<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
        {{-- <a href="{{ route('dashboard') }}" class="brand-link">
            <span class="brand-text fw-light">Containers FL</span>
        </a> --}}

         <a href="{{ route('dashboard') }}" class=" brand-link logo-container">

            <img
                src="{{ Storage::url('logo/logo-Photoroom.png') }}"
                alt="Containers FL"
                class="logo-containers"
            >

        </a>

    </div>

    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu">

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-speedometer"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                {{-- COMERCIAL --}}
                <li class="nav-item {{ request()->routeIs('comercial.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-briefcase"></i>
                        <p>Comercial <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('comercial.clientes.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Clientes</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('comercial.presupuestos.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Presupuestos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('comercial.ventas.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Ventas</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- OPERACIONES --}}
                <li class="nav-item {{ request()->routeIs('operaciones.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-gear-wide-connected"></i>
                        <p>Operaciones <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('operaciones.contenedores.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Contenedores</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.rentas.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Rentas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.viajes.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Viajes / Delivery</p>
                            </a>
                        </li>
                  
                        <li class="nav-item">
                            <a href="{{ route('operaciones.choferes.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Choferes</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{--    COMPRAS --}}
                <li class="nav-item {{ request()->routeIs('operaciones.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">

                        <i class="nav-icon bi bi-cart3"></i>
             
                        <p>Compras <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('operaciones.contenedores.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Proveedores</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.rentas.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Compras</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('operaciones.viajes.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Releases</p>
                            </a>
                        </li>
                        
                    </ul>
                </li>

                {{-- INVENTARIO --}}
                <li class="nav-item {{ request()->routeIs('inventario.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-box-seam"></i>
                        <p>Inventario <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('inventario.insumos.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Insumos, piezas y partes</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('inventario.camiones.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Camiones</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- FINANZAS --}}
                <li class="nav-item {{ request()->routeIs('finanzas.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-cash-coin"></i>
                        <p>Finanzas <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('finanzas.facturacion.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Facturación</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('finanzas.pagos.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Pagos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('finanzas.gastos.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Gastos</p>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- ADMINISTRACIÓN --}}
                <li class="nav-item {{ request()->routeIs('administracion.*') ? 'menu-open' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-shield-lock"></i>
                        <p>Administración <i class="nav-arrow bi bi-chevron-right"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="{{ route('administracion.usuarios.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Usuarios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('administracion.roles.index') }}" class="nav-link">
                                <i class="nav-icon bi bi-circle"></i><p>Roles y permisos</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="{{ route('reportes.index') }}" class="nav-link">
                        <i class="nav-icon bi bi-graph-up"></i>
                        <p>Reportes</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('configuracion.index') }}" class="nav-link">
                        <i class="nav-icon bi bi-sliders"></i>
                        <p>Configuración</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>