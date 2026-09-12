{{--
    ═══════════════════════════════════════════════════════════════════════
    SELECTOR DE EMPRESA
    ═══════════════════════════════════════════════════════════════════════

    Dos comportamientos según el usuario:

      · Con acceso a UNA empresa: una pastilla fija, sin menú. No hay
        nada que elegir, así que no se le ofrece un desplegable que solo
        tiene una opción y encima deshabilitada.

      · Con acceso a VARIAS: la misma pastilla, pero se abre.

    ── QUÉ CAMBIÓ ──

    Era un enlace gris con una etiquetita de color al lado. Ahora la
    pastilla entera va del color de la empresa.

    No es maquillaje. Desde que el login dejó de preguntar en cuál
    empresa entrar, esta pastilla es lo ÚNICO que dice dónde estás
    parado, y emitir una factura desde la empresa equivocada es el error
    más caro que tiene este sistema.

    Tiene que verse desde la puerta.

    ── POR QUÉ CADA OPCIÓN ES UN FORMULARIO ──

    Es más código que unos enlaces, pero un enlace haría el cambio por
    GET, y cualquier cosa que modifique la sesión tiene que ir por POST
    con su token. Un GET se puede disparar con una imagen escondida en
    un correo.
--}}

@php
    $color = $empresaActual?->brand_color ?: '#334155';
@endphp

@if ($empresasDisponibles->count() <= 1)

    {{-- UNA SOLA EMPRESA: pastilla informativa, sin menú --}}
    <li class="nav-item">
        <span class="bs-empresa bs-empresa-fija"
              style="--bs-empresa-color: {{ $color }}"
              title="{{ $empresaActual?->legal_name }}">

            <span class="bs-empresa-codigo">{{ $empresaActual?->code ?? '—' }}</span>

            <span class="bs-empresa-nombre d-none d-md-inline">
                {{ $empresaActual?->name }}
            </span>
        </span>
    </li>

@else

    {{-- VARIAS EMPRESAS: la misma pastilla, pero se abre --}}
    <li class="nav-item dropdown">

        <a href="#"
           class="bs-empresa"
           style="--bs-empresa-color: {{ $color }}"
           data-bs-toggle="dropdown"
           role="button"
           aria-expanded="false"
           title="Está trabajando en {{ $empresaActual?->legal_name }}. Pulse para cambiar.">

            <span class="bs-empresa-codigo">{{ $empresaActual?->code ?? '—' }}</span>

            <span class="bs-empresa-nombre d-none d-md-inline">
                {{ $empresaActual?->name }}
            </span>

            <i class="bi bi-chevron-down bs-chevron"></i>
        </a>

        <ul class="dropdown-menu dropdown-menu-end shadow bs-menu-empresa" style="min-width: 280px;">

            <li>
                <h6 class="dropdown-header">Cambiar de empresa</h6>
            </li>

            @foreach ($empresasDisponibles as $empresa)
                @php
                    $esLaActual = $empresaActual && $empresa->id === $empresaActual->id;
                @endphp

                <li>
                    <form method="POST" action="{{ route('company.switch') }}" class="m-0">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $empresa->id }}">

                        <button type="submit"
                                class="dropdown-item bs-opcion-empresa {{ $esLaActual ? 'activa' : '' }}"
                                @disabled($esLaActual)>

                            <span class="bs-opcion-chip"
                                  style="background-color: {{ $empresa->brand_color ?: '#334155' }}">
                                {{ $empresa->code }}
                            </span>

                            <span class="bs-opcion-texto">
                                <span class="bs-opcion-nombre">{{ $empresa->name }}</span>
                                <span class="bs-opcion-legal">{{ $empresa->legal_name }}</span>
                            </span>

                            @if ($esLaActual)
                                <i class="bi bi-check-lg text-success"></i>
                            @endif
                        </button>
                    </form>
                </li>
            @endforeach

            <li><hr class="dropdown-divider"></li>

            <li>
                <span class="dropdown-item-text small text-secondary">
                    <i class="bi bi-info-circle me-1"></i>
                    Cada empresa emite sus documentos por separado.
                </span>
            </li>

        </ul>

    </li>

@endif
