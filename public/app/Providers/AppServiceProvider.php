<?php

namespace App\Providers;

use App\Models;
use App\Observers;
use App\Support\CompanyContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
        protected array $observers = [
        Models\Invoice::class                 => Observers\InvoiceObserver::class,
        Models\InvoiceItem::class             => Observers\InvoiceItemObserver::class,
        Models\Expense::class                 => Observers\ExpenseObserver::class,
        Models\ExpensePayment::class          => Observers\ExpensePaymentObserver::class,
        Models\CommissionPayment::class       => Observers\CommissionPaymentObserver::class,
        Models\PurchaseItem::class            => Observers\PurchaseItemObserver::class,
        Models\TaxExemptionCertificate::class => Observers\TaxExemptionCertificateObserver::class,
        Models\ExportCertificate::class       => Observers\ExportCertificateObserver::class,
        Models\Container::class               => Observers\ContainerObserver::class,
        Models\DriverSettlementItem::class    => Observers\DriverSettlementItemObserver::class,
        Models\Estimate::class                => Observers\EstimateObserver::class,
        Models\EstimateItem::class            => Observers\EstimateItemObserver::class,
        Models\Payment::class                 => Observers\PaymentObserver::class,

        /*
         | Genera `customer_number` y `display_name`, que son NOT NULL y
         | no los llenaba nadie. Sin esto, guardar un cliente desde la
         | pantalla nueva falla con un error de SQL.
         */
        Models\Customer::class                => Observers\CustomerObserver::class,
    ];

    public function register(): void
    {
        /**
         * singleton = una sola instancia por petición.
         *
         * Es lo que permite que el trait BelongsToCompany y el selector
         * del header estén hablando del mismo objeto. Con bind() cada
         * app(CompanyContext::class) crearía uno nuevo y vacío.
         */
        $this->app->singleton(CompanyContext::class);
    }

    public function boot(): void
    {
        foreach ($this->observers as $model => $observer) {
            $model::observe($observer);
        }

             /* -----------------------------------------------------------------
         | LA PAGINACIÓN, CON ESTILO DE BOOTSTRAP
         |
         | Laravel trae la paginación pintada con Tailwind de fábrica. El
         | login del sistema sí usa Tailwind, pero las pantallas de adentro
         | están armadas con AdminLTE, que es Bootstrap.
         |
         | Sin esta línea, los botones "anterior / siguiente" del listado
         | de presupuestos salen sin estilo: unos enlaces azules sueltos
         | encima de una tabla bien maquetada. Se nota mucho.
         |
         * -------------------------------------------------------------- */
        Paginator::useBootstrapFive();

        $this->registrarPermisos();

        $this->compartirEmpresaConLasVistas();
    }

    /**
     * EL SUPER ADMINISTRADOR PASA POR ENCIMA DE TODO
     * ==============================================
     *
     * `Gate::before` corre ANTES que cualquier comprobación de permiso.
     * Si devuelve true, la autorización se concede sin mirar nada más;
     * si devuelve null, el sistema sigue su curso normal y pregunta a
     * Spatie.
     *
     * ── POR QUÉ HACE FALTA ──
     *
     * Sin esto, el super administrador necesitaría tener asignados los
     * 80 y pico permisos uno a uno. Y cada vez que se agregue un módulo
     * nuevo —con sus cuatro o cinco permisos— habría que acordarse de
     * volver a asignárselos. El día que alguien se olvide, el dueño del
     * sistema se queda fuera de una pantalla sin entender por qué.
     *
     * ── OJO CON EL `null` ──
     *
     * Tiene que devolver `null` y NO `false` cuando el usuario no es
     * super admin. Un `false` aquí significa "denegado, no preguntes
     * más", y eso cerraría el sistema entero para todos los demás.
     * Es un error de una sola letra que deja a todo el mundo fuera.
     */
    protected function registrarPermisos(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
    

    /**
     * PONE LA EMPRESA ACTIVA AL ALCANCE DE LAS VISTAS
     * ================================================
     *
     * Sin esto, cada pantalla que quiera mostrar el nombre o el color
     * de la empresa tendría que ir a buscarlo por su cuenta. Con esto,
     * la variable $empresaActual simplemente está ahí.
     *
     * Un "view composer" es una instrucción que dice: "cada vez que se
     * dibuje esta vista, primero pásale estos datos".
     */
    protected function compartirEmpresaConLasVistas(): void
    {
        /* -----------------------------------------------------------
         | PARA TODAS LAS VISTAS: la empresa activa.
         |
         | El '*' significa todas. Se puede hacer sin miedo porque
         | esto NO consulta la base de datos: solo lee la caja que el
         | middleware ya llenó al principio de la petición.
         |
         | En el login sale null, porque ahí todavía no hay empresa.
         | Las vistas tienen que estar preparadas para eso, por eso más
         | abajo verás $empresaActual?->name con el signo de pregunta.
         * -------------------------------------------------------- */
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            $view->with('empresaActual', app(CompanyContext::class)->get());
        });

        /* -----------------------------------------------------------
         | SOLO PARA EL LAYOUT: la lista de empresas del usuario.
         |
         | Esta sí consulta la base, así que se limita al layout, que
         | se dibuja una vez por página. Si la pusiéramos en el '*' de
         | arriba, se ejecutaría la misma consulta veinte o treinta
         | veces por pantalla, una por cada pedacito de vista.
         |
         | Es la diferencia entre una página que carga y una que se
         | arrastra.
         * -------------------------------------------------------- */
        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            $view->with(
                'empresasDisponibles',
                auth()->check()
                    ? auth()->user()->companies()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                    : collect(),
            );
        });
    }
}
