{{--
    ═══════════════════════════════════════════════════════════════════════
    EL RESUMEN DE ERRORES
    ═══════════════════════════════════════════════════════════════════════

    Se usa así, en cualquier formulario, justo encima de los botones de
    guardar:

        <x-ui.errores />

    Y si además quieres uno arriba de la página:

        <x-ui.errores id="resumen-errores" titulo="No se pudo guardar." />

    ── PARA QUÉ EXISTE ──

    El problema que resuelve lo describiste tú: le das a Guardar, el campo
    se pone rojo con un signo de exclamación, y no dice nada. El usuario
    ve que algo está mal pero no qué, ni dónde.

    Pasaba por dos motivos distintos:

      1. Los campos tenían la clase is-invalid pero les faltaba el
         <div class="invalid-feedback"> que muestra el texto. Bootstrap
         pinta el rojo y calla.

      2. Cuando sí había un resumen, estaba arriba del todo. Y tú le das
         a Guardar desde abajo, así que nunca lo veías.

    Este componente resuelve el segundo, que es el que importa: pone la
    lista completa de lo que falta AL LADO del botón, y salta al primer
    campo en rojo.

    ── EL SALTO ──

    El componente escucha el evento 'errores-de-validacion'. Para que
    salte, el componente Livewire tiene que dispararlo al fallar:

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('errores-de-validacion');
            throw $e;
        }

    El throw es obligatorio: sin él, Livewire creería que la validación
    pasó y seguiría guardando.
--}}

@props([
    'titulo' => null,   // si se omite, se arma solo con el número de errores
    'id'     => null,   // opcional, para poder saltar hasta aquí
])

{{--
    El escuchador va SIEMPRE, haya errores o no.

    Si estuviera dentro del @if, en la primera pulsación de Guardar el
    elemento todavía no existe cuando llega el evento, y el salto no
    ocurre nunca. Es un detalle chico que hace que la función parezca
    rota de forma intermitente.
--}}
<div x-data
     x-on:errores-de-validacion.window="
        $nextTick(() => {
            const destino = document.querySelector('.is-invalid')
                         || document.querySelector('.resumen-errores');
            destino?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        })
     "></div>

@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'alert alert-danger resumen-errores']) }}
         @if ($id) id="{{ $id }}" @endif
         role="alert">

        <div class="fw-semibold mb-2">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            @if ($titulo)
                {{ $titulo }}
            @else
                Falta {{ $errors->count() }} {{ $errors->count() === 1 ? 'dato' : 'datos' }}:
            @endif
        </div>

        <ul class="mb-0 ps-3 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
