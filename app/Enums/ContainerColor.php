<?php

namespace App\Enums;

use App\Models\Concerns\HasOptions;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL COLOR DE LA UNIDAD
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DÓNDE SALE ──
 *
 * Reunión del 16 de septiembre. Denisse pidió cambiar el campo "material"
 * por "color": el material no le sirve para nada operativo, y el color sí
 * es lo primero que se ve al caminar por la yarda.
 *
 * Los tres que nombró son amarillo, gris y azul. Los demás están porque
 * un inventario real siempre trae excepciones, y es preferible que haya
 * una opción a que alguien escriba el color en las notas.
 *
 * ── POR QUÉ UN ENUM Y NO UNA TABLA ──
 *
 * Porque reemplaza a "material", que tampoco era tabla. Los catálogos que
 * el cliente administra (tipo, medida, condición, calidad) sí tienen tabla
 * propia porque cambian con el negocio. La lista de colores no cambia: los
 * contenedores son de los colores que son.
 *
 * Si mañana hace falta uno más, se agrega un case acá y aparece solo en
 * el formulario y en el filtro. No hace falta migración.
 * ═══════════════════════════════════════════════════════════════════════════
 */
enum ContainerColor: string
{
    use HasOptions;

    case Yellow = 'yellow';
    case Gray   = 'gray';
    case Blue   = 'blue';
    case Red    = 'red';
    case Green  = 'green';
    case White  = 'white';
    case Black  = 'black';
    case Brown  = 'brown';
    case Other  = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Yellow => __('containers.color_yellow'),
            self::Gray   => __('containers.color_gray'),
            self::Blue   => __('containers.color_blue'),
            self::Red    => __('containers.color_red'),
            self::Green  => __('containers.color_green'),
            self::White  => __('containers.color_white'),
            self::Black  => __('containers.color_black'),
            self::Brown  => __('containers.color_brown'),
            self::Other  => __('containers.color_other'),
        };
    }

    /**
     * El color del badge en pantalla.
     *
     * Se elige el tono del badge que más se parece al color real de la
     * unidad, para que la lista se lea de un vistazo. Blanco y negro no
     * tienen badge propio y caen en gris, que es lo que hay.
     */
    public function color(): string
    {
        return match ($this) {
            self::Yellow => 'yellow',
            self::Gray   => 'gray',
            self::Blue   => 'blue',
            self::Red    => 'red',
            self::Green  => 'green',
            self::White  => 'gray',
            self::Black  => 'gray',
            self::Brown  => 'orange',
            self::Other  => 'gray',
        };
    }

    /**
     * El código hexadecimal, para pintar el puntito de la lista.
     *
     * No se usa el badge para esto: un badge de texto ocupa espacio y la
     * pantalla de inventario ya tiene seis columnas. Un círculo de color
     * al lado del número dice lo mismo en 12 píxeles.
     */
    public function hex(): string
    {
        return match ($this) {
            self::Yellow => '#EAB308',
            self::Gray   => '#9CA3AF',
            self::Blue   => '#2563EB',
            self::Red    => '#DC2626',
            self::Green  => '#16A34A',
            self::White  => '#F3F4F6',
            self::Black  => '#1F2937',
            self::Brown  => '#92400E',
            self::Other  => '#D1D5DB',
        };
    }
}
