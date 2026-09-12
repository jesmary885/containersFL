<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UN USUARIO DADO DE BAJA SE VA AHORA, NO MAÑANA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── EL HUECO QUE TAPA ──
 *
 * `Login::verificarCredenciales()` ya comprueba `is_active`. Perfecto,
 * pero solo corre UNA vez: al entrar.
 *
 * El caso real es el otro. Denisse despide a alguien el martes a las
 * 10 y lo desactiva desde la pantalla de usuarios. Esa persona tiene la
 * sesión abierta en su teléfono desde el lunes, y con "recordarme"
 * marcado esa sesión dura semanas. Sigue dentro del sistema, viendo
 * precios y clientes, hasta que se le ocurra cerrar sesión.
 *
 * Desactivar a alguien tiene que surtir efecto en su siguiente clic.
 *
 * ── DÓNDE SE REGISTRA ──
 *
 * En el grupo 'web' completo, igual que SetCompanyContext y SetLocale, y
 * por la misma razón: Livewire manda todo por `/livewire/update`, que no
 * está en web.php. Si solo estuviera en las rutas con `auth`, la persona
 * desactivada no podría cargar páginas nuevas pero SÍ seguiría operando
 * en la pantalla que ya tenía abierta.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario && ! $usuario->is_active) {

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            /*
             | Livewire no entiende un redirect normal en mitad de una
             | petición suya: la respuesta llega y el JavaScript no sabe
             | qué hacer con ella, así que la pantalla se queda igual y
             | el usuario cree que no pasó nada.
             |
             | El 403 sí lo entiende: Livewire recarga la página entera,
             | y la página entera ya no tiene sesión, así que cae en el
             | login. Es el camino que funciona en los dos casos.
             */
            if ($request->hasHeader('X-Livewire')) {
                abort(403, 'Su usuario fue desactivado.');
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Su usuario fue desactivado. Contacte al administrador.']);
        }

        return $next($request);
    }
}
