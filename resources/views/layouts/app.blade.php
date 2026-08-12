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

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">

        {{-- NAVBAR SUPERIOR --}}
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto">
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