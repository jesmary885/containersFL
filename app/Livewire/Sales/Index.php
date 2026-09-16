<?php

namespace App\Livewire\Sales;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Invoice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * VENTAS · LA RENTABILIDAD REAL
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── POR QUÉ ESTE MÓDULO NO ES UN FORMULARIO ──
 *
 * En este sistema la factura ES la venta: el ciclo es presupuesto →
 * factura, y no hay ningún papel entre medias.
 *
 * Así que crear una pantalla donde alguien teclee la venta otra vez sería
 * pedirle a Denisse que capture dos veces lo mismo, y garantizar que
 * tarde o temprano los dos registros digan cosas distintas.
 *
 * Esta pantalla NO SE LLENA. Lee las facturas que ya existen, les junta el
 * costo de las unidades y la comisión, y contesta la pregunta que hoy no
 * tiene respuesta en ningún sitio:
 *
 *      ¿Cuánto ganamos de verdad?
 *
 * ── DE DÓNDE SALE CADA NÚMERO ──
 *
 *   Lo vendido    de los renglones de venta de la factura
 *   El costo      de invoice_items.unit_cost, congelado al facturar
 *   La comisión   de invoices.commission_amount, congelada al emitir
 *
 * Los tres son datos que el sistema ya guardaba. No hay nada que teclear.
 *
 * ── LO QUE ESTE NÚMERO TODAVÍA NO INCLUYE ──
 *
 * El pago al chofer y los gastos generales. Los dos módulos faltan. Se
 * dice en la pantalla, arriba y en grande, porque un margen que se lee
 * como final y no lo es hace tomar decisiones equivocadas.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess;
    use WithPagination;

    /** El mes que se está mirando, en formato YYYY-MM. */
    #[Url]
    public string $mes = '';

    #[Url]
    public string $buscar = '';

    /**
     * Solo las ya cobradas.
     *
     * Una venta facturada y no cobrada tiene margen en el papel y cero en
     * el banco. Poder separarlas es la diferencia entre saber cuánto se
     * ganó y cuánto se espera ganar.
     */
    #[Url]
    public bool $soloCobradas = false;

    public function mount(): void
    {
        $this->exigirPermiso('view');

        if ($this->mes === '') {
            $this->mes = now()->format('Y-m');
        }
    }

    protected string $permisoBase = 'invoices';

    public function updatedMes(): void     { $this->resetPage(); }
    public function updatedBuscar(): void  { $this->resetPage(); }
    public function updatedSoloCobradas(): void { $this->resetPage(); }

    public function mesAnterior(): void
    {
        $this->mes = \Carbon\Carbon::parse($this->mes.'-01')
            ->subMonth()->format('Y-m');

        $this->resetPage();
    }

    public function mesSiguiente(): void
    {
        $this->mes = \Carbon\Carbon::parse($this->mes.'-01')
            ->addMonth()->format('Y-m');

        $this->resetPage();
    }

    /**
     * La consulta base.
     *
     * Solo facturas con AL MENOS UN renglón de venta de contenedor. Una
     * factura que solo cobra un delivery o una mora no es una venta y no
     * pinta nada en este listado.
     *
     * El filtro de compañía lo aplica solo el scope global de Invoice.
     */
    protected function consulta()
    {
        [$anio, $mes] = explode('-', $this->mes);

        $desde = \Carbon\Carbon::create((int) $anio, (int) $mes, 1)->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        return Invoice::query()
            ->with([
                'customer:id,display_name,company_name',
                'salesperson:id,name',
                'items.product:id,code,name,type',
                'items.container:id,container_number,internal_code',
            ])
            ->where('status', '!=', 'void')
            ->whereBetween('issue_date', [$desde->toDateString(), $hasta->toDateString()])

            // Con al menos un renglón cuyo concepto sea venta de contenedor.
            ->whereHas('items.product', fn ($q) => $q->where('code', 'CONT-SALE'))

            ->when($this->soloCobradas, fn ($q) => $q->where('balance_due', '<=', 0.01))
            ->when($this->buscar, fn ($q) => $q->search($this->buscar))
            ->orderByDesc('issue_date')
            ->orderByDesc('id');
    }

    /**
     * Los totales del mes.
     *
     * Se calculan sobre TODAS las facturas del mes, no sobre la página que
     * se está viendo. Un total que solo suma lo que cabe en pantalla no es
     * un total.
     *
     * ── SIN COSTO Y CON COSTO, POR SEPARADO ──
     *
     * Las facturas emitidas antes de que el sistema guardara el costo
     * salen sin él. Meterlas en la suma con un cero inflaría el margen.
     * Se cuentan aparte y se dice cuántas son, para que el número que se
     * enseña sea el de las ventas que sí se pueden medir.
     */
    public function getTotalesProperty(): array
    {
        $facturas = $this->consulta()->get();

        $vendido = 0.0;
        $costo = 0.0;
        $comision = 0.0;
        $medibles = 0;
        $sinCosto = 0;
        $unidades = 0;

        foreach ($facturas as $factura) {

            $unidades += $factura->lineasDeVenta()->count();

            if ($factura->costo_de_venta === null) {
                $sinCosto++;

                continue;
            }

            $medibles++;
            $vendido  += $factura->ingreso_por_venta;
            $costo    += $factura->costo_de_venta;
            $comision += (float) $factura->commission_amount;
        }

        $bruto = round($vendido - $costo, 2);
        $neto  = round($bruto - $comision, 2);

        return [
            'facturas'  => $facturas->count(),
            'medibles'  => $medibles,
            'sinCosto'  => $sinCosto,
            'unidades'  => $unidades,
            'vendido'   => round($vendido, 2),
            'costo'     => round($costo, 2),
            'bruto'     => $bruto,
            'comision'  => round($comision, 2),
            'neto'      => $neto,
            'porcentaje' => $vendido > 0.01 ? round($neto / $vendido * 100, 1) : null,
        ];
    }

    public function render()
    {
        $this->exigirPermiso('view');

        return view('livewire.sales.index', [
            'facturas' => $this->consulta()->paginate(20),
            'titulo'   => \Carbon\Carbon::parse($this->mes.'-01')
                    ->translatedFormat('F Y'),
        ]);
    }
}
