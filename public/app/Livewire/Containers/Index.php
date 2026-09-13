<?php

namespace App\Livewire\Containers;

use App\Enums\ContainerStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Container;
use App\Models\ContainerCondition;
use App\Models\ContainerGrade;
use App\Models\ContainerSize;
use App\Models\Location;
use App\Support\CompanyContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * INVENTARIO DE CONTENEDORES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Operaciones › Contenedores.
 *
 * Es la pantalla más importante del sistema para el dueño, y la que peor
 * resuelve el Excel de hoy: el Excel dice 416 unidades en stock y
 * físicamente no están.
 *
 * ── DE DÓNDE SALE EL STOCK ──
 *
 * De los contenedores, uno por uno. NUNCA de un contador que alguien
 * suma y resta a mano.
 *
 * "Disponible" no es un campo: es el resultado de preguntar si está en
 * yarda Y no tiene ninguna venta ni renta encima (RB-019). Eso lo
 * responde el scope `available()` del modelo, y por eso el número de
 * arriba y la lista de abajo no se pueden contradecir: salen de la misma
 * consulta.
 *
 * ── POR QUÉ FILTRA POR EMPRESA Y POR QUÉ SE DICE EN PANTALLA ──
 *
 * `containers` no usa el trait BelongsToCompany: es un maestro
 * compartido con dos columnas de compañía.
 *
 *   owner_company_id    quién puso el dinero
 *   billing_company_id  quién lo vende o lo renta
 *
 * Casi siempre son la misma, FLCHR, porque RST es la transportista
 * (RB-002). Esta pantalla filtra por la que FACTURA, que es la que
 * importa para vender.
 *
 * El efecto es que alguien parado en RST puede ver cero unidades
 * teniendo la yarda llena. Sin un aviso en pantalla, eso se lee como
 * "el sistema perdió el inventario". Por eso, cuando la lista sale
 * vacía, se cuenta cuántas hay del otro lado y se dice.
 *
 * ── LO QUE ESTA PANTALLA NO HACE ──
 *
 * No borra. Un contenedor tiene movimientos, ventas y gastos colgando.
 * Se marca como desguazado o perdido, que es lo que pasó de verdad.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination, AuthorizesAccess;

    protected string $permisoBase = 'containers';

    /* =====================================================================
     | LOS FILTROS
     * ================================================================== */

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'medida', except: '')]
    public string $medida = '';

    #[Url(as: 'condicion', except: '')]
    public string $condicion = '';

    #[Url(as: 'grado', except: '')]
    public string $grado = '';

    #[Url(as: 'ubicacion', except: '')]
    public string $ubicacion = '';

    /** Filtros de un clic desde los contadores de arriba. */
    #[Url(as: 'marca', except: '')]
    public string $marca = '';

    #[Url(as: 'orden', except: 'created_at')]
    public string $ordenarPor = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $direccion = 'desc';

    public int $porPagina = 20;

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    public function updatingBuscar(): void    { $this->resetPage(); }
    public function updatingEstado(): void    { $this->resetPage(); }
    public function updatingMedida(): void    { $this->resetPage(); }
    public function updatingCondicion(): void { $this->resetPage(); }
    public function updatingGrado(): void     { $this->resetPage(); }
    public function updatingUbicacion(): void { $this->resetPage(); }

    public function ordenar(string $columna): void
    {
        if ($this->ordenarPor === $columna) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->direccion  = 'asc';
        }

        $this->resetPage();
    }

    /** Pulsar el mismo contador otra vez lo apaga. */
    public function filtrarPor(string $marca): void
    {
        $this->marca  = $this->marca === $marca ? '' : $marca;
        $this->estado = '';

        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'estado', 'medida', 'condicion', 'grado', 'ubicacion', 'marca']);

        $this->ordenarPor = 'created_at';
        $this->direccion  = 'desc';

        $this->resetPage();
    }

    public function getHayFiltrosProperty(): bool
    {
        return filled($this->buscar) || filled($this->estado) || filled($this->medida)
            || filled($this->condicion) || filled($this->grado) || filled($this->ubicacion)
            || filled($this->marca);
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        $columnasValidas = [
            'created_at', 'container_number', 'internal_code',
            'status', 'received_at', 'list_price',
        ];

        $orden = in_array($this->ordenarPor, $columnasValidas, true)
            ? $this->ordenarPor
            : 'created_at';

        /* -----------------------------------------------------------------
         | LA CONSULTA BASE
         |
         | Se arma una vez y se reutiliza para la lista y para los
         | contadores. Si los contadores usaran otra consulta, tarde o
         | temprano dirían un número distinto al de la tabla y nadie
         | sabría cuál creer.
         * -------------------------------------------------------------- */
        $base = fn () => Container::query()
            ->forBillingCompany($empresa?->id)
            ->with(['size', 'condition', 'grade', 'type', 'location']);

        $unidades = $base()
            ->when($this->buscar,    fn ($q) => $q->search($this->buscar))
            ->when($this->estado,    fn ($q) => $q->where('status', $this->estado))
            ->when($this->medida,    fn ($q) => $q->where('container_size_id', $this->medida))
            ->when($this->condicion, fn ($q) => $q->where('container_condition_id', $this->condicion))
            ->when($this->grado,     fn ($q) => $q->where('container_grade_id', $this->grado))
            ->when($this->ubicacion, fn ($q) => $q->where('location_id', $this->ubicacion))

            /* -------------------------------------------------------------
             | LOS FILTROS DE LOS CONTADORES
             |
             | `disponibles` no es un estado: es el scope available(), que
             | además de mirar el estado comprueba que no tenga venta ni
             | renta encima. Es la diferencia entre "dice en yarda" y "se
             | puede vender hoy".
             * ---------------------------------------------------------- */
            ->when($this->marca === 'disponibles', fn ($q) => $q->available())
            ->when($this->marca === 'por_llegar',  fn ($q) => $q->whereIn('status', [
                ContainerStatus::OnOrder->value,
                ContainerStatus::AtSupplier->value,
                ContainerStatus::InTransit->value,
            ]))
            ->when($this->marca === 'exportables', fn ($q) => $q->exportEligible())
            ->when($this->marca === 'sin_precio',  fn ($q) => $q->whereNull('list_price'))

            ->orderBy($orden, $this->direccion === 'asc' ? 'asc' : 'desc')
            ->paginate($this->porPagina);

        /* -----------------------------------------------------------------
         | EL VALOR DEL INVENTARIO
         |
         | Suma de compra + recogida + reacondicionamiento de lo que está
         | en yarda. Es lo que la empresa tiene metido en el patio ahora
         | mismo, y es el número que ningún Excel de estos contesta sin
         | media hora de trabajo.
         |
         | Se suma en la base y no en PHP: traer mil filas para sumar tres
         | columnas es pedirle a la base que haga de disco duro.
         * -------------------------------------------------------------- */
        $valorEnYarda = (float) $base()
            ->inYard()
            ->selectRaw('COALESCE(SUM(acquisition_cost),0) + COALESCE(SUM(pickup_cost),0) '
                       .'+ COALESCE(SUM(reconditioning_cost),0) as total')
            ->value('total');

        return view('livewire.containers.index', [

            'unidades' => $unidades,
            'empresa'  => $empresa,

            'resumen' => [
                'disponibles' => $base()->available()->count(),
                'enYarda'     => $base()->inYard()->count(),
                'porLlegar'   => $base()->whereIn('status', [
                                     ContainerStatus::OnOrder->value,
                                     ContainerStatus::AtSupplier->value,
                                     ContainerStatus::InTransit->value,
                                 ])->count(),
                'exportables' => $base()->available()->exportEligible()->count(),
                'sinPrecio'   => $base()->available()->whereNull('list_price')->count(),
                'valor'       => $valorEnYarda,
            ],

            /*
             | Cuántas unidades hay del otro lado.
             |
             | Solo se consulta si esta pantalla salió vacía y sin
             | filtros: es el caso de alguien parado en RST mirando un
             | inventario que es de FLCHR. En cualquier otro caso no se
             | gasta la consulta.
             */
            'hayEnLaOtraEmpresa' => ($unidades->total() === 0 && ! $this->hayFiltros && $empresa)
                ? Container::where('billing_company_id', '!=', $empresa->id)->count()
                : 0,

            'estados'    => ContainerStatus::options(),
            'medidas'    => ContainerSize::active()->get(),
            'condiciones'=> ContainerCondition::active()->get(),
            'grados'     => ContainerGrade::active()->get(),
            'ubicaciones'=> Location::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
