<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * CAMBIAR DE EMPRESA SIN CERRAR SESIÓN
 * =====================================
 *
 * Un controlador de un solo método (__invoke). Recibe el id de la
 * empresa elegida en el header, verifica que el usuario tenga acceso
 * y recarga el sistema apuntando a esa empresa.
 */


class CompanySwitchController extends Controller
{
    public function __invoke(Request $request)
    {
        $datos = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        $usuario = $request->user();

        /* -----------------------------------------------------------
         | switchCompany() verifica la pertenencia antes de guardar
         | nada en la sesión.
         |
         | Esa verificación es la que impide que alguien cambie a mano
         | el número que viaja en el formulario y entre a ver la
         | contabilidad de la otra empresa. El botón del menú no es la
         | protección; el botón se puede saltar. La protección es esta
         | línea.
         * -------------------------------------------------------- */
        if (! $usuario->switchCompany($datos['company_id'])) {
            return back()->with('error', 'No tiene acceso a la empresa seleccionada.');
        }

        $empresa = $usuario->currentCompany();

        /* -----------------------------------------------------------
         | Se vuelve al dashboard, NO a la página anterior.
         |
         | Suena a detalle pero no lo es. Imagina que estabas viendo la
         | factura 1358 de FLCHR y cambias a RS Transport. La página
         | anterior es esa factura, que ya no te pertenece: con el
         | filtro activo, la consulta no la encuentra y te sale un 404
         | justo después de cambiar de empresa.
         |
         | El dashboard siempre existe en las dos. Es el único destino
         | que nunca falla.
         * -------------------------------------------------------- */
        return redirect()
            ->route('dashboard')
            ->with('status', 'Ahora está trabajando en '.$empresa->name.'.');
    }
}
