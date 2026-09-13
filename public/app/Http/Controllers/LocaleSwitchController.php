<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * El selector de idioma de la barra de arriba.
 *
 * Guarda la elección en dos sitios y no en uno:
 *
 *   · En la SESIÓN, para que el cambio se vea en el próximo clic.
 *   · En la FICHA del usuario, para que mañana entre en el idioma que
 *     eligió y no tenga que volver a cambiarlo.
 *
 * Sin lo segundo, la persona que trabaja en inglés cambia el idioma
 * todos los días al entrar. Es de esas cosas chicas que hacen que
 * alguien deje de usar una funcionalidad.
 */
class LocaleSwitchController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['es', 'en'], true)) {
            return back();
        }

        session(['locale' => $locale]);

        if ($usuario = $request->user()) {
            $usuario->update(['locale' => $locale]);
        }

        return back();
    }
}
