<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LA FICHA DEL CLIENTE, SIN SALIR DEL DOCUMENTO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── EL PROBLEMA ──
 *
 * Estás cotizando y necesitas saber algo del cliente: la nota que le
 * pusiste hace tres meses, a qué teléfono llamar, si tiene el certificado
 * de exención vigente, cuánto debe ya.
 *
 * Hasta ahora había que irse al módulo de Clientes a buscarlo. Y volver
 * significaba empezar el documento otra vez, porque lo escrito se perdía.
 *
 * ── POR QUÉ UN PANEL Y NO UN ENLACE ──
 *
 * Un enlace a la ficha saca del formulario. Abrirlo en otra pestaña no
 * pierde el trabajo, pero deja dos pantallas abiertas del mismo sistema y
 * es fácil acabar editando en la equivocada.
 *
 * Este panel se abre encima, es de SOLO LECTURA y se cierra con Escape.
 * No hay nada que guardar y no hay forma de perder el documento.
 *
 * ── LO QUE ENSEÑA Y POR QUÉ ESO ──
 *
 * Solo lo que cambia una decisión mientras se cotiza o se factura:
 *
 *   · Las notas. Es lo que más se busca y lo que solo está ahí.
 *   · Teléfonos y correos, para llamar sin cambiar de pantalla.
 *   · Si puede pagar con tarjeta y si está verificado en Sunbiz
 *     (RB-012, RB-013): son los dos candados del cobro con tarjeta.
 *   · El certificado de exención vigente (RB-014): decide si se cobra
 *     el 7% o no.
 *   · Lo que ya debe. Un cliente que arrastra $12.000 vencidos es una
 *     conversación distinta antes de cotizarle otra cosa.
 *
 * Lo usan el formulario de factura y el de presupuesto, con la misma
 * vista parcial, para que se vea igual en los dos sitios.
 */
trait MuestraFichaDelCliente
{
    /** Si el panel está abierto. */
    public bool $verFichaCliente = false;

    public function abrirFichaCliente(): void
    {
        // Sin cliente elegido no hay nada que enseñar.
        if (! $this->customer_id) {
            return;
        }

        $this->verFichaCliente = true;
    }

    public function cerrarFichaCliente(): void
    {
        $this->verFichaCliente = false;
    }

    /**
     * Los datos del cliente para el panel.
     *
     * Se consulta solo cuando el panel está abierto. Si se resolviera
     * siempre, cada tecla del formulario dispararía cinco consultas que
     * nadie está mirando.
     */
    public function getFichaDelClienteProperty(): ?Customer
    {
        if (! $this->verFichaCliente || ! $this->customer_id) {
            return null;
        }

        return Customer::with([
            'contacts',
            'addresses',

            /*
             | Solo los certificados vigentes hoy. Uno vencido no exime de
             | nada, y enseñarlo al lado de uno bueno invita a confundirse
             | justo en la decisión que menos perdona: si el estado audita
             | y no se puede probar la exención, el 7% lo paga la empresa.
             */
            'certificates' => fn ($q) => $q
                ->whereDate('valid_from', '<=', now())
                ->whereDate('valid_until', '>=', now())
                ->orderByDesc('valid_until'),
        ])->find($this->customer_id);
    }

    /**
     * Lo que este cliente debe en la empresa activa.
     *
     * Sale de las facturas, no de un campo guardado: un saldo que se
     * calcula nunca se queda desfasado.
     *
     * El filtro de compañía lo aplica solo el scope global de Invoice, y
     * eso es lo correcto: el historial de un cliente enseña lo que tiene
     * con la empresa en la que estás trabajando.
     */
    public function getDeudaDelClienteProperty(): float
    {
        if (! $this->customer_id) {
            return 0.0;
        }

        return (float) \App\Models\Invoice::query()
            ->where('customer_id', $this->customer_id)
            ->where('balance_due', '>', 0)
            ->sum('balance_due');
    }

    /** Cuántas de esas facturas ya se pasaron de fecha. */
    public function getFacturasVencidasDelClienteProperty(): int
    {
        if (! $this->customer_id) {
            return 0;
        }

        return \App\Models\Invoice::query()
            ->where('customer_id', $this->customer_id)
            ->where('balance_due', '>', 0)
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();
    }
}
