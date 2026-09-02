<?php

namespace App\Livewire\Estimates;

use App\Enums\EstimateStatus;
use App\Enums\ProductType;
use App\Enums\UseType;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Depot;
use App\Models\Estimate;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceCalculator;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FORMULARIO DE PRESUPUESTO — crear y editar
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * El mismo archivo sirve para las dos cosas. La diferencia es una sola:
 * si la ruta trajo un presupuesto, se editan sus datos; si no, se crea
 * uno nuevo.
 *
 * ── POR QUÉ NO SE TRABAJA DIRECTO SOBRE EL MODELO ──
 *
 * Livewire manda el estado del componente al navegador y lo trae de
 * vuelta en cada tecla. Un modelo de Eloquent con sus relaciones cargadas
 * es un objeto pesado y con partes que no viajan bien.
 *
 * Por eso el formulario usa propiedades sueltas (textos, números,
 * arreglos) y solo al guardar se vuelca todo al modelo. Es más código,
 * pero es la diferencia entre un formulario que responde al instante y
 * uno que se siente lento.
 *
 * ── LA PIEZA MÁS IMPORTANTE: LOS GRUPOS DE IMPRESIÓN ──
 *
 * RB-006 y RB-007 se contradicen a primera vista:
 *
 *   RB-007: al cliente se le muestra UN precio consolidado.
 *   RB-006: el 7% se cobra solo sobre el contenedor, nunca sobre el
 *           delivery.
 *
 * Se resuelven así: por dentro hay dos líneas, y en el papel se imprimen
 * como una.
 *
 *     Por dentro   Contenedor 40HC ....... 2,400.00   gravable
 *                  Delivery Homestead ....   350.00   no gravable
 *
 *     El cliente   Contenedor 40HC entregado  2,750.00
 *
 *     El impuesto  7% sobre 2,400 = 168.00, no sobre 2,750
 *
 * En la pantalla eso es la columna "Grupo": las líneas que llevan la
 * misma etiqueta se imprimen juntas.
 */
#[Layout('layouts.app')]
class Form extends Component
{
    /* =====================================================================
     | QUÉ SE ESTÁ EDITANDO
     * ================================================================== */

    /** null = presupuesto nuevo. Con número = se está editando ese. */
    public ?int $estimateId = null;

    /** El número, solo para mostrarlo en el título. Se asigna al guardar. */
    public string $numero = '';

    /* =====================================================================
     | EL CLIENTE
     * ================================================================== */

    public ?int $customer_id = null;

    /** Lo que el usuario escribe en el buscador de clientes. */
    public string $buscarCliente = '';

    /** El nombre del cliente ya elegido, para mostrarlo sin volver a consultar. */
    public string $clienteNombre = '';

    /* =====================================================================
     | LA CABECERA DEL DOCUMENTO
     * ================================================================== */

    public string $issue_date   = '';
    public ?string $valid_until = null;
    public string $terms        = '';
    public string $use_type     = 'storage';
    public ?int $salesperson_id = null;

    /**
     * Las direcciones, como copia congelada.
     *
     * No son una relación a customer_addresses: son una FOTO del día en
     * que se cotizó. Si el cliente se muda antes de aceptar, el documento
     * sigue mostrando la dirección con la que se le cotizó.
     */
    public array $bill_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => '', 'zip' => ''];
    public array $ship_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => '', 'zip' => ''];

    /** Si el SHIP TO es distinto del BILL TO (RB-035). */
    public bool $envioDistinto = false;

    /* =====================================================================
     | LA ENTREGA
     * ================================================================== */

    public ?string $delivery_zip = null;
    public ?float $miles         = null;
    public ?float $rate_per_mile = null;
    public ?int $depot_id        = null;
    public float $pickup_fee     = 0;

    /* =====================================================================
     | EL DINERO
     * ================================================================== */

    public float $discount_amount = 0;
    public float $tax_rate        = 0;
    public bool  $tax_exempt      = false;

    /** El interruptor de la pantalla. Si está encendido se cobra el 3.5%. */
    public bool $pagaConTarjeta = false;

    public float $credit_card_fee_percent = 0;

    /* =====================================================================
     | TEXTOS LIBRES
     * ================================================================== */

    public ?string $notes        = null;
    public ?string $footer_terms = null;

    /* =====================================================================
     | LAS LÍNEAS
     |
     | Cada una es un arreglo con estas claves:
     |
     |   id           el número de la fila en la base, o null si es nueva
     |   product_id   el concepto del catálogo (opcional)
     |   container_id la unidad concreta (opcional)
     |   description  lo que se imprime
     |   quantity     cantidad
     |   unit_price   precio unitario
     |   taxable      si paga el 7%
     |   grupo        la etiqueta de impresión ("A", "1", o vacío)
     * ================================================================== */

    public array $lineas = [];

    /**
     * El texto que se imprime por cada grupo.
     *
     *     ['A' => 'Contenedor 40HC entregado en Homestead']
     *
     * Solo aparece en pantalla cuando un grupo tiene 2 líneas o más:
     * agrupar una sola línea no tiene sentido.
     */
    public array $gruposDescripcion = [];

    /* =====================================================================
     | ARRANQUE
     * ================================================================== */

    /**
     * mount() corre UNA vez, al abrir la pantalla.
     *
     * El ?Estimate con signo de interrogación es lo que permite que el
     * mismo componente sirva para las dos rutas: la de crear no manda
     * nada y llega null; la de editar manda el presupuesto.
     *
     * Y como el modelo tiene el filtro por compañía puesto, si alguien
     * escribe a mano el id de un presupuesto de la otra empresa, Laravel
     * no lo encuentra y responde 404. No hay que comprobarlo aquí.
     */
    public function mount(?Estimate $estimate = null)
    {
        $empresa = app(CompanyContext::class)->get();
        $calc    = app(InvoiceCalculator::class);

        /* -----------------------------------------------------------------
         | CASO A · EDITAR UNO QUE YA EXISTE
         * -------------------------------------------------------------- */
        if ($estimate && $estimate->exists) {

            // Un presupuesto ya convertido en factura no se edita.
            if (! $estimate->isEditable()) {
                session()->flash('error',
                    'El presupuesto '.$estimate->estimate_number.' está en estado "'
                    .$estimate->status->label().'" y ya no se puede modificar.');

                return redirect()->route('comercial.presupuestos.show', $estimate);
            }

            $this->cargarDesde($estimate);

            return null;
        }

        /* -----------------------------------------------------------------
         | CASO B · UNO NUEVO
         |
         | Se precargan los valores por defecto. Todos editables: son
         | sugerencias, no reglas.
         * -------------------------------------------------------------- */
        $this->issue_date     = now()->toDateString();
        $this->salesperson_id = auth()->id();

        if ($empresa) {
            $this->terms       = $calc->defaultTerms($empresa);
            $this->tax_rate    = $calc->defaultTaxRate($empresa);
            $this->valid_until = now()
                ->addDays($calc->defaultEstimateValidDays($empresa))
                ->toDateString();
        }

        // Se arranca con una línea vacía para que el usuario tenga dónde
        // escribir sin tener que darle antes a "agregar".
        $this->agregarLinea();

        return null;
    }

    /** Vuelca un presupuesto guardado a las propiedades del formulario. */
    protected function cargarDesde(Estimate $estimate): void
    {
        $estimate->load('items');

        $this->estimateId = $estimate->id;
        $this->numero     = $estimate->estimate_number;

        $this->customer_id   = $estimate->customer_id;
        $this->clienteNombre = $estimate->customer?->name ?? '';

        $this->issue_date     = $estimate->issue_date?->toDateString() ?? now()->toDateString();
        $this->valid_until    = $estimate->valid_until?->toDateString();
        $this->terms          = (string) $estimate->terms;
        $this->use_type       = $estimate->use_type?->value ?? 'storage';
        $this->salesperson_id = $estimate->salesperson_id;

        // El ?: deja el arreglo con las claves esperadas aunque en la base
        // esté guardado como null.
        $this->bill_to = array_merge($this->bill_to, $estimate->bill_to ?: []);
        $this->ship_to = array_merge($this->ship_to, $estimate->ship_to ?: []);

        $this->envioDistinto = ! empty($estimate->ship_to);

        $this->delivery_zip  = $estimate->delivery_zip;
        $this->miles         = $estimate->miles !== null ? (float) $estimate->miles : null;
        $this->rate_per_mile = $estimate->rate_per_mile !== null ? (float) $estimate->rate_per_mile : null;
        $this->depot_id      = $estimate->depot_id;
        $this->pickup_fee    = (float) $estimate->pickup_fee;

        $this->discount_amount = (float) $estimate->discount_amount;
        $this->tax_rate        = (float) $estimate->tax_rate;
        $this->tax_exempt      = (bool) $estimate->tax_exempt;

        $this->credit_card_fee_percent = (float) $estimate->credit_card_fee_percent;
        $this->pagaConTarjeta          = $this->credit_card_fee_percent > 0;

        $this->notes        = $estimate->notes;
        $this->footer_terms = $estimate->footer_terms;

        /* -----------------------------------------------------------------
         | LAS LÍNEAS
         |
         | El bundle_key guardado en la base vuelve a la columna "Grupo"
         | tal cual: es una etiqueta corta que escribió el usuario, no un
         | código interno. Eso lo hace fácil de entender al reabrir el
         | presupuesto seis meses después.
         * -------------------------------------------------------------- */
        $this->lineas = $estimate->items->map(fn ($linea) => [
            'id'           => $linea->id,
            'product_id'   => $linea->product_id,
            'container_id' => $linea->container_id,
            'description'  => $linea->description,
            'quantity'     => (float) $linea->quantity,
            'unit_price'   => (float) $linea->unit_price,
            'taxable'      => (bool) $linea->taxable,
            'grupo'        => (string) ($linea->bundle_key ?? ''),
        ])->all();

        foreach ($estimate->items as $linea) {
            if ($linea->bundle_key) {
                $this->gruposDescripcion[$linea->bundle_key] = $linea->bundle_description ?? '';
            }
        }

        if (empty($this->lineas)) {
            $this->agregarLinea();
        }
    }

    /* =====================================================================
     | EL CLIENTE
     * ================================================================== */

    /**
     * Los clientes que coinciden con lo que se está escribiendo.
     *
     * Se limita a 8 a propósito: una lista más larga no ayuda, estorba.
     * Si el cliente no aparece entre los primeros 8, es que hay que
     * escribir un poco más.
     *
     * El scopeSearch del modelo Customer también busca dentro de los
     * contactos, porque es común que llamen dando el nombre del empleado
     * y no el de la empresa.
     */
    public function getResultadosClienteProperty()
    {
        if (strlen(trim($this->buscarCliente)) < 2) {
            return collect();
        }

        return Customer::query()
            ->active()
            ->search($this->buscarCliente)
            ->orderBy('display_name')
            ->limit(8)
            ->get();
    }

    /**
     * El usuario eligió un cliente de la lista.
     *
     * Aquí se hacen cuatro cosas de golpe, y las cuatro se pueden
     * cambiar después a mano:
     */
    public function seleccionarCliente(int $id): void
    {
        $cliente = Customer::with('addresses')->find($id);

        if (! $cliente) {
            return;
        }

        $this->customer_id   = $cliente->id;
        $this->clienteNombre = $cliente->name;
        $this->buscarCliente = '';

        /* -----------------------------------------------------------------
         | 1 · LAS DIRECCIONES
         |
         | toSnapshot() devuelve la copia congelada. Si el cliente no tiene
         | dirección de facturación marcada, se toma la primera que tenga.
         * -------------------------------------------------------------- */
        $facturacion = $cliente->addresses->firstWhere('is_default_billing', true)
            ?? $cliente->addresses->first();

        if ($facturacion) {
            $this->bill_to = array_merge($this->bill_to, $facturacion->toSnapshot());
        }

        $envio = $cliente->addresses->firstWhere('is_default_shipping', true);

        if ($envio && $envio->id !== $facturacion?->id) {
            $this->ship_to       = array_merge($this->ship_to, $envio->toSnapshot());
            $this->envioDistinto = true;
        }

        /* -----------------------------------------------------------------
         | 2 · LA EXENCIÓN DE IMPUESTO (RB-014)
         |
         | La bandera tax_exempt del cliente es solo la respuesta rápida:
         | la mantiene al día el TaxExemptionCertificateObserver mirando
         | los certificados de verdad.
         |
         | Aquí se usa para precargar el formulario. La PRUEBA —el id del
         | certificado— se guarda al convertir el presupuesto en factura,
         | que es cuando importa fiscalmente.
         * -------------------------------------------------------------- */
        $this->tax_exempt = (bool) $cliente->tax_exempt;

        /* -----------------------------------------------------------------
         | 3 · ¿PUEDE PAGAR CON TARJETA? (RB-012, RB-013)
         |
         | Si el cliente no está autorizado, se apaga el interruptor del
         | recargo. No se puede cobrar un 3.5% de un método de pago que no
         | se le va a aceptar.
         * -------------------------------------------------------------- */
        if (! $cliente->allow_credit_card) {
            $this->pagaConTarjeta          = false;
            $this->credit_card_fee_percent = 0;
        }

        $this->resetValidation('customer_id');
    }

    public function quitarCliente(): void
    {
        $this->customer_id   = null;
        $this->clienteNombre = '';
        $this->bill_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => '', 'zip' => ''];
        $this->ship_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => '', 'zip' => ''];
        $this->envioDistinto = false;
    }

    /* =====================================================================
     | LAS LÍNEAS
     * ================================================================== */

    public function agregarLinea(): void
    {
        $this->lineas[] = [
            'id'           => null,
            'product_id'   => null,
            'container_id' => null,
            'description'  => '',
            'quantity'     => 1,
            'unit_price'   => 0,
            'taxable'      => false,
            'grupo'        => '',
        ];
    }

    /**
     * Quita una línea.
     *
     * array_values() vuelve a numerar el arreglo desde cero. Sin eso
     * quedarían huecos (0, 2, 3) y Livewire pierde el hilo de qué campo
     * es cuál: se ve como campos que se mezclan solos al borrar uno del
     * medio.
     */
    public function quitarLinea(int $indice): void
    {
        unset($this->lineas[$indice]);

        $this->lineas = array_values($this->lineas);

        if (empty($this->lineas)) {
            $this->agregarLinea();
        }

        $this->limpiarGruposHuerfanos();
    }

    /**
     * Reacciona a lo que el usuario cambia en la pantalla.
     *
     * Livewire llama a este método con el nombre de la propiedad que
     * acaba de cambiar, por ejemplo "lineas.2.product_id".
     */
    public function updated(string $campo): void
    {
        /* -----------------------------------------------------------------
         | CAMBIÓ EL PRODUCTO DE UNA LÍNEA
         |
         | Se precargan descripción, precio y si paga impuesto, desde el
         | catálogo. Los tres quedan editables: el precio de venta no es
         | fijo, varía por temporada y por volumen (RB-029).
         * -------------------------------------------------------------- */
        if (preg_match('/^lineas\.(\d+)\.product_id$/', $campo, $partes)) {
            $this->aplicarProducto((int) $partes[1]);
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL GRUPO DE UNA LÍNEA
         * -------------------------------------------------------------- */
        if (preg_match('/^lineas\.\d+\.grupo$/', $campo)) {
            $this->limpiarGruposHuerfanos();
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL INTERRUPTOR DE LA TARJETA
         |
         | El porcentaje sale de la configuración, no está escrito aquí.
         | Si mañana Square cambia su comisión, se ajusta en la pantalla
         | de Configuración.
         * -------------------------------------------------------------- */
        if ($campo === 'pagaConTarjeta') {
            $empresa = app(CompanyContext::class)->get();

            $this->credit_card_fee_percent = $this->pagaConTarjeta && $empresa
                ? app(InvoiceCalculator::class)->defaultCreditCardFeePercent($empresa)
                : 0;
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL TIPO DE USO (RB-006, RB-016, RB-017)
         |
         | Exportación cambia tres cosas del negocio:
         |
         |   - No lleva sales tax de Florida.
         |   - Exige certificado CSC (se avisa en pantalla).
         |   - Normalmente no lleva delivery: el cliente contrata su
         |     propia línea naviera.
         |
         | Aquí se desmarca el impuesto de las líneas. Se desmarca, no se
         | prohíbe: el usuario puede volver a marcarlo si el caso lo pide.
         * -------------------------------------------------------------- */
        if ($campo === 'use_type' && $this->use_type === UseType::Export->value) {
            foreach ($this->lineas as $i => $linea) {
                $this->lineas[$i]['taxable'] = false;
            }
        }
    }

    /** Precarga una línea con los datos del producto elegido. */
    protected function aplicarProducto(int $indice): void
    {
        $productoId = $this->lineas[$indice]['product_id'] ?? null;

        if (! $productoId) {
            return;
        }

        $producto = Product::find($productoId);

        if (! $producto) {
            return;
        }

        // lineDefaults() vive en el modelo Product y es el mismo método
        // que va a usar la factura. Un solo sitio para los valores por
        // defecto de una línea.
        $defaults = $producto->lineDefaults();

        // La descripción solo se pisa si estaba vacía: si el usuario ya
        // había escrito algo suyo, se respeta.
        if (blank($this->lineas[$indice]['description'])) {
            $this->lineas[$indice]['description'] = $defaults['description'];
        }

        if (empty($this->lineas[$indice]['unit_price'])) {
            $this->lineas[$indice]['unit_price'] = $defaults['unit_price'];
        }

        // En exportación nada paga impuesto, sin importar el producto.
        $this->lineas[$indice]['taxable'] = $this->use_type === UseType::Export->value
            ? false
            : $defaults['taxable'];

        // Si el producto no es un contenedor, se limpia la unidad que
        // pudiera haber quedado seleccionada de antes.
        if (! $producto->type->requiresContainer()) {
            $this->lineas[$indice]['container_id'] = null;
        }
    }

    /**
     * Borra las descripciones de grupos que ya no tienen líneas, o que se
     * quedaron con una sola.
     *
     * Sin esto, el usuario agrupa dos líneas, escribe el texto, borra una
     * de las dos, y el texto sigue guardado esperando a nadie.
     */
    protected function limpiarGruposHuerfanos(): void
    {
        $usados = $this->gruposConVariasLineas();

        $this->gruposDescripcion = array_intersect_key(
            $this->gruposDescripcion,
            array_flip($usados),
        );

        foreach ($usados as $grupo) {
            $this->gruposDescripcion[$grupo] ??= '';
        }
    }

    /**
     * Las etiquetas de grupo que tienen DOS O MÁS líneas.
     *
     * Solo esas se imprimen agrupadas. Una línea sola con etiqueta se
     * imprime normal, como si no tuviera.
     */
    public function gruposConVariasLineas(): array
    {
        $conteo = [];

        foreach ($this->lineas as $linea) {
            $grupo = trim((string) ($linea['grupo'] ?? ''));

            if ($grupo !== '') {
                $conteo[$grupo] = ($conteo[$grupo] ?? 0) + 1;
            }
        }

        return array_keys(array_filter($conteo, fn ($n) => $n > 1));
    }

    /* =====================================================================
     | LOS TOTALES EN VIVO
     * ================================================================== */

    /**
     * Los totales que se ven en el recuadro de la derecha.
     *
     * Se recalculan en cada tecla, sin guardar nada, y usan EXACTAMENTE
     * la misma calculadora que va a correr al guardar. Ese es el punto:
     * si la pantalla sumara por su cuenta, un día mostraría un número y
     * guardaría otro.
     *
     * Un método que empieza por "get" y termina en "Property" se usa
     * desde la vista sin paréntesis: {{ $this->totales['total'] }}.
     */
    public function getTotalesProperty(): array
    {
        $lineas = [];

        foreach ($this->lineas as $linea) {
            $lineas[] = [
                'amount'  => round(
                    (float) ($linea['quantity'] ?? 0) * (float) ($linea['unit_price'] ?? 0),
                    2,
                ),
                'taxable' => (bool) ($linea['taxable'] ?? false),
            ];
        }

        return app(InvoiceCalculator::class)->preview($lineas, [
            'company'            => app(CompanyContext::class)->get(),
            'descuento'          => (float) $this->discount_amount,
            'tasa_impuesto'      => (float) $this->tax_rate,
            'exento'             => (bool) $this->tax_exempt,
            'porcentaje_tarjeta' => (float) $this->credit_card_fee_percent,
        ]);
    }

    /** El importe de una línea suelta, para la columna de la derecha. */
    public function importeLinea(int $indice): float
    {
        $linea = $this->lineas[$indice] ?? null;

        if (! $linea) {
            return 0.0;
        }

        return round((float) $linea['quantity'] * (float) $linea['unit_price'], 2);
    }

    /* =====================================================================
     | VALIDACIÓN
     * ================================================================== */

    protected function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'issue_date'  => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'use_type'    => ['required', Rule::in(UseType::values())],
            'terms'       => ['nullable', 'string', 'max:50'],

            'tax_rate'        => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['required', 'numeric', 'min:0'],

            'delivery_zip'  => ['nullable', 'string', 'max:10'],
            'miles'         => ['nullable', 'numeric', 'min:0'],
            'rate_per_mile' => ['nullable', 'numeric', 'min:0'],
            'depot_id'      => ['nullable', 'exists:depots,id'],
            'pickup_fee'    => ['nullable', 'numeric', 'min:0'],

            'salesperson_id' => ['nullable', 'exists:users,id'],

            // El punto significa "cada elemento del arreglo".
            'lineas'                => ['required', 'array', 'min:1'],
            'lineas.*.description'  => ['required', 'string', 'max:1000'],
            'lineas.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'lineas.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'lineas.*.product_id'   => ['nullable', 'exists:products,id'],
            'lineas.*.container_id' => ['nullable', 'exists:containers,id'],
            'lineas.*.grupo'        => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function messages(): array
    {
        return [
            'customer_id.required' => 'Elija un cliente.',
            'issue_date.required'  => 'La fecha de emisión es obligatoria.',
            'valid_until.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la de emisión.',

            'lineas.required'   => 'El presupuesto necesita al menos una línea.',
            'lineas.min'        => 'El presupuesto necesita al menos una línea.',

            'lineas.*.description.required' => 'Escriba qué se está cotizando en esta línea.',
            'lineas.*.quantity.required'    => 'Falta la cantidad.',
            'lineas.*.quantity.min'         => 'La cantidad tiene que ser mayor que cero.',
            'lineas.*.unit_price.required'  => 'Falta el precio.',
        ];
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    /**
     * @param  bool  $yEnviar  si además hay que marcarlo como enviado
     */
    public function guardar(bool $yEnviar = false)
    {
        $this->validate();

        $empresa = app(CompanyContext::class)->get();

        if (! $empresa) {
            session()->flash('error', 'No hay ninguna empresa activa. Vuelva a iniciar sesión.');

            return null;
        }

        /* -----------------------------------------------------------------
         | TODO DENTRO DE UNA TRANSACCIÓN
         |
         | O se guarda la cabecera Y las líneas Y se recalcula, o no se
         | guarda nada.
         |
         | Sin esto, un fallo en la línea 3 de 5 dejaría un presupuesto
         | con dos líneas y un total que no corresponde a ninguna venta
         | real. Y nadie se enteraría hasta imprimirlo.
         * -------------------------------------------------------------- */
        $presupuesto = DB::transaction(function () use ($empresa, $yEnviar) {

            $datos = [
                'company_id'  => $empresa->id,
                'customer_id' => $this->customer_id,
                'issue_date'  => $this->issue_date,
                'valid_until' => $this->valid_until ?: null,
                'terms'       => $this->terms ?: null,
                'use_type'    => $this->use_type,

                'bill_to' => $this->limpiarDireccion($this->bill_to),
                'ship_to' => $this->envioDistinto ? $this->limpiarDireccion($this->ship_to) : null,

                'delivery_zip'  => $this->delivery_zip ?: null,
                'miles'         => $this->miles,
                'rate_per_mile' => $this->rate_per_mile,
                'depot_id'      => $this->depot_id,
                'pickup_fee'    => $this->pickup_fee ?: 0,

                'discount_amount'         => $this->discount_amount ?: 0,
                'tax_rate'                => $this->tax_rate ?: 0,
                'tax_exempt'              => $this->tax_exempt,
                'credit_card_fee_percent' => $this->credit_card_fee_percent ?: 0,

                'salesperson_id' => $this->salesperson_id,
                'notes'          => $this->notes ?: null,
                'footer_terms'   => $this->footer_terms ?: null,
            ];

            /* -------------------------------------------------------------
             | LA CABECERA
             |
             | Al crear no se pasa ni número ni estado: de eso se encarga
             | el EstimateObserver, que es el mismo camino por el que pasa
             | un presupuesto venga de donde venga.
             * ---------------------------------------------------------- */
            if ($this->estimateId) {
                $presupuesto = Estimate::findOrFail($this->estimateId);
                $presupuesto->update($datos);
            } else {
                $presupuesto = Estimate::create($datos);
            }

            /* -------------------------------------------------------------
             | LAS LÍNEAS
             |
             | Tres pasos: borrar las que el usuario quitó, guardar las
             | que quedan, y volver a sumar.
             * ---------------------------------------------------------- */
            $gruposReales = $this->gruposConVariasLineas();

            // 1 · Las que ya no están en pantalla se borran de la base.
            $idsQueSiguen = collect($this->lineas)
                ->pluck('id')
                ->filter()
                ->all();

            $presupuesto->items()
                ->when($idsQueSiguen, fn ($q) => $q->whereNotIn('id', $idsQueSiguen))
                ->delete();

            // 2 · Se guardan o actualizan las que quedan.
            foreach (array_values($this->lineas) as $orden => $linea) {

                $grupo = trim((string) ($linea['grupo'] ?? ''));

                /*
                 | Solo se guarda el grupo si de verdad agrupa algo. Una
                 | etiqueta en una línea sola no cambia nada al imprimir y
                 | además confunde al reabrir el documento.
                 */
                $grupoValido = in_array($grupo, $gruposReales, true) ? $grupo : null;

                $atributos = [
                    'line_number'  => $orden + 1,
                    'sort_order'   => $orden,
                    'product_id'   => $linea['product_id'] ?: null,
                    'container_id' => $linea['container_id'] ?: null,
                    'description'  => $linea['description'],
                    'quantity'     => $linea['quantity'],
                    'unit_price'   => $linea['unit_price'],
                    'taxable'      => (bool) ($linea['taxable'] ?? false),

                    'bundle_key'         => $grupoValido,
                    'bundle_description' => $grupoValido
                        ? ($this->gruposDescripcion[$grupoValido] ?? null)
                        : null,
                ];

                if (! empty($linea['id'])) {
                    $presupuesto->items()->whereKey($linea['id'])->update($atributos);
                } else {
                    $presupuesto->items()->create($atributos);
                }
            }

            // 3 · Los totales.
            //
            // El observer de las líneas ya recalculó en cada guardado,
            // pero se hace una vez más al final por si la última acción
            // fue un borrado: así el número guardado siempre corresponde
            // al estado final, no a uno intermedio.
            $presupuesto->load('items')->recalculate();

            if ($yEnviar && $presupuesto->status === EstimateStatus::Draft) {
                $presupuesto->markAsSent();
            }

            return $presupuesto;
        });

        session()->flash('exito',
            'Presupuesto '.$presupuesto->estimate_number.' guardado'
            .($yEnviar ? ' y marcado como enviado.' : '.'));

        return redirect()->route('comercial.presupuestos.show', $presupuesto);
    }

    /**
     * Quita las partes vacías de una dirección antes de guardarla.
     *
     * Guardar {"line1": null, "city": null} no aporta nada y ensucia el
     * JSON. Si al final no queda nada, se guarda null y ya.
     */
    protected function limpiarDireccion(array $direccion): ?array
    {
        $limpia = array_filter(
            $direccion,
            fn ($valor) => $valor !== null && trim((string) $valor) !== '',
        );

        return empty($limpia) ? null : $limpia;
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        return view('livewire.estimates.form', [

            /*
             | Los productos disponibles: los compartidos y los propios de
             | esta empresa. Un producto de FLCHR no aparece en un
             | presupuesto de RS Transport.
             */
            'productos' => Product::query()
                ->active()
                ->forCompany($empresa?->id)
                ->get(),

            /*
             | Las unidades que se pueden vender de verdad (RB-019).
             |
             | El scope available() del modelo Container filtra tres cosas
             | a la vez: que esté en yarda, que no tenga una venta activa y
             | que no esté rentada. Los que están comprados pero todavía en
             | el depósito del proveedor NO aparecen: no se puede vender lo
             | que no se ha retirado.
             |
             | El límite de 300 es una red de seguridad. Cuando el
             | inventario crezca, esto se cambia por un buscador igual al
             | de clientes.
             */
            'contenedores' => Container::query()
                ->available()
                ->with(['size:id,name', 'condition:id,name'])
                ->orderBy('internal_code')
                ->limit(300)
                ->get(),

            'depositos'  => Depot::query()->active()->orderBy('name')->get(),
            'vendedores' => User::query()->active()->orderBy('name')->get(['id', 'name']),

            'tiposDeUso' => UseType::options(),
            'grupos'     => $this->gruposConVariasLineas(),
        ]);
    }
}
