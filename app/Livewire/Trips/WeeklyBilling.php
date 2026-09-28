<?php

namespace App\Livewire\Trips;

use App\Enums\TripStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Trip;
use App\Services\InvoiceCalculator;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FACTURAR LA SEMANA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DÓNDE SALE ──
 *
 * Reunión del 16 de septiembre, palabras de Denisse: los viajes se registran
 * especificando la compañía cliente —Florida Logistics, Maritin, Ricardo—,
 * lo que permite generar facturas semanales de forma directa.
 *
 * Eso es esta pantalla. Se elige una compañía y una semana, salen todos sus
 * viajes completados sin facturar, se destildan los que no van, y sale UNA
 * factura con un renglón por viaje.
 *
 * ── POR QUÉ NO ES UN BOTÓN EN CADA VIAJE ──
 *
 * Porque veinte viajes serían veinte facturas y veinte correos. Lo que pidió
 * el cliente es exactamente lo contrario: una factura el viernes con toda la
 * semana. Facturar de a uno ya se puede desde la factura normal.
 *
 * ── LO QUE SE ESCRIBE EN CADA RENGLÓN ──
 *
 * La fecha, el destino y el número de viaje. Esto importa más de lo que
 * parece: cuando Florida Logistics llame preguntando por qué la factura dice
 * $3,400, la respuesta tiene que estar en el papel y no en la memoria de
 * alguien.
 *
 * ── LO QUE NO HACE ──
 *
 * No emite ni envía. Deja la factura en borrador para que se revise antes.
 * Emitir es un acto con consecuencias —numera, congela y dispara la comisión—
 * y no tiene por qué ocurrir de rebote al pulsar "generar".
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class WeeklyBilling extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'invoices';

    public ?int $customer_id = null;

    public ?string $desde = null;
    public ?string $hasta = null;

    /** id del viaje => true/false. Lo que se va a facturar. */
    public array $elegidos = [];

    public function mount(): void
    {
        $this->exigirPermiso('create');

        /*
         | La semana pasada, de lunes a domingo.
         |
         | Se factura los viernes o el lunes siguiente, así que lo que
         | interesa casi siempre es la semana que acaba de cerrar. Si hace
         | falta otra, se cambian las fechas.
         */
        $this->desde = now()->subWeek()->startOfWeek()->toDateString();
        $this->hasta = now()->subWeek()->endOfWeek()->toDateString();
    }

    public function updated(string $campo): void
    {
        /*
         | Cambió el cliente o el rango → la selección anterior ya no
         | aplica. Dejarla pegada facturaría viajes que ya no están en
         | pantalla, que es la clase de error que nadie nota hasta que
         | el cliente reclama.
         */
        if (in_array($campo, ['customer_id', 'desde', 'hasta'], true)) {
            $this->elegidos = [];
        }
    }

    /* =====================================================================
     | LOS VIAJES QUE ENTRAN
     * ================================================================== */

    public function getViajesProperty()
    {
        if (! $this->customer_id || ! $this->desde || ! $this->hasta) {
            return collect();
        }

        $empresa = app(CompanyContext::class)->get();

        return Trip::query()
            ->when($empresa, fn ($q) => $q->where('company_id', $empresa->id))
            ->where('customer_id', $this->customer_id)
            ->where('status', TripStatus::Completed)
            ->where('customer_price', '>', 0)

            /*
             | Sin factura todavía. Es lo que impide facturar dos veces el
             | mismo viaje, que en un cobro semanal es fácil: basta con
             | solapar los rangos de fechas por un día.
             */
            ->whereNull('intercompany_invoice_id')

            ->whereBetween('completed_at', [
                Carbon::parse($this->desde)->startOfDay(),
                Carbon::parse($this->hasta)->endOfDay(),
            ])

            ->with(['container:id,container_number,internal_code', 'driver:id,first_name,last_name'])
            ->orderBy('completed_at')
            ->get();
    }

    public function getTotalProperty(): float
    {
        return (float) $this->viajes
            ->filter(fn ($v) => ! empty($this->elegidos[$v->id]))
            ->sum('customer_price');
    }

    public function marcarTodos(): void
    {
        foreach ($this->viajes as $v) {
            $this->elegidos[$v->id] = true;
        }
    }

    public function desmarcarTodos(): void
    {
        $this->elegidos = [];
    }

    /* =====================================================================
     | GENERAR
     * ================================================================== */

    public function generar()
    {
        $this->exigirPermiso('create');

        $ids = collect($this->elegidos)->filter()->keys()->all();

        if (empty($ids)) {
            $this->addError('elegidos', 'Marque al menos un viaje para poder facturar.');

            return null;
        }

        $empresa = app(CompanyContext::class)->get();

        $factura = DB::transaction(function () use ($ids, $empresa) {

            /* -------------------------------------------------------------
             | SE VUELVEN A LEER CON CANDADO
             |
             | Entre que se cargó la pantalla y se pulsó el botón, alguien
             | pudo facturar estos mismos viajes desde otro navegador. El
             | lockForUpdate y el whereNull de abajo hacen que el segundo
             | en llegar se encuentre con la lista vacía en vez de duplicar
             | el cobro.
             * ---------------------------------------------------------- */
            $viajes = Trip::whereIn('id', $ids)
                ->where('status', TripStatus::Completed)
                ->whereNull('intercompany_invoice_id')
                ->lockForUpdate()
                ->orderBy('completed_at')
                ->get();

            if ($viajes->isEmpty()) {
                return null;
            }

            $cliente = Customer::findOrFail($this->customer_id);

            $calc = app(InvoiceCalculator::class);

            $factura = new Invoice();
            $factura->company_id  = $empresa?->id;
            $factura->customer_id = $cliente->id;
            $factura->type        = 'service';
            $factura->issue_date  = now()->toDateString();
            /*
             | defaultTerms exige una compañía. Sin compañía activa no
             | debería llegarse hasta acá, pero si pasa es mejor una
             | factura sin términos que una pantalla rota.
             */
            $factura->terms = $empresa ? $calc->defaultTerms($empresa) : null;

            /*
             | Impuesto en cero: el transporte no lleva sales tax en Florida.
             | Está en las reglas del negocio y lo confirmó Denisse.
             */
            $factura->tax_rate = 0;

            $factura->notes = 'Viajes del '
                .Carbon::parse($this->desde)->format('d/m/Y').' al '
                .Carbon::parse($this->hasta)->format('d/m/Y').'.';

            $factura->created_by = auth()->id();
            $factura->save();

            /*
             | El producto de transporte. Si el catálogo no lo tiene, el
             | renglón va sin producto: es preferible una factura sin
             | clasificar que una factura que no se pudo emitir.
             */
            $producto = Product::where('code', 'DELIVERY')->first();

            $orden = 1;

            foreach ($viajes as $v) {

                $destino = $v->destination_address['city']
                    ?? $v->destination_zip
                    ?? 'destino sin indicar';

                InvoiceItem::create([
                    'invoice_id' => $factura->id,
                    'product_id' => $producto?->id,
                    'sort_order' => $orden++,

                    /*
                     | La descripción lleva fecha, destino y número de viaje.
                     | Es lo que se mira cuando el cliente pregunta de dónde
                     | sale el total.
                     */
                    'description' => $v->completed_at?->format('d/m').' · '
                                   .$destino.' · '.$v->trip_number
                                   .($v->miles ? ' · '.rtrim(rtrim(number_format((float) $v->miles, 1), '0'), '.').' mi' : ''),

                    'quantity'   => 1,
                    'unit_price' => (float) $v->customer_price,
                    'amount'     => (float) $v->customer_price,
                    'taxable'    => false,   // el transporte no lleva sales tax

                    /*
                     | El renglón queda amarrado al viaje. Así, desde la
                     | factura se puede volver al viaje y desde el viaje a
                     | la factura, sin tener que leer la descripción.
                     */
                    'trip_id'      => $v->id,
                    'container_id' => $v->container_id,

                    'delivery_zip'  => $v->destination_zip,
                    'miles'         => $v->miles,
                    'rate_per_mile' => $v->rate_per_mile,
                    'service_date'  => $v->completed_at?->toDateString(),
                    'line_number'   => $orden - 1,
                ]);

                /*
                 | El viaje queda amarrado a la factura. Esta es la marca
                 | que impide volver a cobrarlo.
                 */
                $v->intercompany_invoice_id = $factura->id;
                $v->trip_payment_status     = 'invoiced';
                $v->save();
            }

            /*
             | apply() recalcula subtotal, impuesto y total del documento
             | a partir de sus renglones. Se le pasa fresh() para que los
             | vea recién guardados.
             */
            $calc->apply($factura->fresh());

            return $factura;
        });

        if (! $factura) {
            $this->addError('elegidos',
                'Esos viajes ya se facturaron desde otra pantalla. Recargue para ver lo que queda.');

            $this->elegidos = [];

            return null;
        }

        session()->flash('exito',
            'Factura en borrador creada con '.count($ids).' viajes. Revísela y emítala.');

        return redirect()->route('finanzas.facturacion.edit', $factura);
    }

    public function render()
    {
        return view('livewire.trips.weekly-billing', [
            'clientes' => Customer::where('is_active', true)->orderBy('name')->get(),
            'viajes'   => $this->viajes,
            'total'    => $this->total,
        ]);
    }
}
