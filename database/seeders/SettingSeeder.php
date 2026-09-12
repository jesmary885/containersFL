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

            /* -------------------------------------------------------------
             | LOS DOS DIAS DE GRACIA DE LA YARDA
             |
             | Levantamiento del 8 de agosto: "Cobro por permanencia del
             | contenedor en el patio despues de vendido. Se otorgan 2 dias
             | de gracia; a partir del tercer dia se cobra el espacio. Se
             | implemento el año pasado porque quedaban contenedores hasta
             | dos semanas sin ser retirados."
             |
             | Confirmado el 14 de agosto: "los clientes tienen un plazo de
             | dos dias para retirar los contenedores adquiridos. A partir
             | del tercer dia se generan tarifas de almacenamiento."
             |
             | El cobro NO empieza el dia de la venta: empieza al tercero.
             * ---------------------------------------------------------- */
            ['rentals', 'yard_grace_days', 2, 'int',
             'Dias de gracia antes de cobrar yarda'],

            /* -------------------------------------------------------------
             | LOS AVISOS DE MORA
             |
             | Levantamiento del 14 de agosto: los avisos salen entre el dia
             | 5 y el 10 despues del vencimiento, uno cada dos dias, a TODOS
             | los contactos del cliente (telefonos y correos).
             |
             | Erik prometio en esa reunion que la frecuencia seria
             | administrable desde el sistema "sin necesidad de solicitar
             | cambios externos a los desarrolladores". De ahi que sean
             | ajustes y no numeros escritos en el codigo.
             * ---------------------------------------------------------- */
            ['notifications', 'dunning_start_day', 5, 'int',
             'Primer aviso de mora: dias tras el vencimiento'],

            ['notifications', 'dunning_end_day', 10, 'int',
             'Ultimo aviso de mora: dias tras el vencimiento'],

            ['notifications', 'dunning_every_days', 2, 'int',
             'Cada cuantos dias se repite el aviso'],

            ['notifications', 'dunning_all_contacts', true, 'bool',
             'Avisar a todos los telefonos y correos del cliente'],

            /* -------------------------------------------------------------
             | EL PLAZO DE RETIRO DE UN RELEASE
             |
             | Levantamiento del 14 de agosto: "los releases son compras
             | masivas de contenedores con un plazo de retiro de 14 dias".
             | Pasado el plazo, el deposito cobra tarifas diarias que se
             | registran como gasto.
             * ---------------------------------------------------------- */
            ['purchases', 'release_pickup_days', 14, 'int',
             'Dias para retirar los contenedores de un release'],

            /* -------------------------------------------------------------
             | COMISIONES DE VENTA
             |
             | Las dos formas, porque el Excel usa las dos. En la hoja
             | COMISIONES VENTAS todos los pagos son montos planos ($200,
             | $300, $1,100, $650, $40); en la hoja VENTAS hay una
             | comision de $150 sobre $2,650, que es 5.66% — un numero que
             | nadie pacta como porcentaje pero que tampoco es redondo,
             | asi que salio de un calculo y se ajusto.
             |
             | default_mode es con lo que NACE la factura. Se cambia
             | factura por factura.
             |
             | ⚠️ CONFIRMAR default_percent. Puse 5% porque es lo mas
             | comun en el sector y porque 5.66% de la hoja VENTAS anda
             | cerca, pero en el Excel no hay ninguna hoja de tarifas de
             | comision: el numero se teclea en cada venta.
             * ---------------------------------------------------------- */
            ['commissions', 'default_mode', 'percent', 'string',
             'Como nace la comision: percent | fixed'],

            ['commissions', 'default_percent', 5.00, 'decimal',
             'Porcentaje de comision por defecto (%)'],

            ['commissions', 'default_fixed', 0.00, 'decimal',
             'Monto fijo de comision por defecto ($)'],
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
