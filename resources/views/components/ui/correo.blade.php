{{--
    ═══════════════════════════════════════════════════════════════════════
    CAMPO DE CORREO
    ═══════════════════════════════════════════════════════════════════════

    El nombre a la izquierda, el dominio en un desplegable a la derecha.

        <x-ui.correo model="primary_email" :value="$primary_email" />
        <x-ui.correo :model="'contactos.'.$i.'.email'"
                     :value="$contacto['email']" size="sm" />

    ── PARA QUÉ ──

    Porque "@gmail.com" son diez caracteres que alguien teclea cincuenta
    veces al mes, y porque teclearlos cincuenta veces significa
    escribirlos mal una o dos.

    "@gmial.com" pasa la validación de formato sin problema. El correo se
    guarda, la factura se manda y rebota, y nadie se entera hasta que el
    cliente llama preguntando por qué no le llegó nada.

    Un desplegable no se escribe mal.

    ── LA OPCIÓN "OTRO" ──

    Los trece dominios de la lista cubren casi todo, pero las empresas
    tienen el suyo: @homesteadconstruction.com. Al elegir "Otro" aparece
    un campo para escribirlo.

    Y si se edita un cliente cuyo correo ya está en un dominio raro, la
    pantalla lo detecta sola: elige "Otro" y rellena el campo. No hay que
    volver a escribirlo.

    ── LO QUE SE GUARDA ──

    El correo entero de siempre: "carlos@gmail.com". La columna no
    cambió, la validación de Laravel tampoco. Esto es solo una forma más
    rápida de escribir lo mismo.

    La función campoCorreo() vive en layouts/app.blade.php, una sola vez
    para toda la aplicación.
--}}

@props([
    'model',                 // la propiedad de Livewire: 'primary_email'
    'value'   => null,       // lo que hay guardado ahora
    'size'    => null,       // 'sm' para los renglones de contacto
    'invalid' => false,

    // Identificador estable del renglón.
    'campoKey' => null,
])

@php
    $clase       = $size === 'sm' ? 'form-control-sm' : '';
    $claseSelect = $size === 'sm' ? 'form-select-sm' : '';
@endphp

{{--
    ── AQUÍ HABÍA UN x-effect Y BORRABA LO QUE SE ESCRIBÍA ──

    El mismo error que en el teléfono, explicado en detalle allí: el
    efecto leía el valor local para compararlo con el del servidor, así
    que cada letra tecleada lo disparaba, y el efecto respondía
    reescribiendo el campo con lo que había en el servidor —nada—.

    Se quitó. La identidad de cada renglón se resuelve con wire:key.
--}}
<div wire:key="mail-{{ $campoKey ?? $model }}"
     x-data="campoCorreo(@js($value), @js($model), @js(\App\Support\UsPlaces::DOMINIOS_CORREO))">

    <div class="input-group {{ $size === 'sm' ? 'input-group-sm' : '' }}">

        <input type="text"
               autocomplete="off"
               class="form-control {{ $clase }} {{ $invalid ? 'is-invalid' : '' }}"
               placeholder="nombre"
               x-model="usuario"
               x-on:input="alEscribirUsuario()"
               x-on:blur="guardar()">

        <span class="input-group-text px-2">@</span>

        <select class="form-select {{ $claseSelect }} flex-grow-0"
                style="width: 10.5rem;"
                x-model="dominio"
                x-on:change="alCambiarDominio()">
            @foreach (\App\Support\UsPlaces::DOMINIOS_CORREO as $d)
                <option value="{{ $d }}">{{ $d }}</option>
            @endforeach
            <option value="__otro__">Otro…</option>
        </select>

    </div>

    {{--
        El campo del dominio propio solo aparece si hace falta.

        x-cloak evita que se vea un parpadeo del campo antes de que
        Alpine decida si va o no.
    --}}
    <div x-show="dominio === '__otro__'" x-cloak class="mt-1">
        <input type="text"
               autocomplete="off"
               class="form-control {{ $clase }}"
               placeholder="miempresa.com"
               x-model="otroDominio"
               x-on:input="alEscribirDominio()"
               x-on:blur="guardar()">
        <div class="form-text">
            Solo el dominio, sin la arroba.
        </div>
    </div>

    {{-- El correo tal como va a quedar guardado. Se revisa de un vistazo. --}}
    <div class="form-text" x-show="compuesto()" x-cloak>
        <i class="bi bi-envelope me-1"></i><span x-text="compuesto()"></span>
    </div>

</div>
