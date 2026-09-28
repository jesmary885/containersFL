<?php

namespace App\Enums;

use App\Models\Concerns\HasOptions;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * SOBRE QUÉ SE CALCULA LA COMISIÓN
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DÓNDE SALE ──
 *
 * Reunión del 16 de septiembre. Denisse explicó que el vendedor principal
 * recibe un porcentaje "aplicado específicamente en unidades de venta
 * directa". Esas cuatro palabras son las que obligan a distinguir.
 *
 * ── LA DIFERENCIA, EN DINERO ──
 *
 * Una venta de $2,650 que incluye $650 de delivery, al 5%:
 *
 *   Toda la venta      $132.50
 *   Solo contenedores  $100.00
 *
 * $32.50 por venta. Con veinte ventas al mes son $650 que alguien paga de
 * más, y nadie lo nota porque el número sale calculado y parece correcto.
 *
 * ── CUÁL ES EL CORRECTO ──
 *
 * Depende de lo pactado con cada vendedor, y por eso es un campo y no una
 * regla fija. El sistema propone el de la ficha del vendedor y deja que
 * se cambie en cada factura.
 *
 * Lo que NO entra en ninguno de los dos, nunca: el sales tax y el recargo
 * de tarjeta. El tax es dinero del estado de Florida que solo pasa por la
 * cuenta, y el recargo es lo que cobra la procesadora. Comisionar sobre
 * eso sería pagarle al vendedor un porcentaje de algo que la empresa no
 * ganó.
 * ═══════════════════════════════════════════════════════════════════════════
 */
enum CommissionBase: string
{
    use HasOptions;

    /** Subtotal menos descuento. Incluye el transporte. */
    case Subtotal = 'subtotal';

    /** Solo los renglones de contenedor. Deja fuera delivery y servicios. */
    case Containers = 'containers';

    public function label(): string
    {
        return match ($this) {
            self::Subtotal   => __('commissions.base_subtotal'),
            self::Containers => __('commissions.base_containers'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Subtotal   => __('commissions.base_subtotal_hint'),
            self::Containers => __('commissions.base_containers_hint'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Subtotal   => 'blue',
            self::Containers => 'indigo',
        };
    }
}
