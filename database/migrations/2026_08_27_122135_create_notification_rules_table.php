<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
         /* -----------------------------------------------------------------
         | LAS REGLAS
         |
         | Cada fila es una instrucción del tipo:
         |   "cuando falten 3 días para que venza una renta,
         |    mandar un email con la plantilla rental_due_soon"
         * -------------------------------------------------------------- */
        
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
           // Nullable = la regla vale para las dos compañías.
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();

            /* -------------------------------------------------------------
             | QUÉ DISPARA EL AVISO
             |
             | rental_due            se acerca el vencimiento de la renta
             | rental_overdue        ya venció
             | invoice_sent          se emitió una factura
             | certificate_expiring  vence un certificado
             | release_deadline      vence el plazo de retiro del release
             | storage_charge        empieza a correr el almacenaje
             * ---------------------------------------------------------- */
            $table->string('event', 50);

            // email | sms | both
            $table->string('channel', 10)->default('email');

            /* -------------------------------------------------------------
             | CUÁNDO
             |
             | offset_days es la clave, y el SIGNO importa:
             |   −3 = tres días ANTES del evento
             |    0 = el mismo día
             |    5 = cinco días DESPUÉS
             |
             | Con eso una sola columna cubre recordatorios y avisos de
             | mora, sin necesitar dos campos.
             * ---------------------------------------------------------- */
            $table->integer('offset_days')->default(0);

            /* -------------------------------------------------------------
             | INSISTENCIA
             |
             | repeat_every_days = cada cuánto se vuelve a mandar
             | max_repeats       = cuántas veces como máximo
             |
             | Ejemplo: recordatorio de mora cada 7 días, máximo 4 veces.
             |
             | max_repeats existe para no hostigar: sin tope, un cliente
             | moroso recibiría el mismo correo para siempre.
             * ---------------------------------------------------------- */
            $table->integer('repeat_every_days')->nullable();
            $table->tinyInteger('max_repeats')->nullable();

            /* -------------------------------------------------------------
             | QUÉ TEXTO
             |
             | Es la clave de la plantilla, no el texto. Así el contenido
             | del correo se puede cambiar sin tocar esta tabla.
             * ---------------------------------------------------------- */
            $table->string('template_key', 60);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // La consulta del comando diario: "¿qué reglas activas hay
            // para este evento en esta compañía?"
            $table->index(['company_id', 'event', 'is_active']);
        });

        /* -----------------------------------------------------------------
         | EL REGISTRO
         |
         | Una fila por cada aviso mandado. Responde tres preguntas que
         | siempre aparecen:
         |
         |   "¿le avisamos a este cliente?"
         |   "¿le llegó el correo?"
         |   "¿cuántas veces ya se le mandó esto?"
         * -------------------------------------------------------------- */
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            /* -------------------------------------------------------------
             | SOBRE QUÉ SE AVISÓ
             |
             | nullableMorphs crea dos columnas que pueden apuntar a
             | cualquier tabla:
             |   notifiable_type -> "App\Models\RentalPeriod"
             |   notifiable_id   -> 47
             |
             | Así un mismo registro sirve para avisos de facturas,
             | períodos de renta, compras o certificados, sin necesitar
             | una columna por cada cosa.
             |
             | La versión "nullable" permite avisos generales que no
             | cuelgan de ningún documento.
             * ---------------------------------------------------------- */
            $table->nullableMorphs('notifiable');

            // A quién se le mandó, si fue a un contacto cargado.
            $table->foreignId('customer_contact_id')->nullable()
                  ->constrained()->nullOnDelete();

            // Qué regla lo disparó.
            $table->foreignId('notification_rule_id')->nullable()
                  ->constrained()->nullOnDelete();

            /* -------------------------------------------------------------
             | EL ENVÍO
             |
             | recipient guarda el email o teléfono TAL COMO ESTABA ese
             | día. Si el contacto después cambia de correo, este registro
             | sigue diciendo a dónde se mandó de verdad.
             * ---------------------------------------------------------- */
            $table->string('channel', 10);
            $table->string('recipient', 200);
            $table->string('template_key', 60)->nullable();

            $table->timestamp('sent_at')->nullable();

            /* -------------------------------------------------------------
             | RESULTADO
             |
             | queued = en cola, todavía no salió
             | sent   = salió
             | failed = falló el envío
             | bounced = rebotó (la dirección no existe)
             |
             | 'bounced' separado de 'failed' a propósito: un rebote
             | significa que el correo del cliente está mal y hay que
             | corregirlo. Un fallo puede ser un problema pasajero.
             * ---------------------------------------------------------- */
            $table->string('status', 20)->default('queued');

            // El identificador que devuelve el proveedor de correo.
            // Sirve para rastrear el envío en su panel si el cliente
            // dice que no le llegó.
            $table->string('provider_message_id', 100)->nullable();

            $table->text('error')->nullable();
            $table->timestamps();

            // "¿qué quedó en cola sin salir?" y "¿qué rebotó ayer?"
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_rules');
    }
};
