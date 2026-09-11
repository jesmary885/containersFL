<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Los ajustes que el sistema consulta.
     *
     * Todos van como GLOBALES (company_id null). Si una compañía necesita
     * un valor distinto, se crea su fila propia desde la pantalla de
     * Configuración y esa pisa al global.
     */
    public function run(): void
    {
        $ajustes = [

            /* ===========================================================
             | FISCAL
             * ======================================================== */

            ['fiscal', 'default_tax_rate', 7.00, 'decimal',
             'Sales tax (%)'],
            // RB-006: 7% sobre el valor del contenedor, nunca sobre el
            // delivery.

            ['fiscal', 'tax_applies_to_cc_fee', false, 'bool',
             '¿El recargo de tarjeta paga sales tax?'],
            // PENDIENTE ROSA. Se deja en false y se cambia en pantalla
            // cuando responda, sin tocar código.

            /* ===========================================================
             | PAGOS
             * ======================================================== */

            ['payments', 'credit_card_fee_percent', 3.50, 'decimal',
             'Recargo por tarjeta (%)'],
            // RB-009: constante 3.5%.

            ['payments', 'cc_fee_base', 'total_with_tax', 'string',
             'Base de cálculo del recargo de tarjeta'],
            // PENDIENTE DENISSE. Valores posibles:
            //   'subtotal'       -> el 3.5% sobre el subtotal
            //   'total_with_tax' -> el 3.5% sobre el total con impuesto
            // El InvoiceCalculator ya lee esta clave.

            /* ===========================================================
             | RENTAS
             * ======================================================== */

            ['rentals', 'due_day', 5, 'int',
             'Día de vencimiento de la renta'],
            // RB-024: vence entre el 1 y el 5.

            ['rentals', 'grace_days', 5, 'int',
             'Días de gracia antes de la mora'],
            // RB-024.

            ['rentals', 'late_fee_amount', 100.00, 'decimal',
             'Mora por contrato ($)'],
            // RB-024: $100 fijos por contrato, sin importar los dias
            // de atraso.

            ['rentals', 'default_months', 1, 'int',
             'Plazo de renta por defecto (meses)'],
            // Con lo que nace una linea de renta en el presupuesto.
            // Editable renglon por renglon: un presupuesto puede llevar
            // un contenedor a 6 meses y otro a 12.

            /* -------------------------------------------------------------
             | RENTA DE YARDA — hoja RENTAS YARDA del Excel
             |
             | El contrato de MODUGO va a $2.00/dia. ⚠️ CONFIRMAR si es la
             | tarifa de lista o un precio pactado con ese cliente: en el
             | Excel no hay ninguna hoja de tarifas de yarda, el numero se
             | teclea en cada contrato.
             * ---------------------------------------------------------- */
            ['rentals', 'default_daily_rate', 2.00, 'decimal',
             'Tarifa de yarda por dia ($)'],
            // Con lo que nace una linea de renta en el presupuesto.
            // Editable renglon por renglon: un presupuesto puede llevar
            // un contenedor a 6 meses y otro a 12.
            // RB-024: $100 fijos por contrato, sin importar los días
            // de atraso.

            /* ===========================================================
             | OPERACIONES
             * ======================================================== */

            ['operations', 'default_rate_per_mile', 3.50, 'decimal',
             'Tarifa por milla ($)'],
            // RB-031: los deliveries se calculan por millas.

            ['operations', 'default_pickup_fee', 150.00, 'decimal',
             'Fee de recogida por defecto ($)'],
            // RB-031: los pickups tienen fee fijo por depósito. Este es
            // el que se usa si el depósito no tiene el suyo.

            ['operations', 'driver_pay_percent', 30.00, 'decimal',
             'Pago al chofer (% del delivery)'],
            // RB-032: pendiente confirmar si es fijo, por chofer o
            // libre. El PricingResolver ya soporta las tres: mira
            // primero el viaje, luego el chofer, y por último esta clave.

            ['operations', 'release_pickup_days', 14, 'int',
             'Plazo de retiro de un release (días)'],
            // RB-018: 14 días.

            /* ===========================================================
             | YARDA
             * ======================================================== */

            ['yard', 'free_storage_days', 2, 'int',
             'Días libres de almacenaje tras la venta'],
            // RB-021: 2 días; desde el 3ro se cobra.

            ['yard', 'daily_storage_fee', 25.00, 'decimal',
             'Almacenaje diario en yarda ($)'],
            // RB-021.

            /* ===========================================================
             | DOCUMENTOS
             * ======================================================== */

            ['documents', 'estimate_valid_days', 3, 'int',
             'Validez del presupuesto (días)'],
            // RB-041: pendiente confirmar si los 3 días son del
            // presupuesto o solo un texto heredado en la plantilla.

            ['documents', 'default_terms', 'Due on receipt', 'string',
             'Términos de pago por defecto'],

            /* ===========================================================
             | NOTIFICACIONES
             * ======================================================== */

            ['notifications', 'reminder_days', [1, 5, 10], 'json',
             'Días de recordatorio tras el vencimiento'],
            // RB-027: frecuencia configurable.

            ['notifications', 'certificate_warning_days', 30, 'int',
             'Aviso previo de vencimiento de certificados (días)'],
            // RB-014: el Annual Resale Certificate vence el 12/31.
        ];

        foreach ($ajustes as [$group, $key, $value, $type, $label]) {
            Setting::updateOrCreate(
                ['company_id' => null, 'group' => $group, 'key' => $key],
                ['value' => $value, 'type' => $type, 'label' => $label],
            );
        }
    
    }
}
