<?php

namespace App\Livewire;

use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL PANEL
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── QUÉ CAMBIÓ ──
 *
 * Todos los números de esta pantalla estaban escritos a mano: 125 clientes,
 * 86 contenedores, $25.450 de ventas. Datos de ejemplo que servían para ver
 * el diseño y que no se podían enseñar en una reunión.
 *
 * Ahora salen de la base de datos, de los módulos que ya funcionan:
 * Clientes, Contenedores, Compras, Presupuestos, Facturación y Pagos.
 *
 * ── LAS DOS REGLAS QUE SEGUÍ ──
 *
 * 1. NINGÚN NÚMERO INVENTADO. Si el dato todavía no existe porque su
 *    módulo no está construido, no se pone un cero disfrazado ni un valor
 *    de relleno: se enseña otra cosa que sí sea cierta.
 *
 *    Por eso "viajes del mes" desapareció —el módulo de Viajes no existe—
 *    y en su lugar va el valor del inventario en yarda, que sí es real y
 *    además es el número que Michael saca a mano hoy.
 *
 * 2. TODO FILTRADO POR LA EMPRESA ACTIVA. Container, Invoice, Estimate y
 *    Purchase llevan el filtro automático de compañía, así que estas
 *    consultas ya salen filtradas solas. Customer NO lo lleva, porque el
 *    cliente es compartido entre FLCHR y RST: se registra una vez y las
 *    dos le venden.
 *
 * ── POR QUÉ CONSULTAS Y NO UNA TABLA DE RESUMEN ──
 *
 * Porque un contador guardado se queda desfasado en cuanto alguien anula
 * una factura o mueve un contenedor, y nadie se entera hasta que los
 * números dejan de cuadrar. Estas consultas son baratas y siempre dicen la
 * verdad de este segundo.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        $inicioDeMes = now()->startOfMonth()->toDateString();
        $finDeMes    = now()->endOfMonth()->toDateString();

        /* =====================================================================
         | LOS CONTADORES DE ARRIBA
         * ================================================================== */

        $indicadores = [

            /*
             | CLIENTES
             |
             | Sin filtro de compañía a propósito: el cliente es compartido.
             | El mismo le compra un contenedor a FLCHR y le paga el
             | transporte a RST, y se registra una sola vez.
             */
            'clientes' => Customer::where('is_active', true)->count(),

            /*
             | CONTENEDORES DISPONIBLES
             |
             | No es el total del inventario: es lo que se puede vender HOY.
             | El scope available() pregunta si está en yarda Y sin venta ni
             | renta encima.
             |
             | Es justo la distinción que el Excel no hace, y la razón de
             | los 416 fantasma. Enseñar aquí el total sería repetir el
             | mismo error en pantalla bonita.
             */
            'contenedores' => Container::available()->count(),

            /*
             | RENTADOS
             |
             | Se cuenta por el ESTADO de la unidad, no por contratos: el
             | módulo de Rentas todavía no existe, así que no hay contratos
             | que contar. Un contenedor en estado "rentado" está con un
             | cliente, y eso sí es cierto hoy.
             */
            'rentas_activas' => Container::where('status', ContainerStatus::Rented)->count(),

            /*
             | FACTURADO DEL MES
             |
             | Por issue_date y no por created_at: cuenta la fecha del
             | documento, que es la que ve el cliente y la que mira el
             | contador. Una factura cargada hoy con fecha del mes pasado
             | pertenece al mes pasado.
             |
             | Las anuladas quedan fuera: su número sigue existiendo pero no
             | son ingreso.
             */
            'ventas_mes' => (float) Invoice::query()
                ->where('status', '!=', 'void')
                ->whereBetween('issue_date', [$inicioDeMes, $finDeMes])
                ->sum('total'),

            /*
             | VALOR DEL INVENTARIO EN YARDA
             |
             | Compra + recogida + reacondicionamiento de todo lo que está
             | físicamente en el patio.
             |
             | Este contador reemplaza a "viajes del mes", que no se podía
             | calcular porque el módulo de Viajes no existe. Y sale
             | ganando: es el número que Michael saca a mano hoy y el que
             | abre la demo.
             |
             | Se suma en SQL y no cargando los modelos, porque con
             | cuatrocientas unidades traerlas todas a memoria para sumar
             | tres columnas es caro y no hace falta.
             */
            'valor_yarda' => (float) Container::inYard()
                ->selectRaw('COALESCE(SUM(acquisition_cost + pickup_cost + reconditioning_cost), 0) as v')
                ->value('v'),

            /*
             | POR COBRAR
             |
             | La suma de lo que falta cobrar de todas las facturas vivas.
             | balance_due ya descuenta los pagos y los anticipos aplicados.
             */
            'por_cobrar' => (float) Invoice::query()
                ->where('status', '!=', 'void')
                ->sum('balance_due'),
        ];

        /* =====================================================================
         | EL MARGEN DEL MES
         |
         | Desde que la factura congela el costo de cada unidad, este número
         | se puede calcular. Antes no existía en ninguna parte del sistema.
         |
         | Vendido − costo − comisión, sobre las facturas del mes que tienen
         | al menos un renglón de venta de contenedor.
         |
         | ── LAS DOS COSAS QUE ESTE NÚMERO NO HACE ──
         |
         | No incluye rentas: en una renta el contenedor vuelve, su costo no
         | se consume, y restarlo daría una pérdida enorme el primer mes y un
         | beneficio del 100% los siguientes. Los dos serían falsos.
         |
         | No descuenta el pago al chofer ni los gastos generales, porque
         | esos módulos no existen todavía. Se dice en la pantalla.
         * ================================================================== */

        $facturasDelMes = Invoice::query()
            ->with(['items.product:id,code,type'])
            ->where('status', '!=', 'void')
            ->whereBetween('issue_date', [$inicioDeMes, $finDeMes])
            ->get();

        $margen = 0.0;
        $vendido = 0.0;
        $medibles = 0;

        foreach ($facturasDelMes as $factura) {

            // Sin costo congelado no se puede medir: son las facturas
            // anteriores a que existiera la columna. Se saltan en vez de
            // contarlas con un cero, que se leería como margen del 100%.
            if ($factura->costo_de_venta === null) {
                continue;
            }

            $medibles++;
            $vendido += $factura->ingreso_por_venta;
            $margen  += (float) $factura->margen_neto;
        }

        $indicadores['margen_mes']   = round($margen, 2);
        $indicadores['vendido_mes']  = round($vendido, 2);
        $indicadores['margen_pct']   = $vendido > 0.01
            ? round($margen / $vendido * 100, 1)
            : null;
        $indicadores['ventas_medibles'] = $medibles;

        /* =====================================================================
         | LO QUE ESTÁ VENCIDO
         |
         | Es la lista que hay que trabajar hoy, no un adorno: son las
         | facturas que ya se pasaron de fecha y siguen con saldo.
         |
         | Ordenadas por la MÁS VIEJA primero. Cuanto más tiempo lleva sin
         | cobrarse, más difícil es cobrarla, así que es la que hay que
         | llamar antes.
         * ================================================================== */

        $vencidas = Invoice::query()
            ->with('customer:id,display_name,company_name')
            ->where('status', '!=', 'void')
            ->where('balance_due', '>', 0)
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        $pagosPendientes = $vencidas->map(fn (Invoice $f) => [
            'cliente'    => $f->customer?->display_name
                            ?? $f->customer?->company_name
                            ?? 'Sin cliente',

            // Antes decía "contenedor". Ahora el número de factura, que es
            // lo que se busca para llamar y cobrar.
            'contenedor' => $f->invoice_number,

            'vencido'    => $f->due_date?->translatedFormat('d M') ?? '—',
            'monto'      => (float) $f->balance_due,

            // Los días de atraso: no es lo mismo tres días que noventa.
            'dias'       => $f->due_date
                            ? (int) $f->due_date->diffInDays(now())
                            : 0,
        ]);

        /* =====================================================================
         | LOS ÚLTIMOS SEIS MESES
         |
         | Una consulta por mes en vez de un GROUP BY, a propósito: así los
         | meses SIN facturas salen en cero en vez de desaparecer de la
         | gráfica. Una gráfica a la que le faltan meses miente sobre la
         | tendencia.
         |
         | Son seis consultas triviales, una vez por carga de pantalla.
         * ================================================================== */

        $etiquetas = [];
        $importes  = [];

        foreach (range(5, 0) as $atras) {
            $mes = now()->copy()->subMonths($atras);

            $etiquetas[] = ucfirst($mes->translatedFormat('M'));

            $importes[] = (float) Invoice::query()
                ->where('status', '!=', 'void')
                ->whereBetween('issue_date', [
                    $mes->copy()->startOfMonth()->toDateString(),
                    $mes->copy()->endOfMonth()->toDateString(),
                ])
                ->sum('total');
        }

        $ventasMensuales = ['labels' => $etiquetas, 'data' => $importes];

        /* =====================================================================
         | COBRADAS CONTRA PENDIENTES
         |
         | Por CANTIDAD de facturas, no por importe. Es la pregunta de
         | cobranza: $40.000 en una factura de un cliente bueno es una
         | conversación; los mismos $40.000 repartidos en treinta facturas
         | de veinte clientes son un problema.
         * ================================================================== */

        $cobradas  = Invoice::query()
            ->where('status', '!=', 'void')
            ->where('balance_due', '<=', 0.01)
            ->count();

        $pendientes = Invoice::query()
            ->where('status', '!=', 'void')
            ->where('balance_due', '>', 0.01)
            ->count();

        $estadoCuentas = [
            'labels' => ['Cobradas', 'Pendientes'],
            'data'   => [$cobradas, $pendientes],
        ];

        /* =====================================================================
         | LO QUE HAY QUE ATENDER
         |
         | Tres avisos que hoy solo se descubren entrando módulo por módulo.
         * ================================================================== */

        $atencion = [

            /*
             | UNIDADES COMPRADAS QUE SIGUEN EN EL DEPÓSITO DEL PROVEEDOR.
             |
             | Es el problema que originó todo el proyecto: el Excel las
             | cuenta como stock y físicamente no están (RB-019).
             */
            'por_retirar' => (int) Purchase::query()
                ->with('items')
                ->get()
                ->sum(fn (Purchase $c) => max(0, $c->pending_quantity ?? 0)),

            /*
             | RELEASES CON EL PLAZO DE 14 DÍAS VENCIDO.
             |
             | Desde ahí el depósito cobra almacenaje diario, y ese gasto
             | aparece después sin que nadie lo esperara (RB-020).
             */
            'releases_vencidos' => Purchase::overdueForPickup()->count(),

            /*
             | PRESUPUESTOS ABIERTOS.
             |
             | Valen tres días. Son los que hay que llamar antes de que se
             | venzan solos.
             */
            'presupuestos_abiertos' => Estimate::open()->count(),

            /*
             | Unidades en yarda sin precio de venta cargado.
             |
             | Una unidad sin precio no se puede cotizar sin que alguien lo
             | invente. Es el trabajo pendiente más silencioso del
             | inventario: nadie lo ve hasta que hay que cotizar deprisa.
             */
            'sin_precio' => Container::inYard()
                ->where(fn ($q) => $q->whereNull('list_price')->orWhere('list_price', 0))
                ->count(),
        ];

        return view('livewire.dashboard', [
            'indicadores'     => $indicadores,
            'pagosPendientes' => $pagosPendientes,
            'totalMorosos'    => $pagosPendientes->count(),
            'totalDeuda'      => $pagosPendientes->sum('monto'),
            'ventasMensuales' => $ventasMensuales,
            'estadoCuentas'   => $estadoCuentas,
            'atencion'        => $atencion,
            'empresaNombre'   => $empresa?->name ?? '',
        ]);
    }
}
