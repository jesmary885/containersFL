<?php

namespace App\Livewire\Companies;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Company;
use App\Support\CompanyContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * DATOS DE LA EMPRESA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DÓNDE SALE ──
 *
 * Reunión del 16 de septiembre. Denisse pidió incluir una sección estática
 * al pie de la factura con toda la información bancaria y los métodos de
 * pago, para que el cliente decida cómo pagar.
 *
 * Esa información ya tenía dónde vivir: `companies.payment_instructions`
 * existe desde el primer bloque de migraciones. Lo que no existía era una
 * pantalla para escribirla. Hasta hoy había que meterla a mano en la base
 * de datos, que es justo lo que este sistema viene a eliminar.
 *
 * ── POR QUÉ CADA COMPAÑÍA POR SEPARADO ──
 *
 * Porque cobran en cuentas distintas. Denisse lo explicó el 14 de agosto:
 * la de transporte y la de contenedores se mantienen separadas por el tema
 * de los impuestos de fin de año. Una factura de transporte que llevara la
 * cuenta de la de contenedores mandaría el dinero al sitio equivocado.
 *
 * Esta pantalla edita la compañía ACTIVA, la que está elegida en la barra
 * de arriba. Para cargar la otra se cambia de compañía y se vuelve a
 * entrar. Es más claro que un selector propio que dijera algo distinto a
 * lo que dice la barra.
 *
 * ── QUÉ NO HACE ──
 *
 * No toca el EIN, el sales tax number ni el resale certificate. Esos son
 * datos fiscales que se cargan una vez y cambiarlos por accidente saldría
 * caro. Se quedan donde están hasta que alguien los pida.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    /** El permiso que gobierna esta pantalla. */
    protected string $permisoBase = 'settings';

    public ?int $companyId = null;

    /* =====================================================================
     | LO QUE SALE IMPRESO EN EL DOCUMENTO
     * ================================================================== */

    public ?string $address_line1 = null;
    public ?string $address_line2 = null;
    public ?string $city          = null;
    public ?string $state         = null;
    public ?string $zip           = null;
    public ?string $phone         = null;
    public ?string $email         = null;
    public ?string $website       = null;

    /** El texto legal del pie. Términos, garantías, lo que sea. */
    public ?string $invoice_footer_terms = null;

    /* =====================================================================
     | LAS FORMAS DE PAGO
     |
     | Un arreglo de filas con dos campos: cómo se llama la forma de pago
     | ("Zelle", "Wire / SWIFT", "Cheque a nombre de...") y los datos.
     |
     | ── POR QUÉ UN JSON Y NO UNA TABLA ──
     |
     | Porque no se consulta, no se filtra y no se relaciona con nada. Se
     | imprime entero, siempre junto, al pie de un documento. Una tabla
     | con su modelo y su migración para eso sería ceremonia sin uso.
     |
     | ── POR QUÉ DOS CAMPOS Y NO UNO POR DATO BANCARIO ──
     |
     | Porque cada forma de pago pide datos distintos. Un Zelle es un
     | correo; un wire internacional lleva SWIFT, routing, número de cuenta,
     | nombre y dirección del banco. Un formulario con quince campos
     | dejaría trece vacíos en casi todas las filas.
     |
     | Los saltos de línea se respetan al imprimir, así que los datos de
     | una transferencia se leen en renglones y no en un párrafo.
     * ================================================================== */

    public array $formasDePago = [];

    /* =====================================================================
     | CARGA
     * ================================================================== */

    public function mount(): void
    {
        $this->exigirPermiso('view');

        $empresa = app(CompanyContext::class)->get();

        abort_if(! $empresa, 404, 'No hay una compañía activa.');

        $this->companyId = $empresa->id;

        $this->address_line1 = $empresa->address_line1;
        $this->address_line2 = $empresa->address_line2;
        $this->city          = $empresa->city;
        $this->state         = $empresa->state;
        $this->zip           = $empresa->zip;
        $this->phone         = $empresa->phone;
        $this->email         = $empresa->email;
        $this->website       = $empresa->website;

        $this->invoice_footer_terms = $empresa->invoice_footer_terms;

        $this->formasDePago = $this->normalizar($empresa->payment_instructions ?? []);

        /*
         | Si nunca se cargó ninguna, se propone la lista real del negocio.
         |
         | Sale de la factura de RS Transport que está entre los documentos
         | recolectados: Cash, Check, Zelle, ACH, Wire/SWIFT, Credit Card y
         | Square. Vienen con el nombre puesto y los datos en blanco, para
         | que Denisse solo tenga que rellenar y borrar las que no usa.
         |
         | Es más rápido que empezar de una pantalla vacía y más honesto
         | que inventarle números de cuenta.
         */
        if (empty($this->formasDePago)) {
            $this->formasDePago = [
                ['label' => 'Zelle',            'details' => ''],
                ['label' => 'ACH',              'details' => ''],
                ['label' => 'Wire / SWIFT',     'details' => ''],
                ['label' => 'Check',            'details' => ''],
                ['label' => 'Credit Card',      'details' => ''],
            ];
        }
    }

    /**
     * Deja el arreglo en la forma que espera la pantalla.
     *
     * El JSON guardado puede venir de una versión anterior con otra
     * estructura, o directamente a mano desde la base. En vez de confiar,
     * se normaliza: cada fila termina con 'label' y 'details', sean textos
     * o cadenas vacías.
     */
    private function normalizar(mixed $crudo): array
    {
        if (! is_array($crudo)) {
            return [];
        }

        return collect($crudo)
            ->map(fn ($fila) => is_array($fila)
                ? [
                    'label'   => (string) ($fila['label']   ?? ''),
                    'details' => (string) ($fila['details'] ?? ''),
                ]
                : ['label' => (string) $fila, 'details' => ''])
            ->values()
            ->all();
    }

    /* =====================================================================
     | EDICIÓN DE LAS FILAS
     * ================================================================== */

    public function agregarForma(): void
    {
        $this->exigirPermiso('update');

        $this->formasDePago[] = ['label' => '', 'details' => ''];
    }

    public function quitarForma(int $i): void
    {
        $this->exigirPermiso('update');

        unset($this->formasDePago[$i]);

        /*
         | array_values reindexa. Sin esto quedan huecos en las claves
         | (0, 2, 3) y Livewire empieza a pintar filas donde no toca.
         */
        $this->formasDePago = array_values($this->formasDePago);
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    public function guardar()
    {
        $this->exigirPermiso('update');

        $this->validate([
            'address_line1' => ['nullable', 'string', 'max:150'],
            'address_line2' => ['nullable', 'string', 'max:150'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'size:2'],
            'zip'           => ['nullable', 'string', 'max:10'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:150'],
            'website'       => ['nullable', 'string', 'max:150'],

            'invoice_footer_terms' => ['nullable', 'string', 'max:2000'],

            'formasDePago'           => ['array', 'max:12'],
            'formasDePago.*.label'   => ['nullable', 'string', 'max:60'],
            'formasDePago.*.details' => ['nullable', 'string', 'max:500'],
        ], [], [
            'formasDePago.*.label'   => 'el nombre de la forma de pago',
            'formasDePago.*.details' => 'los datos de la forma de pago',
        ]);

        $empresa = Company::findOrFail($this->companyId);

        /*
         | Las filas vacías no se guardan.
         |
         | La pantalla arranca con cinco propuestas; si Denisse llena tres
         | y deja dos en blanco, esas dos no tienen por qué acabar en el
         | JSON ni salir impresas como recuadros vacíos.
         |
         | Una fila cuenta como llena si tiene NOMBRE. Unos datos sueltos
         | sin decir de qué forma de pago son no le sirven a nadie.
         */
        $formas = collect($this->formasDePago)
            ->map(fn ($f) => [
                'label'   => trim((string) ($f['label']   ?? '')),
                'details' => trim((string) ($f['details'] ?? '')),
            ])
            ->filter(fn ($f) => $f['label'] !== '')
            ->values()
            ->all();

        $empresa->update([
            'address_line1' => $this->address_line1 ?: null,
            'address_line2' => $this->address_line2 ?: null,
            'city'          => $this->city ?: null,
            'state'         => $this->state ? strtoupper($this->state) : null,
            'zip'           => $this->zip ?: null,
            'phone'         => $this->phone ?: null,
            'email'         => $this->email ?: null,
            'website'       => $this->website ?: null,

            'invoice_footer_terms' => $this->invoice_footer_terms ?: null,

            'payment_instructions' => $formas ?: null,
        ]);

        $this->formasDePago = $this->normalizar($formas);

        session()->flash('exito', __('companies.saved'));
    }

    public function render()
    {
        return view('livewire.companies.form', [
            'empresa' => Company::find($this->companyId),
        ]);
    }
}
