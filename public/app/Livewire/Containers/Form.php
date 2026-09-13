<?php

namespace App\Livewire\Containers;

use App\Enums\ContainerStatus;
use App\Enums\MovementType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerCondition;
use App\Models\ContainerGrade;
use App\Models\ContainerSize;
use App\Models\ContainerType;
use App\Models\Depot;
use App\Models\Location;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL CONTENEDOR — crear y editar
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── POR QUÉ ESTA NO VA POR PASOS Y LA DE CLIENTE SÍ ──
 *
 * Porque se usa distinto. La ficha de un cliente se llena una vez, con
 * el cliente al teléfono, y son datos que llegan salteados.
 *
 * Los contenedores se cargan en tanda: llega un release de siete y
 * alguien registra siete seguidos, casi iguales. Ahí un asistente de
 * tres pasos son dos clics de más por unidad, catorce por release.
 *
 * Por eso: una sola pantalla, y el botón "Guardar y registrar otro" que
 * conserva la clasificación y los costos. Registrar la segunda unidad
 * de un release es teclear el número y guardar.
 *
 * ── EL NÚMERO DE CONTENEDOR ES LA CÉDULA ──
 *
 * Es único en toda la base. Dos unidades con el mismo número no son un
 * error de tecleo: son un contenedor contado dos veces, y eso es
 * exactamente lo que el Excel hace hoy.
 *
 * Pero no es obligatorio. Hay unidades que llegan sin número legible y
 * la yarda las llama "Unit #3" (RB-042). Para esas está el código
 * interno.
 *
 * ── LO QUE EL FORMULARIO DECIDE SOLO ──
 *
 * 1. Los pesos. Al elegir la medida se precargan la tara y el máximo de
 *    ese tamaño. Son editables: se rellenan, no se imponen.
 *
 * 2. La aptitud de exportación. Solo el Cargo Worthy exporta (RB-016,
 *    RB-056), y eso vive en el catálogo de calidades, no en una lista
 *    escrita a mano aquí.
 *
 * 3. La fecha de recepción. Si el estado es "en yarda" y nadie puso
 *    fecha, se pone hoy: si está en la yarda, llegó.
 *
 * ── EL MOVIMIENTO SE ESCRIBE SOLO ──
 *
 * La tabla `container_movements` existía y nadie escribía en ella. Ahora
 * cada alta deja su asiento de recepción, y cada cambio de estado o de
 * ubicación deja el suyo.
 *
 * No es un lujo: es la única forma de contestar "¿dónde estaba esta
 * unidad en marzo?" sin que alguien se acuerde.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'containers';

    /** null = unidad nueva. */
    public ?int $containerId = null;

    /* =====================================================================
     | IDENTIFICACIÓN
     * ================================================================== */

    public ?string $container_number = null;
    public ?string $internal_code    = null;
    public ?int    $year_manufactured = null;

    /* =====================================================================
     | QUÉ ES
     * ================================================================== */

    public ?int $container_type_id      = null;
    public ?int $container_size_id      = null;
    public ?int $container_condition_id = null;
    public ?int $container_grade_id     = null;

    public ?string $material = 'steel';

    public ?int $tare_weight_lbs = null;
    public ?int $max_weight_lbs  = null;

    /* =====================================================================
     | DÓNDE ESTÁ
     * ================================================================== */

    public string $status = 'in_yard';

    public ?int $location_id = null;
    public ?int $depot_id    = null;

    public ?string $received_at = null;

    /* =====================================================================
     | DINERO
     * ================================================================== */

    public ?string $acquisition_cost    = null;
    public ?string $pickup_cost         = '0';
    public ?string $reconditioning_cost = '0';

    public ?string $list_price   = null;
    public ?string $monthly_rate = null;

    /* =====================================================================
     | EXPORTACIÓN
     * ================================================================== */

    public bool $is_export_eligible = false;

    public ?string $csc_valid_through = null;

    /* =====================================================================
     | DE QUIÉN ES
     * ================================================================== */

    public ?int $owner_company_id   = null;
    public ?int $billing_company_id = null;

    public ?string $condition_notes = null;

    /* =====================================================================
     | ARRANQUE
     * ================================================================== */

    public function mount(?Container $container = null)
    {
        if ($container && $container->exists) {
            $this->exigirPermiso('update');
            $this->cargarDesde($container);

            return null;
        }

        $this->exigirPermiso('create');

        /*
         | Una unidad nueva nace en la empresa activa y con fecha de hoy.
         |
         | Es lo que pasa el 95% de las veces: alguien está registrando
         | lo que acaba de entrar a la yarda de su empresa.
         */
        $empresa = app(CompanyContext::class)->get();

        $this->owner_company_id   = $empresa?->id;
        $this->billing_company_id = $empresa?->id;
        $this->received_at        = now()->toDateString();

        return null;
    }

    protected function cargarDesde(Container $c): void
    {
        $this->containerId = $c->id;

        $this->container_number  = $c->container_number;
        $this->internal_code     = $c->internal_code;
        $this->year_manufactured = $c->year_manufactured;

        $this->container_type_id      = $c->container_type_id;
        $this->container_size_id      = $c->container_size_id;
        $this->container_condition_id = $c->container_condition_id;
        $this->container_grade_id     = $c->container_grade_id;

        $this->material        = $c->material ?: 'steel';
        $this->tare_weight_lbs = $c->tare_weight_lbs;
        $this->max_weight_lbs  = $c->max_weight_lbs;

        $this->status      = $c->status?->value ?? 'in_yard';
        $this->location_id = $c->location_id;
        $this->depot_id    = $c->depot_id;
        $this->received_at = $c->received_at?->toDateString();

        $this->acquisition_cost    = $c->acquisition_cost;
        $this->pickup_cost         = $c->pickup_cost;
        $this->reconditioning_cost = $c->reconditioning_cost;

        $this->list_price   = $c->list_price;
        $this->monthly_rate = $c->monthly_rate;

        $this->is_export_eligible = (bool) $c->is_export_eligible;
        $this->csc_valid_through  = $c->csc_valid_through?->toDateString();

        $this->owner_company_id   = $c->owner_company_id;
        $this->billing_company_id = $c->billing_company_id;

        $this->condition_notes = $c->condition_notes;
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    public function updated(string $campo): void
    {
        /* -----------------------------------------------------------------
         | CAMBIÓ LA MEDIDA → SE PROPONEN LOS PESOS
         |
         | Cada tamaño tiene su tara y su máximo de fábrica. Están en el
         | catálogo y nadie los recuerda de memoria.
         |
         | Solo se rellenan si están vacíos: si alguien ya pesó ESTA
         | unidad, su número manda sobre el de la tabla.
         * -------------------------------------------------------------- */
        if ($campo === 'container_size_id' && $this->container_size_id) {

            $medida = ContainerSize::find($this->container_size_id);

            $this->tare_weight_lbs ??= $medida?->default_tare_lbs;
            $this->max_weight_lbs  ??= $medida?->default_max_lbs;
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ LA CALIDAD → SE PROPONE LA APTITUD DE EXPORTACIÓN
         |
         | Solo el Cargo Worthy exporta. El dato vive en el catálogo de
         | calidades (`container_grades.is_export_eligible`), no en una
         | lista escrita a mano aquí: el día que se agregue una calidad
         | nueva, esto sigue funcionando.
         |
         | Se propone y se deja editable: una unidad Cargo Worthy con el
         | CSC vencido deja de ser exportable, y eso lo sabe quien la
         | está mirando.
         * -------------------------------------------------------------- */
        if ($campo === 'container_grade_id') {

            $calidad = $this->container_grade_id
                ? ContainerGrade::find($this->container_grade_id)
                : null;

            $this->is_export_eligible = (bool) $calidad?->is_export_eligible;
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL ESTADO
         |
         | En yarda sin fecha de recepción no tiene sentido: si está en la
         | yarda, llegó.
         |
         | Y al revés: mientras sigue en el depósito del proveedor, la
         | ubicación de la yarda no aplica. Dejarla puesta haría que el
         | inventario dijera que está en un sitio donde no está.
         * -------------------------------------------------------------- */
        if ($campo === 'status') {

            if ($this->status === ContainerStatus::InYard->value) {
                $this->received_at ??= now()->toDateString();
                $this->depot_id = null;
            }

            if ($this->status === ContainerStatus::AtSupplier->value) {
                $this->location_id = null;
            }
        }
    }

    /** Lo que costó puesto en la yarda. Se enseña mientras se teclea. */
    public function getCostoTotalProperty(): float
    {
        return (float) $this->acquisition_cost
             + (float) $this->pickup_cost
             + (float) $this->reconditioning_cost;
    }

    /**
     * El margen, si hay precio y hay costo.
     *
     * Es el número que decide si la venta tiene sentido, y hasta hoy
     * había que sacarlo con una calculadora al lado.
     */
    public function getMargenProperty(): ?array
    {
        if ($this->list_price === null || $this->list_price === '') {
            return null;
        }

        $precio = (float) $this->list_price;
        $costo  = $this->costoTotal;

        if ($costo <= 0) {
            return null;
        }

        return [
            'monto'   => $precio - $costo,
            'porciento' => round((($precio - $costo) / $costo) * 100, 1),
        ];
    }

    /* =====================================================================
     | LAS REGLAS
     * ================================================================== */

    protected function rules(): array
    {
        return [
            /*
             | El número es único en TODA la base, no por empresa.
             |
             | Un contenedor es un objeto físico: existe uno solo en el
             | mundo con ese número. Si apareciera dos veces, una de las
             | dos fichas está de más.
             |
             | `ignore` es para poder guardar la misma unidad sin que se
             | choque consigo misma al editarla.
             */
            'container_number' => ['nullable', 'string', 'max:15',
                Rule::unique('containers', 'container_number')
                    ->ignore($this->containerId)
                    ->whereNull('deleted_at')],

            'internal_code'     => ['nullable', 'string', 'max:20'],
            'year_manufactured' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],

            'container_type_id'      => ['nullable', 'exists:container_types,id'],
            'container_size_id'      => ['required', 'exists:container_sizes,id'],
            'container_condition_id' => ['nullable', 'exists:container_conditions,id'],
            'container_grade_id'     => ['nullable', 'exists:container_grades,id'],

            'material'        => ['nullable', Rule::in(['steel', 'aluminum', 'frp'])],
            'tare_weight_lbs' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_weight_lbs'  => ['nullable', 'integer', 'min:0', 'max:200000'],

            'status'      => ['required', Rule::in(ContainerStatus::values())],
            'location_id' => ['nullable', 'exists:locations,id'],
            'depot_id'    => ['nullable', 'exists:depots,id'],
            'received_at' => ['nullable', 'date'],

            'acquisition_cost'    => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'pickup_cost'         => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'reconditioning_cost' => ['nullable', 'numeric', 'min:0', 'max:999999'],

            'list_price'   => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'monthly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999'],

            'csc_valid_through' => ['nullable', 'date'],

            'owner_company_id'   => ['required', 'exists:companies,id'],
            'billing_company_id' => ['required', 'exists:companies,id'],

            'condition_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'container_number'       => 'número de contenedor',
            'internal_code'          => 'código interno',
            'container_size_id'      => 'la medida',
            'container_condition_id' => 'la condición',
            'container_grade_id'     => 'la calidad',
            'acquisition_cost'       => 'el costo de compra',
            'list_price'             => 'el precio de venta',
            'monthly_rate'           => 'la renta mensual',
            'owner_company_id'       => 'la empresa dueña',
            'billing_company_id'     => 'la empresa que factura',
        ];
    }

    protected function messages(): array
    {
        return [
            'container_number.unique' => 'Ya hay una unidad registrada con ese número. '
                                        .'Un contenedor no puede estar dos veces en el inventario.',
            'container_size_id.required' => 'Elija la medida. Sin ella no se puede cotizar la unidad.',
        ];
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    public function guardar(bool $yOtro = false)
    {
        $this->exigirPermiso($this->containerId ? 'update' : 'create');

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('errores-de-validacion');

            throw $e;
        }

        /* -----------------------------------------------------------------
         | NI NÚMERO NI CÓDIGO: NO HAY CÓMO LLAMARLA
         |
         | Los dos son opcionales por separado, pero no los dos a la vez.
         | Una unidad sin ninguna forma de nombrarla no se puede buscar,
         | no se puede poner en un presupuesto y no se puede pedir por
         | teléfono.
         * -------------------------------------------------------------- */
        if (blank($this->container_number) && blank($this->internal_code)) {
            $this->addError('container_number',
                'Escriba el número del contenedor o, si no lo tiene, un código interno '
                .'con el que la yarda pueda pedirla.');

            $this->dispatch('errores-de-validacion');

            return null;
        }

        $unidad = DB::transaction(function () {

            $esNueva = ! $this->containerId;

            $unidad = $esNueva
                ? new Container()
                : Container::findOrFail($this->containerId);

            // Lo de antes, para saber qué cambió y dejar el asiento.
            $estadoAntes   = $unidad->status?->value;
            $ubicacionAntes = $unidad->location_id;

            $unidad->fill([
                'container_number'  => $this->container_number ?: null,
                'internal_code'     => $this->internal_code ?: null,
                'year_manufactured' => $this->year_manufactured ?: null,

                'container_type_id'      => $this->container_type_id ?: null,
                'container_size_id'      => $this->container_size_id,
                'container_condition_id' => $this->container_condition_id ?: null,
                'container_grade_id'     => $this->container_grade_id ?: null,

                'material'        => $this->material ?: null,
                'tare_weight_lbs' => $this->tare_weight_lbs ?: null,
                'max_weight_lbs'  => $this->max_weight_lbs ?: null,

                'status'      => $this->status,
                'location_id' => $this->location_id ?: null,
                'depot_id'    => $this->depot_id ?: null,
                'received_at' => $this->received_at ?: null,

                /*
                 | Los costos van a 0 y no a null cuando están vacíos: son
                 | sumandos. Un null en medio de una suma de tres columnas
                 | la convierte en null entera, y el valor del inventario
                 | saldría vacío por una unidad sin costo de recogida.
                 */
                'acquisition_cost'    => $this->acquisition_cost !== '' ? $this->acquisition_cost : null,
                'pickup_cost'         => (float) ($this->pickup_cost ?: 0),
                'reconditioning_cost' => (float) ($this->reconditioning_cost ?: 0),

                /*
                 | Los precios sí van a null cuando están vacíos.
                 |
                 | Es lo contrario que los costos, y a propósito: un precio
                 | en 0 es un precio, y el presupuesto lo cargaría tal cual
                 | dejando una línea de $0.00. null significa "esta unidad
                 | todavía no tiene precio", y el contador del listado los
                 | cuenta para que alguien los ponga.
                 */
                'list_price'   => $this->list_price   !== '' && $this->list_price   !== null ? $this->list_price   : null,
                'monthly_rate' => $this->monthly_rate !== '' && $this->monthly_rate !== null ? $this->monthly_rate : null,

                'is_export_eligible' => $this->is_export_eligible,
                'csc_valid_through'  => $this->csc_valid_through ?: null,

                'owner_company_id'   => $this->owner_company_id,
                'billing_company_id' => $this->billing_company_id,

                'condition_notes' => $this->condition_notes ?: null,
            ]);

            if ($esNueva) {
                $unidad->created_by = auth()->id();
            }

            $unidad->save();

            $this->dejarAsiento($unidad, $esNueva, $estadoAntes, $ubicacionAntes);

            return $unidad;
        });

        /* -----------------------------------------------------------------
         | "GUARDAR Y REGISTRAR OTRO"
         |
         | El caso real: llega un release de siete unidades iguales. Se
         | conserva todo lo que se repite —clasificación, costos, precios,
         | ubicación— y se limpia solo lo que cambia de una a otra: el
         | número y el código.
         |
         | Registrar la segunda unidad pasa a ser teclear el número y
         | pulsar guardar.
         * -------------------------------------------------------------- */
        if ($yOtro) {
            $numero = $unidad->full_identifier;

            $this->containerId      = null;
            $this->container_number = null;
            $this->internal_code    = null;

            $this->resetValidation();

            session()->flash('exito', 'Unidad '.$numero.' registrada. '
                .'Los datos se conservan para la siguiente: escriba el número y guarde.');

            return null;
        }

        session()->flash('exito',
            $this->containerId
                ? 'Unidad '.$unidad->full_identifier.' actualizada.'
                : 'Unidad '.$unidad->full_identifier.' registrada.');

        return redirect()->route('operaciones.contenedores.show', $unidad);
    }

    /**
     * El asiento en el historial de la unidad.
     *
     * ── POR QUÉ NO VA EN UN OBSERVER ──
     *
     * Porque un observer no sabe QUIÉN lo hizo ni POR QUÉ. Un
     * movimiento sin autor es la mitad de un movimiento: cuando alguien
     * pregunte por qué esta unidad pasó a dañada, la respuesta "el
     * sistema" no sirve de nada.
     *
     * Y porque no todos los cambios merecen asiento. Corregir un precio
     * no es un movimiento; cambiar de estado sí.
     */
    protected function dejarAsiento(
        Container $unidad,
        bool $esNueva,
        ?string $estadoAntes,
        ?int $ubicacionAntes,
    ): void {

        if ($esNueva) {
            $unidad->movements()->create([
                'type'          => MovementType::Receipt->value,
                'to_location_id' => $unidad->location_id,
                'status_after'  => $unidad->status?->value,
                'moved_at'      => $unidad->received_at ?: now(),
                'notes'         => 'Alta de la unidad en el sistema.',
                'created_by'    => auth()->id(),
            ]);

            return;
        }

        $cambioEstado    = $estadoAntes !== $unidad->status?->value;
        $cambioUbicacion = $ubicacionAntes !== $unidad->location_id;

        if (! $cambioEstado && ! $cambioUbicacion) {
            return;
        }

        $unidad->movements()->create([
            /*
             | Si se movió de sitio es un traslado, aunque además haya
             | cambiado de estado: lo que se recuerda de una unidad es
             | dónde estuvo.
             */
            'type' => $cambioUbicacion
                ? MovementType::Transfer->value
                : MovementType::StatusChange->value,

            'from_location_id' => $ubicacionAntes,
            'to_location_id'   => $unidad->location_id,

            'status_before' => $estadoAntes,
            'status_after'  => $unidad->status?->value,

            'moved_at'   => now(),
            'created_by' => auth()->id(),
        ]);
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        return view('livewire.containers.form', [
            'tipos'       => ContainerType::active()->get(),
            'medidas'     => ContainerSize::active()->get(),
            'condiciones' => ContainerCondition::active()->get(),
            'calidades'   => ContainerGrade::active()->get(),

            'ubicaciones' => Location::where('is_active', true)->orderBy('name')->get(),
            'depositos'   => Depot::orderBy('name')->get(),

            'empresas' => Company::where('is_active', true)->orderBy('name')->get(),

            'estados' => ContainerStatus::options(),

            'materiales' => [
                'steel'    => 'Acero',
                'aluminum' => 'Aluminio',
                'frp'      => 'Fibra de vidrio',
            ],
        ]);
    }
}
