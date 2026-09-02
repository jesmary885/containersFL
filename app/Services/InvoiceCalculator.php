<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Estimate;
use App\Models\Invoice;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LA CALCULADORA DE DOCUMENTOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Este archivo es el único lugar de todo el sistema donde se hacen las
 * cuentas de una factura o de un presupuesto. Ni en la pantalla, ni en
 * el PDF, ni en un reporte: aquí.
 *
 * ── POR QUÉ EN UN SOLO SITIO ──
 *
 * Si la suma se escribe en tres lugares distintos, tarde o temprano uno
 * de los tres se queda viejo. Y el día que eso pasa, la pantalla muestra
 * $2,750, el PDF que recibe el cliente dice $2,745 y el reporte de
 * impuestos dice otra cosa. No hay forma de saber cuál es la buena.
 *
 * Teniéndola aquí, cualquier cambio de regla se hace una vez y todo el
 * sistema queda igual de una sola pasada.
 *
 * ── QUÉ CALCULA Y QUÉ NO TOCA ──
 *
 * CALCULA (los deriva de las líneas y de las reglas):
 *      subtotal, taxable_base, tax_amount, credit_card_fee,
 *      total y balance_due
 *
 * NO TOCA (son decisiones de quien captura, no del sistema):
 *      tax_rate                 el % de impuesto que se le aplicó
 *      tax_exempt               si el cliente está exento
 *      discount_amount          el descuento que le dieron
 *      credit_card_fee_percent  el % de recargo (0 si no paga con tarjeta)
 *      deposit_applied          el anticipo que se le descuenta
 *      amount_paid              lo que ya pagó (lo mantiene Payment::applyTo)
 *
 * Si esta clase sobrescribiera esos campos, editar una factura sería
 * imposible: el usuario pone 5% de impuesto, guarda, y el sistema se lo
 * cambia de vuelta a 7%.
 *
 * ── EL ORDEN DE LA CUENTA ──
 *
 *      subtotal            suma de TODAS las líneas
 *    − discount_amount     el descuento
 *      ─────────────────
 *      taxable_base        SOLO las líneas gravables, con el descuento
 *                          repartido en proporción
 *    × tax_rate            = tax_amount
 *    + credit_card_fee     el 3.5% si paga con tarjeta (RB-009)
 *    − deposit_applied     el anticipo que ya entregó
 *      ─────────────────
 *      total
 *    − amount_paid         lo cobrado hasta hoy
 *      ─────────────────
 *      balance_due         lo que falta
 *
 * ── LA IDEA CLAVE: taxable_base ──
 *
 * Es la columna que hace funcionar todo el negocio de FLCHR.
 *
 * RB-007 dice que al cliente se le muestra UN precio consolidado:
 * "Contenedor 40HC entregado ......... $2,750".
 *
 * RB-006 dice que el 7% se cobra SOLO sobre el contenedor, nunca sobre
 * el delivery.
 *
 * Las dos cosas a la vez solo son posibles si por dentro la factura
 * tiene dos líneas separadas —contenedor $2,400 gravable, delivery $350
 * no gravable— y al imprimirla se suman en un renglón. Eso se logra con
 * la columna bundle_key de las líneas, y el impuesto se calcula sobre
 * taxable_base, que en este ejemplo vale 2,400 y no 2,750.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */
class InvoiceCalculator
{
        /* =====================================================================
     | 1 · PARA DOCUMENTOS GUARDADOS
     * ================================================================== */

    /**
     * Recalcula todos los montos del documento y se los asigna.
     *
     * OJO: asigna pero NO guarda. Guardar es responsabilidad de quien
     * llama. Así, recalculate() puede decidir si guarda o solo quiere ver
     * el resultado para mostrarlo mientras el usuario todavía escribe.
     *
     * Acepta Invoice o Estimate porque las dos tablas tienen las mismas
     * columnas de montos, en el mismo orden. Eso fue a propósito desde la
     * migración: convertir un presupuesto en factura es copiar valores uno
     * a uno, sin traducir nada.
     *
     * ── LO QUE ESTA CLASE NO TOCA NUNCA ──
     *
     *   tax_rate                 el % de impuesto que se le aplicó
     *   tax_exempt               si el cliente está exento
     *   discount_amount          el descuento que le dieron
     *   credit_card_fee_percent  el % de recargo
     *   deposit_applied          el anticipo
     *   amount_paid              lo cobrado (lo mantiene Payment::applyTo)
     *
     * Esos son decisiones de quien captura, no del sistema. Si la
     * calculadora los sobrescribiera, editar una factura sería imposible:
     * el usuario pone 5% de impuesto, guarda, y el sistema se lo cambia
     * de vuelta a 7%.
     */
    public function apply(Invoice|Estimate $documento): Invoice|Estimate
    {
        /* -----------------------------------------------------------------
         | Las líneas se convierten a un arreglo simple.
         |
         | El motor de cálculo no sabe nada de Eloquent a propósito: solo
         | entiende de importes y de si pagan impuesto o no. Eso es lo que
         | permite que el mismo motor sirva para la pantalla de captura,
         | donde las líneas todavía no existen en la base.
         * -------------------------------------------------------------- */
        $lineas = $documento->items
            ->map(fn ($linea) => [
                'amount'  => (float) $linea->amount,
                'taxable' => (bool) $linea->taxable,
            ])
            ->all();

        $r = $this->calcular(
            lineas:            $lineas,
            company:           $documento->company,
            descuento:         (float) $documento->discount_amount,
            tasaImpuesto:      (float) $documento->tax_rate,
            exento:            (bool) $documento->tax_exempt,
            porcentajeTarjeta: (float) $documento->credit_card_fee_percent,

            // Solo las facturas descuentan anticipos. Un presupuesto no,
            // porque todavía no ha entrado dinero.
            anticipo: $documento instanceof Invoice
                ? (float) $documento->deposit_applied
                : 0.0,
        );

        $documento->subtotal        = $r['subtotal'];
        $documento->discount_amount = $r['discount_amount'];
        $documento->taxable_base    = $r['taxable_base'];
        $documento->tax_amount      = $r['tax_amount'];
        $documento->credit_card_fee = $r['credit_card_fee'];
        $documento->total           = $r['total'];

        /* -----------------------------------------------------------------
         | EL SALDO, solo en facturas.
         |
         | amount_paid NO se calcula aquí: lo mantiene Payment::applyTo()
         | cada vez que se aplica un cobro. Acá solo se lee para restarlo.
         | Si se recalculara, un recálculo de la factura borraría los pagos
         | ya registrados.
         * -------------------------------------------------------------- */
        if ($documento instanceof Invoice) {
            $documento->balance_due = round(
                $r['total'] - (float) $documento->amount_paid,
                2,
            );
        }

        return $documento;
    }

    /* =====================================================================
     | 2 · PARA LA PANTALLA DE CAPTURA
     * ================================================================== */

    /**
     * Los mismos totales, pero sin base de datos de por medio.
     *
     * Se le pasa una lista de líneas como arreglo de PHP y devuelve un
     * arreglo con los totales. Nada se guarda.
     *
     * Cada línea necesita solo dos datos:
     *
     *     ['amount' => 2400.00, 'taxable' => true]
     *
     * Así la pantalla de presupuestos puede mostrar el total actualizado
     * mientras el usuario todavía está escribiendo, usando exactamente la
     * misma aritmética que se va a guardar después. Sin esto habría que
     * escribir la suma dos veces —una en la pantalla y otra aquí— y sería
     * cuestión de tiempo que dieran resultados distintos.
     *
     * $opciones acepta:
     *   company            (Company)  para leer la configuración
     *   descuento          (float)
     *   tasa_impuesto      (float)    el % de sales tax
     *   exento             (bool)
     *   porcentaje_tarjeta (float)
     *   anticipo           (float)
     */
    public function preview(array $lineas, array $opciones = []): array
    {
        return $this->calcular(
            lineas:            $lineas,
            company:           $opciones['company'] ?? null,
            descuento:         (float) ($opciones['descuento'] ?? 0),
            tasaImpuesto:      (float) ($opciones['tasa_impuesto'] ?? 0),
            exento:            (bool) ($opciones['exento'] ?? false),
            porcentajeTarjeta: (float) ($opciones['porcentaje_tarjeta'] ?? 0),
            anticipo:          (float) ($opciones['anticipo'] ?? 0),
        );
    }

    /* =====================================================================
     | 3 · EL MOTOR
     |
     | Aquí está la cuenta de verdad. Es el único método que hace
     | aritmética en todo el sistema.
     * ================================================================== */

    protected function calcular(
        array $lineas,
        ?Company $company,
        float $descuento,
        float $tasaImpuesto,
        bool $exento,
        float $porcentajeTarjeta,
        float $anticipo,
    ): array {

        /* -----------------------------------------------------------------
         | PASO 1 · EL SUBTOTAL
         |
         | La suma de todas las líneas, gravables y no gravables.
         * -------------------------------------------------------------- */
        $subtotal = 0.0;
        $sumaGravable = 0.0;

        foreach ($lineas as $linea) {
            $importe = round((float) ($linea['amount'] ?? 0), 2);

            $subtotal += $importe;

            if (! empty($linea['taxable'])) {
                $sumaGravable += $importe;
            }
        }

        $subtotal     = round($subtotal, 2);
        $sumaGravable = round($sumaGravable, 2);

        /* -----------------------------------------------------------------
         | PASO 2 · EL DESCUENTO
         |
         | Se lee tal como lo escribió el usuario, pero con dos topes:
         |
         |   - No puede ser negativo (sería un recargo escondido).
         |   - No puede pasar del subtotal (regalar el contenedor y
         |     encima deber dinero).
         |
         | Los topes existen porque el campo es editable a mano y un dedo
         | torcido no debería poder producir un documento absurdo.
         * -------------------------------------------------------------- */
        $descuento = round($descuento, 2);
        $descuento = max(0, min($descuento, $subtotal));

        /* -----------------------------------------------------------------
         | PASO 3 · LA BASE GRAVABLE
         |
         | Se suman solo las líneas marcadas como gravables, y se les
         | descuenta LA PARTE DEL DESCUENTO QUE LES TOCA.
         |
         | ¿Por qué "la parte que les toca" y no el descuento entero?
         |
         | Ejemplo: contenedor $2,400 (gravable) + delivery $350 (no
         | gravable) = $2,750. Le hacen $275 de descuento.
         |
         |   Si le restáramos los $275 completos al contenedor, estaríamos
         |   diciendo que el descuento fue solo sobre el contenedor, y se
         |   cobraría menos impuesto del que corresponde.
         |
         |   Si no le restáramos nada, se cobraría impuesto sobre un dinero
         |   que el cliente nunca pagó.
         |
         |   Lo correcto es repartirlo en la misma proporción en que se
         |   repartió el precio: el contenedor es el 87.3% de la venta, así
         |   que le toca el 87.3% del descuento ($240).
         |
         |   Base gravable = 2,400 − 240 = $2,160
         * -------------------------------------------------------------- */

        // El "if" evita dividir entre cero cuando el documento está vacío.
        $proporcionGravable = $subtotal > 0 ? $sumaGravable / $subtotal : 0;

        $baseGravable = round($sumaGravable - ($descuento * $proporcionGravable), 2);

        /* -----------------------------------------------------------------
         | PASO 4 · EL CLIENTE EXENTO
         |
         | RB-014: un cliente con Florida Annual Resale Certificate vigente
         | no paga sales tax.
         |
         | Cuando el documento viene marcado como exento, la base gravable
         | se pone en cero. Así el impuesto sale cero por aritmética, no
         | por una excepción escrita aparte.
         |
         | IMPORTANTE para la pantalla de venta: la marca tax_exempt NO se
         | decide sola. Al emitir la factura hay que buscar el certificado
         | vigente del cliente y guardar su id en
         | invoices.tax_exemption_certificate_id (RB-015). El campo
         | tax_exempt es el interruptor; el certificado es la prueba. Sin
         | la prueba, ante una auditoría del estado el impuesto lo termina
         | pagando la empresa.
         * -------------------------------------------------------------- */
        if ($exento) {
            $baseGravable = 0.0;
        }

        /* -----------------------------------------------------------------
         | PASO 5 · EL IMPUESTO
         * -------------------------------------------------------------- */
        $impuesto = round($baseGravable * $tasaImpuesto / 100, 2);

        /* -----------------------------------------------------------------
         | PASO 6 · EL RECARGO POR TARJETA (RB-009)
         |
         | 3.5% constante, pero falta decidir una cosa: ¿el 3.5% se calcula
         | sobre el subtotal, o sobre el total con impuesto ya incluido?
         |
         | La diferencia en una venta de $2,750 con $151.20 de impuesto:
         |
         |     sobre el subtotal        →  2,750.00 × 3.5% = $96.25
         |     sobre el total con tax   →  2,901.20 × 3.5% = $101.54
         |
         | Son $5.29. Poco en una venta, varios cientos de dólares al año.
         |
         | Como todavía está pendiente de confirmar con Denisse, la
         | decisión NO está escrita en el código: vive en la tabla de
         | configuración, clave 'payments' / 'cc_fee_base'. Cuando ella
         | responda, se cambia desde la pantalla de Configuración y listo,
         | sin tocar código ni volver a desplegar.
         |
         | Valores posibles: 'subtotal'  |  'total_with_tax'
         * -------------------------------------------------------------- */
        $subtotalConDescuento = round($subtotal - $descuento, 2);

        $tarjetaGrava = (bool) ($company?->setting('fiscal', 'tax_applies_to_cc_fee', false));

        if ($tarjetaGrava) {
            /*
             | Si el recargo paga impuesto, el recargo se calcula SIEMPRE
             | sobre el subtotal, aunque la configuración diga otra cosa.
             |
             | Motivo: si el impuesto dependiera del recargo y el recargo
             | dependiera del impuesto, cada uno estaría esperando al otro
             | y la cuenta no cerraría nunca. Es como pedirle a alguien que
             | firme un papel que todavía no existe.
             */
            $baseDelRecargo = $subtotalConDescuento;
        } else {
            $modo = $company?->setting('payments', 'cc_fee_base', 'total_with_tax');

            $baseDelRecargo = $modo === 'subtotal'
                ? $subtotalConDescuento
                : $subtotalConDescuento + $impuesto;
        }

        $recargoTarjeta = round($baseDelRecargo * $porcentajeTarjeta / 100, 2);

        /* -----------------------------------------------------------------
         | PASO 6-B · SI EL RECARGO PAGA IMPUESTO, SE REHACE LA CUENTA
         |
         | Otra pregunta pendiente, esta para Rosa. En Florida hay
         | criterios distintos sobre si el surcharge de tarjeta forma parte
         | de la base gravable.
         |
         | Vive en la configuración, clave 'fiscal' /
         | 'tax_applies_to_cc_fee', y viene apagada por defecto.
         * -------------------------------------------------------------- */
        if ($tarjetaGrava && $recargoTarjeta > 0 && ! $exento) {
            $baseGravable = round($baseGravable + $recargoTarjeta, 2);
            $impuesto     = round($baseGravable * $tasaImpuesto / 100, 2);
        }

        /* -----------------------------------------------------------------
         | PASO 7 · EL TOTAL
         * -------------------------------------------------------------- */
        $anticipo = round(max(0, $anticipo), 2);

        $total = round(
            $subtotalConDescuento + $impuesto + $recargoTarjeta - $anticipo,
            2,
        );

        return [
            'subtotal'        => $subtotal,
            'discount_amount' => $descuento,
            'taxable_base'    => $baseGravable,
            'tax_amount'      => $impuesto,
            'credit_card_fee' => $recargoTarjeta,
            'deposit_applied' => $anticipo,
            'total'           => $total,

            // Extra, solo informativo para la pantalla: cuánto de la
            // venta NO paga impuesto. Sirve para explicarle al cliente
            // por qué el 7% no salió sobre el total.
            'non_taxable_base' => round($subtotalConDescuento - $baseGravable, 2),
        ];
    }

    /* =====================================================================
     | 4 · VALORES CON LOS QUE NACE UN DOCUMENTO NUEVO
     |
     | Estos NO se usan durante el cálculo. Son para que el formulario
     | llegue con los campos ya llenos y el usuario solo confirme.
     |
     | Todos siguen la misma cascada de tres niveles:
     |   1. ¿la compañía tiene su propio valor?  -> ese
     |   2. ¿hay un valor global?                -> ese
     |   3. ¿ninguno?                            -> el que trae el código
     * ================================================================== */

    /**
     * El % de sales tax con el que arranca un documento nuevo.
     *
     * Fíjate en collects_sales_tax: RS Transport tiene esa bandera
     * apagada porque el transporte no lleva impuesto (RB-005). Así, un
     * documento emitido desde RS Transport nace con 0% y nadie tiene que
     * acordarse de bajarlo a mano.
     *
     * La excepción de RB-004 —RS Transport vendiendo un contenedor, que
     * sí lleva el 7%— se resuelve dejando el campo editable en la
     * pantalla. El valor de aquí es una sugerencia, no una regla.
     */
    public function defaultTaxRate(Company $company): float
    {
        if (! $company->collects_sales_tax) {
            return 0.0;
        }

        return (float) $company->setting(
            'fiscal',
            'default_tax_rate',
            $company->default_tax_rate ?? 7.00,
        );
    }

    /**
     * El % de recargo por tarjeta (RB-009: 3.5% constante).
     *
     * Devuelve el porcentaje SIEMPRE, esté o no pagando con tarjeta. Es
     * la pantalla la que decide si lo pone en el documento: si el cliente
     * eligió cheque, el campo credit_card_fee_percent se queda en 0 y la
     * calculadora no cobra recargo.
     */
    public function defaultCreditCardFeePercent(Company $company): float
    {
        return (float) $company->setting(
            'payments',
            'credit_card_fee_percent',
            $company->credit_card_fee_percent ?? 3.50,
        );
    }

    /**
     * Cuántos días vale un presupuesto (RB-041).
     *
     * Está en configuración porque todavía falta confirmar si los 3 días
     * que aparecen en la plantilla de FLCHR son de verdad la vigencia de
     * la cotización o solo un texto heredado.
     */
    public function defaultEstimateValidDays(Company $company): int
    {
        return (int) $company->setting('documents', 'estimate_valid_days', 3);
    }

    /** Los términos de pago con los que nace un documento. */
    public function defaultTerms(Company $company): string
    {
        return (string) $company->setting('documents', 'default_terms', 'Due on receipt');
    }
}
