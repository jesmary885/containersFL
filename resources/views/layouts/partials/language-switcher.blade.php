{{--
    ═══════════════════════════════════════════════════════════════════════
    SELECTOR DE IDIOMA
    ═══════════════════════════════════════════════════════════════════════

    Va en la barra de arriba, al lado del selector de compañía.

    ── POR QUÉ LAS BANDERAS SON TEXTO Y NO IMÁGENES ──

    Porque una bandera no representa un idioma. La bandera de España
    para una empleada venezolana o la de Estados Unidos para un cliente
    de otro país es una decisión que no hace falta tomar. "ES" y "EN"
    no ofenden a nadie y ocupan menos.

    ── CÓMO SE USA ──

    En resources/views/layouts/app.blade.php, dentro del <ul> de la
    barra de navegación superior:

        @include('layouts.partials.language-switcher')
--}}
<li class="nav-item dropdown">

    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
        <i class="bi bi-translate me-1"></i>
        <span class="d-none d-sm-inline">{{ strtoupper(app()->getLocale()) }}</span>
    </a>

    <ul class="dropdown-menu dropdown-menu-end">

        <li>
            <h6 class="dropdown-header">{{ __('common.language') }}</h6>
        </li>

        <li>
            <a class="dropdown-item {{ app()->getLocale() === 'es' ? 'active' : '' }}"
               href="{{ route('locale.switch', 'es') }}">
                <span class="fw-semibold me-2">ES</span>
                {{ __('common.spanish') }}
                @if (app()->getLocale() === 'es')
                    <i class="bi bi-check2 ms-2"></i>
                @endif
            </a>
        </li>

        <li>
            <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}"
               href="{{ route('locale.switch', 'en') }}">
                <span class="fw-semibold me-2">EN</span>
                {{ __('common.english') }}
                @if (app()->getLocale() === 'en')
                    <i class="bi bi-check2 ms-2"></i>
                @endif
            </a>
        </li>

    </ul>
</li>
