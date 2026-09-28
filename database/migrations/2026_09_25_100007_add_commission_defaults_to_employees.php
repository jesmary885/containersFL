<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*------------------------------------------------------------------------
        REUNIÓN 16-09-2026 · El vendedor con condiciones especiales.

        Denisse lo explicó así: la mayoría de los vendedores manejan montos
        fijos, salvo un vendedor principal que recibe un porcentaje aplicado
        específicamente en unidades de venta directa.

        Hasta hoy el formulario de Trabajadores PROHIBÍA llenar los dos
        campos: ponía monto o porcentaje, nunca los dos. Con esa regla ese
        vendedor no se podía guardar.

        La prohibición tenía una razón válida —con los dos llenos, el
        sistema no sabía cuál usar— y estas dos columnas la resuelven sin
        quitarle libertad a nadie:

          default_commission_mode   cuál de los dos se PROPONE al facturar.
                                    Solo hace falta cuando hay dos valores
                                    cargados; con uno solo se usa ese.

          default_commission_base   sobre qué se calcula el porcentaje:
                                    'subtotal'   toda la venta, delivery incluido
                                    'containers' solo las unidades vendidas

        La segunda es la que evita pagar de más. Hoy el sistema comisiona
        sobre toda la venta: en una de $2,650 que incluye $650 de delivery,
        un 5% son $132.50 en vez de $100. La diferencia se la come la
        empresa en cada venta.

        Las dos son SUGERENCIAS. Lo que manda es lo que quede escrito en la
        factura, que es donde se congela lo pactado (RB-058).
     *----------------------------------------------------------------------*/
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {

            // 'fixed' | 'percent'. Null = se deduce del único valor cargado.
            $table->string('default_commission_mode', 10)
                  ->nullable()
                  ->after('default_commission_percent');

            // 'subtotal' | 'containers'
            $table->string('default_commission_base', 20)
                  ->default('subtotal')
                  ->after('default_commission_mode');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['default_commission_mode', 'default_commission_base']);
        });
    }
};
