{{--
    SELECTOR DE EMPRESA DEL HEADER

    Dos comportamientos según el usuario:

      - Con acceso a UNA empresa: una etiqueta fija. No hay nada que
        elegir, así que no se le ofrece un menú que solo tiene una
        opción y encima deshabilitada.

      - Con acceso a VARIAS: un menú desplegable.

    Cada opción es un formulario POST propio. Es más código que unos
    enlaces, pero un enlace haría el cambio por GET, y cualquier cosa
    que modifique la sesión tiene que ir por POST con su token.
--}}

@if ($empresasDisponibles->count() <= 1)

    {{-- UNA SOLA EMPRESA: etiqueta informativa --}}
    <li class="nav-item d-flex align-items-center me-2">
        <span
            class="badge rounded-pill px-3 py-2"
            style="background-color: {{ $empresaActual?->brand_color ?: '#334155' }}"
            title="{{ $empresaActual?->legal_name }}"
        >
            {{ $empresaActual?->code ?? '—' }}
        </span>
    </li>

@else

    {{-- VARIAS EMPRESAS: menú desplegable --}}
    <li class="nav-item dropdown me-2">

        <a href="#"
           class="nav-link d-flex align-items-center gap-2"
           data-bs-toggle="dropdown"
           role="button"
           aria-expanded="false">

            {{-- El cuadrito de color: es lo que se ve sin leer --}}
            <span
                class="badge rounded-pill px-3 py-2"
                style="background-color: {{ $empresaActual?->brand_color ?: '#334155' }}"
            >
                {{ $empresaActual?->code ?? '—' }}
            </span>

            <span class="d-none d-md-inline text-body">
                {{ $empresaActual?->name }}
            </span>

            <i class="bi bi-chevron-down small text-secondary"></i>
        </a>

        <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width: 260px;">

            <li>
                <h6 class="dropdown-header">Cambiar de empresa</h6>
            </li>

            @foreach ($empresasDisponibles as $empresa)
                @php
                    // ¿Es la que ya está activa? Se compara con <=> por si
                    // uno de los dos viene como texto y el otro como número.
                    $esLaActual = $empresaActual && $empresa->id === $empresaActual->id;
                @endphp

                <li>
                    <form method="POST" action="{{ route('company.switch') }}" class="m-0">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $empresa->id }}">

                        <button
                            type="submit"
                            class="dropdown-item d-flex align-items-center gap-2 py-2 {{ $esLaActual ? 'active' : '' }}"
                            @disabled($esLaActual)
                        >
                            <span
                                class="badge rounded-pill px-2"
                                style="background-color: {{ $empresa->brand_color ?: '#334155' }}"
                            >
                                {{ $empresa->code }}
                            </span>

                            <span class="flex-grow-1 text-start">
                                <span class="d-block small fw-semibold">{{ $empresa->name }}</span>
                                <span class="d-block text-secondary" style="font-size: .75rem;">
                                    {{ $empresa->legal_name }}
                                </span>
                            </span>

                            @if ($esLaActual)
                                <i class="bi bi-check-lg"></i>
                            @endif
                        </button>
                    </form>
                </li>
            @endforeach

            <li><hr class="dropdown-divider"></li>

            <li>
                <span class="dropdown-item-text small text-secondary">
                    Cada empresa emite sus documentos por separado.
                </span>
            </li>

        </ul>

    </li>

@endif