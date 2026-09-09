<?php

namespace App\Models\Concerns;

/**
 * Las dos direcciones congeladas del documento (RB-035).
 *
 * Lo usan Estimate, Invoice, Sale y Rental. Los cuatro guardan bill_to y
 * ship_to con los mismos nombres a propósito, así convertir uno en otro
 * es copiar y no traducir.
 *
 * ══════════════════════════════════════════════════════════════════════
 * POR QUÉ ship_to SE QUEDA EN null Y LA COPIA SE HACE AL IMPRIMIR
 * ══════════════════════════════════════════════════════════════════════
 *
 * El documento impreso muestra SIEMPRE los dos bloques, aunque digan lo
 * mismo. Es lo que hacen las facturas del cliente y es lo correcto: un
 * documento fiscal no debe obligar a nadie a deducir a dónde iba la
 * mercancía.
 *
 * Pero en la base ship_to sigue guardándose null cuando el interruptor
 * de "la entrega va a otra dirección" está apagado. Guardar una copia
 * rompería tres cosas:
 *
 *   1. EL INTERRUPTOR. Form.php hace
 *          $this->envioDistinto = ! empty($estimate->ship_to);
 *      Con una copia guardada, TODO documento se reabriría con el
 *      interruptor encendido y el segundo bloque de campos desplegado,
 *      como si alguien hubiera escrito ahí una dirección distinta.
 *
 *   2. LA CORRECCIÓN DE UN ERROR. Si mañana se corrige la dirección de
 *      facturación, la copia guardada en ship_to se queda con la vieja.
 *      El documento pasaría a mostrar dos direcciones diferentes sin que
 *      nadie haya tocado la de entrega.
 *
 *   3. EL DATO. null significa "no se pidió otro destino". Una copia
 *      significa "se pidió este destino, que resultó ser el mismo". Son
 *      cosas distintas y solo una de las dos es cierta.
 *
 * null es el dato. Los dos bloques son la presentación. No se mezclan.
 * ══════════════════════════════════════════════════════════════════════
 */
trait HasDocumentAddresses
{
    /**
     * La dirección de entrega TAL COMO VA IMPRESA.
     *
     * Si no se pidió otro destino, la entrega es la de facturación.
     */
    public function printableShipTo(): array
    {
        return $this->ship_to ?: ($this->bill_to ?: []);
    }

    /**
     * ¿La entrega va a un sitio distinto del de facturación?
     *
     * Sirve para poner el aviso de "misma dirección" en el documento, y
     * para que quien lea el código no tenga que acordarse de que null
     * quiere decir "la misma".
     */
    public function shipsElsewhere(): bool
    {
        return ! empty($this->ship_to);
    }

    /**
     * La dirección en una línea, lista para imprimir.
     *
     * El filter() salta las partes vacías: sin él, un cliente sin
     * "línea 2" sale con dos comas seguidas, que es de esas cosas que
     * nadie reporta pero todos notan en un documento que se le manda a
     * un cliente.
     *
     * El 'label' se salta a propósito: es el nombre interno de la
     * dirección ("Oficina", "Yarda"), no parte de la dirección misma.
     */
    public static function addressToLine(?array $direccion): string
    {
        return collect($direccion ?? [])
            ->except('label')
            ->filter()
            ->implode(', ');
    }
}
