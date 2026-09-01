<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La lista de precios del proveedor. **Cada cambio de precio es una fila
*nueva**, no se sobrescribe la anterior: así queda el historial de cuánto
*costaba cada cosa en cada momento.
     */
    public function up(): void
    {
        Schema::create('supplier_prices', function (Blueprint $table) {
             $table->id();

            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | QUÉ COMBINACIÓN
             |
             | Sin constrained(): los catálogos de contenedor se crean en
             | la migración 18, DESPUÉS de esta. Todavía no existen, así
             | que la llave foránea no se puede crear ahora.
             |
             | Se agregan en el bloque 8, cuando ya están todas las tablas.
             |
             | Por eso se escribe unsignedBigInteger a mano en vez de
             | foreignId: foreignId intentaría crear la llave de una vez.
             * ---------------------------------------------------------- */
            $table->unsignedBigInteger('container_type_id')->nullable();
            $table->unsignedBigInteger('container_size_id')->nullable();
            $table->unsignedBigInteger('container_condition_id')->nullable();
            $table->unsignedBigInteger('container_grade_id')->nullable();

            /* -------------------------------------------------------------
             | EL PRECIO
             * ---------------------------------------------------------- */
            $table->decimal('price', 12, 2);
            $table->decimal('pickup_fee', 12, 2)->nullable();

            /* -------------------------------------------------------------
             | VIGENCIA
             |
             | quoted_at    = cuándo cotizaron este precio
             | valid_until  = hasta cuándo lo respetan (null = sin límite)
             | is_current   = si es el precio que se usa hoy
             |
             | is_current es un atajo: se podría deducir de las fechas,
             | pero tenerlo como bandera hace la consulta mucho más rápida
             | y permite descartar un precio a mano sin tocar las fechas.
             * ---------------------------------------------------------- */
            $table->date('quoted_at');
            $table->date('valid_until')->nullable();
            $table->boolean('is_current')->default(true);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* -------------------------------------------------------------
             | ÍNDICES
             |
             | El segundo lleva nombre corto a mano: el automático que
             | armaría Laravel con esos tres nombres de columna pasa de
             | los 64 caracteres que permite MySQL.
             * ---------------------------------------------------------- */
            $table->index(['supplier_id', 'is_current']);
            $table->index(
                ['container_size_id', 'container_grade_id', 'is_current'],
                'sp_size_grade_current_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_prices');
    }
};
