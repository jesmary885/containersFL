<?php

namespace App\Livewire\Roles;

use App\Livewire\Concerns\AuthorizesAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ROLES Y PERMISOS — la matriz
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Una tabla: los módulos en las filas, las acciones en las columnas, y
 * una casilla en cada cruce. Se elige el rol arriba y se marca qué puede
 * hacer.
 *
 * ── POR QUÉ UNA MATRIZ Y NO UNA LISTA DE 80 CASILLAS ──
 *
 * Los permisos se llaman `modulo.accion`: hay 21 módulos y de tres a
 * cinco acciones cada uno. Puestos en una lista vertical son 80 y pico
 * de líneas donde nadie encuentra nada y donde es imposible ver de un
 * golpe si "ventas" puede o no anular facturas.
 *
 * En la matriz esa pregunta se contesta mirando un cruce.
 *
 * ── EL ROL `super_admin` NO SE EDITA ──
 *
 * No porque esté protegido, sino porque no tiene sentido: el
 * `Gate::before` del AppServiceProvider le concede todo antes de que
 * nadie consulte sus permisos. Marcar o desmarcar casillas ahí no
 * cambiaría nada, y una pantalla que acepta cambios que no surten
 * efecto es peor que una pantalla que no los acepta.
 *
 * ── LA CACHÉ DE SPATIE ──
 *
 * Spatie guarda los permisos en caché para no consultarlos en cada
 * petición. Si se cambian sin vaciarla, el sistema sigue aplicando los
 * de antes hasta que la caché expire —por defecto 24 horas— y el
 * usuario jura que la pantalla no guardó. Por eso el
 * `forgetCachedPermissions()` de abajo no es opcional.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'users';

    /** El rol que se está mirando. */
    public string $rolActivo = 'admin';

    /**
     * Los permisos marcados del rol activo, como lista de nombres.
     *
     * Se trabaja sobre una copia en memoria y se escribe a la base solo
     * al pulsar Guardar. Así se pueden marcar quince casillas sin
     * quince viajes al servidor, y "Cancelar" cancela de verdad.
     */
    public array $marcados = [];

    /** Para saber si hay cambios sin guardar. */
    public array $original = [];

    /**
     * Los módulos con sus acciones, en el orden en que se enseñan.
     *
     * Es la misma lista del RoleSeeder. Está repetida a propósito: el
     * seeder define qué permisos EXISTEN y esta pantalla define cómo se
     * AGRUPAN para mirarlos. Si mañana se agrega un módulo, hay que
     * tocarlo en los dos sitios — y el chequeo de abajo avisa si se
     * olvidó uno.
     */
    public const MODULOS = [
        'Comercial' => [
            'customers'   => 'Clientes',
            'estimates'   => 'Presupuestos',
            'sales'       => 'Ventas',
        ],
        'Operaciones' => [
            'containers'  => 'Contenedores',
            'rentals'     => 'Rentas',
            'trips'       => 'Viajes',
            'drivers'     => 'Choferes',
            'vehicles'    => 'Camiones',
        ],
        'Compras' => [
            'suppliers'   => 'Proveedores',
            'purchases'   => 'Compras y releases',
            'depots'      => 'Depósitos',
            'parts'       => 'Insumos y piezas',
        ],
        'Finanzas' => [
            'invoices'    => 'Facturación',
            'payments'    => 'Pagos',
            'expenses'    => 'Gastos',
            'commissions' => 'Comisiones',
            'settlements' => 'Liquidación de choferes',
        ],
        'Sistema' => [
            'reports'     => 'Reportes',
            'catalogs'    => 'Catálogos',
            'settings'    => 'Configuración',
            'users'       => 'Usuarios y roles',
        ],
    ];

    /** Cómo se lee cada acción en pantalla. */
    public const ACCIONES = [
        'view'           => 'Ver',
        'create'         => 'Crear',
        'update'         => 'Editar',
        'delete'         => 'Borrar',
        'send'           => 'Enviar',
        'void'           => 'Anular',
        'approve'        => 'Aprobar',
        'pay'            => 'Pagar',
        'dispatch'       => 'Despachar',
        'export'         => 'Exportar',
        'waive_late_fee' => 'Perdonar mora',
    ];

    public function mount(): void
    {
        $this->exigirPermiso('view');

        $this->cargarRol($this->rolActivo);
    }

    /* =====================================================================
     | CAMBIAR DE ROL
     * ================================================================== */

    public function cargarRol(string $nombre): void
    {
        $rol = Role::where('name', $nombre)->first();

        if (! $rol) {
            return;
        }

        $this->rolActivo = $nombre;

        $this->marcados = $rol->permissions->pluck('name')->all();
        $this->original = $this->marcados;
    }

    /* =====================================================================
     | ATAJOS DE MARCADO
     |
     | Marcar cuarenta casillas a mano es la diferencia entre que alguien
     | mantenga los permisos y que no los toque nunca.
     * ================================================================== */

    /** Todas las acciones de un módulo. */
    public function marcarModulo(string $modulo, bool $encender): void
    {
        $delModulo = $this->permisosDisponibles()
            ->filter(fn ($p) => str_starts_with($p, $modulo.'.'))
            ->all();

        $this->marcados = $encender
            ? array_values(array_unique([...$this->marcados, ...$delModulo]))
            : array_values(array_diff($this->marcados, $delModulo));
    }

    /** Todo un bloque del menú (Comercial, Finanzas...). */
    public function marcarBloque(string $bloque, bool $encender): void
    {
        foreach (array_keys(self::MODULOS[$bloque] ?? []) as $modulo) {
            $this->marcarModulo($modulo, $encender);
        }
    }

    /**
     * Solo lectura en todo: marca los `view` y quita el resto.
     *
     * Es el caso de Michael, textual del levantamiento: "el dueño
     * consulta sin capturar".
     */
    public function soloLectura(): void
    {
        $this->marcados = $this->permisosDisponibles()
            ->filter(fn ($p) => str_ends_with($p, '.view'))
            ->values()
            ->all();
    }

    public function descartar(): void
    {
        $this->marcados = $this->original;
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    public function guardar(): void
    {
        $this->exigirPermiso('update');

        if ($this->rolActivo === 'super_admin') {
            session()->flash('error',
                'El super administrador tiene acceso total por diseño. Sus permisos '
                .'no se editan desde aquí.');

            return;
        }

        DB::transaction(function () {
            $rol = Role::where('name', $this->rolActivo)->firstOrFail();

            /*
             | Se filtra contra los permisos que existen de verdad. Sin
             | esto, un nombre inventado llegando desde el navegador haría
             | reventar syncPermissions() con un error de Spatie que no le
             | dice nada a nadie.
             */
            $validos = $this->permisosDisponibles()
                ->intersect($this->marcados)
                ->values()
                ->all();

            $rol->syncPermissions($validos);
        });

        // Sin esto los cambios no surten efecto hasta que expire la caché.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->original = $this->marcados;

        session()->flash('exito',
            'Permisos de "'.$this->etiquetaDelRol($this->rolActivo).'" guardados. '
            .'Surten efecto en el siguiente clic de cada usuario.');
    }

    /* =====================================================================
     | CONSULTAS DE APOYO
     * ================================================================== */

    /** Todos los permisos que existen, cacheados por petición. */
    protected function permisosDisponibles()
    {
        static $cache = null;

        return $cache ??= Permission::pluck('name');
    }

    public function getHayCambiosProperty(): bool
    {
        /*
         | Las copias son obligatorias.
         |
         | sort() ordena POR REFERENCIA. Si se llamara sobre
         | $this->marcados directamente, este getter —que corre en cada
         | render— reordenaría una propiedad de Livewire como efecto
         | secundario de una simple consulta.
         |
         | Livewire compara el estado antes y después de cada petición
         | para saber qué mandar al navegador: una propiedad que cambia
         | de orden sola produce actualizaciones fantasma y casillas que
         | parpadean.
         */
        $ahora    = $this->marcados;
        $original = $this->original;

        sort($ahora);
        sort($original);

        return $ahora !== $original;
    }

    /**
     * Las acciones que existen para un módulo.
     *
     * Se saca de la base y no de una lista escrita a mano: así la
     * pantalla enseña exactamente lo que el seeder creó, y si alguien
     * agrega un permiso nuevo aparece solo.
     */
    public function accionesDe(string $modulo): array
    {
        return $this->permisosDisponibles()
            ->filter(fn ($p) => str_starts_with($p, $modulo.'.'))
            ->map(fn ($p) => substr($p, strlen($modulo) + 1))
            ->values()
            ->all();
    }

    public function etiquetaDelRol(string $nombre): string
    {
        return \App\Livewire\Users\Form::ETIQUETAS[$nombre]['label'] ?? $nombre;
    }

    public function render()
    {
        /* -----------------------------------------------------------------
         | ¿HAY PERMISOS QUE LA MATRIZ NO ESTÁ ENSEÑANDO?
         |
         | Si alguien agrega un módulo al RoleSeeder y se olvida de
         | ponerlo en self::MODULOS, sus permisos existen en la base pero
         | no aparecen en esta pantalla. Nadie podría asignarlos y nadie
         | se enteraría.
         |
         | Este chequeo los lista en un aviso. Cuesta una consulta y
         | ahorra un diagnóstico de media tarde.
         * -------------------------------------------------------------- */
        $enLaMatriz = collect(self::MODULOS)->flatMap(fn ($m) => array_keys($m));

        $huerfanos = $this->permisosDisponibles()
            ->map(fn ($p) => explode('.', $p)[0])
            ->unique()
            ->diff($enLaMatriz)
            ->values()
            ->all();

        /* -----------------------------------------------------------------
         | EL ORDEN DE LAS PESTAÑAS
         |
         | De más permisos a menos, que es como se leen: del dueño al
         | chofer. Alfabético pondría "accounting" primero y
         | "super_admin" al final, que no significa nada.
         |
         | Se ordena en PHP y no con un orderByRaw("FIELD(...)") porque
         | FIELD() es de MySQL: dejaría el proyecto atado al motor por
         | una lista de seis nombres. Los roles que no estén en la lista
         | —si mañana se crean a medida— caen al final, no desaparecen.
         * -------------------------------------------------------------- */
        $orden = ['super_admin', 'admin', 'accounting', 'operations', 'sales', 'driver'];

        $roles = Role::get(['id', 'name'])
            ->sortBy(fn ($rol) => array_search($rol->name, $orden) === false
                ? PHP_INT_MAX
                : array_search($rol->name, $orden))
            ->values();

        return view('livewire.roles.index', [
            'roles'     => $roles,
            'bloques'   => self::MODULOS,
            'acciones'  => self::ACCIONES,
            'huerfanos' => $huerfanos,
        ]);
    }
}
