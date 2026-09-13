<?php

namespace App\Enums;

use App\Models\Concerns\HasOptions;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * CÓMO SE CALCULA UNA COMISIÓN
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Las dos formas, porque el Excel usa las dos.
 *
 * En la hoja COMISIONES VENTAS todos los pagos son montos planos: $200,
 * $300, $1,100, $650, $40. Ninguno se parece a un porcentaje de nada.
 *
 * Pero en la hoja VENTAS hay una comisión de $150.00 sobre una venta de
 * $2,650.00. Eso es 5.66%, que nadie pacta como porcentaje — pero
 * tampoco es un monto redondo, así que probablemente salió de un cálculo
 * y se redondeó.
 *
 * Conclusión: se pactan las dos y a veces se ajustan a mano. Forzar una
 * sola forma obligaría a que alguien hiciera la cuenta en una
 * calculadora y tecleara el resultado, y ahí se pierde el rastro de cómo
 * se llegó a la cifra.
 * ═══════════════════════════════════════════════════════════════════════════
 */
enum CommissionMode: string
{
    use HasOptions;

    /** Un porcentaje de la base. El monto se calcula. */
    case Percent = 'percent';

    /** Un monto pactado. El porcentaje se deduce, solo para referencia. */
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('commissions.mode_percent'),
            self::Fixed   => __('commissions.mode_fixed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Percent => 'blue',
            self::Fixed   => 'indigo',
        };
    }

    /**
     * Calcula el monto de la comisión.
     *
     * En modo fijo devuelve lo pactado tal cual: el porcentaje se ignora
     * a propósito, porque el acuerdo fue el monto.
     */
    public function resolveAmount(float $base, ?float $percent, ?float $fixed): float
    {
        return match ($this) {
            self::Percent => round($base * (float) $percent / 100, 2),
            self::Fixed   => round((float) $fixed, 2),
        };
    }

    /**
     * El porcentaje equivalente, para poder comparar vendedores.
     *
     * Una comisión de $150 sobre $2,650 es 5.66%. El número no se pactó
     * así, pero es el único con el que se puede contestar "¿a quién le
     * estamos pagando más?".
     */
    public function equivalentPercent(float $base, float $amount): ?float
    {
        if ($base <= 0) {
            return null;
        }

        return round($amount / $base * 100, 2);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
