<?php

use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\LoginController;
use App\Livewire\Dashboard;
use App\Livewire\Placeholder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Livewire\Estimates\Form   as EstimateForm;
use App\Livewire\Estimates\Index  as EstimateIndex;
use App\Livewire\Estimates\Show   as EstimateShow;

use App\Livewire\Auth\Login;


/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get('/', [LoginController::class, 'login'])->name('login');

});

/*
|--------------------------------------------------------------------------
| RUTAS CON SESIÓN INICIADA
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    /* ---------------------------------------------------------------
     | CAMBIO DE EMPRESA
     |
     | POST y no GET a propósito. Un GET se puede disparar con un
     | enlace o una imagen escondida en un correo; un POST necesita el
     | token CSRF que Laravel pone en el formulario.
     |
     | Cambiar la empresa activa modifica el estado de la sesión, y
     | todo lo que modifica estado va por POST.
     * ------------------------------------------------------------ */
    Route::post('/empresa/cambiar', CompanySwitchController::class)
        ->name('company.switch');

    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    /* ---------------------------------------------------------------
     | MÓDULOS PENDIENTES
     |
     | Todos apuntan al mismo componente provisional. A medida que
     | construimos cada módulo, se va reemplazando Placeholder::class
     | por el componente real. El nombre de la ruta NO cambia, así que
     | el sidebar no hay que tocarlo nunca.
     * ------------------------------------------------------------ */

    // COMERCIAL
     Route::get('/comercial/clientes',      Placeholder::class)->name('comercial.clientes.index');
    
       Route::get('/comercial/presupuestos', EstimateIndex::class)
       ->name('comercial.presupuestos.index');

      Route::get('/comercial/presupuestos/nuevo', EstimateForm::class)
          ->name('comercial.presupuestos.create');

     Route::get('/comercial/presupuestos/{estimate}/editar', EstimateForm::class)
          ->name('comercial.presupuestos.edit');

      Route::get('/comercial/presupuestos/{estimate}', EstimateShow::class)
          ->name('comercial.presupuestos.show');


    Route::get('/comercial/ventas',        Placeholder::class)->name('comercial.ventas.index');

    // OPERACIONES
    Route::get('/operaciones/contenedores', Placeholder::class)->name('operaciones.contenedores.index');
    Route::get('/operaciones/rentas',       Placeholder::class)->name('operaciones.rentas.index');
    Route::get('/operaciones/viajes',       Placeholder::class)->name('operaciones.viajes.index');
    Route::get('/operaciones/releases',     Placeholder::class)->name('operaciones.releases.index');
    Route::get('/operaciones/choferes',     Placeholder::class)->name('operaciones.choferes.index');

    // INVENTARIO
    Route::get('/inventario/insumos',  Placeholder::class)->name('inventario.insumos.index');
    Route::get('/inventario/camiones', Placeholder::class)->name('inventario.camiones.index');

    // FINANZAS
    Route::get('/finanzas/facturacion', Placeholder::class)->name('finanzas.facturacion.index');
    Route::get('/finanzas/pagos',       Placeholder::class)->name('finanzas.pagos.index');
    Route::get('/finanzas/gastos',      Placeholder::class)->name('finanzas.gastos.index');

    // ADMINISTRACIÓN
    Route::get('/administracion/usuarios', Placeholder::class)->name('administracion.usuarios.index');
    Route::get('/administracion/roles',    Placeholder::class)->name('administracion.roles.index');

    Route::get('/reportes',      Placeholder::class)->name('reportes.index');
    Route::get('/configuracion', Placeholder::class)->name('configuracion.index');

});