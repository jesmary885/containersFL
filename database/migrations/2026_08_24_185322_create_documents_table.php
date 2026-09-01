<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
             $table->id();

            /* -------------------------------------------------------------
             | A QUÉ ESTÁ ADJUNTO
             |
             | morphs() crea DOS columnas de golpe:
             |   documentable_type -> "App\Models\Customer"
             |   documentable_id   -> 47
             |
             | Juntas dicen "esto pertenece al cliente 47". Mañana pueden
             | decir "al contenedor 12" o "al gasto 300", sin cambiar nada.
             |
             | Es lo que evita tener customer_documents, container_documents,
             | invoice_documents... una tabla por cada cosa que adjunta.
             |
             | morphs() también crea el índice sobre las dos columnas.
             * ---------------------------------------------------------- */
            $table->morphs('documentable');

              /* -------------------------------------------------------------
             | DE QUÉ COMPAÑÍA ES EL ARCHIVO  ← COLUMNA NUEVA
             |
             | Los documentos cuelgan de cualquier cosa vía morph, y eso
             | está bien. Pero un archivo adjunto a un CLIENTE —que es
             | maestro compartido entre las dos empresas— no tiene forma
             | de decir quién lo subió.
             |
             |   null      = lo ven las dos compañías
             |               (certificado de exención del cliente: es
             |               del cliente, aplica a las dos)
             |
             |   con valor = solo lo ve esa compañía
             |               (contrato firmado con FLCHR, autorización
             |               de tarjeta tomada por RS Transport)
             |
             | Sin esta columna, un contrato privado de una empresa
             | sería visible desde la otra.
             * ---------------------------------------------------------- */
            $table->foreignId('company_id')->nullable()
                  ->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | QUÉ ES
             |
             | tax_exemption | export_certificate | cc_authorization |
             | contract | supplier_invoice | container_photo | pod |
             | expense_receipt | other
             * ---------------------------------------------------------- */
            $table->string('category', 40)->default('other');

            /* -------------------------------------------------------------
             | EL ARCHIVO
             |
             | name = el nombre original que subió el usuario
             | path = dónde quedó guardado de verdad
             | disk = en qué almacenamiento: 's3', 'local'
             |
             | Nunca se expone 'path' al navegador: se genera un enlace
             | temporal con temporaryUrl().
             * ---------------------------------------------------------- */
            $table->string('name', 255);
            $table->string('path', 500);
            $table->string('disk', 20)->default('s3');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            /* -------------------------------------------------------------
             | VENCIMIENTO
             |
             | Los certificados y contratos vencen. Esta fecha alimenta
             | los avisos automáticos.
             * ---------------------------------------------------------- */
            $table->date('expires_at')->nullable();

             /* -------------------------------------------------------------
             | ¿VA CON LA FACTURA?  ← COLUMNA NUEVA · RB-034
             |
             | La factura debe permitir adjuntar documentos que se envían
             | CON ella: el certificado de exportación, la autorización
             | de tarjeta firmada, la foto de entrega.
             |
             | Pero no todos los adjuntos de una factura se le mandan al
             | cliente. Las notas internas y los comprobantes de costo se
             | quedan dentro.
             |
             | Esta bandera separa las dos cosas. Por defecto en false:
             | que un archivo salga hacia afuera tiene que ser una
             | decisión consciente, no lo que pasa si nadie hace nada.
             * ---------------------------------------------------------- */
            $table->boolean('attach_to_invoice')->default(false);

            $table->text('notes')->nullable();

            // nullOnDelete: si el usuario se da de baja, el archivo queda.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Para el panel de "documentos por vencer".
            $table->index(['category', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
