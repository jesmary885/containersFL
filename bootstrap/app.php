<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /* -----------------------------------------------------------------
        | LLENAR LA COMPAÑÍA ACTIVA EN CADA PETICIÓN WEB
        |
        | appendToGroup('web') lo pone al final del grupo 'web', o sea
        | después de que arrancó la sesión y de que Laravel ya sabe quién
        | es el usuario. Ese orden importa: si corriera antes, no habría
        | ni sesión que leer ni usuario que consultar.
        |
        | ── POR QUÉ AL GRUPO COMPLETO Y NO A RUTAS SUELTAS ──
        |
        | Livewire manda todas sus actualizaciones a la ruta /livewire/update,
        | que no es ninguna de las rutas que escribiste en web.php.
        |
        | Si el middleware solo estuviera en tus rutas, pasaría esto: la
        | pantalla carga con datos, el usuario hace clic en "buscar", y la
        | tabla se vacía sola. Porque ese clic viaja por /livewire/update,
        | donde la caja quedó vacía.
        |
        | Es un error muy difícil de diagnosticar. Registrándolo en el
        | grupo entero, no ocurre.
        * -------------------------------------------------------------- */
        $middleware->appendToGroup('web', \App\Http\Middleware\SetCompanyContext::class,
        );

        /* -----------------------------------------------------------------
        | EL IDIOMA DE LA INTERFAZ EN CADA PETICIÓN
        |
        | Sin esta línea el selector de idioma guarda la elección y no
        | pasa nada más: App::setLocale() no se llama nunca, así que la
        | pantalla sigue en el idioma por defecto y el "ES/EN" de la barra
        | siempre muestra lo mismo. El botón parece roto sin estarlo.
        |
        | Al grupo 'web' y no a rutas sueltas, por lo mismo que
        | SetCompanyContext: Livewire viaja por /livewire/update, que no
        | está en web.php. Si no estuviera acá, la página cargaría en
        | inglés y volvería al español en el primer clic de un filtro.
        |
        | appendToGroup lo deja después de StartSession, que es lo que hace
        | falta: antes no habría ni sesión que leer ni usuario que
        | consultar.
        * -------------------------------------------------------------- */
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);

        $middleware->prependToPriorityList(
    before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
    prepend: \App\Http\Middleware\SetCompanyContext::class,
    
);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
