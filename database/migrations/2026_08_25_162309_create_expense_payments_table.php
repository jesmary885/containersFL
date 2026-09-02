<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     /**
     * Los abonos a un gasto.
     *
     * ── POR QUÉ ES UNA TABLA APARTE Y NO DOS COLUMNAS EN expenses ──
     *
     * Un gasto de $3,000 se puede pagar en tres partes, con tres
     * métodos distintos y tres fechas distintas. Con columnas
     * `paid_amount` y `paid_at` en la tabla de gastos solo cabría el
     * último pago, y se perdería el rastro de los anteriores.
     *
     * La tabla `expenses` SÍ guarda `paid_amount` y `balance`, pero
     * como resumen: son la suma de estas filas, y los mantiene el
     * ExpensePaymentObserver. Nunca se escriben a mano.
     *
     * ── OJO: esto NO es la tabla `payments` ──
     *
     * `payments`         = dinero que ENTRA (lo que paga el cliente)
     * `expense_payments` = dinero que SALE  (lo que pagamos nosotros)
     *
     * Se parecen pero no comparten reglas: los cobros pasan por Square
     * y llevan fee de tarjeta; los pagos a proveedores son cheque,
     * Zelle o transferencia y no llevan recargo.
     */
    public function up(): void
    {
        Schema::create('expense_payments', function (Blueprint $table) {
           
            $table->id();
        /* -------------------------------------------------------------
             | DE QUÉ GASTO ES
             |
             | cascadeOnDelete: si se borra el gasto, se van sus abonos.
             | No queda huérfano un pago que no se sabe a qué corresponde.
             |
             | (Los gastos sí se pueden borrar, a diferencia de las
             | facturas: no son documento fiscal propio, son el registro
             | interno de un documento que emitió otro.)
             * ---------------------------------------------------------- */
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();

            /* -------------------------------------------------------------
             | CUÁNTO Y CUÁNDO
             |
             | decimal(12,2), nunca float. Con float, 0.1 + 0.2 no da
             | exactamente 0.3, y en dinero eso significa un centavo de
             | diferencia que aparece descuadrando el cierre del mes.
             * ---------------------------------------------------------- */
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');

            /* -------------------------------------------------------------
             | CÓMO SE PAGÓ
             |
             | Mismo catálogo que la tabla payments, reutilizando el enum
             | PaymentMethod: cash | check | zelle | ach | wire |
             | credit_card | other
             * ---------------------------------------------------------- */
            $table->string('method', 20)->default('check');

            /* -------------------------------------------------------------
             | EL COMPROBANTE
             |
             | Número de cheque, confirmación de Zelle, número de
             | transferencia. Es el dato que pide el contador cuando
             | concilia el banco.
             * ---------------------------------------------------------- */
            $table->string('reference', 100)->nullable();

            /* -------------------------------------------------------------
             | DE QUÉ CUENTA SALIÓ
             |
             | Texto libre a propósito. Todavía no hay un catálogo de
             | cuentas bancarias, y montarlo ahora sería adelantarse.
             | Cuando haga falta, esta columna se convierte en un
             | bank_account_id y los datos viejos se migran.
             * ---------------------------------------------------------- */
            $table->string('bank_account', 100)->nullable();

            $table->text('notes')->nullable();

            // nullOnDelete: si el usuario se da de baja, el pago queda.
            $table->foreignId('created_by')->nullable()
                  ->constrained('users')->nullOnDelete();

            $table->timestamps();

            /* -------------------------------------------------------------
             | ÍNDICES
             |
             | El primero: "los abonos de este gasto, del más reciente
             | al más viejo" — la ficha del gasto.
             |
             | El segundo: "cuánto salió de caja en septiembre" — el
             | reporte de flujo, que filtra por fecha sin importar el
             | gasto.
             * ---------------------------------------------------------- */
            $table->index(['expense_id', 'paid_at']);
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
