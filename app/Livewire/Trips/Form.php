<?php

namespace App\Livewire\Trips;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Carrier;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Depot;
use App\Models\Driver;
use App\Models\Location;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\DistanceResolver;
use App\Services\PricingResolver;
use App\Support\CompanyContext;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UN VIAJE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DÓNDE SALE ──
 *
 * Reunión del 16 de septiembre. Denisse pidió una sección de compañía en el
 * módulo de viajes que permita generar facturas semanales, especificando la
 * compañía cliente —nombró a Florida Logistics, Maritin y Ricardo—.
 *
 * Eso resolvió una duda que llevaba abierta desde el 8 de agosto: SÍ hacen
 * transporte para terceros. Un viaje puede existir sin venta ni renta
 * detrás, cobrándose a una compañía que no es ninguna de las dos de ellos.
 *
 * ── LOS TRES ORÍGENES DE UN VIAJE ──
 *
 *   Nace de una venta       el contenedor se entrega al comprador
 *   Nace de una renta       el contenedor va y vuelve
 *   No nace de nada         transporte puro para un tercero
 *
 * El tercero es el caso nuevo y el que da pie a la factura semanal: veinte
 * viajes sueltos de Florida Logistics durante la semana, una sola factura
 * el viernes.
 *
 * ── LO QUE ESTA PANTALLA NO HACE ──
 *
 * No factura. Registrar el viaje y cobrarlo son dos actos distintos, y
 * mezclarlos obligaría a facturar uno por uno, que es justo lo contrario
 * de lo que pidió Denisse. La facturación vive en su propia pantalla y
 * toma los viajes ya completados.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'trips';

    public ?int $tripId = null;

    /** El número. Se genera al guardar; antes no existe. */
    public ?string $numero = null;

    /* =====================================================================
     | QUÉ VIAJE ES
     * ================================================================== */

    public string $type   = 'delivery';
    public string $status = 'scheduled';

    /* =====================================================================
     | A QUIÉN SE LE COBRA — REUNIÓN 16-09
     |
     | Es un CLIENTE, no una compañía del sistema. Florida Logistics,
     | Maritin y Ricardo están en la tabla de clientes igual que quien
     | compra un contenedor: son quienes reciben la factura.
     |
     | Cuando el viaje nace de una venta o una renta, el cliente sale de
     | ahí solo. Cuando es transporte puro, se elige a mano.
     * ================================================================== */

    public ?int $customer_id = null;

    /* =====================================================================
     | DE DÓNDE A DÓNDE
     * ================================================================== */

    public ?int $origin_location_id = null;
    public ?int $depot_id           = null;

    public ?string $destination_zip = null;
    public array $destination_address = [
        'line1' => null, 'city' => null, 'state' => null, 'zip' => null,
    ];

    public $miles = null;

    /* =====================================================================
     | QUIÉN LO HACE
     * ================================================================== */

    public ?int $carrier_id = null;
    public ?int $driver_id  = null;
    public ?int $vehicle_id = null;

    /* =====================================================================
     | QUÉ LLEVA
     * ================================================================== */

    public ?int $container_id = null;

    /* =====================================================================
     | CUÁNDO
     * ================================================================== */

    public ?string $scheduled_at = null;
    public ?string $completed_at = null;

    /* =====================================================================
     | LOS NÚMEROS
     |
     | Todos editables. El sistema propone y la persona decide: es la misma
     | regla que se acordó para las tarifas de entrega el 16-09.
     * ================================================================== */

    public $rate_per_mile      = null;
    public $pickup_fee         = 0;
    public $customer_price     = 0;
    public $carrier_cost       = 0;
    public $driver_pay_percent = null;
    public $driver_pay         = 0;

    public ?string $notes = null;

    /* =====================================================================
     | CARGA
     * ================================================================== */

    public function mount(?Trip $trip = null)
    {
        if ($trip && $trip->exists) {
            $this->exigirPermiso('update');
            $this->cargarDesde($trip);

            return null;
        }

        $this->exigirPermiso('create');

        $empresa = app(CompanyContext::class)->get();

        $this->scheduled_at = now()->format('Y-m-d\TH:i');

        /*
         | El pago del chofer arranca en el porcentaje de la casa.
         |
         | En la reunión del 16-09 quedó dicho que son el 30% del viaje,
         | que es lo que sale del Excel. Vive en Configuración para poder
         | cambiarlo sin tocar código.
         */
        $this->driver_pay_percent = app(PricingResolver::class)
            ->driverPayPercent($empresa, null, null);

        return null;
    }

    protected function cargarDesde(Trip $t): void
    {
        $this->tripId = $t->id;
        $this->numero = $t->trip_number;

        $this->type   = $t->type?->value ?? 'delivery';
        $this->status = $t->status?->value ?? 'scheduled';

        $this->customer_id        = $t->customer_id;
        $this->origin_location_id = $t->origin_location_id;
        $this->depot_id           = $t->depot_id;
        $this->destination_zip    = $t->destination_zip;

        $this->destination_address = array_merge(
            ['line1' => null, 'city' => null, 'state' => null, 'zip' => null],
            $t->destination_address ?? [],
        );

        $this->miles      = $t->miles;
        $this->carrier_id = $t->carrier_id;
        $this->driver_id  = $t->driver_id;
        $this->vehicle_id = $t->vehicle_id;
        $this->container_id = $t->container_id;

        $this->scheduled_at = $t->scheduled_at?->format('Y-m-d\TH:i');
        $this->completed_at = $t->completed_at?->format('Y-m-d\TH:i');

        $this->rate_per_mile      = $t->rate_per_mile;
        $this->pickup_fee         = $t->pickup_fee;
        $this->customer_price     = $t->customer_price;
        $this->carrier_cost       = $t->carrier_cost;
        $this->driver_pay_percent = $t->driver_pay_percent;
        $this->driver_pay         = $t->driver_pay;

        $this->notes = $t->notes;
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    public function updated(string $campo): void
    {
        /*
         | Cambió el tipo de viaje → cambia de dónde sale el precio.
         |
         | Un pickup se cobra por el fee del depósito y un delivery por
         | millas. Son dos formas distintas de cotizar lo mismo y la
         | pantalla tiene que enseñar la que toca.
         */
        if ($campo === 'type') {
            $this->recalcularPrecio();
        }

        if (in_array($campo, ['miles', 'rate_per_mile', 'depot_id', 'pickup_fee'], true)) {
            $this->recalcularPrecio();
        }

        if (in_array($campo, ['customer_price', 'driver_pay_percent'], true)) {
            $this->recalcularPagoChofer();
        }

        /*
         | Cambió el chofer → se propone el camión que suele manejar y el
         | transportista al que pertenece. Los dos quedan editables.
         */
        if ($campo === 'driver_id' && $this->driver_id) {
            $chofer = Driver::find($this->driver_id);

            if ($chofer) {
                $this->carrier_id = $this->carrier_id ?: $chofer->carrier_id;
            }
        }
    }

    /* =====================================================================
     | CALCULAR LAS MILLAS — REUNIÓN 16-09
     |
     | El mismo botón que ya está en presupuestos y facturas. Sin clave de
     | Google no aparece y las millas se escriben a mano, como siempre.
     * ================================================================== */
    public function calcularMillas(): void
    {
        $this->exigirPermiso($this->tripId ? 'update' : 'create');

        $zip = trim((string) ($this->destination_zip ?: ($this->destination_address['zip'] ?? '')));

        if ($zip === '') {
            $this->addError('destination_zip',
                'Escriba el código postal del destino para poder calcular las millas.');

            return;
        }

        $empresa = app(CompanyContext::class)->get();
        $resolver = app(DistanceResolver::class);

        /*
         | Un pickup sale del DEPÓSITO y un delivery de la YARDA. Medir
         | siempre desde la yarda daría millas de más en cada recogida.
         */
        $millas = ($this->type === 'pickup' && $this->depot_id)
            ? $resolver->milesFromDepot($this->depot_id, $zip)
            : $resolver->milesFromCompany($empresa, $zip);

        if ($millas === null) {
            $this->addError('miles',
                'No se pudo calcular la distancia. Escriba las millas a mano y siga.');

            return;
        }

        $this->miles = $millas;
        $this->rate_per_mile = null;   // que la vuelva a resolver por rango

        $this->recalcularPrecio();

        $this->resetValidation(['miles', 'destination_zip']);
    }

    /* =====================================================================
     | LOS NÚMEROS QUE SE PROPONEN
     * ================================================================== */

    protected function recalcularPrecio(): void
    {
        $empresa  = app(CompanyContext::class)->get();
        $resolver = app(PricingResolver::class);

        if ($this->type === 'pickup') {

            /*
             | Un pickup no se cobra por millas: es un fee fijo del
             | depósito. Denisse lo explicó el 14 de agosto y está en las
             | reglas del negocio.
             */
            if (! $this->pickup_fee || (float) $this->pickup_fee == 0.0) {
                $this->pickup_fee = $resolver->pickupFee(
                    $empresa,
                    $this->depot_id ? Depot::find($this->depot_id) : null,
                );
            }

            $this->customer_price = round((float) $this->pickup_fee, 2);
            $this->recalcularPagoChofer();

            return;
        }

        if ($this->type === 'repositioning') {
            // Mover una unidad de yarda a yarda no se le cobra a nadie.
            $this->customer_price = 0;
            $this->recalcularPagoChofer();

            return;
        }

        $millas = (float) ($this->miles ?? 0);

        /*
         | La tarifa sale del RANGO de millas (reunión 16-09) e incluye el
         | recargo por combustible. Solo se propone si está vacía: si
         | alguien la escribió a mano, manda la suya.
         */
        if ($this->rate_per_mile === null || $this->rate_per_mile === '') {
            $this->rate_per_mile = $resolver->effectiveRatePerMile(
                $empresa,
                $this->carrier_id,
                $millas > 0 ? $millas : null,
            );
        }

        $this->customer_price = round($millas * (float) $this->rate_per_mile, 2);

        $this->recalcularPagoChofer();
    }

    protected function recalcularPagoChofer(): void
    {
        $porcentaje = (float) ($this->driver_pay_percent ?? 0);

        if ($porcentaje <= 0) {
            return;
        }

        $this->driver_pay = round((float) $this->customer_price * $porcentaje / 100, 2);
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    protected function rules(): array
    {
        return [
            'type'   => ['required', Rule::in(TripType::values())],
            'status' => ['required', Rule::in(TripStatus::values())],

            'customer_id' => ['nullable', 'exists:customers,id'],

            'origin_location_id' => ['nullable', 'exists:locations,id'],
            'depot_id'           => ['nullable', 'exists:depots,id'],

            'destination_zip' => ['nullable', 'string', 'max:10'],
            'destination_address.line1' => ['nullable', 'string', 'max:150'],
            'destination_address.city'  => ['nullable', 'string', 'max:100'],
            'destination_address.state' => ['nullable', 'string', 'size:2'],
            'destination_address.zip'   => ['nullable', 'string', 'max:10'],

            'miles'         => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'rate_per_mile' => ['nullable', 'numeric', 'min:0', 'max:9999'],

            'carrier_id'   => ['nullable', 'exists:carriers,id'],
            'driver_id'    => ['nullable', 'exists:drivers,id'],
            'vehicle_id'   => ['nullable', 'exists:vehicles,id'],
            'container_id' => ['nullable', 'exists:containers,id'],

            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],

            'pickup_fee'         => ['nullable', 'numeric', 'min:0'],
            'customer_price'     => ['required', 'numeric', 'min:0'],
            'carrier_cost'       => ['nullable', 'numeric', 'min:0'],
            'driver_pay_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'driver_pay'         => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'customer_id'     => 'compañía cliente',
            'destination_zip' => 'código postal del destino',
            'miles'           => 'millas',
            'rate_per_mile'   => 'tarifa por milla',
            'customer_price'  => 'precio al cliente',
            'driver_pay'      => 'pago al chofer',
        ];
    }

    public function guardar()
    {
        $this->exigirPermiso($this->tripId ? 'update' : 'create');

        $this->validate();

        /* -----------------------------------------------------------------
         | UN VIAJE COMPLETADO NECESITA FECHA
         |
         | Sin ella la factura semanal no sabe en qué semana cae, y un
         | viaje sin semana no entra en ninguna factura: desaparece.
         * -------------------------------------------------------------- */
        if ($this->status === 'completed' && blank($this->completed_at)) {
            $this->completed_at = now()->format('Y-m-d\TH:i');
        }

        /* -----------------------------------------------------------------
         | SI SE COBRA, HAY QUE SABER A QUIÉN
         |
         | Un reposicionamiento no se cobra y puede no tener cliente. Todo
         | lo demás sí: un viaje cobrado sin cliente es dinero que nadie
         | va a reclamar nunca.
         * -------------------------------------------------------------- */
        if ((float) $this->customer_price > 0 && ! $this->customer_id) {
            $this->addError('customer_id',
                'Este viaje se cobra, así que hace falta decir a qué compañía. '
                .'Si es un movimiento interno, ponga el precio en cero.');

            return null;
        }

        $empresa = app(CompanyContext::class)->get();

        $viaje = $this->tripId
            ? Trip::findOrFail($this->tripId)
            : new Trip();

        if (! $this->tripId) {
            $viaje->company_id  = $empresa?->id;
            $viaje->trip_number = $this->siguienteNumero($empresa?->id);
            $viaje->created_by  = auth()->id();
        }

        $viaje->fill([
            'type'   => $this->type,
            'status' => $this->status,

            'customer_id'        => $this->customer_id ?: null,
            'origin_location_id' => $this->origin_location_id ?: null,
            'depot_id'           => $this->depot_id ?: null,

            'destination_zip'     => $this->destination_zip ?: null,
            'destination_address' => array_filter($this->destination_address) ?: null,

            'miles'         => $this->miles !== '' ? $this->miles : null,
            'rate_per_mile' => $this->rate_per_mile !== '' ? $this->rate_per_mile : null,

            'carrier_id'   => $this->carrier_id ?: null,
            'driver_id'    => $this->driver_id ?: null,
            'vehicle_id'   => $this->vehicle_id ?: null,
            'container_id' => $this->container_id ?: null,

            'scheduled_at' => $this->scheduled_at ?: null,
            'completed_at' => $this->completed_at ?: null,

            'pickup_fee'         => (float) ($this->pickup_fee ?: 0),
            'customer_price'     => (float) $this->customer_price,
            'carrier_cost'       => (float) ($this->carrier_cost ?: 0),
            'driver_pay_percent' => $this->driver_pay_percent !== '' ? $this->driver_pay_percent : null,
            'driver_pay'         => (float) ($this->driver_pay ?: 0),

            'notes' => $this->notes ?: null,
        ])->save();

        session()->flash('exito', 'Viaje '.$viaje->trip_number.' guardado.');

        return redirect()->route('operaciones.viajes.show', $viaje);
    }

    /** La secuencia es por empresa: FLCHR y RST numeran aparte. */
    protected function siguienteNumero(?int $companyId): string
    {
        $prefijo = 'TRP-'.now()->format('y').'-';

        $ultimo = Trip::where('company_id', $companyId)
            ->where('trip_number', 'like', $prefijo.'%')
            ->orderByDesc('trip_number')
            ->value('trip_number');

        $siguiente = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        do {
            $numero = $prefijo.str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
            $siguiente++;
        } while (Trip::where('company_id', $companyId)
                     ->where('trip_number', $numero)->exists());

        return $numero;
    }

    public function getPuedeCalcularMillasProperty(): bool
    {
        return filled(config('services.google_maps.key'));
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        return view('livewire.trips.form', [
            'tipos'    => TripType::options(),
            'estados'  => TripStatus::options(),

            'clientes' => Customer::where('is_active', true)->orderBy('name')->get(),
            'depositos'=> Depot::where('is_active', true)->orderBy('name')->get(),
            'origenes' => Location::where('is_active', true)->orderBy('name')->get(),

            'transportistas' => Carrier::where('is_active', true)->orderBy('name')->get(),
            'choferes' => Driver::where('is_active', true)
                ->orderBy('first_name')->orderBy('last_name')->get(),
            'camiones' => Vehicle::where('is_active', true)->orderBy('plate_number')->get(),

            /*
             | Solo las unidades de la empresa activa, para no ofrecer un
             | contenedor de la otra compañía en un viaje que no es suyo.
             */
            'contenedores' => Container::query()
                ->when($empresa, fn ($q) => $q->where('owner_company_id', $empresa->id))
                ->orderByDesc('id')
                ->limit(300)
                ->get(),
        ]);
    }
}
