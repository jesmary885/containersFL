<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Livewire\Concerns\AuthorizesAccess;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LISTADO DE FACTURAS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Se abre desde el menú Finanzas › Facturación.
 *
 * ── EN QUÉ SE DIFERENCIA DEL LISTADO DE PRESUPUESTOS ──
 *
 * En que este no es una lista de documentos: es una herramienta de
 * cobranza. Un vendedor abre presupuestos para ver qué ofreció; alguien
 * abre facturas para ver QUIÉN LE DEBE.
 *
 * Por eso los contadores de arriba son dinero y no cantidades, y por eso
 * el filtro por defecto es "por cobrar" y no "todas". Al entrar, lo
 * primero que se ve es la plata en la calle.
 *
 * Como en presupuestos, no hay ni un "where company_id": el trait
 * BelongsToCompany filtra solo todas las consultas.
 */
#[Layout('layouts.app')]
class Index extends Component
{

    use WithPagination, AuthorizesAccess;

    /* =====================================================================
     | LOS PERMISOS
     |
     | El `can:` de la ruta impide ABRIR esta pantalla. No impide llamar
     | a sus metodos: Livewire manda cada clic a /livewire/update, que es
     | otra ruta y no lleva ese `can:` encima.
     |
     | Por eso cada metodo que cambia algo exige el permiso otra vez.
     * ================================================================== */

    protected string $permisoBase = 'invoices';

    /* =====================================================================
     | LOS FILTROS
     |
     | #[Url] los guarda en la dirección del navegador, para poder
     | recargar sin perder lo que estabas viendo y mandarle a un compañero
     | el enlace exacto de lo que estás mirando.
     * ================================================================== */

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipo = '';

    /**
     * El interruptor de "solo lo que tiene saldo".
     *
     * Viene encendido a propósito. Una empresa con dos años de operación
     * tiene miles de facturas pagadas, y ninguna de ellas es lo que se
     * viene a mirar aquí. Se apaga con un clic cuando hace falta buscar
     * una vieja.
     */
    #[Url(as: 'pendientes', except: true)]
    public bool $soloPendientes = true;

    #[Url(as: 'orden', except: 'issue_date')]
    public string $ordenarPor = 'issue_date';

    #[Url(as: 'dir', except: 'desc')]
    public string $direccion = 'desc';

    public int $porPagina = 15;

    /* =====================================================================
     | ARRANQUE
     * ================================================================== */

    /** El permiso de ver, una sola vez al abrir. */
    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    /**
     * Al cambiar cualquier filtro hay que volver a la página 1.
     *
     * Sin esto: estás en la página 4, escribes en el buscador, quedan 6
     * resultados —una sola página— y la pantalla te muestra la página 4
     * de 1, que está vacía. El usuario concluye que no encontró nada.
     *
     * "updated" + el nombre de la propiedad es la forma que tiene
     * Livewire de decir "justo después de que cambió esto".
     */
    public function updatedBuscar(): void         { $this->resetPage(); }
    public function updatedEstado(): void         { $this->resetPage(); }
    public function updatedTipo(): void           { $this->resetPage(); }
    public function updatedSoloPendientes(): void { $this->resetPage(); }

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
        $this->reset(['buscar', 'estado', 'tipo']);
        $this->soloPendientes = false;
        $this->resetPage();
    }

    /**
     * Atajo del contador de vencidas: al hacer clic, filtra por ellas.
     *
     * Un número que no se puede pulsar obliga al usuario a traducirlo a
     * mano en el filtro de al lado. Es un clic contra tres.
     */
    public function verVencidas(): void
    {
        $this->estado         = InvoiceStatus::Overdue->value;
        $this->soloPendientes = true;
        $this->resetPage();
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        /* -----------------------------------------------------------------
         | Lista blanca de columnas ordenables.
         |
         | La columna llega desde la dirección del navegador, o sea desde
         | fuera, y meter texto de fuera directo en un orderBy es la puerta
         | por la que entran los ataques de inyección de SQL.
         |
         | Si alguien escribe algo raro, cae en el default y la pantalla
         | sigue funcionando.
         * -------------------------------------------------------------- */
        $columnasValidas = [
            'invoice_number', 'issue_date', 'due_date',
            'total', 'balance_due', 'status',
        ];

        $columna = in_array($this->ordenarPor, $columnasValidas, true)
            ? $this->ordenarPor
            : 'issue_date';

        $sentido = $this->direccion === 'asc' ? 'asc' : 'desc';

        $facturas = Invoice::query()
            /*
             | with() trae los clientes en UNA consulta extra, no en una
             | por fila. Sin esto, un listado de 15 facturas hace 16
             | consultas.
             */
            ->with(['customer:id,display_name,company_name,customer_number'])
            ->search($this->buscar)
            ->statusIs($this->estado)
            ->when($this->tipo, fn ($q) => $q->where('type', $this->tipo))
            ->when($this->soloPendientes, fn ($q) => $q->unpaid())
            ->orderBy($columna, $sentido)
            ->orderBy('id', 'desc')   // desempate estable
            ->paginate($this->porPagina);

        /* -----------------------------------------------------------------
         | LOS CONTADORES
         |
         | Son consultas de suma y conteo: no traen las filas, solo el
         | número. Son baratas incluso con miles de facturas.
         |
         | Las cuatro responden preguntas distintas:
         |
         |   porCobrar   ¿cuánto me deben en total?
         |   vencido     ¿cuánto de eso ya se pasó de fecha?
         |   vencidas    ¿en cuántas facturas está repartido?
         |   delMes      ¿cuánto facturé este mes?
         |
         | La tercera importa más de lo que parece: $40,000 en una sola
         | factura de un cliente bueno es una conversación; los mismos
         | $40,000 repartidos en treinta facturas de veinte clientes son
         | un problema de cobranza.
         * -------------------------------------------------------------- */
        $resumen = [
            'porCobrar' => (float) Invoice::query()->unpaid()->sum('balance_due'),
            'vencido'   => (float) Invoice::query()->overdue()->sum('balance_due'),
            'vencidas'  => Invoice::query()->overdue()->count(),
            'delMes'    => (float) Invoice::query()
                ->where('status', '!=', InvoiceStatus::Void->value)
                ->whereBetween('issue_date', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])
                ->sum('total'),
        ];

        return view('livewire.invoices.index', [
            'facturas' => $facturas,
            'resumen'  => $resumen,
            'estados'  => InvoiceStatus::options(),

            /*
             | Intercompañía se saca del filtro visible.
             |
             | Esas facturas las genera el sistema solo: RS Transport le
             | cobra a FLCHR todos los viajes de la semana (RB-003). No se
             | crean ni se buscan desde aquí, tienen su propia pantalla en
             | el módulo de viajes.
             */
            'tipos' => collect(InvoiceType::options())
                ->except(InvoiceType::Intercompany->value)
                ->all(),
        ]);
    }
}
