<?php

use App\Enums\EstimateStatus;
use App\Models\Estimate;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| TAREAS PROGRAMADAS
|--------------------------------------------------------------------------
|
| ── POR QUÉ ESTE ARCHIVO ESTABA VACÍO Y POR QUÉ IMPORTA ──
|
| Cuatro reglas del negocio dicen "el sistema lo hace solo", y ninguna
| tenía quién la ejecutara:
|
|   RB-022   generar el período mensual de cada renta
|   RB-024   aplicar la mora tras los 5 días de gracia
|   RB-027   mandar los avisos de cobranza
|   RB-041   vencer los presupuestos pasados los 3 días
|
| Sin un programador de tareas, las cuatro dependen de que alguien entre
| a la pantalla correcta el día correcto. Es exactamente el trabajo
| manual que el sistema viene a quitar.
|
| ── ESTO NO FUNCIONA SOLO EN EL SERVIDOR ──
|
| Laravel no programa nada por sí mismo: necesita UNA entrada de cron en
| el servidor que lo despierte cada minuto.
|
|     * * * * * cd /var/www/containersfl && php artisan schedule:run >> /dev/null 2>&1
|
| Sin esa línea, este archivo no hace nada y no da ningún error. El día
| que el cliente pregunte por qué no salió un aviso de mora, va a ser
| esto.
|
| ── EL ORDEN NO ES CASUAL ──
|
| Los períodos primero, la mora después. La mora se aplica sobre
| períodos vencidos; si se ejecutara antes, el período del día todavía
| no existiría y se saltaría un cobro.
*/

/* ─────────────────────────────────────────────────────────────────────
 | 1 · VENCER LOS PRESUPUESTOS (RB-041)
 |
 | Tres días de validez, confirmado por la propia plantilla del cliente:
 | "Invoice valid for: 3 days".
 |
 | Solo los que nadie tocó. Un presupuesto aceptado o convertido no
 | vence, y uno rechazado ya está cerrado.
 |
 | A la 1 de la mañana: antes de que alguien abra el sistema, para que
 | el listado ya esté correcto cuando llegue la primera persona.
 * ────────────────────────────────────────────────────────────────── */
Schedule::call(function () {
    Estimate::query()
        ->whereIn('status', [
            EstimateStatus::Draft->value,
            EstimateStatus::Processed->value,
            EstimateStatus::Sent->value,
        ])
        ->whereNotNull('valid_until')
        ->whereDate('valid_until', '<', now()->toDateString())
        ->update(['status' => EstimateStatus::Expired->value]);
})->dailyAt('01:00')->name('vencer-presupuestos')->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| LO QUE FALTA PROGRAMAR
|--------------------------------------------------------------------------
|
| Las otras tres tareas necesitan comandos que todavía no existen. Los
| dejo escritos acá, comentados, con el horario ya pensado, para que
| cuando se construyan solo haya que descomentar:
|
|   Schedule::command('rentas:generar-periodos')->dailyAt('01:15');
|   Schedule::command('rentas:aplicar-mora')->dailyAt('01:30');
|   Schedule::command('cobranza:avisos')->dailyAt('08:00');
|
| ── POR QUÉ ESOS HORARIOS ──
|
| Los períodos a la 1:15 y la mora a la 1:30, en ese orden: la mora se
| calcula sobre períodos vencidos y necesita que el del día ya exista.
|
| Los avisos a las 8:00 y no de madrugada, porque los recibe un cliente.
| Un SMS de cobranza a las 3 de la mañana es una queja, no un cobro.
|
| ── LO QUE HACE FALTA ANTES DE QUE LOS AVISOS FUNCIONEN ──
|
| El módulo de cobranza necesita tres cosas que no dependen del código:
|
|   · un servicio de correo configurado (MAIL_* en el .env)
|   · una cuenta de SMS, si se quiere el canal de texto — el
|     levantamiento del 14 de agosto pide correo Y texto
|   · un worker de colas corriendo, o cada aviso bloquea la petición
|
| La tabla `notification_rules` ya existe con offset_days,
| repeat_every_days y max_repeats, así que la configuración del
| levantamiento —avisos del día 5 al 10, uno cada dos días— se guarda
| sin tocar código. Lo que falta es quien la lea y mande.
*/
