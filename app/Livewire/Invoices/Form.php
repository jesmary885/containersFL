<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceCalculator;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\AuthorizesAccess;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FORMULARIO DE FACTURA — emitir y corregir
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Es hermano del formulario de presupuestos: misma estructura, mismas
 * líneas dinámicas, mismos grupos de impresión, misma calculadora.
 *
 * ── LO QUE CAMBIA RESPECTO AL PRESUPUESTO ──
 *
 *   1. TIPO DE FACTURA
 *      Venta, renta, transporte u otro. No es decoración: la de renta
 *      obliga a poner el período (RB-023) y la de transporte nunca lleva
 *      impuesto (RB-005).
 *
 *   2. PERÍODO DE SERVICIO
 *      Solo en las de renta. El invoice tiene que decir explícitamente
 *      desde cuándo y hasta cuándo cubre.
 *
 *   3. MÉTODO DE PAGO ESPERADO
 *      Sustituye a la casilla "pagará con tarjeta" del presupuesto. Si
 *      se elige tarjeta, entra el 3.5% (RB-009).
 *
 *   4. DEPÓSITO APLICADO
 *      El anticipo que ya entregó el cliente y se le descuenta del total.
 *
 *   5. NO SE PUEDE EDITAR TODO SIEMPRE
 *      En cuanto entra el primer dólar, la factura se cierra. Y una
 *      pagada o anulada no se abre ni con el botón escondido: el
 *      InvoiceObserver la protege desde el modelo.
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

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
     | QUÉ SE ESTÁ EDITANDO
     * ================================================================== */

    public ?int $invoiceId = null;

    /** El número, solo para el título. Lo asigna el observer al guardar. */
    public string $numero = '';

    /** Si la factura ya se le mandó al cliente. Cambia el aviso de arriba. */
    public bool $yaEnviada = false;

    /* =====================================================================
     | EL PASO EN EL QUE ESTA
     |
     | Mismo asistente que el presupuesto, y por la misma razon: la
     | factura tiene cabecera, dos direcciones, lineas, impuestos,
     | descuento, deposito y recargo de tarjeta. Todo junto en una
     | pantalla es un metro de scroll con el boton de guardar a ciegas al
     | final.
     |
     |   1 · A QUIEN Y CUANDO   cliente, fechas, terminos, direcciones
     |   2 · QUE SE LE COBRA    los renglones
     |   3 · REVISAR Y EMITIR   el documento armado, como lo vera el cliente
     |
     | Y hay una razon mas fuerte que la comodidad: quien convierte un
     | presupuesto aterriza directo en el paso 3, con todo cargado. Si
     | algo esta mal, retrocede al paso que toca en vez de buscar el
     | campo en una pantalla larga.
     * ================================================================== */

    public int $paso = 1;

    public const PASOS = 3;

    /* =====================================================================
     | EL EDITOR DE RENGLONES
     |
     | ── POR QUE UN MODAL Y NO LA TABLA ──
     |
     | La tabla editable obligaba a ensenarle las mismas ocho columnas a
     | todos los conceptos. Una renta no tiene "Cant." —un renglon es un
     | contenedor— y ahi estaba el campo pidiendo un numero que no
     | significaba nada. Al mismo tiempo, un viaje de transporte necesita
     | su fecha de servicio y no tenia donde.
     |
     | Cada concepto pide lo suyo. El modal ensena solo eso.
     |
     | ── EL BORRADOR ──
     |
     | Lo que se edita es una COPIA. Solo al guardar se escribe sobre
     | $lineas. Asi "Cancelar" cancela de verdad: sin la copia, cada
     | tecla ya habria modificado el renglon.
     * ================================================================== */

    /* =====================================================================
     | AGRUPAR RENGLONES
     |
     | ── POR QUE NO ES UN CAMPO DENTRO DEL MODAL ──
     |
     | Agrupar es una decision sobre VARIOS renglones a la vez: "estos
     | tres se imprimen como uno". Pedirlo renglon por renglon, dentro
     | de una ventana que solo ve uno, obliga a acordarse de la etiqueta
     | que se puso en el anterior y a escribirla igual. Un error de
     | tecleo y el grupo se parte en dos.
     |
     | Se marcan las casillas en la lista y se pulsa "Agrupar". La letra
     | la pone el sistema.
     |
     | $seleccionadas guarda POSICIONES, no identificadores. Por eso
     | quitarLinea() la vacia: despues de renumerar, la posicion 3 ya no
     | es la misma linea.
     * ================================================================== */

    public array $seleccionadas = [];

    public ?string $avisoAgrupar = null;

    public ?int $lineaEditando = null;

    public array $borrador = [];

    /** Si el renglon se acaba de crear: cancelar lo borra. */
    public bool $borradorEsNuevo = false;

    /* =====================================================================
     | EL CLIENTE
     * ================================================================== */

    public ?int $customer_id     = null;
    public string $buscarCliente = '';
    public string $clienteNombre = '';

    /* =====================================================================
     | LA CABECERA
     * ================================================================== */

    public string $type       = 'sale';
    public string $issue_date = '';
    public ?string $due_date  = null;
    public string $terms      = '';

    /** Solo en facturas de renta (RB-023). */
    public ?string $service_period_start = null;
    public ?string $service_period_end   = null;

    /**
     * Las direcciones, como copia congelada del día de la emisión.
     *
     * No son una relación a customer_addresses: son una FOTO. Si el
     * cliente se muda el año que viene, esta factura sigue mostrando
     * dónde estaba el día que se emitió. Eso es lo que la hace un
     * documento y no una pantalla de consulta.
     */
    public array $bill_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => '', 'zip' => ''];
    public array $ship_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => '', 'zip' => ''];

    public bool $envioDistinto = false;

    /* =====================================================================
     | EL DINERO
     * ================================================================== */

    public float $discount_amount = 0;
    public float $tax_rate        = 0;
    public bool  $tax_exempt      = false;
    public float $deposit_applied = 0;

    public ?string $expected_payment_method = null;
    public float $credit_card_fee_percent   = 0;

    /* =====================================================================
     | TEXTOS
     * ================================================================== */

    public ?string $notes        = null;
    public ?string $footer_terms = null;

    /* =====================================================================
     | LAS LÍNEAS
     |
     | Mismo formato que en presupuestos:
     |
     |   id, product_id, container_id, description, quantity,
     |   unit_price, taxable, grupo
     |
     | Más una propia de la factura: service_date, la fecha en que se
     | prestó el servicio. En una factura semanal de transporte, cada
     | línea es un viaje de un día distinto.
     * ================================================================== */

    public array $lineas = [];

    /** El texto que se imprime por cada grupo de líneas. */
    public array $gruposDescripcion = [];

    /* =====================================================================
     | ARRANQUE
     * ================================================================== */

    public function mount(?Invoice $invoice = null)
    {
        $empresa = app(CompanyContext::class)->get();
        $calc    = app(InvoiceCalculator::class);

        /* -----------------------------------------------------------------
         | CASO A · CORREGIR UNA QUE YA EXISTE
         * -------------------------------------------------------------- */
        if ($invoice && $invoice->exists) {

            $this->exigirPermiso('update');

            if (! $invoice->isEditable()) {
                session()->flash('error', $this->porQueNoSePuedeEditar($invoice));

                return redirect()->route('finanzas.facturacion.show', $invoice);
            }

            $this->cargarDesde($invoice);

            return null;
        }

        /* -----------------------------------------------------------------
         | CASO B · UNA NUEVA
         * -------------------------------------------------------------- */
        $this->exigirPermiso('create');

        $this->issue_date = now()->toDateString();

        if ($empresa) {
            $this->terms    = $calc->defaultTerms($empresa);
            $this->tax_rate = $calc->defaultTaxRate($empresa);
        }

        /*
         | Se siembra el primer renglon SIN abrir el editor.
         |
         | agregarLinea() ahora abre el modal, y llamarlo aqui hacia que
         | "Nueva factura" apareciera con la ventana de concepto encima
         | antes de haber elegido siquiera el cliente.
         */
        $this->lineas[] = $this->lineaVacia();

        return null;
    }

    /** El mensaje exacto de por qué esta factura no se toca. */
    protected function porQueNoSePuedeEditar(Invoice $invoice): string
    {
        if ($invoice->status === \App\Enums\InvoiceStatus::Void) {
            return 'La factura '.$invoice->invoice_number.' está anulada. '
                 .'Si hay que rehacerla, emita una nueva.';
        }

        if ((float) $invoice->amount_paid > 0) {
            return 'La factura '.$invoice->invoice_number.' ya tiene $'
                 .number_format((float) $invoice->amount_paid, 2).' cobrados y no '
                 .'puede modificarse. Cambiar el monto de un documento ya cobrado '
                 .'descuadraría la contabilidad. Para corregirla, anúlela y emita '
                 .'una nueva.';
        }

        return 'La factura '.$invoice->invoice_number.' está cerrada y no puede modificarse.';
    }

    /** Vuelca una factura guardada a las propiedades del formulario. */
    protected function cargarDesde(Invoice $invoice): void
    {
        $invoice->load('items');

        $this->invoiceId = $invoice->id;
        $this->numero    = $invoice->invoice_number;
        $this->yaEnviada = $invoice->sent_at !== null;

        $this->customer_id   = $invoice->customer_id;
        $this->clienteNombre = $invoice->customer?->name ?? '';

        $this->type       = $invoice->type?->value ?? 'sale';
        $this->issue_date = $invoice->issue_date?->toDateString() ?? now()->toDateString();
        $this->due_date   = $invoice->due_date?->toDateString();
        $this->terms      = (string) $invoice->terms;

        $this->service_period_start = $invoice->service_period_start?->toDateString();
        $this->service_period_end   = $invoice->service_period_end?->toDateString();

        // El ?: deja el arreglo con todas las claves aunque en la base
        // esté guardado como null.
        $this->bill_to = array_merge($this->bill_to, $invoice->bill_to ?: []);
        $this->ship_to = array_merge($this->ship_to, $invoice->ship_to ?: []);

        $this->envioDistinto = ! empty($invoice->ship_to);

        $this->discount_amount = (float) $invoice->discount_amount;
        $this->tax_rate        = (float) $invoice->tax_rate;
        $this->tax_exempt      = (bool) $invoice->tax_exempt;
        $this->deposit_applied = (float) $invoice->deposit_applied;

        $this->expected_payment_method = $invoice->expected_payment_method?->value;
        $this->credit_card_fee_percent = (float) $invoice->credit_card_fee_percent;

        $this->notes        = $invoice->notes;
        $this->footer_terms = $invoice->footer_terms;

        $this->lineas = $invoice->items->map(fn ($linea) => [
            'id'           => $linea->id,
            'product_id'   => $linea->product_id,
            'container_id' => $linea->container_id,
            'description'  => $linea->description,
            'quantity'     => (float) $linea->quantity,
            'unit_price'   => (float) $linea->unit_price,
            'taxable'      => (bool) $linea->taxable,
            'grupo'        => (string) ($linea->bundle_key ?? ''),
            'service_date' => $linea->service_date?->toDateString(),
        ])->all();

        foreach ($invoice->items as $linea) {
            if ($linea->bundle_key) {
                $this->gruposDescripcion[$linea->bundle_key] = $linea->bundle_description ?? '';
            }
        }

        if (empty($this->lineas)) {
            /*
         | Se siembra el primer renglon SIN abrir el editor.
         |
         | agregarLinea() ahora abre el modal, y llamarlo aqui hacia que
         | "Nueva factura" apareciera con la ventana de concepto encima
         | antes de haber elegido siquiera el cliente.
         */
        $this->lineas[] = $this->lineaVacia();
        }
    }

    /* =====================================================================
     | EL CLIENTE
     * ================================================================== */

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
         | LAS DIRECCIONES
         |
         | toSnapshot() devuelve la copia congelada. Si el cliente no tiene
         | marcada una de facturación, se toma la primera que tenga: una
         | factura sin BILL TO no se puede guardar, la columna es
         | obligatoria en la base.
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
         | LA EXENCIÓN (RB-014)
         |
         | La bandera del cliente es solo la respuesta rápida: la mantiene
         | al día el TaxExemptionCertificateObserver mirando los
         | certificados de verdad.
         |
         | Aquí precarga el formulario. Al guardar, si sigue marcada, se
         | busca y se guarda el certificado que la respalda (RB-015). El
         | interruptor es la intención; el certificado es la prueba.
         * -------------------------------------------------------------- */
        $this->tax_exempt = (bool) $cliente->tax_exempt;

        /* -----------------------------------------------------------------
         | ¿PUEDE PAGAR CON TARJETA? (RB-012, RB-013)
         |
         | Si el cliente no está autorizado, se quita esa opción. No se le
         | puede cobrar un 3.5% de un método que no se le va a aceptar.
         * -------------------------------------------------------------- */
        if (! $cliente->allow_credit_card && $this->expected_payment_method === PaymentMethod::CreditCard->value) {
            $this->expected_payment_method = null;
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

    /**
     * La forma de un renglon, en un solo sitio.
     *
     * Existe como metodo aparte porque la estructura se arma en tres
     * lugares. Con el arreglo escrito tres veces, agregar un campo
     * significa acordarse de los tres, y alguna se olvida.
     */
    protected function lineaVacia(): array
    {
        return [
            'id'           => null,
            'product_id'   => null,
            'container_id' => null,
            'description'  => '',
            'quantity'     => 1,
            'unit_price'   => 0,
            'taxable'      => false,
            'grupo'        => '',
            'service_date' => null,
        ];
    }

    /**
     * Abre el modal sobre un renglon nuevo.
     *
     * El renglon se crea YA y el modal trabaja sobre el. Si se cancela,
     * se borra. Es mas simple que sostener un renglon "en el aire" que
     * todavia no existe en $lineas: aplicarProducto() necesita un indice
     * real al que escribirle.
     */
    public function agregarLinea(): void
    {
        $this->lineas[] = $this->lineaVacia();

        $this->abrirLinea(array_key_last($this->lineas), esNuevo: true);
    }

    /** Abre el modal sobre un renglon que ya existe. */
    public function abrirLinea(int $indice, bool $esNuevo = false): void
    {
        if (! isset($this->lineas[$indice])) {
            return;
        }

        $this->lineaEditando   = $indice;
        $this->borrador        = $this->lineas[$indice];
        $this->borradorEsNuevo = $esNuevo;

        $this->resetValidation();
    }

    /**
     * Cierra sin guardar.
     *
     * Un renglon recien creado se va con el modal. Dejarlo vacio en la
     * lista seria dejar basura que despues hay que borrar a mano.
     */
    public function cancelarLinea(): void
    {
        if ($this->borradorEsNuevo && $this->lineaEditando !== null) {
            unset($this->lineas[$this->lineaEditando]);
            $this->lineas = array_values($this->lineas);
        }

        $this->cerrarEditor();
    }

    protected function cerrarEditor(): void
    {
        $this->lineaEditando   = null;
        $this->borrador        = [];
        $this->borradorEsNuevo = false;

        $this->resetValidation();
    }

    /**
     * Vuelca el borrador sobre el renglon.
     *
     * Se valida SOLO este renglon. Validar la factura entera aqui
     * sacaria errores del cliente o de la direccion mientras la persona
     * esta en un modal que no habla de eso.
     */
    public function guardarLinea(): void
    {
        if ($this->lineaEditando === null) {
            return;
        }

        $this->validate([
            'borrador.description' => ['required', 'string', 'max:1000'],
            'borrador.quantity'    => ['required', 'numeric', 'min:0.01'],
            'borrador.unit_price'  => ['required', 'numeric', 'min:0'],
        ], [
            'borrador.description.required' => 'Escriba que se le esta cobrando. '
                                              .'Es el texto que el cliente va a leer.',
            'borrador.quantity.min'   => 'La cantidad tiene que ser mayor que cero.',
            'borrador.unit_price.min' => 'El precio no puede ser negativo.',
        ]);

        $this->lineas[$this->lineaEditando] = $this->borrador;

        $this->limpiarGruposHuerfanos();

        $this->cerrarEditor();
    }

    /**
     * Junta los renglones marcados bajo una misma letra.
     *
     * La descripcion del grupo se toma del renglon MAS CARO, que es el
     * que describe mejor de que va el paquete: si se agrupan un
     * contenedor de $2.400 y su entrega de $150, el cliente tiene que
     * leer "contenedor", no "entrega".
     */
    public function agruparSeleccionadas(): void
    {
        $this->avisoAgrupar = null;

        $indices = collect($this->seleccionadas)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($i) => isset($this->lineas[$i]))
            ->unique()
            ->values();

        if ($indices->count() < 2) {
            $this->avisoAgrupar = 'Marque al menos dos renglones. '
                                 .'Agrupar uno solo no cambia nada al imprimir.';

            return;
        }

        $letra = $this->siguienteLetraDeGrupo();

        foreach ($indices as $i) {
            $this->lineas[$i]['grupo'] = $letra;
        }

        $masCaro = $indices
            ->sortByDesc(fn ($i) => $this->importeLinea($i))
            ->first();

        $this->gruposDescripcion[$letra] = trim((string) ($this->lineas[$masCaro]['description'] ?? ''));

        $this->seleccionadas = [];

        $this->limpiarGruposHuerfanos();
    }

    /** La siguiente letra libre: A, B, C... */
    protected function siguienteLetraDeGrupo(): string
    {
        $usadas = collect($this->lineas)
            ->pluck('grupo')
            ->filter()
            ->unique()
            ->all();

        foreach (range('A', 'Z') as $letra) {
            if (! in_array($letra, $usadas, true)) {
                return $letra;
            }
        }

        return 'A';
    }

    /** Deshace un grupo: los renglones vuelven a imprimirse por separado. */
    public function desagrupar(string $letra): void
    {
        foreach ($this->lineas as $i => $linea) {
            if (trim((string) ($linea['grupo'] ?? '')) === $letra) {
                $this->lineas[$i]['grupo'] = '';
            }
        }

        unset($this->gruposDescripcion[$letra]);

        $this->avisoAgrupar = null;

        $this->limpiarGruposHuerfanos();
    }

    /* =====================================================================
     | MOVERSE ENTRE PASOS
     |
     | Hacia atras es libre. Hacia adelante valida lo que queda en medio:
     | llegar a los renglones sin cliente no significa nada, porque el
     | cliente decide si lleva impuesto y a que direccion se factura.
     * ================================================================== */

    public function siguientePaso(): void
    {
        $this->validate($this->reglasDelPaso($this->paso));

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

            // La validacion no dejo pasar: se queda donde esta, con los
            // errores en pantalla.
            if ($this->paso === $antes) {
                return;
            }
        }
    }

    /**
     * Que se exige en cada paso.
     *
     * Se parte en tres para que el error salga en la pantalla donde esta
     * el campo. Validar todo de golpe en el paso 1 pondria un mensaje
     * rojo sobre un campo que la persona todavia no ha visto.
     */
    protected function reglasDelPaso(int $paso): array
    {
        $todas = $this->rules();

        $porPaso = [
            1 => ['customer_id', 'type', 'issue_date', 'due_date', 'terms',
                  'service_period_start', 'service_period_end',
                  'bill_to.line1', 'bill_to.city', 'bill_to.state', 'bill_to.zip',
                  'ship_to.line1', 'ship_to.city', 'ship_to.state', 'ship_to.zip'],

            2 => ['lineas', 'lineas.*.description', 'lineas.*.quantity',
                  'lineas.*.unit_price', 'lineas.*.product_id',
                  'lineas.*.container_id', 'lineas.*.grupo', 'lineas.*.service_date'],

            3 => ['tax_rate', 'discount_amount', 'deposit_applied',
                  'expected_payment_method', 'notes', 'footer_terms'],
        ];

        return collect($porPaso[$paso] ?? [])
            ->mapWithKeys(fn ($campo) => [$campo => $todas[$campo] ?? []])
            ->filter(fn ($reglas) => ! empty($reglas))
            ->all();
    }

    /**
     * La tira de contexto que se queda arriba en los pasos 2 y 3.
     *
     * Va en texto y no en cajitas: un dato que se lee se revisa, un dato
     * dentro de un input se ignora. Ahi es donde se cazan los errores.
     */
    public function getResumenProperty(): array
    {
        $ciudad = collect([$this->bill_to['city'] ?? null, $this->bill_to['state'] ?? null])
            ->filter()->implode(', ');

        return [
            'cliente'   => $this->clienteNombre ?: null,
            'emision'   => $this->issue_date
                ? \Carbon\Carbon::parse($this->issue_date)->format('d/m/Y')
                : null,
            'vence'     => $this->due_date
                ? \Carbon\Carbon::parse($this->due_date)->format('d/m/Y')
                : null,
            'direccion' => trim($ciudad.' '.($this->bill_to['zip'] ?? '')) ?: null,
            'renglones' => count($this->lineas),
        ];
    }

    /**
     * Copia al borrador el precio que esa unidad tiene en el inventario.
     *
     *   Venta  ->  containers.list_price
     *   Renta  ->  containers.monthly_rate
     *
     * ── POR QUE NO PISA UN PRECIO YA NEGOCIADO ──
     *
     * Si alguien tecleo 2.250 porque lo negocio asi y despues corrige la
     * unidad elegida, seria muy molesto que el sistema le devolviera los
     * 2.400 de lista. Sin $forzar solo escribe si el campo estaba vacio.
     *
     * Con $forzar si pisa, y se usa cuando cambia la unidad o el
     * concepto: ahi el numero anterior corresponde a otra cosa.
     *
     * ── SIGUE SIENDO EDITABLE ──
     *
     * El precio varia por temporada y por volumen. Esto solo ahorra
     * teclear el caso normal.
     */
    protected function aplicarPrecioDeContenedor(int $contenedorId, bool $forzar = false): void
    {
        $contenedor = \App\Models\Container::with(['size:id,name', 'condition:id,name', 'grade:id,name'])
            ->find($contenedorId);

        if (! $contenedor) {
            return;
        }

        $producto = ! empty($this->borrador['product_id'])
            ? Product::find($this->borrador['product_id'])
            : null;

        $esRenta = $producto?->isRental() ?? ($this->type === 'rental');

        $precio = $contenedor->suggestedPrice($esRenta);

        /*
         | null = esa unidad no tiene precio cargado para eso. Se deja el
         | campo como esta para que la persona escriba, en vez de meter
         | un cero que se puede guardar por distraccion.
         */
        if ($precio !== null && ($forzar || empty($this->borrador['unit_price']))) {
            $this->borrador['unit_price'] = $precio;
        }

        /*
         | LA DESCRIPCION
         |
         | La arma el concepto si hay uno; si no, la propia unidad. Asi el
         | presupuesto y la factura escriben exactamente el mismo texto.
         |
         | Solo se escribe si el campo esta vacio: lo que teclee una
         | persona no se pisa nunca.
         */
        if (blank($this->borrador['description'] ?? null)) {
            $this->borrador['description'] = $producto
                ? $producto->autoDescription($contenedor)
                : $contenedor->lineDescription();
        }
    }

    /** El importe del borrador, para ensenarlo en vivo dentro del modal. */
    public function getImporteBorradorProperty(): float
    {
        return round(
            (float) ($this->borrador['quantity'] ?? 1) * (float) ($this->borrador['unit_price'] ?? 0),
            2,
        );
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

        /*
         | Se repone un renglon en blanco SIN abrir el modal: agregarLinea()
         | ahora lo abre, y abrirlo justo despues de borrar seria una
         | ventana que nadie pidio.
         */
        if (empty($this->lineas)) {
            $this->lineas[] = $this->lineaVacia();
        }

        /*
         | Se vacia la seleccion: guarda posiciones, y acabamos de
         | renumerar. Sin esto, borrar una fila del medio agrupa las que
         | no eran.
         */
        $this->seleccionadas = [];
        $this->avisoAgrupar  = null;

        $this->limpiarGruposHuerfanos();

        /*
         | Se puede llamar desde dentro del modal. Si quedara abierto
         | apuntando a un indice que ya se renumero, editaria el renglon
         | equivocado.
         */
        $this->cerrarEditor();
    }

    /**
     * Reacciona a lo que cambia el usuario.
     *
     * Livewire llama a este método con el nombre de la propiedad que
     * acaba de cambiar, por ejemplo "lineas.2.product_id".
     */
    public function updated(string $campo): void
    {
        // Cambió el producto de una línea.
        if (preg_match('/^lineas\.(\d+)\.product_id$/', $campo, $partes)) {
            $this->aplicarProducto((int) $partes[1]);
        }

        /* -----------------------------------------------------------------
         | CAMBIO EL PRODUCTO DENTRO DEL MODAL
         |
         | aplicarProducto() trabaja sobre $lineas, no sobre el borrador.
         | En vez de duplicar esa logica —que es donde se decide el
         | precio, si lleva impuesto y el texto que lee el cliente— se
         | vuelca el borrador, se aplica, y se recoge el resultado.
         |
         | Duplicarla significaria que el dia que cambie una regla de
         | precios haya que acordarse de cambiarla en dos sitios. Nunca se
         | acuerda uno de los dos.
         * -------------------------------------------------------------- */
        if ($campo === 'borrador.product_id' && $this->lineaEditando !== null) {
            $this->lineas[$this->lineaEditando] = $this->borrador;

            $this->aplicarProducto($this->lineaEditando);

            $this->borrador = $this->lineas[$this->lineaEditando];

            // Si ya habia una unidad elegida, su precio manda sobre el
            // del concepto: es el precio de ESE contenedor.
            if (! empty($this->borrador['container_id'])) {
                $this->aplicarPrecioDeContenedor((int) $this->borrador['container_id'], forzar: true);
            }
        }

        /* -----------------------------------------------------------------
         | CAMBIO LA UNIDAD DENTRO DEL MODAL
         |
         | El precio no se teclea: sale de la ficha del contenedor. Es el
         | mismo comportamiento del presupuesto, y por la misma razon —
         | nadie recuerda de memoria el precio de cada unidad.
         * -------------------------------------------------------------- */
        if ($campo === 'borrador.container_id' && $this->lineaEditando !== null) {

            if (empty($this->borrador['container_id'])) {
                return;
            }

            $this->aplicarPrecioDeContenedor((int) $this->borrador['container_id'], forzar: true);
        }

        // Cambió la etiqueta de grupo.
        if (preg_match('/^lineas\.\d+\.grupo$/', $campo)) {
            $this->limpiarGruposHuerfanos();
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL MÉTODO DE PAGO ESPERADO (RB-009)
         |
         | El 3.5% solo entra si es tarjeta. El porcentaje sale de la
         | configuración, no está escrito aquí: si mañana Square cambia su
         | comisión, se ajusta en Configuración y no en el código.
         * -------------------------------------------------------------- */
        if ($campo === 'expected_payment_method') {
            $empresa = app(CompanyContext::class)->get();

            $esTarjeta = $this->expected_payment_method === PaymentMethod::CreditCard->value;

            $this->credit_card_fee_percent = $esTarjeta && $empresa
                ? app(InvoiceCalculator::class)->defaultCreditCardFeePercent($empresa)
                : 0;
        }

        /* -----------------------------------------------------------------
         | CAMBIARON LOS TÉRMINOS DE PAGO
         |
         | Se recalcula el vencimiento en pantalla para que el usuario vea
         | el efecto antes de guardar. Al guardar, el observer hace lo
         | mismo si el campo viene vacío.
         * -------------------------------------------------------------- */
        if ($campo === 'terms' || $campo === 'issue_date') {
            $this->due_date = \Carbon\Carbon::parse($this->issue_date ?: now())
                ->addDays($this->diasDeTermino($this->terms))
                ->toDateString();
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL TIPO DE FACTURA
         |
         | Transporte nunca lleva sales tax (RB-005). Se desmarcan las
         | líneas y se baja la tasa a cero.
         |
         | Se desmarca, no se prohíbe: si RS Transport factura un
         | contenedor en la misma factura —cosa rara pero posible según
         | RB-004— el usuario puede volver a marcarlo.
         * -------------------------------------------------------------- */
        if ($campo === 'type' && $this->type === InvoiceType::Transport->value) {
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

        // lineDefaults() vive en el modelo Product: es el mismo método que
        // usa el presupuesto. Un solo sitio para los valores por defecto.
        $defaults = $producto->lineDefaults();

        // La descripción solo se pisa si estaba vacía: lo que el usuario
        // ya escribió, se respeta.
        if (blank($this->lineas[$indice]['description'])) {
            $this->lineas[$indice]['description'] = $defaults['description'];
        }

        if (empty($this->lineas[$indice]['unit_price'])) {
            $this->lineas[$indice]['unit_price'] = $defaults['unit_price'];
        }

        $this->lineas[$indice]['taxable'] = $this->type === InvoiceType::Transport->value
            ? false
            : $defaults['taxable'];

        if (! $producto->type->requiresContainer()) {
            $this->lineas[$indice]['container_id'] = null;
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

    /**
     * Borra las descripciones de grupos que ya no tienen líneas.
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

    /* =====================================================================
     | LOS TOTALES EN VIVO
     * ================================================================== */

    /**
     * Los totales del recuadro de la derecha.
     *
     * Se recalculan en cada tecla, sin guardar nada, y usan EXACTAMENTE
     * la misma calculadora que va a correr al guardar. Ese es el punto:
     * si la pantalla sumara por su cuenta, un día mostraría un número y
     * guardaría otro.
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
            'anticipo'           => (float) $this->deposit_applied,
        ]);
    }

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
            'type'        => ['required', Rule::in(InvoiceType::values())],
            'issue_date'  => ['required', 'date'],
            'due_date'    => ['nullable', 'date', 'after_or_equal:issue_date'],
            'terms'       => ['nullable', 'string', 'max:50'],

            /*
             | El período de servicio es obligatorio en las facturas de
             | renta (RB-023): el invoice tiene que decir explícitamente
             | desde cuándo y hasta cuándo cubre.
             |
             | required_if hace justo eso: obligatorio solo si el tipo es
             | 'rental'.
             */
            'service_period_start' => ['nullable', 'date', 'required_if:type,rental'],
            'service_period_end'   => ['nullable', 'date', 'required_if:type,rental', 'after_or_equal:service_period_start'],

            'expected_payment_method' => ['nullable', Rule::in(PaymentMethod::values())],

            'tax_rate'        => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'deposit_applied' => ['required', 'numeric', 'min:0'],

            /*
             | La dirección de facturación es obligatoria porque la
             | columna bill_to lo es en la base: toda factura tiene que
             | decir a nombre de quién se emitió.
             */
            'bill_to.line1' => ['required', 'string', 'max:255'],

            'lineas'                => ['required', 'array', 'min:1'],
            'lineas.*.description'  => ['required', 'string', 'max:1000'],
            'lineas.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'lineas.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'lineas.*.product_id'   => ['nullable', 'exists:products,id'],
            'lineas.*.container_id' => ['nullable', 'exists:containers,id'],
            'lineas.*.grupo'        => ['nullable', 'string', 'max:20'],
            'lineas.*.service_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Los nombres con los que el usuario conoce cada campo.
     *
     * Laravel los mete dentro del mensaje cuando el mensaje no está
     * escrito a mano. Es lo que convierte "El campo lineas.0.quantity es
     * obligatorio" en "El campo cantidad es obligatorio".
     */
    protected function validationAttributes(): array
    {
        return [
            'customer_id'          => 'cliente',
            'type'                 => 'tipo de factura',
            'issue_date'           => 'fecha de emisión',
            'due_date'             => 'fecha de vencimiento',
            'terms'                => 'términos de pago',
            'service_period_start' => 'inicio del período',
            'service_period_end'   => 'fin del período',
            'expected_payment_method' => 'forma de pago prevista',
            'tax_rate'             => 'porcentaje de impuesto',
            'discount_amount'      => 'descuento',
            'deposit_applied'      => 'anticipo aplicado',
            'bill_to.line1'        => 'dirección de facturación',
        ];
    }


    protected function messages(): array
    {
        return [
            'customer_id.required' => 'Elija un cliente.',
            'issue_date.required'  => 'La fecha de emisión es obligatoria.',
            'due_date.after_or_equal' => 'El vencimiento no puede ser anterior a la emisión.',

            'service_period_start.required_if' => 'Una factura de renta tiene que decir desde qué fecha cubre.',
            'service_period_end.required_if'   => 'Una factura de renta tiene que decir hasta qué fecha cubre.',
            'service_period_end.after_or_equal' => 'El fin del período no puede ser anterior al inicio.',

            'bill_to.line1.required' => 'La dirección de facturación es obligatoria en una factura.',

            'lineas.required' => 'La factura necesita al menos una línea.',
            'lineas.min'      => 'La factura necesita al menos una línea.',

            'lineas.*.description.required' => 'Línea :position: escriba qué se está cobrando.',
            'lineas.*.quantity.required'    => 'Línea :position: falta la cantidad.',
            'lineas.*.quantity.min'         => 'Línea :position: la cantidad tiene que ser mayor que cero.',
            'lineas.*.quantity.numeric'     => 'Línea :position: la cantidad tiene que ser un número.',
            'lineas.*.unit_price.required'  => 'Línea :position: falta el precio.',
            'lineas.*.unit_price.numeric'   => 'Línea :position: el precio tiene que ser un número.',

            'tax_rate.required'        => 'Escriba el porcentaje de impuesto, o cero si no aplica.',
            'discount_amount.required' => 'Escriba el descuento, o cero si no hay.',
            'deposit_applied.required' => 'Escriba el anticipo aplicado, o cero si no hay.',
        ];
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    /**
     * @param  bool  $yEnviar  si además hay que marcarla como enviada
     */
    public function guardar(bool $yEnviar = false)
    {
        /*
         | Emitir y enviar son dos actos distintos, igual que en el
         | presupuesto. El rol de ventas del RoleSeeder tiene
         | invoices.create y invoices.send pero NO invoices.update:
         | puede emitir, no puede corregir una ya emitida.
         */
        $this->exigirPermiso($this->invoiceId ? 'update' : 'create');

        if ($yEnviar) {
            $this->exigirPermiso('send');
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('errores-de-validacion');

            throw $e;
        }

        $empresa = app(CompanyContext::class)->get();

        if (! $empresa) {
            session()->flash('error', 'No hay ninguna empresa activa. Vuelva a iniciar sesión.');

            return null;
        }

        /* -----------------------------------------------------------------
         | TODO DENTRO DE UNA TRANSACCIÓN
         |
         | O se guarda la cabecera Y las líneas Y se recalcula, o no pasa
         | nada.
         |
         | En una factura esto importa más que en un presupuesto: emitir
         | consume un número de la secuencia. Un fallo a mitad dejaría un
         | número gastado en un documento incompleto, y ese hueco hay que
         | poder explicarlo.
         * -------------------------------------------------------------- */
        $factura = DB::transaction(function () use ($empresa, $yEnviar) {

            $esRenta = $this->type === InvoiceType::Rental->value;

            $datos = [
                'company_id'  => $empresa->id,
                'customer_id' => $this->customer_id,
                'type'        => $this->type,
                'issue_date'  => $this->issue_date,
                'due_date'    => $this->due_date ?: null,
                'terms'       => $this->terms ?: null,

                // El período solo se guarda si es de renta. Si el usuario
                // cambió el tipo después de haberlo llenado, se limpia.
                'service_period_start' => $esRenta ? $this->service_period_start : null,
                'service_period_end'   => $esRenta ? $this->service_period_end : null,

                'bill_to' => $this->limpiarDireccion($this->bill_to),
                'ship_to' => $this->envioDistinto ? $this->limpiarDireccion($this->ship_to) : null,

                'discount_amount' => $this->discount_amount ?: 0,
                'tax_rate'        => $this->tax_rate ?: 0,
                'tax_exempt'      => $this->tax_exempt,
                'deposit_applied' => $this->deposit_applied ?: 0,

                'expected_payment_method' => $this->expected_payment_method ?: null,
                'credit_card_fee_percent' => $this->credit_card_fee_percent ?: 0,

                'notes'        => $this->notes ?: null,
                'footer_terms' => $this->footer_terms ?: null,
            ];

            /* -------------------------------------------------------------
             | EL CERTIFICADO DE EXENCIÓN (RB-015)
             |
             | Si el cliente va exento, no basta con marcar la casilla: hay
             | que guardar CUÁL certificado lo justifica.
             |
             | Se busca el vigente a la fecha de emisión. Si el año que
             | viene se vence, esta factura conserva la prueba de que el
             | día que se emitió el cliente sí estaba exento. Eso es
             | exactamente lo que pide una auditoría del estado.
             |
             | Sin la prueba, el impuesto lo termina pagando la empresa.
             * ---------------------------------------------------------- */
            if ($this->tax_exempt) {
                $cliente = Customer::find($this->customer_id);

                /*
                 | Carbon::parse y no el texto pelado: el método del modelo
                 | espera una fecha de verdad, y pasarle "2026-09-02" como
                 | texto lanza un error de tipo.
                 */
                $datos['tax_exemption_certificate_id'] = $cliente
                    ?->activeExemptionCertificate(\Carbon\Carbon::parse($this->issue_date))?->id;
            } else {
                $datos['tax_exemption_certificate_id'] = null;
            }

            /* -------------------------------------------------------------
             | LA CABECERA
             |
             | Al crear no se pasa ni número ni estado: de eso se encarga
             | el InvoiceObserver, que es el mismo camino por el que pasa
             | una factura venga de donde venga (pantalla, conversión de
             | presupuesto, importación o comando).
             * ---------------------------------------------------------- */
            if ($this->invoiceId) {
                $factura = Invoice::findOrFail($this->invoiceId);
                $factura->update($datos);
            } else {
                $factura = Invoice::create($datos);
            }

            /* -------------------------------------------------------------
             | LAS LÍNEAS: borrar las que se quitaron, guardar el resto
             * ---------------------------------------------------------- */
            $gruposReales = $this->gruposConVariasLineas();

            $idsQueSiguen = collect($this->lineas)->pluck('id')->filter()->all();

            $factura->items()
                ->when($idsQueSiguen, fn ($q) => $q->whereNotIn('id', $idsQueSiguen))
                ->delete();

            foreach (array_values($this->lineas) as $orden => $linea) {

                $grupo = trim((string) ($linea['grupo'] ?? ''));

                // Solo se guarda el grupo si de verdad agrupa algo. Una
                // etiqueta en una línea sola no cambia nada al imprimir y
                // confunde al reabrir el documento.
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
                    'service_date' => $linea['service_date'] ?: null,

                    'bundle_key'         => $grupoValido,
                    'bundle_description' => $grupoValido
                        ? ($this->gruposDescripcion[$grupoValido] ?? null)
                        : null,
                ];

                if (! empty($linea['id'])) {

                    $item = $factura->items()->whereKey($linea['id'])->first();

                    if ($item) {
                        $item->fill($atributos)->save();
                    } else {
                        // La línea traía un id que ya no existe (alguien la
                        // borró desde otra pestaña). Se crea de nuevo en vez
                        // de perderla.
                        $factura->items()->create($atributos);
                    }

                } else {
                    $factura->items()->create($atributos);
                }
            }

            // El observer de las líneas ya recalculó en cada guardado,
            // pero se hace una vez más al final por si la última acción
            // fue un borrado: así el número guardado corresponde al estado
            // final y no a uno intermedio.
            $factura->load('items')->recalculate();

            if ($yEnviar) {
                $factura->markAsSent();
            }

            return $factura;
        });

        session()->flash('exito',
            'Factura '.$factura->invoice_number.' guardada'
            .($yEnviar ? ' y marcada como enviada.' : '.'));

        return redirect()->route('finanzas.facturacion.show', $factura);
    }

    /**
     * Quita las partes vacías de una dirección antes de guardarla.
     *
     * Guardar {"line1": null, "city": null} no aporta nada y ensucia el
     * JSON.
     */
    protected function limpiarDireccion(array $direccion): ?array
    {
        $limpia = array_filter(
            $direccion,
            fn ($valor) => $valor !== null && trim((string) $valor) !== '',
        );

        return empty($limpia) ? null : $limpia;
    }

    /** La misma tabla de términos que usa el observer. */
    protected function diasDeTermino(?string $terms): int
    {
        return match (strtolower(trim((string) $terms))) {
            'net 15'         => 15,
            'net 30'         => 30,
            'net 45'         => 45,
            'net 60'         => 60,
            'due on receipt' => 0,
            default          => 0,
        };
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        return view('livewire.invoices.form', [

            // Los productos de esta empresa más los compartidos.
            'productos' => Product::query()
                ->active()
                ->forCompany($empresa?->id)
                ->get(),

            /*
             | Las unidades disponibles de verdad (RB-019).
             |
             | El scope available() filtra tres cosas: que esté en yarda,
             | que no tenga venta activa y que no esté rentada. Los
             | comprados pero todavía en el depósito del proveedor NO
             | aparecen: no se factura lo que no se ha retirado.
             */
            'contenedores' => Container::query()
                ->available()
                ->with(['size:id,name', 'condition:id,name'])
                ->orderBy('internal_code')
                ->limit(300)
                ->get(),

            /*
             | Intercompañía no se ofrece: esas facturas las genera el
             | sistema solo cuando RS Transport le cobra a FLCHR los
             | viajes de la semana (RB-003).
             */
            'tipos' => collect(InvoiceType::options())
                ->except(InvoiceType::Intercompany->value)
                ->all(),

            'metodosDePago' => PaymentMethod::options(),
            'grupos'        => $this->gruposConVariasLineas(),
        ]);
    }
}
