<?php

use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\LocaleSwitchController;
use App\Http\Controllers\LoginController;
use App\Livewire\Dashboard;
use App\Livewire\Placeholder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


use App\Livewire\Estimates\Form   as EstimateForm;
use App\Livewire\Estimates\Index  as EstimateIndex;
use App\Livewire\Estimates\Show   as EstimateShow;

use App\Livewire\Invoices\Form  as InvoiceForm;
use App\Livewire\Invoices\Index as InvoiceIndex;
use App\Livewire\Invoices\Show  as InvoiceShow;

use App\Livewire\Payments\Form  as PaymentForm;
use App\Livewire\Payments\Index as PaymentIndex;
use App\Livewire\Payments\Show  as PaymentShow;

use App\Livewire\Auth\Login;

use App\Livewire\Customers\Form  as CustomerForm;
use App\Livewire\Customers\Index as CustomerIndex;
use App\Livewire\Customers\Show  as CustomerShow;

use App\Livewire\Users\Form  as UserForm;
use App\Livewire\Users\Index as UserIndex;

use App\Livewire\Roles\Index as RoleIndex;


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


     /*
    | El selector de idioma de la barra de arriba.
    | Guarda en sesión y en la ficha del usuario. Ver el controlador.
    */
    Route::get('/locale/{locale}', LocaleSwitchController::class)->name('locale.switch');


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
     |
     | ── EL `can:` DE CADA RUTA ──
     |
     | Impide ABRIR la pantalla si al usuario le falta el permiso.
     |
     | ⚠️ NO es suficiente por sí solo. Livewire manda las acciones a
     | /livewire/update, que es otra ruta y no lleva este `can:`
     | encima: con la pantalla ya abierta, el método se puede llamar
     | igual. Por eso cada componente exige además el permiso dentro
     | del método, con el trait AuthorizesAccess.
     |
     | Los dos hacen falta. El de aquí para que el menú sea honesto,
     | el del componente para que sea seguro.
     * ------------------------------------------------------------ */

    // COMERCIAL

    /* ---------------------------------------------------------------
     | CLIENTES
     |
     | Ojo con el ORDEN: /nuevo va ANTES que /{customer}.
     |
     | Laravel toma la primera ruta que coincida, y `{customer}` acepta
     | cualquier cosa — incluida la palabra "nuevo". Al revés, abrir
     | "Nuevo cliente" buscaría un cliente llamado "nuevo", no lo
     | encontraría y devolvería un 404 que no señala a ninguna parte.
     * ------------------------------------------------------------ */
    Route::middleware('can:customers.view')->group(function () {

        Route::get('/comercial/clientes', CustomerIndex::class)
            ->name('comercial.clientes.index');

        Route::get('/comercial/clientes/nuevo', CustomerForm::class)
            ->middleware('can:customers.create')
            ->name('comercial.clientes.create');

        Route::get('/comercial/clientes/{customer}/editar', CustomerForm::class)
            ->middleware('can:customers.update')
            ->name('comercial.clientes.edit');

        Route::get('/comercial/clientes/{customer}', CustomerShow::class)
            ->name('comercial.clientes.show');
    });

    Route::middleware('can:estimates.view')->group(function () {

        Route::get('/comercial/presupuestos', EstimateIndex::class)
            ->name('comercial.presupuestos.index');

        Route::get('/comercial/presupuestos/nuevo', EstimateForm::class)
            ->middleware('can:estimates.create')
            ->name('comercial.presupuestos.create');

        Route::get('/comercial/presupuestos/{estimate}/editar', EstimateForm::class)
            ->middleware('can:estimates.update')
            ->name('comercial.presupuestos.edit');

        Route::get('/comercial/presupuestos/{estimate}', EstimateShow::class)
            ->name('comercial.presupuestos.show');
    });

    Route::get('/comercial/ventas', Placeholder::class)
        ->middleware('can:sales.view')
        ->name('comercial.ventas.index');

    // OPERACIONES
    Route::get('/operaciones/contenedores', Placeholder::class)
        ->middleware('can:containers.view')->name('operaciones.contenedores.index');

    Route::get('/operaciones/rentas', Placeholder::class)
        ->middleware('can:rentals.view')->name('operaciones.rentas.index');

    Route::get('/operaciones/viajes', Placeholder::class)
        ->middleware('can:trips.view')->name('operaciones.viajes.index');

    Route::get('/operaciones/releases', Placeholder::class)
        ->middleware('can:purchases.view')->name('operaciones.releases.index');

    Route::get('/operaciones/choferes', Placeholder::class)
        ->middleware('can:drivers.view')->name('operaciones.choferes.index');

    /* ---------------------------------------------------------------
     | CAMIONES
     |
     | Estaba en /inventario/camiones. Se muda a Operaciones porque es
     | donde lo pone la pantalla de Roles, y el menu ahora dice lo
     | mismo que Roles.
     * ------------------------------------------------------------ */
    Route::get('/operaciones/camiones', Placeholder::class)
        ->middleware('can:vehicles.view')->name('operaciones.camiones.index');

    /* ---------------------------------------------------------------
     | COMPRAS
     |
     | Las cuatro rutas son nuevas y las cuatro caen en la pantalla
     | provisional.
     |
     | Antes el menu tenia estas entradas apuntando a OTROS modulos:
     | "Proveedores" llevaba a Contenedores, "Compras" a Rentas y
     | "Releases" a Viajes. Funcionaba, pero le mentia al usuario.
     |
     | Ahora cada una llega a su propia direccion y la pantalla dice
     | "en construccion", que es la verdad y no confunde a nadie.
     * ------------------------------------------------------------ */
    Route::get('/compras/proveedores', Placeholder::class)
        ->middleware('can:suppliers.view')->name('compras.proveedores.index');

    Route::get('/compras/compras', Placeholder::class)
        ->middleware('can:purchases.view')->name('compras.compras.index');

    Route::get('/compras/depositos', Placeholder::class)
        ->middleware('can:depots.view')->name('compras.depositos.index');

    Route::get('/compras/insumos', Placeholder::class)
        ->middleware('can:parts.view')->name('compras.insumos.index');

    // FACTURACION

    Route::middleware('can:invoices.view')->group(function () {

        Route::get('/finanzas/facturacion', InvoiceIndex::class)
            ->name('finanzas.facturacion.index');

        Route::get('/finanzas/facturacion/nueva', InvoiceForm::class)
            ->middleware('can:invoices.create')
            ->name('finanzas.facturacion.create');

        Route::get('/finanzas/facturacion/{invoice}/editar', InvoiceForm::class)
            ->middleware('can:invoices.update')
            ->name('finanzas.facturacion.edit');

        Route::get('/finanzas/facturacion/{invoice}', InvoiceShow::class)
            ->name('finanzas.facturacion.show');
    });

    //PAGOS

    Route::middleware('can:payments.view')->group(function () {

        Route::get('/finanzas/pagos', PaymentIndex::class)
            ->name('finanzas.pagos.index');

        Route::get('/finanzas/pagos/nuevo', PaymentForm::class)
            ->middleware('can:payments.create')
            ->name('finanzas.pagos.create');

        Route::get('/finanzas/pagos/{payment}', PaymentShow::class)
            ->name('finanzas.pagos.show');
    });

    /* ---------------------------------------------------------------
     | LO QUE FALTABA DE FINANZAS
     |
     | Gastos, Comisiones y Liquidacion de choferes existen en Roles
     | desde el principio y no tenian ruta: el menu ensenaba "Gastos"
     | como un enlace a "#", que no lleva a ningun lado y parece roto.
     * ------------------------------------------------------------ */
    Route::get('/finanzas/gastos', Placeholder::class)
        ->middleware('can:expenses.view')->name('finanzas.gastos.index');

    Route::get('/finanzas/comisiones', Placeholder::class)
        ->middleware('can:commissions.view')->name('finanzas.comisiones.index');

    Route::get('/finanzas/liquidaciones', Placeholder::class)
        ->middleware('can:settlements.view')->name('finanzas.liquidaciones.index');

    /* ---------------------------------------------------------------
     | ADMINISTRACIÓN
     |
     | Usuarios y roles comparten el permiso `users.*` a propósito: son
     | la misma responsabilidad. Quien puede crear un usuario puede
     | decidir qué hace ese rol; separarlo daría la combinación inútil
     | de poder crear gente y no poder darle permisos.
     * ------------------------------------------------------------ */

    Route::middleware('can:users.view')->group(function () {

        Route::get('/administracion/usuarios', UserIndex::class)
            ->name('administracion.usuarios.index');

        Route::get('/administracion/usuarios/nuevo', UserForm::class)
            ->middleware('can:users.create')
            ->name('administracion.usuarios.create');

        Route::get('/administracion/usuarios/{user}/editar', UserForm::class)
            ->middleware('can:users.update')
            ->name('administracion.usuarios.edit');

        Route::get('/administracion/roles', RoleIndex::class)
            ->name('administracion.roles.index');
    });

    /* ---------------------------------------------------------------
     | DESCARGAR UN DOCUMENTO ADJUNTO
     |
     | No se expone nunca la ruta real del archivo. El controlador
     | comprueba el permiso y despues entrega el contenido.
     |
     | Se hace con un controlador y no con Storage::temporaryUrl()
     | porque el disco configurado hoy es 'local', y el disco local de
     | Laravel NO sabe generar enlaces temporales: revienta con
     | "This driver does not support creating temporary URLs".
     * ------------------------------------------------------------ */
    Route::get('/documentos/{document}/descargar', DocumentDownloadController::class)
        ->name('documentos.descargar');

    Route::get('/reportes', Placeholder::class)
        ->middleware('can:reports.view')->name('reportes.index');

    Route::get('/sistema/catalogos', Placeholder::class)
        ->middleware('can:catalogs.view')->name('sistema.catalogos.index');

    Route::get('/configuracion', Placeholder::class)
        ->middleware('can:settings.view')->name('configuracion.index');

});