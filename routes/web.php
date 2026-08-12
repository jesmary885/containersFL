<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Livewire\Auth\Login;



// Route::middleware('guest')->group(function () {
//     Route::get('/login', Login::class)->name('login');
// });

Route::middleware('guest')->group(function () {

    Route::get('/', [LoginController::class, 'login'])->name('login');

});

Route::middleware('auth')->group(function () {
     Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('login');
    })->name('logout');

     Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');

     Route::view('/comercial/clientes', 'placeholder')->name('comercial.clientes.index');
    Route::view('/comercial/presupuestos', 'placeholder')->name('comercial.presupuestos.index');
    Route::view('/comercial/ventas', 'placeholder')->name('comercial.ventas.index');

    Route::view('/operaciones/contenedores', 'placeholder')->name('operaciones.contenedores.index');
    Route::view('/operaciones/rentas', 'placeholder')->name('operaciones.rentas.index');
    Route::view('/operaciones/viajes', 'placeholder')->name('operaciones.viajes.index');
    Route::view('/operaciones/releases', 'placeholder')->name('operaciones.releases.index');
    Route::view('/operaciones/choferes', 'placeholder')->name('operaciones.choferes.index');

    Route::view('/inventario/insumos', 'placeholder')->name('inventario.insumos.index');
    Route::view('/inventario/camiones', 'placeholder')->name('inventario.camiones.index');

    Route::view('/finanzas/facturacion', 'placeholder')->name('finanzas.facturacion.index');
    Route::view('/finanzas/pagos', 'placeholder')->name('finanzas.pagos.index');
    Route::view('/finanzas/gastos', 'placeholder')->name('finanzas.gastos.index');

    Route::view('/administracion/usuarios', 'placeholder')->name('administracion.usuarios.index');
    Route::view('/administracion/roles', 'placeholder')->name('administracion.roles.index');

    Route::view('/reportes', 'placeholder')->name('reportes.index');
    Route::view('/configuracion', 'placeholder')->name('configuracion.index');

});

