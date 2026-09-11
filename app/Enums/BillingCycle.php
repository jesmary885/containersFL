<?php

namespace App\Enums;

use App\Models\Concerns\HasOptions;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * CADA CUÁNTO SE COBRA UNA RENTA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * `rentals.billing_cycle` existía desde el principio con default 'monthly' y
 * no lo leía nadie. Este enum es lo que lo hace significar algo.
 *
 * Las dos formas salen del Excel que la empresa usa hoy, y son dos hojas
 * distintas porque son dos negocios distintos:
 *
 *   MENSUAL (hoja RENTAS)
 *     El cliente renta un contenedor y se lo lleva. Se cobra por mes, con
 *     el ciclo anclado al día de la entrega (RB-022). Una factura por mes,
 *     cada una con su sales tax.
 *
 *   DIARIA (hoja RENTAS YARDA)
 *     El cliente deja SU contenedor guardado en la yarda. Se cobra por día
 *     que pasa ahí. El contrato no tiene fin previsto: los días corren
 *     hasta que se lo lleva.
 *
 * La diferencia de fondo no es la unidad de tiempo, es quién tiene el
 * contenedor. En la mensual sale de la yarda; en la diaria entra a ella. De
 * ahí que la de yarda tenga cargos de entrada y salida y la mensual no.
 * ═══════════════════════════════════════════════════════════════════════════
 */
enum BillingCycle: string
{
    use HasOptions;

    case Monthly = 'monthly';
    case Daily   = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('rentals.cycle_monthly'),
            self::Daily   => __('rentals.cycle_daily'),
        };
    }

    /** Cómo se lee la tarifa: "$850.00/mes" o "$2.00/día". */
    public function rateSuffix(): string
    {
        return match ($this) {
            self::Monthly => __('rentals.per_month_abbr'),
            self::Daily   => __('rentals.per_day_abbr'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Monthly => 'blue',
            self::Daily   => 'purple',
        };
    }

    public function isDaily(): bool
    {
        return $this === self::Daily;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
