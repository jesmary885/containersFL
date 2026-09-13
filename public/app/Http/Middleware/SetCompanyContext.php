<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * EL QUE LLENA LA CAJA EN CADA PETICIÓN
 * ======================================
 *
 * Un middleware es un filtro por el que pasa toda petición antes de
 * llegar a la pantalla. Este hace una sola cosa, siempre la misma:
 *
 *   1. Mira quién es el usuario que está pidiendo la página.
 *   2. Lee de la sesión qué compañía eligió al entrar.
 *   3. Mete esa compañía en el CompanyContext.
 *
 * ── POR QUÉ HACE FALTA EN CADA PETICIÓN ──
 *
 * La sesión sí se guarda de una página a la siguiente. La caja no: se
 * fabrica limpia cada vez que el navegador pide algo. Así que alguien
 * tiene que rellenarla, y ese alguien es este archivo.
 *
 * Si esto no corre, el usuario ve todas las pantallas vacías aunque
 * haya elegido su compañía correctamente en el login.
 */

class SetCompanyContext
{
    /**
     * Laravel inyecta la caja aquí automáticamente, y por ser
     * singleton es exactamente la misma que van a leer los modelos.
     */
    public function __construct(protected CompanyContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        /* -----------------------------------------------------------
         | Sin usuario no hay nada que hacer.
         |
         | Es el caso de la pantalla de login y de los archivos
         | públicos. No es un error: simplemente se deja pasar con la
         | caja vacía.
         * -------------------------------------------------------- */
        if (! $user) {
            return $next($request);
        }

        /* -----------------------------------------------------------
         | La compañía que el usuario eligió al entrar.
         |
         | currentCompany() lee session('current_company_id') y
         | verifica que el usuario siga teniendo acceso a ella. Si no
         | hay nada en sesión, devuelve la marcada como predeterminada
         | en la tabla company_user.
         * -------------------------------------------------------- */
        $company = $user->currentCompany();

        /* -----------------------------------------------------------
         | Un usuario sin ninguna compañía asignada no puede trabajar.
         |
         | Pasa en dos casos: alguien creó el usuario y olvidó
         | asignarle empresa, o le quitaron el acceso mientras estaba
         | adentro.
         |
         | Se le cierra la sesión y se le manda al login con un
         | mensaje claro. La alternativa —dejarlo navegar viendo todo
         | vacío— haría que llame a soporte diciendo "se borraron
         | todos los datos", que es mucho peor.
         * -------------------------------------------------------- */
        if (! $company) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Su usuario no tiene ninguna empresa asignada. '
                             . 'Contacte al administrador del sistema.',
                ]);
        }

        /* -----------------------------------------------------------
         | Se llena la caja. A partir de esta línea, todas las
         | consultas de Invoice, Estimate, Sale, Rental, Payment,
         | Expense, Trip, Purchase, Commission y DriverSettlement
         | quedan filtradas por esta compañía, solas.
         * -------------------------------------------------------- */
        $this->context->set($company);

        /* -----------------------------------------------------------
         | Se reescribe la sesión con el id confirmado.
         |
         | Cubre el caso de la primera petición después del login,
         | cuando currentCompany() cayó a la predeterminada porque la
         | sesión venía sin nada. Así la siguiente petición ya la
         | encuentra escrita y no tiene que recalcularla.
         * -------------------------------------------------------- */
        $request->session()->put('current_company_id', $company->id);

        return $next($request);
    }
}
