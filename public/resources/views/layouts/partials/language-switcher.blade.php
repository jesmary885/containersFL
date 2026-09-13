{{--
    ═══════════════════════════════════════════════════════════════════════
    SELECTOR DE IDIOMA
    ═══════════════════════════════════════════════════════════════════════

    Va entre la pastilla de la empresa y la del usuario.

    ── POR QUÉ LAS BANDERAS SON TEXTO Y NO IMÁGENES ──

    Porque una bandera no representa un idioma. La bandera de España
    para una empleada venezolana, o la de Estados Unidos para un cliente
    de otro país, es una decisión que no hace falta tomar. "ES" y "EN"
    no ofenden a nadie y ocupan menos.

    ── POR QUÉ ES LA PIEZA MÁS APAGADA DE LA BARRA ──

    Porque es la que menos se toca. Se elige el idioma una vez y no se
    vuelve a mirar en meses. Darle el mismo peso visual que a la empresa
    haría que las dos compitieran, y la que tiene que ganar es la
    empresa.
--}}
<li class="nav-item dropdown">

    <a class="bs-idioma" data-bs-toggle="dropdown" href="#" role="button"
       title="Idioma del sistema">
        <i class="bi bi-translate"></i>
        <span class="d-none d-sm-inline">{{ strtoupper(app()->getLocale()) }}</span>
    </a>

    <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 200px;">

        <li>
            <h6 class="dropdown-header">{{ __('common.language') }}</h6>
        </li>

        <li>
            <a class="dropdown-item d-flex align-items-center gap-2 {{ app()->getLocale() === 'es' ? 'active' : '' }}"
               href="{{ route('locale.switch', 'es') }}">
                <span class="bs-idioma-chip">ES</span>
                <span class="flex-grow-1">{{ __('common.spanish') }}</span>
                @if (app()->getLocale() === 'es')
                    <i class="bi bi-check2"></i>
                @endif
            </a>
        </li>

        <li>
            <a class="dropdown-item d-flex align-items-center gap-2 {{ app()->getLocale() === 'en' ? 'active' : '' }}"
               href="{{ route('locale.switch', 'en') }}">
                <span class="bs-idioma-chip">EN</span>
                <span class="flex-grow-1">{{ __('common.english') }}</span>
                @if (app()->getLocale() === 'en')
                    <i class="bi bi-check2"></i>
                @endif
            </a>
        </li>

    </ul>
</li>
