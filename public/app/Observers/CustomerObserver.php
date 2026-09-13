<?php

namespace App\Observers;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL NÚMERO DE CLIENTE Y EL NOMBRE A MOSTRAR
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── POR QUÉ ESTE OBSERVER TENÍA QUE EXISTIR ──
 *
 * `customers.customer_number` es `unique()` y NOT NULL, y en todo el
 * proyecto no había una sola línea que lo generara: los tres clientes
 * que hay salieron del `CustomerSeeder` con el número escrito a mano
 * (`CUST-0001`, `CUST-0002`, `CUST-0003`).
 *
 * Mientras la pantalla de clientes no existía, eso no se notaba. En
 * cuanto alguien le da a "Guardar" en un cliente nuevo, MySQL rechaza el
 * INSERT con un error de columna obligatoria que no le dice nada a nadie.
 *
 * Lo mismo con `display_name`: también es NOT NULL. El modelo tiene un
 * `getNameAttribute()` que compone el nombre desde otros campos, pero eso
 * solo ayuda al LEER — la columna sigue exigiendo un valor al escribir.
 *
 * ── POR QUÉ NO SE USA `Company::nextNumber()` ──
 *
 * Porque los clientes NO son de una empresa. La tabla `customers` no
 * tiene `company_id`: el mismo cliente le compra a FLCHR y le renta a
 * RST, y eso es deliberado —está en las reglas: "registrar una sola vez
 * y reutilizar".
 *
 * `document_sequences` sí es por empresa (`unique(company_id, type)`).
 * Si se usara, FLCHR y RST llevarían contadores separados y los dos
 * generarían `CUST-0005`. El segundo rompería el índice único, y el
 * error saldría un martes cualquiera sin que nadie entienda por qué.
 *
 * Por eso el número sale de la propia tabla, que es la única fuente
 * global que hay.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class CustomerObserver
{
    public const PREFIJO = 'CUST-';

    public const RELLENO = 4;

    public function creating(Customer $customer): void
    {
        if (blank($customer->customer_number)) {
            $customer->customer_number = $this->siguienteNumero();
        }

        $this->componerNombre($customer);
    }

    /**
     * Al editar también, porque el nombre puede quedar en blanco.
     *
     * Caso real: un cliente se registró como persona natural, después
     * resultó ser una empresa y alguien cambió el tipo. Si el nombre a
     * mostrar no se recompone, la lista sigue enseñando el nombre de
     * pila de quien llamó por teléfono.
     */
    public function updating(Customer $customer): void
    {
        $this->componerNombre($customer);
    }

    /* =====================================================================
     | EL NÚMERO
     * ================================================================== */

    protected function siguienteNumero(): string
    {
        return DB::transaction(function () {

            /* -------------------------------------------------------------
             | ── LAS TRES DECISIONES DE ESTA CONSULTA ──
             |
             | `withTrashed()`
             |     `customers` usa borrado lógico. Un cliente dado de baja
             |     sigue ocupando su fila y su número: el índice único no
             |     distingue entre borrado y vivo. Sin esto, el sistema
             |     reutilizaría el número de alguien que sigue teniendo
             |     facturas colgando de su id.
             |
             | `lockForUpdate()`
             |     Dos personas dando de alta un cliente en el mismo
             |     segundo leerían el mismo último número y las dos
             |     intentarían escribir el siguiente. El bloqueo hace que
             |     la segunda espere a que la primera termine.
             |
             |     En InnoDB, un SELECT ... FOR UPDATE sobre un rango
             |     también bloquea el hueco, así que funciona aunque
             |     todavía no exista ninguna fila que coincida.
             |
             | Ordenar por longitud ANTES que por texto
             |     Alfabéticamente, `CUST-9999` va después de `CUST-10000`,
             |     porque compara carácter a carácter y el '9' es mayor que
             |     el '1'. El sistema volvería a entregar el 10000 para
             |     siempre.
             |
             |     Es un problema a diez mil clientes, o sea muy lejos. Pero
             |     cuesta una línea arreglarlo ahora y un diagnóstico
             |     completo descubrirlo después.
             * ---------------------------------------------------------- */
            $ultimo = Customer::withTrashed()
                ->where('customer_number', 'like', self::PREFIJO.'%')
                ->lockForUpdate()
                ->orderByRaw('LENGTH(customer_number) DESC')
                ->orderBy('customer_number', 'DESC')
                ->value('customer_number');

            $correlativo = $ultimo
                ? ((int) substr($ultimo, strlen(self::PREFIJO))) + 1
                : 1;

            return self::PREFIJO
                .str_pad((string) $correlativo, self::RELLENO, '0', STR_PAD_LEFT);
        });
    }

    /* =====================================================================
     | EL NOMBRE A MOSTRAR
     * ================================================================== */

    /**
     * Llena `display_name` si viene vacío.
     *
     * Es el nombre con el que el cliente aparece en el buscador, en la
     * lista y en el encabezado de los documentos. Se respeta si alguien
     * lo escribió a mano: hay clientes que se conocen por un alias que
     * no es ni su razón social ni su nombre.
     *
     * El `?: 'Sin nombre'` del final parece un parche y lo es, pero es un
     * parche a propósito: la columna es NOT NULL y prefiero una fila
     * fea y visible a un error de SQL a mitad de un guardado. La
     * validación del formulario ya impide que llegue hasta aquí.
     */
    protected function componerNombre(Customer $customer): void
    {
        if (filled($customer->display_name)) {
            return;
        }

        $esEmpresa = $customer->type === CustomerType::Business
            || $customer->type === CustomerType::Business->value;

        $compuesto = $esEmpresa
            ? $customer->company_name
            : trim($customer->first_name.' '.$customer->last_name);

        $customer->display_name = trim((string) $compuesto) ?: 'Sin nombre';
    }
}
