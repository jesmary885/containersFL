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

            {{-- NAVBAR SUPERIOR --}}
            {{--
            La línea de color de abajo es la protección más barata contra el
            error más caro de este sistema: emitir una factura desde la empresa
            equivocada (RB-001).

            Va como borde y no como franja aparte para no pelearse con el
            layout fijo de AdminLTE: un div extra arriba del header se le mete
            debajo y no se ve.

            El color sale de companies.brand_color. El gris del ?: es el
            respaldo por si una empresa todavía no lo tiene configurado; sin
            él la barra saldría sin borde y parecería que la página cargó mal.
        --}}
        <nav class="app-header navbar navbar-expand bg-body"
            style="border-bottom: 3px solid {{ $empresaActual?->brand_color ?: '#334155' }}">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto">
                    {{-- Selector de empresa. Va ANTES del menú de usuario a propósito:
                        es lo primero que se mira al entrar. --}}
                    @include('layouts.partials.company-switcher')

                    {{-- Selector de idioma. Entre la empresa y el menú de
                         usuario: los tres son "quién soy y dónde estoy". --}}
                    @include('layouts.partials.language-switcher')

                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                            <li class="user-footer">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-default btn-flat w-100">
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
@livewireScripts
</body>

</html>