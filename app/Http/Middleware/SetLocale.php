<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el idioma de la INTERFAZ para toda la petición.
 *
 * ── EL ORDEN DE PRIORIDAD ──
 *
 *   1. Lo que el usuario eligió en esta sesión (el selector de arriba)
 *   2. Lo que tiene guardado en su ficha (users.locale)
 *   3. El idioma por defecto de la aplicación
 *
 * La sesión gana sobre la ficha a propósito: si alguien cambia el
 * idioma un rato para enseñarle una pantalla a un cliente, eso no
 * debería cambiarle la preferencia permanente hasta que él lo decida.
 *
 * ── LO QUE ESTE MIDDLEWARE NO HACE ──
 *
 * No decide el idioma de los DOCUMENTOS. Un presupuesto se imprime en
 * el idioma del cliente (customers.preferred_locale) o en el que quedó
 * congelado al emitirlo (estimates.locale), sin importar en qué idioma
 * esté viendo el sistema quien lo imprime. Son cosas distintas y
 * mezclarlas produce facturas en español para clientes de Miami.
 */
class SetLocale
{
    /**
     * Los únicos idiomas que existen.
     *
     * La lista está acá y no en config para que un valor raro en la
     * sesión —de una URL manipulada, por ejemplo— no pueda hacer que
     * Laravel busque una carpeta de traducciones que no existe.
     */
    protected const PERMITIDOS = ['es', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $idioma = session('locale')
            ?? $request->user()?->locale
            ?? config('app.locale');

        if (! in_array($idioma, self::PERMITIDOS, true)) {
            $idioma = config('app.locale');
        }

        App::setLocale($idioma);

        return $next($request);
    }
}
