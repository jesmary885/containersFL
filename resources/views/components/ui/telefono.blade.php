{{--
    ═══════════════════════════════════════════════════════════════════════
    CAMPO DE TELÉFONO
    ═══════════════════════════════════════════════════════════════════════

    Código de país a la izquierda, número a la derecha, y el número se
    formatea solo mientras se escribe.

        <x-ui.telefono model="primary_phone" :value="$primary_phone" />
        <x-ui.telefono :model="'contactos.'.$i.'.phone'"
                       :value="$contacto['phone']" size="sm" />

    ── QUÉ PROBLEMA RESUELVE ──

    El campo aceptaba letras. Alguien escribía "llamar al 305 555 1234
    después de las 5" y el sistema lo guardaba tal cual. Después el aviso
    automático de cobranza intenta mandar un mensaje a eso y no llega a
    ninguna parte, sin dar error.

    Ahora el campo descarta todo lo que no sea un dígito, en el momento.
    No hay que corregir a nadie después.

    ── EL FORMATO ──

    Con +1 —que es el 95% de los casos— el número se va acomodando solo:

        3055551234   →   (305) 555-1234

    No es un adorno. Un número formateado se revisa de un vistazo; una
    tira de diez dígitos seguidos hay que contarla con el dedo para saber
    si falta uno.

    Con otros códigos de país se deja el número en grupos de tres, porque
    cada país tiene su propio formato y adivinarlo daría más errores que
    los que evita.

    ── LO QUE SE GUARDA ──

    El texto completo, con su código: "+1 (305) 555-1234". Así el
    historial no depende de acordarse de que lo de antes era todo de
    Florida.

    La función campoTelefono() vive en layouts/app.blade.php, una sola
    vez para toda la aplicación.
--}}

@props([
    'model',                    // la propiedad de Livewire: 'primary_phone'
    'value'      => null,       // lo que hay guardado ahora
    'size'       => null,       // 'sm' para los renglones de contacto
    'placeholder' => '(305) 555-1234',
    'invalid'    => false,

    // Identificador estable del renglón. Ver el comentario de abajo.
    'campoKey'   => null,
])

@php
    $clase = $size === 'sm' ? 'form-control-sm' : '';
    $claseSelect = $size === 'sm' ? 'form-select-sm' : '';
@endphp

{{--
    ── AQUÍ HABÍA UN x-effect Y ERA UN ERROR GRAVE ──

    Lo puse para que el campo se resincronizara si el servidor mandaba
    otro valor. Hacía justo lo contrario: BORRABA LO QUE SE ESTABA
    ESCRIBIENDO, letra por letra.

    Por qué. Un x-effect se vuelve a ejecutar cada vez que cambia
    CUALQUIER cosa que lee por dentro. El mío leía el número local para
    compararlo con el del servidor, así que el número local pasó a ser
    una de sus dependencias.

    Entonces: tecleas un 3 → cambia el número local → se dispara el
    efecto → compara "3" contra lo del servidor, que sigue vacío porque
    todavía no has salido del campo → decide que están desincronizados →
    reescribe el campo con lo del servidor, o sea, con nada.

    Un bucle perfecto de borrarse solo. Y como nunca llegaba a haber
    número, al guardar no se guardaba ningún teléfono.

    El problema que quería resolver —que al borrar un contacto del medio
    los de abajo suban y el campo muestre lo del contacto borrado— se
    resuelve donde tocaba: con un wire:key estable en cada renglón. Ver
    el uid en Customers\Form.php.
--}}
<div wire:key="tel-{{ $campoKey ?? $model }}"
     x-data="campoTelefono(@js($value), @js($model))"
     class="input-group {{ $size === 'sm' ? 'input-group-sm' : '' }}">

    {{--
        El selector de país va primero y angosto.

        Se le pone un ancho fijo para que el campo del número no cambie
        de tamaño al pasar de "+1" a "+1809": un campo que se encoge
        mientras escribes es de las cosas que molestan sin que sepas por
        qué.
    --}}
    <select class="form-select {{ $claseSelect }} flex-grow-0"
            style="width: 7.5rem;"
            x-model="codigo"
            x-on:change="alCambiarPais()">
        @foreach (\App\Support\UsPlaces::PAISES_TELEFONO as $p)
            <option value="{{ $p['codigo'] }}" title="{{ $p['pais'] }}">
                {{ $p['codigo'] }} · {{ $p['iso'] }}
            </option>
        @endforeach
    </select>

    <input type="tel"
           inputmode="numeric"
           autocomplete="tel-national"
           class="form-control {{ $clase }} {{ $invalid ? 'is-invalid' : '' }}"
           placeholder="{{ $placeholder }}"
           x-model="numero"
           x-on:input="alEscribir($event)"
           x-on:blur="guardar()">

</div>
