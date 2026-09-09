<?php


namespace App\Livewire\Estimates;

use App\Enums\EstimateStatus;
use App\Models\Estimate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;


/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LISTADO DE PRESUPUESTOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * La pantalla que se abre desde el menú Comercial › Presupuestos.
 *
 * ── LO QUE NO HACE FALTA ESCRIBIR AQUÍ ──
 *
 * No hay ni un solo "where company_id = ...". El modelo Estimate usa el
 * trait BelongsToCompany, que le pone ese filtro solo a TODAS las
 * consultas.
 *
 * Eso significa que un usuario trabajando en RS Transport no puede ver
 * los presupuestos de FLCHR ni cambiando el número en la barra de
 * direcciones: la consulta simplemente no los encuentra.
 *
 * Es la diferencia entre esconder un dato y no traerlo. Lo primero se
 * salta; lo segundo no.
 */

#[Layout('layouts.app')]
class Index extends Component
{
    /*
     | WithPagination le da al componente los botones de "anterior /
     | siguiente" y el método resetPage() que se usa más abajo.
     */
    use WithPagination;

    /* =====================================================================
     | LOS FILTROS
     |
     | El atributo #[Url] hace que cada filtro se guarde en la dirección
     | del navegador:
     |
     |     /comercial/presupuestos?buscar=homestead&estado=sent
     |
     | Sirve para tres cosas de todos los días: recargar la página sin
     | perder lo que estabas viendo, usar el botón "atrás" del navegador,
     | y poder mandarle a un compañero el enlace exacto de lo que estás
     | mirando.
     |
     | 'as' acorta el nombre en la dirección; 'except' evita ensuciarla
     | con los filtros que están en su valor por defecto.
     * ================================================================== */

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'orden', except: 'issue_date')]
    public string $ordenarPor = 'issue_date';

    #[Url(as: 'dir', except: 'desc')]
    public string $direccion = 'desc';

    public int $porPagina = 15;

    /** El presupuesto que el usuario pidió borrar, esperando confirmación. */
    public ?int $porBorrar = null;

    /* =====================================================================
     | REACCIONES A LO QUE ESCRIBE EL USUARIO
     * ================================================================== */

    /**
     * Cuando cambia un filtro, hay que volver a la página 1.
     *
     * Sin esto pasa algo que confunde mucho: estás en la página 4 de los
     * presupuestos, escribes en el buscador, quedan 6 resultados —o sea
     * una sola página— y la pantalla te muestra la página 4 de 1, que
     * está vacía. El usuario concluye que la búsqueda no encontró nada.
     *
     * "updating" + el nombre de la propiedad es la forma que tiene
     * Livewire de decir "justo antes de que cambie esto".
     */
    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function updatingEstado(): void
    {
        $this->resetPage();
    }

    /**
     * Clic en el encabezado de una columna.
     *
     * Si ya se estaba ordenando por esa columna, invierte el sentido.
     * Si no, ordena por la columna nueva de mayor a menor, que es lo que
     * casi siempre se quiere: lo más reciente arriba.
     */
    public function ordenar(string $columna): void
    {
        if ($this->ordenarPor === $columna) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->direccion  = 'desc';
        }

        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'estado']);
        $this->resetPage();
    }

    /* =====================================================================
     | ACCIONES SOBRE UN PRESUPUESTO
     * ================================================================== */

    /**
     * Primer clic en la papelera: solo guarda cuál se quiere borrar y
     * abre el aviso de confirmación.
     *
     * Borrar en un solo clic es de las cosas que más rabia dan cuando el
     * dedo se resbala.
     */
    public function confirmarBorrado(int $id): void
    {
        $this->porBorrar = $id;
    }

    public function cancelarBorrado(): void
    {
        $this->porBorrar = null;
    }

    /**
     * Segundo clic: se borra de verdad.
     *
     * El observer del modelo se encarga de impedirlo si el presupuesto ya
     * salió de borrador. Aquí solo se atrapa ese aviso y se le muestra al
     * usuario en palabras.
     *
     * Fíjate que la comprobación de verdad NO está en esta pantalla, está
     * en el observer. Una regla escrita solo en la pantalla se salta
     * llamando al modelo desde otro lado.
     */
    public function borrar(): void
    {
        if (! $this->porBorrar) {
            return;
        }

        try {
            // findOrFail respeta el filtro de compañía: si el id es de la
            // otra empresa, simplemente no lo encuentra.
            Estimate::findOrFail($this->porBorrar)->delete();

            session()->flash('exito', 'Presupuesto eliminado.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->porBorrar = null;
    }

    /**
     * Duplicar: crea un borrador nuevo con las mismas líneas y lleva al
     * usuario directo a editarlo.
     *
     * Es de las funciones más usadas en la práctica: "lo mismo pero con
     * dos contenedores", o rehacer uno que se venció.
     */
    public function duplicar(int $id)
    {
        try {
            $copia = Estimate::with('items')->findOrFail($id)->duplicate();

            session()->flash('exito', 'Se creó el presupuesto '.$copia->estimate_number.'.');

            return redirect()->route('comercial.presupuestos.edit', $copia);
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo duplicar: '.$e->getMessage());

            return null;
        }
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        /* -----------------------------------------------------------------
         | Las columnas por las que se deja ordenar.
         |
         | Es una lista blanca a propósito. La columna llega desde la
         | dirección del navegador, o sea desde fuera, y meter texto de
         | fuera directo en un orderBy es la puerta por la que entran los
         | ataques de inyección de SQL.
         |
         | Si alguien escribe algo raro en la dirección, cae en el default
         | y la pantalla sigue funcionando.
         * -------------------------------------------------------------- */
        $columnasValidas = ['estimate_number', 'issue_date', 'valid_until', 'total', 'status'];

        $columna = in_array($this->ordenarPor, $columnasValidas, true)
            ? $this->ordenarPor
            : 'issue_date';

        $sentido = $this->direccion === 'asc' ? 'asc' : 'desc';

        $presupuestos = Estimate::query()
            /*
             | with() trae los clientes en UNA consulta extra, no en una
             | por fila.
             |
             | Sin esto, un listado de 15 presupuestos hace 16 consultas:
             | una para los presupuestos y una por cada cliente. Con 200
             | filas y varios usuarios a la vez, eso se nota.
             */
             ->with([
                'customer:id,display_name,company_name,customer_number',
                'invoice:id,invoice_number',
            ])
            ->search($this->buscar)
            ->when($this->estado, fn ($q) => $q->where('status', $this->estado))
            ->orderBy($columna, $sentido)
            ->orderBy('id', 'desc')   // desempate estable
            ->paginate($this->porPagina);

        /* -----------------------------------------------------------------
         | LOS CONTADORES DE ARRIBA
         |
         | Se calculan con consultas de conteo, que no traen las filas:
         | solo preguntan cuántas hay. Son baratas.
         * -------------------------------------------------------------- */
        $resumen = [
            'abiertos'   => Estimate::query()->open()->count(),
            'porVencer'  => Estimate::query()->expiredByDate()->count(),
            'aceptados'  => Estimate::query()->where('status', EstimateStatus::Accepted)->count(),
            'montoAbierto' => (float) Estimate::query()->open()->sum('total'),
        ];

        return view('livewire.estimates.index', [
            'presupuestos' => $presupuestos,
            'resumen'      => $resumen,
            'estados'      => EstimateStatus::options(),
        ]);
    }
}
