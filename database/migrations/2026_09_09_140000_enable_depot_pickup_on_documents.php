<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL RETIRO DESDE DEPÓSITO — depot_id + pickup_fee
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── ESTO CORRIGE UNA DECISIÓN MÍA ──
 *
 * Las dos columnas estaban COMENTADAS en las migraciones de `estimates` y
 * `sales`, y los modelos Estimate y Sale tenían la relación `depot()`
 * apuntando a una columna que no existía. Al revisar, alineé los modelos
 * con la migración: comenté las relaciones.
 *
 * Estaba al revés. El levantamiento del 14 de agosto con Denisse lo dice
 * explícito:
 *
 *   "es necesario categorizar estos servicios correctamente en el
 *    sistema, ya que tienen costos distintos; por ejemplo, el costo de
 *    las ENTREGAS varía según las millas, mientras que las RECOGIDAS
 *    tienen un costo FIJO ASOCIADO A LOS DEPÓSITOS" (01:17:46)
 *
 * O sea que las dos columnas no eran una idea a medio hacer: son un
 * requisito del negocio. La entrega se calcula por millas —eso ya está
 * en trips.miles y en la fila de entrega del renglón— y la recogida es
 * una tarifa plana que depende del depósito de donde se saca.
 *
 * Sin esto no se puede cotizar un contenedor que está en el depósito de
 * un tercero, que es la mitad de los `releases`: el ejemplo de la
 * reunión del 8 de agosto era un release de 7 contenedores con 1
 * recogido y 6 todavía en el proveedor.
 *
 * ── POR QUÉ EL FEE SE COPIA Y NO SE LEE DEL DEPÓSITO ──
 *
 * `depots` tiene su propia tarifa. Acá se guarda una copia porque el
 * documento tiene que conservar lo que se cotizó (RB-058, el mismo
 * criterio que las direcciones): si el depósito sube su tarifa el mes
 * que viene, este presupuesto sigue diciendo lo que el cliente aprobó.
 * ═══════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | Las dos tablas escritas a mano y no con un foreach.
         |
         | Un `Schema::table($tabla, ...)` dentro de un bucle ahorra ocho
         | líneas y le quita al proyecto algo que vale más: poder buscar
         | "estimates" con grep y encontrar todas las migraciones que la
         | tocan. Con el nombre en una variable, esta no aparece.
         */
        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('depot_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();

            $table->decimal('pickup_fee', 12, 2)->default(0)->after('depot_id');
        });

        Schema::table('sales', function (Blueprint $table) {

            /* -------------------------------------------------------------
            | ¿DE DÓNDE SE SACA EL CONTENEDOR?
            |
            | Nullable: si ya está en la yarda propia, no hay depósito ni
            | recogida que pagar.
            * ---------------------------------------------------------- */
            $table->foreignId('depot_id')->nullable()->after('customer_id')
                ->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
            | LA TARIFA DE RECOGIDA
            |
            | Plana, no por milla. Se precarga de la ficha del depósito y
            | queda editable.
            |
            | default 0 y no nullable: cero es un valor válido y con
            | significado aquí — "se recoge y no me cobran" — mientras que
            | en daily_rate de las rentas null quería decir "no aplica".
            | No es el mismo caso.
            * ---------------------------------------------------------- */
            $table->decimal('pickup_fee', 12, 2)->default(0)->after('depot_id');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depot_id');
            $table->dropColumn('pickup_fee');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depot_id');
            $table->dropColumn('pickup_fee');
        });
    }
};
