<?php

namespace App\Livewire\Reports;

use App\Enums\ContainerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TripStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Container;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Trip;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * REPORTES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── QUÉ ENTRA Y QUÉ NO ──
 *
 * Lo acordado para esta fase son "reportes básicos más el reporte de
 * impuestos". Eso son cinco, y cada uno existe porque contesta una
 * pregunta que alguien hace de verdad:
 *
 *   Impuestos          ¿cuánto sales tax hay que declararle a Florida?
 *   Ingresos y gastos  ¿ganamos o perdimos este mes?
 *   Por cobrar         ¿quién nos debe y desde cuándo?
 *   Por pagar          ¿a quién le debemos y cuándo vence?
 *   Inventario         ¿qué hay en la yarda y cuánto vale?
 *
 * El 1099 y la caja con saldo corrido quedaron para la fase siguiente.
 *
 * ── UNA SOLA PANTALLA CON SELECTOR ──
 *
 * Y no cinco pantallas, porque el 90% de cada reporte es lo mismo: elegir
 * un período, filtrar por compañía y mirar una tabla. Cinco pantallas
 * serían cinco veces el mismo encabezado.
 *
 * ── LO QUE NO HACE ──
 *
 * No exporta a Excel ni a PDF. Se imprime con el navegador, que es lo que
 * la empresa hace hoy con todo lo demás. Exportar es otra conversación y
 * no se pidió.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'reports';

    /** impuestos | resultado | cobrar | pagar | inventario */
    #[Url(as: 'r', except: 'resultado')]
    public string $reporte = 'resultado';

    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'hasta', except: '')]
    public string $hasta = '';

    public function mount(): void
    {
        $this->exigirPermiso('view');

        /* El mes en curso: es lo que se mira al entrar. */
        $this->desde = $this->desde ?: now()->startOfMonth()->toDateString();
        $this->hasta = $this->hasta ?: now()->endOfMonth()->toDateString();
    }

    /* =====================================================================
     | ATAJOS DE PERÍODO
     |
     | Existen porque nadie quiere teclear dos fechas para ver el mes
     | pasado, y porque tecleándolas se equivoca: pone 30 de febrero, o
     | se come un día y el reporte de impuestos queda corto.
     * ================================================================== */

    public function periodo(string $cual): void
    {
        [$d, $h] = match ($cual) {
            'mes'          => [now()->startOfMonth(),            now()->endOfMonth()],
            'mes_pasado'   => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'trimestre'    => [now()->startOfQuarter(),           now()->endOfQuarter()],
            'trim_pasado'  => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            'anio'         => [now()->startOfYear(),              now()->endOfYear()],
            default        => [now()->startOfMonth(),             now()->endOfMonth()],
        };

        $this->desde = $d->toDateString();
        $this->hasta = $h->toDateString();
    }

    /* =====================================================================
     | LOS DATOS
     * ================================================================== */

    private function empresaId(): ?int
    {
        return app(CompanyContext::class)->get()?->id;
    }

    /**
     * ── IMPUESTOS ──
     *
     * Lo que hay que declararle al estado de Florida.
     *
     * Se cuentan las facturas EMITIDAS en el período, no las cobradas. En
     * Florida el sales tax se devenga con la factura: si se emitió en
     * marzo y el cliente paga en mayo, el impuesto es de marzo. Contarlo
     * por cobro dejaría la declaración corta.
     *
     * Las anuladas quedan fuera, obviamente. Los borradores también: una
     * factura en borrador todavía no existe para nadie.
     */
    private function impuestos(): array
    {
        $base = fn () => Invoice::query()
            ->when($this->empresaId(), fn ($q) => $q->where('company_id', $this->empresaId()))
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereBetween('issue_date', [$this->desde, $this->hasta]);

        $porMes = $base()
            ->select(
                DB::raw("DATE_FORMAT(issue_date, '%Y-%m') as mes"),
                DB::raw('SUM(subtotal) as base'),
                DB::raw('SUM(tax_amount) as impuesto'),
                DB::raw('COUNT(*) as facturas'),
            )
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return [
            'porMes' => $porMes,

            'baseTotal'     => (float) $base()->sum('subtotal'),
            'impuestoTotal' => (float) $base()->sum('tax_amount'),

            /*
             | Lo exento se enseña aparte porque es lo primero que audita
             | el estado: una venta sin impuesto tiene que poder explicarse
             | con un certificado de exención o con que fue exportación.
             */
            'exento'        => (float) $base()->where('tax_exempt', true)->sum('subtotal'),
            'exentoCuantas' => $base()->where('tax_exempt', true)->count(),
        ];
    }

    /**
     * ── INGRESOS Y GASTOS ──
     *
     * Se enseñan las dos lecturas porque contestan preguntas distintas y
     * la gente las confunde:
     *
     *   FACTURADO   lo que se vendió. Dice cómo va el negocio.
     *   COBRADO     lo que entró a la cuenta. Dice si alcanza para pagar.
     *
     * Un mes puede ser excelente en facturado y pésimo en cobrado, y eso
     * es justo lo que hay que ver.
     */
    private function resultado(): array
    {
        $eid = $this->empresaId();

        $facturado = (float) Invoice::query()
            ->when($eid, fn ($q) => $q->where('company_id', $eid))
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->whereBetween('issue_date', [$this->desde, $this->hasta])
            ->sum('total');

        $cobrado = (float) Payment::query()
            ->when($eid, fn ($q) => $q->where('company_id', $eid))
            ->whereBetween('received_at', [
                Carbon::parse($this->desde)->startOfDay(),
                Carbon::parse($this->hasta)->endOfDay(),
            ])
            ->sum('net_amount');

        $gastado = (float) Expense::query()
            ->when($eid, fn ($q) => $q->where('company_id', $eid))
            ->where('status', '!=', 'cancelled')
            ->whereBetween('expense_date', [$this->desde, $this->hasta])
            ->sum('amount');

        $porCategoria = Expense::query()
            ->when($eid, fn ($q) => $q->where('company_id', $eid))
            ->where('status', '!=', 'cancelled')
            ->whereBetween('expense_date', [$this->desde, $this->hasta])
            ->select('expense_category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cuantos'))
            ->groupBy('expense_category_id')
            ->with('category:id,name')
            ->orderByDesc('total')
            ->get();

        /*
         | Los viajes van aparte porque su rentabilidad no se ve en el
         | facturado: el pago al chofer es un costo que no siempre pasa
         | por la tabla de gastos.
         */
        $viajes = Trip::query()
            ->when($eid, fn ($q) => $q->where('company_id', $eid))
            ->where('status', TripStatus::Completed)
            ->whereBetween('completed_at', [
                Carbon::parse($this->desde)->startOfDay(),
                Carbon::parse($this->hasta)->endOfDay(),
            ])
            ->selectRaw('COUNT(*) as cuantos, SUM(customer_price) as cobrado,
                         SUM(driver_pay) as choferes, SUM(carrier_cost) as terceros')
            ->first();

        return [
            'facturado' => $facturado,
            'cobrado'   => $cobrado,
            'gastado'   => $gastado,

            /*
             | El resultado se calcula sobre lo FACTURADO, no sobre lo
             | cobrado. Es la lectura del negocio, no la de la cuenta
             | bancaria, y es la que dice si el mes fue bueno.
             */
            'resultado' => $facturado - $gastado,

            'porCategoria' => $porCategoria,
            'viajes'       => $viajes,
        ];
    }

    /**
     * ── CUENTAS POR COBRAR ──
     *
     * Con antigüedad, que es lo único que hace útil un listado de deudas.
     * "Nos deben $40,000" no dice nada; "$8,000 de eso lleva más de 90
     * días" dice que hay un problema.
     *
     * Los tramos son los que usa cualquier aging en Estados Unidos: al
     * día, 1-30, 31-60, 61-90 y más de 90.
     */
    private function porCobrar(): array
    {
        $eid = $this->empresaId();

        /*
         | No se filtra por el período elegido: una deuda vieja sigue
         | siendo deuda aunque la factura sea de hace ocho meses. Filtrar
         | por fecha escondería justo lo que hay que ver.
         */
        $facturas = Invoice::query()
            ->when($eid, fn ($q) => $q->where('company_id', $eid))
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void, InvoiceStatus::Paid])
            ->where('balance_due', '>', 0)
            ->with('customer:id,name')
            ->orderBy('due_date')
            ->get();

        $hoy = Carbon::today();

        $tramos = ['al_dia' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'mas' => 0.0];

        $porCliente = [];

        foreach ($facturas as $f) {

            $saldo = (float) $f->balance_due;

            /*
             | Sin fecha de vencimiento se cuenta como al día. Es lo
             | prudente: marcarla como vencida sería inventarse una deuda
             | atrasada que nadie pactó.
             */
            $dias = $f->due_date ? $hoy->diffInDays($f->due_date, false) * -1 : 0;

            $tramo = match (true) {
                $dias <= 0  => 'al_dia',
                $dias <= 30 => 'd30',
                $dias <= 60 => 'd60',
                $dias <= 90 => 'd90',
                default     => 'mas',
            };

            $tramos[$tramo] += $saldo;

            $nombre = $f->customer?->name ?? 'Sin cliente';

            $porCliente[$nombre] ??= ['total' => 0.0, 'facturas' => 0, 'vencido' => 0.0];
            $porCliente[$nombre]['total'] += $saldo;
            $porCliente[$nombre]['facturas']++;

            if ($tramo !== 'al_dia') {
                $porCliente[$nombre]['vencido'] += $saldo;
            }
        }

        uasort($porCliente, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'tramos'     => $tramos,
            'total'      => array_sum($tramos),
            'porCliente' => $porCliente,
            'facturas'   => $facturas->take(50),
        ];
    }

    /**
     * ── CUENTAS POR PAGAR ──
     *
     * Lo mismo del otro lado: gastos con saldo, ordenados por lo que
     * vence primero.
     */
    private function porPagar(): array
    {
        $eid = $this->empresaId();

        $gastos = Expense::query()
            ->when($eid, fn ($q) => $q->where('company_id', $eid))
            ->whereIn('status', ['pending', 'approved', 'partial'])
            ->where('balance', '>', 0)
            ->with(['supplier:id,name', 'category:id,name'])
            ->orderByRaw('due_date IS NULL, due_date')
            ->get();

        $hoy = Carbon::today();

        return [
            'gastos' => $gastos->take(50),
            'total'  => (float) $gastos->sum('balance'),

            'vencido' => (float) $gastos
                ->filter(fn ($g) => $g->due_date && $g->due_date->lt($hoy))
                ->sum('balance'),

            'estaSemana' => (float) $gastos
                ->filter(fn ($g) => $g->due_date
                    && $g->due_date->gte($hoy)
                    && $g->due_date->lte($hoy->copy()->addDays(7)))
                ->sum('balance'),
        ];
    }

    /**
     * ── INVENTARIO ──
     *
     * Qué hay y cuánto vale. El valor es de COSTO, no de venta: es lo que
     * la empresa tiene metido en la yarda, no lo que espera sacar.
     */
    private function inventario(): array
    {
        $eid = $this->empresaId();

        $base = fn () => Container::query()
            ->when($eid, fn ($q) => $q->where('owner_company_id', $eid));

        $porEstado = $base()
            ->select('status', DB::raw('COUNT(*) as cuantos'))
            ->groupBy('status')
            ->get();

        $porMedida = $base()
            ->select('container_size_id', DB::raw('COUNT(*) as cuantos'))
            ->groupBy('container_size_id')
            ->with('size:id,name')
            ->orderByDesc('cuantos')
            ->get();

        return [
            'total'      => $base()->count(),
            'porEstado'  => $porEstado,
            'porMedida'  => $porMedida,

            'disponibles' => $base()->available()->count(),
            'sinPrecio'   => $base()->available()->whereNull('list_price')->count(),
            'porReparar'  => $base()->where('needs_repair', true)->count(),

            /*
             | El costo total de lo que está en la yarda.
             |
             | acquisition_cost es lo que se pagó por la unidad. No se le
             | suman traslado ni reacondicionamiento: esos ya están en la
             | tabla de gastos y sumarlos acá los contaría dos veces.
             */
            'valorCosto' => (float) $base()->inYard()->sum('acquisition_cost'),
        ];
    }

    public function render()
    {
        $datos = match ($this->reporte) {
            'impuestos'  => $this->impuestos(),
            'cobrar'     => $this->porCobrar(),
            'pagar'      => $this->porPagar(),
            'inventario' => $this->inventario(),
            default      => $this->resultado(),
        };

        return view('livewire.reports.index', [
            'datos'   => $datos,
            'empresa' => app(CompanyContext::class)->get(),
        ]);
    }
}
