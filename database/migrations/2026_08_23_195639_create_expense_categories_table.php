<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las categorías de gasto. **Es tabla y no lista fija en el código** porque
    *el cliente tiene que poder agregar categorías sin llamarte.
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
             $table->id();

            /* -------------------------------------------------------------
             | IDENTIFICACIÓN
             |
             | code se usa en los seeders y en el código para buscar una
             | categoría sin depender del id, que puede cambiar.
             * ---------------------------------------------------------- */
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('name_en', 100)->nullable();

            /* -------------------------------------------------------------
             | JERARQUÍA
             |
             | Una categoría puede colgar de otra: "Mantenimiento" con
             | "Llantas" y "Aceite" adentro.
             |
             | Fíjate que apunta a SU PROPIA TABLA. Por eso hay que decirle
             | explícitamente 'expense_categories': Laravel no lo adivina.
             |
             | nullOnDelete: si borras la categoría padre, las hijas quedan
             | sueltas en la raíz en vez de desaparecer.
             * ---------------------------------------------------------- */
            $table->foreignId('parent_id')->nullable()
                  ->constrained('expense_categories')->nullOnDelete();

            /* -------------------------------------------------------------
             | REPORTE 1099
             |
             | Marca si los gastos de esta categoría entran por defecto en
             | el reporte anual de pagos a proveedores. Es solo el valor
             | que se precarga; en cada gasto se puede cambiar.
             * ---------------------------------------------------------- */
            $table->boolean('is_1099_default')->default(false);

             /* -------------------------------------------------------------
             | ¿SUBE EL COSTO DEL CONTENEDOR?  ← COLUMNA NUEVA
             |
             | Hay dos clases de gasto y la diferencia decide el margen:
             |
             |   SÍ lo sube: reacondicionamiento, pintura, reparación,
             |               cambio de piso. Ese dinero se invirtió en
             |               ESA unidad y hay que sumarlo a su costo
             |               antes de calcular cuánto se ganó al
             |               venderla.
             |
             |   NO lo sube: peajes, papelería, seguro, renta de la
             |               oficina. Son costo del negocio, no de una
             |               unidad concreta.
             |
             | La tabla containers ya tiene 'reconditioning_cost'
             | esperando este dato; lo que faltaba era saber qué
             | categorías alimentan esa columna.
             * ---------------------------------------------------------- */
            $table->boolean('affects_container_cost')->default(false);

            // El orden que el cliente quiera en los desplegables.
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
