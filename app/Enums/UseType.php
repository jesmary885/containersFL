<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/**
 * Para qué va a usar el cliente el contenedor.
 *
 * Es la primera pregunta de la pantalla de venta porque cambia el
 * precio, el papeleo y si hay entrega o no.
 *
 *   storage → sin certificado, con entrega normal
 *   export  → exige certificado CSC (RB-016)
 *             y casi nunca lleva entrega (RB-017)
 *
 * ═══════════════════════════════════════════════════════════════════
 * LO QUE EL USO **NO** DECIDE: EL IMPUESTO
 * ═══════════════════════════════════════════════════════════════════
 *
 * Hasta el 15-sep este enum daba por exenta toda venta de exportación.
 * Se revisó contra las cinco fuentes del levantamiento —las dos minutas
 * de agosto, la transcripción completa del 14 de agosto, los documentos
 * recolectados y las 16 hojas del Excel— y esa regla NO APARECE EN
 * NINGUNA. Nadie la dijo nunca.
 *
 * Lo que sí está dicho sobre no cobrar el 7%:
 *
 *   · el cliente tiene su certificado de exención vigente  (RB-014/015)
 *   · el renglón es de transporte, que nunca paga          (RB-005)
 *   · la compañía no recauda sales tax (RS Transport)      (RB-004)
 *
 * Es decir: el impuesto depende de QUIÉN COMPRA y de QUÉ SE VENDE, no
 * de para qué lo va a usar.
 *
 * ── POR QUÉ SE CORRIGIÓ EN VEZ DE DEJARLO ──
 *
 * Porque el error es caro en una sola dirección. Si el sistema deja de
 * cobrar el 7% en una venta que sí lo llevaba, a fin de año el dinero
 * se le sigue debiendo al estado de la Florida y sale del bolsillo de
 * la empresa. Si lo cobra de más, se detecta y se devuelve.
 *
 * Y hay una razón concreta para dudar justo en este caso: Denisse dijo
 * el 14 de agosto (00:37:45) que en el 98-99% de las exportaciones es
 * el cliente quien viene a retirar la unidad a la yarda. Una venta que
 * se entrega en Florida es una venta de Florida.
 *
 * ── ESTO ESTÁ PENDIENTE DE CONFIRMAR CON EL CONTADOR ──
 *
 * Si confirma que la exportación sí exime, el camino correcto NO es
 * volver a poner la excepción acá: es marcar el documento como exento
 * y guardar cuál papel lo justifica, como ya manda RB-015 y como ya
 * hace el certificado del cliente. La exención sin la prueba guardada
 * la termina pagando la empresa en una auditoría.
 */
enum UseType: string
{

    use ConcernsHasOptions;

    case Storage = 'storage';   // almacenamiento en terreno del cliente
    case Export  = 'export';    // sale del país por línea naviera

    public function label(): string
    {
        return match ($this) {
            self::Storage => 'Almacenamiento',
            self::Export  => 'Exportación',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Storage => 'blue',
            self::Export  => 'purple',
        };
    }

    /**
     * ¿El uso previsto exime del impuesto? **No.**
     *
     * Devuelve true en los dos casos a propósito: el uso no es un motivo
     * de exención. Ver la explicación larga en la cabecera del archivo.
     *
     * El método se conserva en vez de borrarlo porque devolver true es
     * una respuesta, y borrarlo dejaría a quien lo busque mañana sin
     * saber que la pregunta ya se hizo y se contestó.
     *
     * Quien decide de verdad si un renglón paga:
     *
     *   Product::$taxable            el concepto (transporte = no)
     *   Invoice::$tax_exempt         el certificado del cliente
     *   Company::$collects_sales_tax la compañía que emite
     */
    public function isTaxable(): bool
    {
        return true;
    }

    /**
     * RB-016: toda venta de exportación requiere certificado CSC
     * emitido en PDF y enviado digitalmente. Sin él no se factura.
     */
    public function requiresExportCertificate(): bool
    {
        return $this === self::Export;
    }

    /**
     * RB-017: en exportación normalmente NO hay delivery (98–99%): el
     * cliente contrata su propia línea naviera.
     *
     * Devuelve false para que la pantalla OCULTE el bloque de delivery
     * por defecto, no para que lo prohíba. La excepción existe:
     * contenedor vacío hacia una terminal, con su cuota de delivery.
     */
    public function expectsDelivery(): bool
    {
        return $this === self::Storage;
    }
}