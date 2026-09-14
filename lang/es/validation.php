<?php

/*
|--------------------------------------------------------------------------
| MENSAJES DE VALIDACIÓN EN ESPAÑOL
|--------------------------------------------------------------------------
|
| ── POR QUÉ HACÍA FALTA ESTE ARCHIVO ──
|
| No existía. El proyecto tenía lang/en/validation.php y nada en español,
| así que cuando el idioma estaba en ES, Laravel no encontraba la
| traducción y caía al inglés.
|
| De ahí salían mensajes a medias como:
|
|     "The número de contenedor field must not be greater than 15 characters"
|     "The year manufactured field must not be greater than 2027"
|
| El nombre del campo sí estaba traducido —eso lo pone cada componente en
| su validationAttributes()— pero la frase que lo envuelve venía del
| archivo en inglés.
|
| Con este archivo, TODOS los mensajes del sistema salen en español sin
| tener que escribirlos a mano pantalla por pantalla.
|
| ── LO QUE SIGUE MANDANDO ──
|
| El messages() de cada componente Livewire gana sobre esto. Es lo
| correcto: aquí va el texto genérico y allí el que explica la regla del
| negocio concreta.
|
*/

return [

    'accepted'             => 'Debe aceptar :attribute.',
    'active_url'           => ':attribute no es una URL válida.',
    'after'                => ':attribute debe ser una fecha posterior a :date.',
    'after_or_equal'       => ':attribute debe ser una fecha posterior o igual a :date.',
    'alpha'                => ':attribute solo puede contener letras.',
    'alpha_dash'           => ':attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num'            => ':attribute solo puede contener letras y números.',
    'array'                => ':attribute debe ser una lista.',
    'before'               => ':attribute debe ser una fecha anterior a :date.',
    'before_or_equal'      => ':attribute debe ser una fecha anterior o igual a :date.',
    'boolean'              => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed'            => 'La confirmación de :attribute no coincide.',
    'current_password'     => 'La contraseña es incorrecta.',
    'date'                 => ':attribute no es una fecha válida.',
    'date_equals'          => ':attribute debe ser una fecha igual a :date.',
    'date_format'          => ':attribute no corresponde al formato :format.',
    'decimal'              => ':attribute debe tener :decimal decimales.',
    'declined'             => ':attribute debe ser rechazado.',
    'different'            => ':attribute y :other deben ser diferentes.',
    'digits'               => ':attribute debe tener :digits dígitos.',
    'digits_between'       => ':attribute debe tener entre :min y :max dígitos.',
    'dimensions'           => 'Las dimensiones de la imagen :attribute no son válidas.',
    'distinct'             => 'El campo :attribute tiene un valor duplicado.',
    'doesnt_end_with'      => ':attribute no puede terminar con: :values.',
    'doesnt_start_with'    => ':attribute no puede comenzar con: :values.',
    'email'                => ':attribute no es un correo válido.',
    'ends_with'            => ':attribute debe terminar con: :values.',
    'enum'                 => 'El valor de :attribute no es válido.',
    'exists'               => 'El valor de :attribute no existe.',
    'file'                 => ':attribute debe ser un archivo.',
    'filled'               => 'El campo :attribute es obligatorio.',
    'image'                => ':attribute debe ser una imagen.',
    'in'                   => 'El valor de :attribute no es válido.',
    'in_array'             => 'El campo :attribute no existe en :other.',
    'integer'              => ':attribute debe ser un número entero.',
    'ip'                   => ':attribute debe ser una dirección IP válida.',
    'ipv4'                 => ':attribute debe ser una dirección IPv4 válida.',
    'ipv6'                 => ':attribute debe ser una dirección IPv6 válida.',
    'json'                 => ':attribute debe ser una cadena JSON válida.',
    'lowercase'            => ':attribute debe ir en minúsculas.',
    'mac_address'          => ':attribute debe ser una dirección MAC válida.',
    'missing'              => 'El campo :attribute debe estar ausente.',
    'multiple_of'          => ':attribute debe ser múltiplo de :value.',
    'not_in'               => 'El valor de :attribute no es válido.',
    'not_regex'            => 'El formato de :attribute no es válido.',
    'numeric'              => ':attribute debe ser un número.',
    'present'              => 'El campo :attribute debe estar presente.',
    'prohibited'           => 'El campo :attribute está prohibido.',
    'regex'                => 'El formato de :attribute no es válido.',
    'required'             => 'El campo :attribute es obligatorio.',
    'required_if'          => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_unless'      => 'El campo :attribute es obligatorio salvo que :other esté en :values.',
    'required_with'        => 'El campo :attribute es obligatorio cuando :values está presente.',
    'required_with_all'    => 'El campo :attribute es obligatorio cuando :values están presentes.',
    'required_without'     => 'El campo :attribute es obligatorio cuando :values no está presente.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando ninguno de :values está presente.',
    'same'                 => ':attribute y :other deben coincidir.',
    'starts_with'          => ':attribute debe comenzar con: :values.',
    'string'               => ':attribute debe ser texto.',
    'timezone'             => ':attribute debe ser una zona horaria válida.',
    'unique'               => 'El valor de :attribute ya está registrado.',
    'uploaded'             => 'No se pudo subir :attribute.',
    'uppercase'            => ':attribute debe ir en mayúsculas.',
    'url'                  => ':attribute debe ser una URL válida.',
    'uuid'                 => ':attribute debe ser un UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | LAS REGLAS QUE CAMBIAN SEGÚN EL TIPO DE DATO
    |--------------------------------------------------------------------------
    |
    | max, min, size, between y gt/lt dicen cosas distintas según lo que
    | se esté midiendo. No es lo mismo "no puede pasar de 15" hablando de
    | caracteres que hablando de dólares o de archivos.
    |
    */

    'max' => [
        'array'   => ':attribute no puede tener más de :max elementos.',
        'file'    => ':attribute no puede pesar más de :max kilobytes.',
        'numeric' => ':attribute no puede ser mayor que :max.',
        'string'  => ':attribute no puede tener más de :max caracteres.',
    ],

    'min' => [
        'array'   => ':attribute debe tener al menos :min elementos.',
        'file'    => ':attribute debe pesar al menos :min kilobytes.',
        'numeric' => ':attribute debe ser al menos :min.',
        'string'  => ':attribute debe tener al menos :min caracteres.',
    ],

    'size' => [
        'array'   => ':attribute debe tener :size elementos.',
        'file'    => ':attribute debe pesar :size kilobytes.',
        'numeric' => ':attribute debe ser :size.',
        'string'  => ':attribute debe tener :size caracteres.',
    ],

    'between' => [
        'array'   => ':attribute debe tener entre :min y :max elementos.',
        'file'    => ':attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => ':attribute debe estar entre :min y :max.',
        'string'  => ':attribute debe tener entre :min y :max caracteres.',
    ],

    'gt' => [
        'array'   => ':attribute debe tener más de :value elementos.',
        'file'    => ':attribute debe pesar más de :value kilobytes.',
        'numeric' => ':attribute debe ser mayor que :value.',
        'string'  => ':attribute debe tener más de :value caracteres.',
    ],

    'gte' => [
        'array'   => ':attribute debe tener :value elementos o más.',
        'file'    => ':attribute debe pesar :value kilobytes o más.',
        'numeric' => ':attribute debe ser mayor o igual que :value.',
        'string'  => ':attribute debe tener :value caracteres o más.',
    ],

    'lt' => [
        'array'   => ':attribute debe tener menos de :value elementos.',
        'file'    => ':attribute debe pesar menos de :value kilobytes.',
        'numeric' => ':attribute debe ser menor que :value.',
        'string'  => ':attribute debe tener menos de :value caracteres.',
    ],

    'lte' => [
        'array'   => ':attribute no debe tener más de :value elementos.',
        'file'    => ':attribute debe pesar :value kilobytes o menos.',
        'numeric' => ':attribute debe ser menor o igual que :value.',
        'string'  => ':attribute debe tener :value caracteres o menos.',
    ],

    'password' => [
        'letters'       => 'La contraseña debe contener al menos una letra.',
        'mixed'         => 'La contraseña debe contener al menos una mayúscula y una minúscula.',
        'numbers'       => 'La contraseña debe contener al menos un número.',
        'symbols'       => 'La contraseña debe contener al menos un símbolo.',
        'uncompromised' => 'Esta contraseña apareció en una filtración de datos. Elija otra.',
    ],

    /*
    |--------------------------------------------------------------------------
    | MENSAJES A MEDIDA
    |--------------------------------------------------------------------------
    |
    | Para reglas que, dichas en genérico, no se entienden.
    |
    */

    'custom' => [

        /*
         | El año de fabricación se valida con max:{año actual + 1}. En
         | genérico salía "no puede ser mayor que 2027", que es cierto
         | pero no explica nada.
         */
        'year_manufactured' => [
            'max' => 'El año de fabricación no puede ser posterior al año que viene.',
            'min' => 'El año de fabricación parece demasiado antiguo. Revíselo.',
        ],
    ],

    'attributes' => [],

];
