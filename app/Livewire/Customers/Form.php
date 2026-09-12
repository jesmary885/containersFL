<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerType;
use App\Enums\DocumentCategory;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL CLIENTE — crear y editar
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── QUÉ CAMBIÓ EN ESTA VERSIÓN ──
 *
 * 1 · EL FORMULARIO VA POR PASOS, como el de presupuesto.
 *
 *     1 · QUIÉN ES        tipo, nombre, teléfono, correo, Sunbiz
 *     2 · DÓNDE Y QUIÉN   direcciones y contactos
 *     3 · CONDICIONES     tarjeta, crédito, notas y documentos
 *
 *     Era una sola pantalla con cuatro bloques apilados. Con dos
 *     direcciones y tres contactos se iba a metro y medio de scroll, y
 *     el botón de guardar quedaba a ciegas al final.
 *
 * 2 · SE ARREGLARON LAS MARCAS QUE NO SE PODÍAN QUITAR.
 *
 *     Los botones "Facturación", "Entrega" y "Principal" encendían pero
 *     no apagaban. Explicado en detalle más abajo, donde están los tres
 *     métodos.
 *
 * 3 · SE PUEDEN ADJUNTAR DOCUMENTOS.
 *
 *     La tabla `documents` existía desde el principio, con categoría y
 *     fecha de vencimiento, y no había ninguna pantalla que escribiera
 *     en ella. Ahora la hay.
 *
 * ── TRES COSAS QUE ESTA PANTALLA SIGUE SIN HACER, A PROPÓSITO ──
 *
 * 1. NO edita `tax_exempt`.
 *    Es un caché que mantiene el TaxExemptionCertificateObserver: vale
 *    true si el cliente tiene un certificado vigente HOY, y se recalcula
 *    solo. Dejar que alguien lo marque a mano crearía clientes "exentos"
 *    sin papel que lo respalde, y RB-015 pide justo lo contrario: la
 *    factura tiene que guardar CUÁL certificado justificaba la exención.
 *    Sin el certificado, el impuesto lo termina pagando la empresa.
 *
 *    Los certificados se registran desde la ficha (Show).
 *
 * 2. NO genera `customer_number`.
 *    De eso se encarga el CustomerObserver, que es el mismo camino por
 *    el que pasa un cliente venga de donde venga: pantalla, seeder o
 *    importación del Excel.
 *
 * 3. NO borra.
 *    Un cliente tiene presupuestos, facturas y pagos colgando de su id.
 *    Se desactiva.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess, WithFileUploads;

    protected string $permisoBase = 'customers';

    /** null = cliente nuevo. */
    public ?int $customerId = null;

    /** El número, solo para el título. Lo asigna el observer al guardar. */
    public string $numero = '';

    /* =====================================================================
     | EL PASO EN EL QUE ESTÁ
     * ================================================================== */

    public int $paso = 1;

    public const PASOS = 3;

    /* =====================================================================
     | QUIÉN ES
     * ================================================================== */

    public string $type = 'business';

    public ?string $company_name = null;
    public ?string $first_name   = null;
    public ?string $last_name    = null;

    /**
     * El alias con el que se le conoce.
     *
     * Se deja vacío casi siempre: el observer lo compone desde la razón
     * social o desde nombre y apellido. Solo se escribe cuando el
     * cliente se conoce por algo que no es ninguno de los dos.
     */
    public ?string $display_name = null;

    public ?string $primary_phone = null;
    public ?string $primary_email = null;

    public string $preferred_locale = 'en';

    public ?string $source = null;

    /* =====================================================================
     | VERIFICACIÓN EN SUNBIZ
     |
     | Solo para empresas. Es el control contra fraude del levantamiento:
     | antes de darle crédito o de procesarle una tarjeta, se comprueba
     | que la compañía existe de verdad en el registro de Florida.
     * ================================================================== */

    public bool $sunbiz_verified = false;

    public ?string $sunbiz_document_number = null;

    /* =====================================================================
     | CONDICIONES COMERCIALES
     * ================================================================== */

    public bool $allow_credit_card = false;
    public bool $credit_hold       = false;
    public bool $is_active         = true;

    public ?string $notes = null;

    /* =====================================================================
     | LAS DIRECCIONES Y LOS CONTACTOS
     |
     | Van como arreglos de arreglos y se guardan con la ficha, no cada
     | uno por su lado. Un cliente a medio cargar —con la dirección
     | guardada y el contacto no— es peor que uno sin cargar: parece
     | completo.
     * ================================================================== */

    public array $direcciones = [];

    public array $contactos = [];

    /* =====================================================================
     | LOS DOCUMENTOS QUE ESTÁN ESPERANDO PARA SUBIRSE
     |
     | Cada renglón: el archivo, qué es, cuándo vence y una nota.
     |
     | No se suben al vuelo: se guardan cuando se guarda la ficha. La
     | razón es el cliente nuevo —todavía no tiene id, así que no hay a
     | qué colgarle el archivo—, pero además evita el caso de subir tres
     | papeles, arrepentirse y salir sin guardar dejando basura en el
     | disco.
     * ================================================================== */

    public array $adjuntos = [];

    /** El documento ya guardado que espera confirmación para borrarse. */
    public ?int $documentoPorBorrar = null;

    /* =====================================================================
     | ARRANQUE
     * ================================================================== */

    public function mount(?Customer $customer = null)
    {
        if ($customer && $customer->exists) {
            $this->exigirPermiso('update');
            $this->cargarDesde($customer);

            return null;
        }

        $this->exigirPermiso('create');

        /*
         | Un cliente nuevo arranca con una dirección en blanco y ningún
         | contacto.
         |
         | La dirección sí, porque es lo que hace falta para poder
         | cotizarle y es justo lo que hoy falta en la mitad de las
         | fichas. Los contactos no: un cliente de una sola persona no
         | necesita ninguno, y un renglón vacío pidiendo nombre y correo
         | parece una obligación.
         */
        $this->direcciones = [$this->direccionVacia()];

        return null;
    }

    protected function cargarDesde(Customer $customer): void
    {
        $customer->load(['addresses', 'contacts']);

        $this->customerId = $customer->id;
        $this->numero     = $customer->customer_number;

        $this->type         = $customer->type?->value ?? 'business';
        $this->company_name = $customer->company_name;
        $this->first_name   = $customer->first_name;
        $this->last_name    = $customer->last_name;

        /*
         | El alias se enseña solo si es distinto de lo que el observer
         | compondría. Si coincide, el campo sale vacío y el usuario ve
         | el texto de ayuda en vez de un dato que no eligió.
         */
        $this->display_name = $customer->display_name === $this->nombreCompuesto()
            ? null
            : $customer->display_name;

        $this->primary_phone    = $customer->primary_phone;
        $this->primary_email    = $customer->primary_email;
        $this->preferred_locale = $customer->preferred_locale ?: 'en';
        $this->source           = $customer->source;

        $this->sunbiz_verified        = (bool) $customer->sunbiz_verified;
        $this->sunbiz_document_number = $customer->sunbiz_document_number;

        $this->allow_credit_card = (bool) $customer->allow_credit_card;
        $this->credit_hold       = (bool) $customer->credit_hold;
        $this->is_active         = (bool) $customer->is_active;
        $this->notes             = $customer->notes;

        $this->direcciones = $customer->addresses->map(fn ($d) => [
            'id'                  => $d->id,
            'uid'                 => 'd'.$d->id,
            'label'               => $d->label,
            'line1'               => $d->line1,
            'line2'               => $d->line2,
            'city'                => $d->city,
            'state'               => $d->state ?: 'FL',
            'zip'                 => $d->zip,
            'is_default_billing'  => (bool) $d->is_default_billing,
            'is_default_shipping' => (bool) $d->is_default_shipping,
        ])->all();

        $this->contactos = $customer->contacts->map(fn ($c) => [
            'id'                => $c->id,
            'uid'               => 'c'.$c->id,
            'name'              => $c->name,
            'role'              => $c->role,
            'email'             => $c->email,
            'phone'             => $c->phone,
            'is_primary'        => (bool) $c->is_primary,
            'notify_invoices'   => (bool) $c->notify_invoices,
            'notify_reminders'  => (bool) $c->notify_reminders,
        ])->all();

        if (empty($this->direcciones)) {
            $this->direcciones = [$this->direccionVacia()];
        }
    }

    /* =====================================================================
     | LAS FORMAS EN BLANCO
     * ================================================================== */

    /**
     * El uid de cada renglón.
     *
     * ── PARA QUÉ SIRVE ──
     *
     * Livewire vuelve a dibujar la pantalla en cada clic, y para no
     * rehacerla entera compara la nueva con la que ya estaba y cambia
     * solo lo distinto. Para emparejarlas usa el `wire:key` de cada
     * renglón.
     *
     * Si el key es el número de fila —0, 1, 2— y se borra el contacto
     * del medio, el que era 2 pasa a ser 1. Livewire mira el key, ve
     * "1" en los dos lados y concluye que es el mismo renglón: deja el
     * cuadro donde estaba y le cambia los datos por dentro.
     *
     * Con campos normales eso se nota poco. Con los de teléfono y
     * correo, que llevan su propio estado en el navegador, el cuadro se
     * queda con el teléfono del contacto borrado.
     *
     * Un uid que nace con el renglón y no cambia nunca resuelve el
     * emparejamiento de verdad: si el renglón desaparece, su key
     * desaparece con él.
     *
     * Los guardados usan su id de la base, que ya es único y estable.
     */
    protected function nuevoUid(): string
    {
        return uniqid('n', true);
    }

    protected function direccionVacia(): array
    {
        return [
            'id'    => null,
            'uid'   => $this->nuevoUid(),
            'label' => '',
            'line1' => '',
            'line2' => '',
            'city'  => '',

            // FL escrito de verdad, no como texto gris del placeholder.
            'state' => 'FL',
            'zip'   => '',

            'is_default_billing'  => false,
            'is_default_shipping' => false,
        ];
    }

    protected function contactoVacio(): array
    {
        return [
            'id'    => null,
            'uid'   => $this->nuevoUid(),
            'name'  => '',
            'role'  => '',
            'email' => '',
            'phone' => '',

            'is_primary' => false,

            /*
             | Los dos avisos encendidos por defecto.
             |
             | RB-028 dice que el aviso va a TODOS los contactos
             | registrados. Nacer apagados obligaría a acordarse de
             | encenderlos, y el precio de olvidarse es que el cliente no
             | se entera de que debe.
             */
            'notify_invoices'  => true,
            'notify_reminders' => true,
        ];
    }

    protected function adjuntoVacio(): array
    {
        return [
            'archivo'    => null,
            'category'   => DocumentCategory::Contract->value,
            'expires_at' => '',
            'notes'      => '',
        ];
    }

    /* =====================================================================
     | AGREGAR Y QUITAR
     * ================================================================== */

    public function agregarDireccion(): void
    {
        $this->direcciones[] = $this->direccionVacia();
    }

    public function quitarDireccion(int $indice): void
    {
        unset($this->direcciones[$indice]);

        // array_values renumera desde cero. Sin esto, los índices quedan
        // con huecos y las casillas de la vista apuntan a la fila de al lado.
        $this->direcciones = array_values($this->direcciones);

        if (empty($this->direcciones)) {
            $this->direcciones = [$this->direccionVacia()];
        }
    }

    public function agregarContacto(): void
    {
        $this->contactos[] = $this->contactoVacio();
    }

    public function quitarContacto(int $indice): void
    {
        unset($this->contactos[$indice]);
        $this->contactos = array_values($this->contactos);
    }

    public function agregarAdjunto(): void
    {
        $this->adjuntos[] = $this->adjuntoVacio();
    }

    public function quitarAdjunto(int $indice): void
    {
        unset($this->adjuntos[$indice]);
        $this->adjuntos = array_values($this->adjuntos);
    }

    /* =====================================================================
     | LAS MARCAS DE "POR DEFECTO" — EL ARREGLO
     |
     | ── QUÉ ESTABA MAL ──
     |
     | Los tres métodos hacían esto:
     |
     |     $this->direcciones[$i]['is_default_billing'] = ($i === $indice);
     |
     | Eso enciende la fila que pulsaste y apaga todas las demás. Es
     | correcto para "elegir entre varias", pero le falta la mitad: si
     | pulsas la que YA estaba encendida, la vuelve a encender. Nunca se
     | apaga. Por eso no se podía destildar.
     |
     | ── QUÉ HACE AHORA ──
     |
     | Primero pregunta cómo estaba. Si estaba encendida, la apaga y
     | listo. Si estaba apagada, la enciende y apaga las demás.
     |
     | Siguen siendo excluyentes —dos direcciones de facturación no
     | significan nada, porque el presupuesto se queda con la primera que
     | encuentre— pero ahora se pueden dejar las dos en cero, que es lo
     | que uno espera de un botón que se puede pulsar.
     |
     | ── ¿Y SI SE GUARDA SIN NINGUNA MARCADA? ──
     |
     | No pasa nada malo: guardar() marca la primera. Eso ya estaba y se
     | mantiene. La diferencia es que ahora es el sistema el que decide
     | cuando el usuario no quiso decidir, en vez de obligar al usuario a
     | decidir sí o sí.
     * ================================================================== */

    public function marcarFacturacion(int $indice): void
    {
        $yaEstaba = (bool) ($this->direcciones[$indice]['is_default_billing'] ?? false);

        if ($yaEstaba) {
            $this->direcciones[$indice]['is_default_billing'] = false;

            return;
        }

        foreach ($this->direcciones as $i => $direccion) {
            $this->direcciones[$i]['is_default_billing'] = ($i === $indice);
        }
    }

    public function marcarEnvio(int $indice): void
    {
        $yaEstaba = (bool) ($this->direcciones[$indice]['is_default_shipping'] ?? false);

        if ($yaEstaba) {
            $this->direcciones[$indice]['is_default_shipping'] = false;

            return;
        }

        foreach ($this->direcciones as $i => $direccion) {
            $this->direcciones[$i]['is_default_shipping'] = ($i === $indice);
        }
    }

    public function marcarContactoPrincipal(int $indice): void
    {
        $yaEstaba = (bool) ($this->contactos[$indice]['is_primary'] ?? false);

        if ($yaEstaba) {
            $this->contactos[$indice]['is_primary'] = false;

            return;
        }

        foreach ($this->contactos as $i => $contacto) {
            $this->contactos[$i]['is_primary'] = ($i === $indice);
        }
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    public function updated(string $campo): void
    {
        /* -----------------------------------------------------------------
         | CAMBIÓ EL TIPO DE CLIENTE
         |
         | Se limpian los campos que ya no aplican. Si no, una persona
         | natural que antes se registró como empresa conserva la razón
         | social escondida en la base, y el observer podría recomponer
         | el nombre a mostrar desde un dato que la pantalla ya no
         | enseña.
         * -------------------------------------------------------------- */
        if ($campo === 'type') {

            if ($this->esEmpresa()) {
                $this->first_name = null;
                $this->last_name  = null;
            } else {
                $this->company_name           = null;
                $this->sunbiz_verified        = false;
                $this->sunbiz_document_number = null;
            }

            $this->resetValidation();
        }

        /* -----------------------------------------------------------------
         | SE QUITÓ LA VERIFICACIÓN DE SUNBIZ
         |
         | El número de documento sin la verificación es un dato sin
         | significado: es la prueba de algo que se dejó de afirmar.
         * -------------------------------------------------------------- */
        if ($campo === 'sunbiz_verified' && ! $this->sunbiz_verified) {
            $this->sunbiz_document_number = null;
        }
    }

    public function esEmpresa(): bool
    {
        return $this->type === CustomerType::Business->value;
    }

    /** Lo que el observer compondría como nombre a mostrar. */
    public function nombreCompuesto(): string
    {
        return $this->esEmpresa()
            ? trim((string) $this->company_name)
            : trim($this->first_name.' '.$this->last_name);
    }

    /* =====================================================================
     | MOVERSE ENTRE PASOS
     |
     | Hacia atrás es libre. Hacia adelante valida lo que queda en medio:
     | llegar al paso 3 sin nombre de cliente no significa nada.
     * ================================================================== */

    public function siguientePaso(): void
    {
        $this->descartarVacios();

        /*
         | descartarVacios() acaba de borrar los renglones que nadie
         | lleno, y eso incluye la direccion en blanco con la que nace
         | el formulario.
         |
         | Si se dejara asi, quien pasa del paso 1 al 2 sin haber
         | escrito nada llegaria a "Direcciones" sin ningun renglon: una
         | tarjeta vacia con un boton de agregar. Parece roto.
         |
         | Se repone el renglon en blanco. Al guardar vuelve a
         | descartarse, asi que no se guarda nada de mas.
         */
        if (empty($this->direcciones)) {
            $this->direcciones = [$this->direccionVacia()];
        }

        $this->validate($this->reglasDelPaso($this->paso), $this->messages(), $this->validationAttributes());

        $this->paso = min($this->paso + 1, self::PASOS);

        $this->dispatch('subir-al-inicio');
    }

    public function pasoAnterior(): void
    {
        $this->paso = max($this->paso - 1, 1);

        $this->resetValidation();

        $this->dispatch('subir-al-inicio');
    }

    public function irAlPaso(int $destino): void
    {
        $destino = max(1, min($destino, self::PASOS));

        if ($destino <= $this->paso) {
            $this->paso = $destino;
            $this->resetValidation();
            $this->dispatch('subir-al-inicio');

            return;
        }

        while ($this->paso < $destino) {
            $antes = $this->paso;

            $this->siguientePaso();

            // La validación no dejó pasar. Se queda donde está y con los
            // errores en pantalla.
            if ($this->paso === $antes) {
                return;
            }
        }
    }

    /**
     * La tira de contexto que se queda arriba en los pasos 2 y 3.
     *
     * Va en texto y no en cajitas: un dato que se lee se revisa, un dato
     * dentro de un input se ignora. Ahí es donde se cazan los errores de
     * dedo.
     */
    public function getResumenProperty(): array
    {
        $principal = collect($this->direcciones)
            ->first(fn ($d) => ! empty($d['is_default_billing']))
            ?? ($this->direcciones[0] ?? null);

        $ciudad = collect([$principal['city'] ?? null, $principal['state'] ?? null])
            ->filter()->implode(', ');

        return [
            'nombre'      => $this->nombreCompuesto() ?: null,
            'tipo'        => CustomerType::tryFrom($this->type)?->label(),
            'contacto'    => $this->primary_phone ?: $this->primary_email ?: null,
            'direccion'   => trim($ciudad.' '.($principal['zip'] ?? '')) ?: null,
            'contactos'   => count($this->contactos),
            'documentos'  => count(array_filter($this->adjuntos, fn ($a) => ! empty($a['archivo']))),
        ];
    }

    /* =====================================================================
     | LAS REGLAS
     * ================================================================== */

    /**
     * Qué se exige en cada paso.
     *
     * Se parte en tres para que el error salga en la pantalla donde está
     * el campo. Validar todo de golpe en el paso 1 pondría un mensaje
     * rojo sobre un campo que el usuario todavía no ha visto.
     */
    protected function reglasDelPaso(int $paso): array
    {
        $todas = $this->rules();

        $porPaso = [
            1 => ['type', 'company_name', 'first_name', 'last_name', 'display_name',
                  'primary_phone', 'primary_email', 'preferred_locale', 'source',
                  'sunbiz_document_number'],

            2 => ['direcciones', 'direcciones.*.label', 'direcciones.*.line1',
                  'direcciones.*.line2', 'direcciones.*.city', 'direcciones.*.state',
                  'direcciones.*.zip',
                  'contactos', 'contactos.*.name', 'contactos.*.role',
                  'contactos.*.email', 'contactos.*.phone'],

            3 => ['notes', 'adjuntos', 'adjuntos.*.archivo', 'adjuntos.*.category',
                  'adjuntos.*.expires_at', 'adjuntos.*.notes'],
        ];

        return collect($porPaso[$paso] ?? [])
            ->mapWithKeys(fn ($campo) => [$campo => $todas[$campo] ?? []])
            ->filter(fn ($reglas) => ! empty($reglas))
            ->all();
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::in(CustomerType::values())],

            /*
             | `required_if` y no `required`: cuál de los dos nombres hace
             | falta depende del tipo. Escrito así, el mensaje de error
             | señala el campo que el usuario está viendo en pantalla, no
             | el que la otra opción habría pedido.
             */
            'company_name' => ['nullable', 'string', 'max:200',
                Rule::requiredIf(fn () => $this->esEmpresa())],

            'first_name' => ['nullable', 'string', 'max:100',
                Rule::requiredIf(fn () => ! $this->esEmpresa())],

            'last_name' => ['nullable', 'string', 'max:100'],

            'display_name' => ['nullable', 'string', 'max:200'],

            'primary_phone' => ['nullable', 'string', 'max:30'],
            'primary_email' => ['nullable', 'email', 'max:150'],

            'preferred_locale' => ['required', Rule::in(['es', 'en'])],
            'source'           => ['nullable', 'string', 'max:30'],

            'sunbiz_document_number' => ['nullable', 'string', 'max:30'],

            'notes' => ['nullable', 'string', 'max:5000'],

            /* -------------------------------------------------------------
             | LAS DIRECCIONES
             |
             | `line1` es lo único obligatorio de verdad. Ciudad, estado y
             | ZIP se piden porque hacen falta para imprimir un documento
             | decente, pero una dirección a medias es mejor que ninguna:
             | el caso real es el cliente que da el nombre de la calle por
             | teléfono y el resto después.
             * ---------------------------------------------------------- */
            'direcciones'             => ['array'],
            'direcciones.*.label'     => ['nullable', 'string', 'max:50'],
            'direcciones.*.line1'     => ['required', 'string', 'max:150'],
            'direcciones.*.line2'     => ['nullable', 'string', 'max:150'],
            'direcciones.*.city'      => ['nullable', 'string', 'max:100'],
            'direcciones.*.state'     => ['nullable', 'string', 'size:2'],
            'direcciones.*.zip'       => ['nullable', 'string', 'max:10'],

            /* -------------------------------------------------------------
             | LOS CONTACTOS
             |
             | El nombre es obligatorio; el correo y el teléfono no, pero
             | al menos uno de los dos sí. Un contacto sin ninguna forma de
             | contactarlo es una fila que ocupa sitio: RB-028 dice que el
             | aviso va a todos los contactos registrados, y a este no
             | habría por dónde mandárselo.
             * ---------------------------------------------------------- */
            'contactos'         => ['array'],
            'contactos.*.name'  => ['required', 'string', 'max:150'],
            'contactos.*.role'  => ['nullable', 'string', 'max:50'],
            'contactos.*.email' => ['nullable', 'email', 'max:150', 'required_without:contactos.*.phone'],
            'contactos.*.phone' => ['nullable', 'string', 'max:30'],

            /* -------------------------------------------------------------
             | LOS ADJUNTOS
             |
             | 10 MB y formatos cerrados. El límite no es capricho: un
             | escaneo de un certificado pesa menos de 1 MB, y lo que llega
             | de 40 es siempre una foto sin comprimir que nadie va a
             | volver a abrir.
             |
             | La lista de formatos deja fuera los ejecutables, que es el
             | motivo real de tener lista.
             * ---------------------------------------------------------- */
            'adjuntos'              => ['array', 'max:10'],
            'adjuntos.*.archivo'    => ['nullable', 'file', 'max:10240',
                                        'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'adjuntos.*.category'   => ['required', Rule::in(DocumentCategory::values())],
            'adjuntos.*.expires_at' => ['nullable', 'date'],
            'adjuntos.*.notes'      => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'type'                   => 'tipo de cliente',
            'company_name'           => 'razón social',
            'first_name'             => 'nombre',
            'last_name'              => 'apellido',
            'display_name'           => 'nombre a mostrar',
            'primary_phone'          => 'teléfono',
            'primary_email'          => 'correo',
            'preferred_locale'       => 'idioma',
            'sunbiz_document_number' => 'número de documento de Sunbiz',
            'direcciones.*.line1'    => 'la calle de la dirección',
            'direcciones.*.state'    => 'el estado',
            'contactos.*.name'       => 'el nombre del contacto',
            'contactos.*.email'      => 'el correo del contacto',
            'adjuntos.*.archivo'     => 'el archivo',
            'adjuntos.*.category'    => 'el tipo de documento',
            'adjuntos.*.expires_at'  => 'la fecha de vencimiento',
        ];
    }

    protected function messages(): array
    {
        return [
            'company_name.required'   => 'Escriba la razón social de la empresa.',
            'first_name.required'     => 'Escriba el nombre de la persona.',
            'direcciones.*.line1.required' => 'Cada dirección necesita al menos la calle. Si no la tiene todavía, quite el renglón.',
            'direcciones.*.state.size'     => 'El estado va con dos letras: FL, GA, NY.',
            'contactos.*.name.required'    => 'Cada contacto necesita un nombre.',
            'contactos.*.email.required_without' => 'El contacto necesita un correo o un teléfono. Sin ninguno de los dos no hay cómo avisarle.',
            'adjuntos.*.archivo.max'   => 'El archivo no puede pasar de 10 MB.',
            'adjuntos.*.archivo.mimes' => 'Se aceptan PDF, imágenes, Word y Excel.',
            'adjuntos.max'             => 'Máximo diez documentos por vez. Guarde y suba el resto después.',
        ];
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    public function guardar()
    {
        $this->exigirPermiso($this->customerId ? 'update' : 'create');

        $this->descartarVacios();

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('errores-de-validacion');

            throw $e;
        }

        /* -----------------------------------------------------------------
         | SI NADIE MARCÓ LA DIRECCIÓN FISCAL, SE MARCA LA PRIMERA
         |
         | El presupuesto la busca con firstWhere('is_default_billing').
         | Sin ninguna marcada cae a `->first()`, que funciona pero
         | depende del orden de guardado. Marcarla aquí convierte un
         | comportamiento accidental en uno decidido.
         * -------------------------------------------------------------- */
        if ($this->direcciones && ! collect($this->direcciones)->contains('is_default_billing', true)) {
            $this->direcciones[0]['is_default_billing'] = true;
        }

        // Lo mismo con el contacto principal.
        if ($this->contactos && ! collect($this->contactos)->contains('is_primary', true)) {
            $this->contactos[0]['is_primary'] = true;
        }

        $cliente = DB::transaction(function () {

            $datos = [
                'type'         => $this->type,
                'company_name' => $this->esEmpresa() ? $this->company_name : null,
                'first_name'   => $this->esEmpresa() ? null : $this->first_name,
                'last_name'    => $this->esEmpresa() ? null : $this->last_name,

                /*
                 | Si el alias viene vacío se manda null y el observer lo
                 | compone. Mandar cadena vacía lo dejaría pasar: `filled('')`
                 | es false, pero la columna se quedaría con '' y el
                 | getNameAttribute() del modelo devolvería el nombre
                 | correcto mientras la lista enseña un hueco.
                 */
                'display_name' => filled($this->display_name) ? $this->display_name : null,

                'primary_phone'    => $this->primary_phone ?: null,
                'primary_email'    => $this->primary_email ?: null,
                'preferred_locale' => $this->preferred_locale,
                'source'           => $this->source ?: null,

                'sunbiz_verified'        => $this->esEmpresa() && $this->sunbiz_verified,
                'sunbiz_document_number' => $this->esEmpresa() && $this->sunbiz_verified
                    ? ($this->sunbiz_document_number ?: null)
                    : null,

                'allow_credit_card' => $this->allow_credit_card,
                'credit_hold'       => $this->credit_hold,
                'is_active'         => $this->is_active,
                'notes'             => $this->notes ?: null,
            ];

            /* -------------------------------------------------------------
             | QUIÉN Y CUÁNDO SE VERIFICÓ EN SUNBIZ
             |
             | Solo se sella cuando la marca PASA de apagada a encendida.
             | Si se re-sellara en cada guardado, la fecha diría cuándo se
             | editó la ficha por última vez y no cuándo alguien miró el
             | registro de Florida — que es lo único que la hace útil.
             * ---------------------------------------------------------- */
            $cliente = $this->customerId ? Customer::findOrFail($this->customerId) : new Customer();

            $verificaAhora = $datos['sunbiz_verified'] && ! $cliente->sunbiz_verified;

            if ($verificaAhora) {
                $datos['sunbiz_verified_at'] = now();
                $datos['sunbiz_verified_by'] = auth()->id();
            } elseif (! $datos['sunbiz_verified']) {
                $datos['sunbiz_verified_at'] = null;
                $datos['sunbiz_verified_by'] = null;
            }

            $cliente->fill($datos)->save();

            $this->guardarDirecciones($cliente);
            $this->guardarContactos($cliente);
            $this->guardarAdjuntos($cliente);

            return $cliente;
        });

        session()->flash('exito',
            $this->customerId
                ? 'Cliente '.$cliente->name.' actualizado.'
                : 'Cliente '.$cliente->name.' registrado con el número '.$cliente->customer_number.'.');

        return redirect()->route('comercial.clientes.show', $cliente);
    }

    /**
     * Quita los renglones que nadie llegó a llenar.
     *
     * El formulario nace con una dirección en blanco esperando. Si el
     * usuario solo quiere registrar el nombre y el teléfono —caso
     * normal: llamó pidiendo precio y todavía no dio dirección— ese
     * renglón no puede bloquear el guardado.
     *
     * El sistema fue el que lo puso ahí. No tiene sentido que después
     * exija que se llene.
     */
    protected function descartarVacios(): void
    {
        $this->direcciones = array_values(array_filter(
            $this->direcciones,
            fn (array $d) => filled($d['line1'] ?? null)
                          || filled($d['city'] ?? null)
                          || filled($d['zip'] ?? null),
        ));

        $this->contactos = array_values(array_filter(
            $this->contactos,
            fn (array $c) => filled($c['name'] ?? null)
                          || filled($c['email'] ?? null)
                          || filled($c['phone'] ?? null),
        ));

        // El renglón de adjunto sin archivo elegido no es un adjunto.
        $this->adjuntos = array_values(array_filter(
            $this->adjuntos,
            fn (array $a) => ! empty($a['archivo']),
        ));
    }

    protected function guardarDirecciones(Customer $cliente): void
    {
        $idsQueSiguen = collect($this->direcciones)->pluck('id')->filter()->all();

        $cliente->addresses()
            ->when($idsQueSiguen, fn ($q) => $q->whereNotIn('id', $idsQueSiguen))
            ->delete();

        foreach ($this->direcciones as $direccion) {

            /*
             | El `uid` no aparece aquí a propósito: es del formulario,
             | no del negocio. Los atributos se arman a mano uno por uno
             | justamente para que nada de la pantalla se cuele a la
             | base sin que alguien lo haya decidido.
             */
            $atributos = [
                'label' => $direccion['label'] ?: null,
                'line1' => $direccion['line1'],
                'line2' => $direccion['line2'] ?: null,
                'city'  => $direccion['city'] ?: null,
                'state' => $direccion['state'] ? strtoupper($direccion['state']) : null,
                'zip'   => $direccion['zip'] ?: null,

                'is_default_billing'  => (bool) $direccion['is_default_billing'],
                'is_default_shipping' => (bool) $direccion['is_default_shipping'],
            ];

            /*
             | find + fill + save, y no un update() del query builder.
             |
             | Es la misma lección del formulario de presupuesto: el
             | update() del builder manda un UPDATE directo y NO dispara
             | los eventos del modelo. Aquí hoy no hay observer que
             | escuchar, pero el día que lo haya, esto seguiría
             | funcionando y nadie sabría por qué el otro camino no.
             */
            if (! empty($direccion['id'])) {
                $fila = $cliente->addresses()->whereKey($direccion['id'])->first();

                $fila
                    ? $fila->fill($atributos)->save()
                    : $cliente->addresses()->create($atributos);
            } else {
                $cliente->addresses()->create($atributos);
            }
        }
    }

    protected function guardarContactos(Customer $cliente): void
    {
        $idsQueSiguen = collect($this->contactos)->pluck('id')->filter()->all();

        $cliente->contacts()
            ->when($idsQueSiguen, fn ($q) => $q->whereNotIn('id', $idsQueSiguen))
            ->delete();

        foreach ($this->contactos as $contacto) {

            $atributos = [
                'name'  => $contacto['name'],
                'role'  => $contacto['role'] ?: null,
                'email' => $contacto['email'] ?: null,
                'phone' => $contacto['phone'] ?: null,

                'is_primary'       => (bool) $contacto['is_primary'],
                'notify_invoices'  => (bool) $contacto['notify_invoices'],
                'notify_reminders' => (bool) $contacto['notify_reminders'],
            ];

            if (! empty($contacto['id'])) {
                $fila = $cliente->contacts()->whereKey($contacto['id'])->first();

                $fila
                    ? $fila->fill($atributos)->save()
                    : $cliente->contacts()->create($atributos);
            } else {
                $cliente->contacts()->create($atributos);
            }
        }
    }

    /**
     * Sube los archivos y les crea su registro.
     *
     * ── DÓNDE SE GUARDAN ──
     *
     * En `clientes/{id}/`, con un nombre revuelto que pone Laravel. El
     * nombre original queda en la columna `name` y es el que ve y baja
     * el usuario.
     *
     * Guardarlos con su nombre real sería un problema: dos clientes
     * suben "certificado.pdf" y el segundo pisa al primero.
     *
     * ── company_id EN null ──
     *
     * El cliente es de las dos empresas, así que sus papeles también:
     * el certificado de exención vale igual facturando desde FLCHR o
     * desde RST.
     *
     * La columna existe para el caso contrario —un contrato firmado con
     * una sola de las dos— y ese día se llena desde donde corresponda.
     * Ponerle la empresa activa aquí escondería el certificado la mitad
     * de las veces.
     */
    protected function guardarAdjuntos(Customer $cliente): void
    {
        if (empty($this->adjuntos)) {
            return;
        }

        $disco = config('filesystems.default');

        foreach ($this->adjuntos as $adjunto) {

            if (empty($adjunto['archivo'])) {
                continue;
            }

            $archivo = $adjunto['archivo'];

            $ruta = $archivo->store('clientes/'.$cliente->id, $disco);

            $cliente->documents()->create([
                'company_id' => null,
                'category'   => $adjunto['category'],
                'name'       => $archivo->getClientOriginalName(),
                'path'       => $ruta,
                'disk'       => $disco,
                'mime_type'  => $archivo->getMimeType(),
                'size_bytes' => $archivo->getSize(),
                'expires_at' => $adjunto['expires_at'] ?: null,

                /*
                 | attach_to_invoice nace en false: que un papel del
                 | cliente salga hacia afuera con una factura tiene que
                 | ser una decisión de quien emite la factura, no lo que
                 | pasa si nadie hace nada.
                 */
                'attach_to_invoice' => false,

                'notes'       => $adjunto['notes'] ?: null,
                'uploaded_by' => auth()->id(),
            ]);
        }

        $this->adjuntos = [];
    }

    /* =====================================================================
     | BORRAR UN DOCUMENTO YA GUARDADO
     |
     | Con confirmación, y solo los del propio cliente: el whereKey sobre
     | la relación impide que alguien mande el id de un documento de otro
     | cliente manipulando la petición.
     |
     | El archivo del disco lo borra el propio modelo, en su evento
     | `deleted`. Acá no hay que acordarse de nada.
     * ================================================================== */

    public function pedirBorrarDocumento(int $id): void
    {
        $this->documentoPorBorrar = $id;
    }

    public function cancelarBorrarDocumento(): void
    {
        $this->documentoPorBorrar = null;
    }

    public function borrarDocumento(): void
    {
        $this->exigirPermiso('update');

        if (! $this->customerId || ! $this->documentoPorBorrar) {
            $this->documentoPorBorrar = null;

            return;
        }

        $cliente = Customer::findOrFail($this->customerId);

        $documento = $cliente->documents()->whereKey($this->documentoPorBorrar)->first();

        $documento?->delete();

        $this->documentoPorBorrar = null;

        session()->flash('aviso', 'Documento eliminado.');
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        /*
         | Los documentos ya guardados, solo al editar. En un cliente
         | nuevo no hay ninguno y la consulta no tendría a quién
         | preguntarle.
         */
        $documentos = $this->customerId
            ? Customer::findOrFail($this->customerId)
                ->documents()
                ->latest()
                ->get()
            : collect();

        return view('livewire.customers.form', [
            'tipos' => CustomerType::options(),

            'documentos' => $documentos,

            /*
             | Las categorías de documento que tienen sentido en la ficha
             | de un cliente. El enum trae nueve; las otras cuatro son de
             | contenedores, gastos y proveedores, y ofrecerlas acá solo
             | daría archivos mal clasificados.
             */
            'categorias' => [
                DocumentCategory::Contract->value         => 'Contrato',
                DocumentCategory::TaxExemption->value     => 'Certificado de exención',
                DocumentCategory::CcAuthorization->value  => 'Autorización de tarjeta',
                DocumentCategory::ExportCertificate->value => 'Certificado de exportación',
                DocumentCategory::Other->value            => 'Otro',
            ],

            /*
             | De dónde salió el cliente. Lista corta y cerrada para que
             | se pueda reportar por origen; la columna acepta texto libre
             | pero un campo abierto acabaría con "facebook", "Facebook" y
             | "FB" contados como tres cosas distintas.
             */
            'origenes' => [
                'referral'  => 'Recomendado por otro cliente',
                'facebook'  => 'Facebook',
                'google'    => 'Búsqueda en Google',
                'walk_in'   => 'Llegó a la yarda',
                'phone'     => 'Llamada',
                'repeat'    => 'Cliente que vuelve',
                'other'     => 'Otro',
            ],
        ]);
    }
}
