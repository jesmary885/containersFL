{{--
    ═══════════════════════════════════════════════════════════════════════
    LA ETIQUETA DE ESTADO
    ═══════════════════════════════════════════════════════════════════════

    Se usa así, desde cualquier pantalla:

        <x-ui.badge :color="$presupuesto->status->color()"
                    :label="$presupuesto->status->label()" />

    ── PARA QUÉ EXISTE ──

    Los 25 enums del sistema ya saben de qué color va cada estado: el
    método color() de EstimateStatus devuelve 'green' para aceptado y
    'red' para rechazado.

    Pero Bootstrap no entiende 'green': entiende 'text-bg-success'. Este
    archivo es el traductor, y está en un solo sitio para que el día que
    se cambie el diseño se cambie una vez.

    Si mañana se agrega un estado nuevo al enum, la etiqueta ya funciona
    sin tocar nada aquí.
--}}

@props([
    'color' => 'gray',   // el que devuelve el método color() del enum
    'label' => '',       // el texto que se ve
    'icon'  => null,     // opcional: 'bi-check-lg', 'bi-clock', ...
])

@php
    /*
     | La traducción. Los nombres de la izquierda son los que usan los
     | enums; los de la derecha, las clases de Bootstrap 5.
     |
     | 'purple' se mapea a 'info' porque Bootstrap no trae morado de
     | fábrica y no vale la pena agregar CSS solo para eso.
     |
     | El default gris cubre cualquier color que aparezca en el futuro:
     | mejor una etiqueta gris que una etiqueta invisible.
     */
    $clases = match ($color) {
        'green'  => 'text-bg-success',
        'yellow' => 'text-bg-warning',
        'red'    => 'text-bg-danger',
        'blue'   => 'text-bg-primary',
        'purple' => 'text-bg-info',
        default  => 'text-bg-secondary',
    };
@endphp

<span {{ $attributes->merge(['class' => "badge rounded-pill {$clases}"]) }}>
    @if ($icon)
        <i class="bi {{ $icon }} me-1"></i>
    @endif

    {{ $label ?: $slot }}
</span>
