<?php

namespace App\Livewire\Invoices;

use App\Enums\DocumentCategory;
use App\Enums\EstimateStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\UseType;
use App\Models\Container;
use App\Models\Customer;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceCalculator;
use App\Services\PricingResolver;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\AuthorizesAccess;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    /*
     | Detecta las unidades que ya estan en OTRA factura viva. Sin esto,
     | la misma unidad se podia facturar dos veces a dos clientes.
     */
    use \App\Livewire\Concerns\DetectaUnidadesComprometidas;

    /*
     | WithFileUploads le da al componente la capacidad de recibir
     | archivos. Es el mismo trait que ya usaba la ficha de la factura.
     */
    use WithFileUploads;

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

    /**
     * Si ya se guardo en esta misma pantalla.
     *
     * Sirve para enseñar los botones de imprimir, corregir y anular sin
     * saltar a otra ventana.
     */
    public bool $guardada = false;

    /* =====================================================================
     | LOS ADJUNTOS (RB-034)
     |
     | ── POR QUE ESTAN AQUI Y NO SOLO EN LA FICHA ──
     |
     | Porque el momento de adjuntar es JUSTO ANTES de enviar el correo.
     | Teniendolos solo en la ficha, habia que guardar, salir a otra
     | pantalla, subir los papeles, y volver a entrar para mandar la
     | factura. Tres pantallas para una sola gestion.
     |
     | ── POR QUE SOLO DESPUES DE GUARDAR ──
     |
     | Un adjunto se cuelga DE la factura: necesita el id de la factura
     | para saber de quien es. Mientras la factura no existe no hay a que
     | colgarlo.
     |
     | Por eso el paso 3 ensena la zona desactivada con el motivo escrito,
     | en vez de esconderla: si no se ve, nadie sabe que existe.
     * ================================================================== */

    public $archivo = null;

    public string $categoriaArchivo = 'other';

    /**
     * Si el archivo se le manda al cliente con la factura.
     *
     * Viene marcado porque ese es el caso normal. Se desmarca para los
     * papeles internos —la autorizacion de tarjeta firmada, por ejemplo—
     * que no salen de la oficina.
     */
    public bool $viajaConLaFactura = true;

    /* =====================================================================
     | TERMINOS DE PAGO
     |
     | Mismo mecanismo que el presupuesto: un desplegable con los cuatro
     | de siempre y una opcion "otro" para escribirlo a mano.
     |
     | ── LO QUE SE GUARDA ──
     |
     | El termino se guarda SIN TRADUCIR: "Net 30", "Due on receipt". Es
     | texto que va impreso en un documento legal, y esos terminos son
     | los que el cliente conoce y los que su contador espera ver. No se
     | traducen por cambiar el idioma de la pantalla.
     |
     | Lo que si cambia con el idioma es la EXPLICACION que se lee en el
     | desplegable: "Net 30 — 30 días" o "Net 30 — 30 days".
     * ================================================================== */

    public const TERMINO_OTRO = '__otro__';

    public string $termsSeleccion = '';

    public ?string $termsOtro = null;

    /** Los cuatro de siempre. La clave es lo que se imprime. */
    public function terminosDePago(): array
    {
        return [
            'Due on receipt' => 'Due on receipt — '.__('invoices.terms_on_receipt'),
            'Net 15'         => 'Net 15 — '.__('invoices.terms_days', ['n' => 15]),
            'Net 30'         => 'Net 30 — '.__('invoices.terms_days', ['n' => 30]),
            '50% deposit'    => __('invoices.terms_deposit'),
        ];
    }

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

    /* =====================================================================
     | QUIEN VENDIO
     |
     | ── POR QUE VA EN LA FACTURA Y NO EN UN MODULO APARTE ──
     |
     | Porque es un dato de la venta, no un tramite posterior. Denisse lo
     | describe asi del Excel: "dice que tipo de contenedor, el numero del
     | contenedor, si hay comision, porque tenemos vendedores".
     |
     | La comision es una columna de la venta. Si se registrara despues,
     | alguien tendria que acordarse, y las que se olvidan no se pagan.
     |
     | ── Y POR QUE UN TRABAJADOR Y NO UN USUARIO ──
     |
     | Miguelito vende y cobra comision desde 2024, y probablemente nunca
     | ha abierto el sistema. Denisse teclea la factura; Miguelito la
     | vendio. Son dos personas distintas y las dos quedan guardadas:
     | `sold_by_employee_id` dice quien vendio y `created_by` quien
     | registro.
     * ================================================================== */

    public ?int $sold_by_employee_id = null;

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

    /* =====================================================================
     | EL BUSCADOR DE UNIDADES
     |
     | ── POR QUE UN BUSCADOR Y NO UN DESPLEGABLE ──
     |
     | Un desplegable con doscientos contenedores obliga a bajar con la
     | rueda buscando un numero que ya se sabe. Y no ensena nada de cada
     | unidad: solo el numero y la medida, que es lo unico que cabe en
     | una linea de <option>.
     |
     | El buscador filtra escribiendo y ensena de cada unidad su
     | clasificacion y su precio, que es lo que decide cual ofrecer.
     |
     | Es el mismo que el presupuesto, y a proposito: quien factura y
     | quien cotiza son la misma persona.
     * ================================================================== */

    /**
     * Sobre QUE renglon esta abierto el buscador de unidades.
     *
     * Antes era un booleano ($buscadorUnidad) y el buscador se dibujaba
     * dentro del propio modal, apretado en media columna. Ahora es una
     * capa aparte a pantalla completa —la misma del presupuesto— y por
     * eso necesita saber a que renglon le va a escribir.
     *
     * null = cerrado.
     */
    public ?int $lineaBuscandoContenedor = null;

    public string $buscarContenedor = '';

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

            /* -------------------------------------------------------------
             | ENTRAR DIRECTO A UN PASO
             |
             | Lo usa la conversión de presupuesto a factura, que manda
             | ?paso=3: el cliente, las direcciones y las líneas vienen ya
             | copiadas, así que lo único que falta es revisar y emitir.
             | Entrar por el paso 1 obligaría a pulsar Siguiente dos veces
             | para llegar a lo que realmente toca.
             |
             | Se limita al rango válido a propósito: el número llega por
             | la dirección del navegador, o sea desde fuera, y un valor
             | raro dejaría la pantalla en un paso que no existe.
             * ---------------------------------------------------------- */
            $pedido = (int) request()->query('paso', 1);

            $this->paso = max(1, min($pedido, self::PASOS));

            return null;
        }

        /* -----------------------------------------------------------------
         | CASO B · UNA NUEVA
         * -------------------------------------------------------------- */
        $this->exigirPermiso('create');

        $this->issue_date = now()->toDateString();

        if ($empresa) {
            $this->terms          = $calc->defaultTerms($empresa);
            $this->termsSeleccion = array_key_exists((string) $this->terms, $this->terminosDePago())
                ? (string) $this->terms
                : '';
            $this->tax_rate = $calc->defaultTaxRate($empresa);

            /* -------------------------------------------------------------
             | LOS TERMINOS DEL PIE
             |
             | Salen de la ficha de la empresa (companies.invoice_footer_terms).
             | Esa columna existe desde la primera migracion y no la leia
             | nadie: cada factura nueva arrancaba con el pie en blanco y
             | habia que escribirlo a mano o quedaba sin el.
             |
             | Es texto fijo —condiciones, garantia, aviso de mora— y es el
             | mismo en todas las facturas de esa empresa. Queda editable
             | aqui por si una en concreto necesita otro.
             * ---------------------------------------------------------- */
            $this->footer_terms = $empresa->invoice_footer_terms;
        }

        /*
         | SIN NINGUN RENGLON.
         |
         | Aqui estaba el error del "2 conceptos" habiendo uno.
         |
         | Se sembraba un renglon en blanco al entrar. No se veia en la
         | tabla —la vista salta los que no tienen descripcion— pero SI
         | contaba: el pie decia dos, y al guardar la validacion pedia la
         | descripcion de la "Linea 1" que nadie habia creado.
         |
         | Ahora la lista arranca vacia y el unico camino es el boton de
         | agregar concepto.
         */

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

        $this->customer_id          = $invoice->customer_id;
        $this->sold_by_employee_id  = $invoice->sold_by_employee_id;
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
            'use_type'     => $linea->use_type,

            // Los campos propios de cada concepto. Sin esto, reabrir una
            // factura guardada perdia el plazo, las millas y el detalle
            // de la reparacion aunque estuvieran en la base.
            'delivery_zip'  => $linea->delivery_zip,
            'miles'         => $linea->miles !== null ? (float) $linea->miles : null,
            'rate_per_mile' => $linea->rate_per_mile !== null ? (float) $linea->rate_per_mile : null,
            'rental_months' => $linea->rental_months,
            'work_details'  => $linea->work_details,

            /*
             | Una descripcion que ya esta guardada se considera escrita a
             | mano: la reviso una persona al emitir. Cambiar el concepto
             | al corregir no debe pisarla.
             */
            'desc_manual'   => filled($linea->description),
        ])->all();

        foreach ($invoice->items as $linea) {
            if ($linea->bundle_key) {
                $this->gruposDescripcion[$linea->bundle_key] = $linea->bundle_description ?? '';
            }
        }

        /*
         | Si la factura guardada no tiene renglones, se deja vacia: se
         | agregan con el boton, igual que en una nueva.
         */
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

            /*
             | La fecha del servicio nace hoy.
             |
             | Es la que se cumple casi siempre: se factura lo que se
             | acaba de hacer. Dejarla vacia obligaba a abrirla en cada
             | renglon para escribir la fecha de hoy.
             */
            'service_date' => now()->toDateString(),

            /*
             | El uso previsto de ESTA unidad.
             |
             | Va por renglon y no en la cabecera: una factura puede
             | llevar tres contenedores con tres destinos distintos.
             */
            'use_type'     => null,

            /* -------------------------------------------------------------
             | LOS CAMPOS QUE VENIAN DEL PRESUPUESTO Y AQUI SE PERDIAN
             |
             | Las columnas ya existen en invoice_items desde la migracion
             | del 11-sep (align_invoices_with_estimates). Lo que faltaba
             | era que el formulario las pidiera y las guardara.
             |
             | Sin ellas, el cliente aprobaba un presupuesto que decia
             | "reparacion: cambio de pisos y pintura, 3 meses, 42 millas"
             | y recibia una factura que decia "reparacion".
             * ---------------------------------------------------------- */

            // Solo en lineas de ENTREGA. El importe se calcula: millas x tarifa.
            'delivery_zip'  => null,
            'miles'         => null,
            'rate_per_mile' => null,

            // Solo en lineas de RENTA. NO multiplica el importe.
            'rental_months' => null,

            // Solo en REPARACION. Que se le hizo al contenedor.
            'work_details'  => null,

            /*
             | Bandera: la descripcion la escribio una persona.
             |
             | Mientras sea false, cambiar el concepto reescribe el texto.
             | En cuanto alguien lo edita a mano pasa a true y no se toca
             | nunca mas.
             |
             | Sin esta bandera pasa esto: se elige "Renta de contenedor",
             | el sistema escribe ese nombre, se cambia el concepto a
             | "Entrega / Delivery" y la descripcion se queda diciendo
             | "Renta de contenedor". La factura sale impresa con un
             | delivery llamado renta.
             */
            'desc_manual'   => false,
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
    public function abrirLinea(?int $indice, bool $esNuevo = false): void
    {
        if ($indice === null || ! isset($this->lineas[$indice])) {
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

        // El buscador de unidades es una capa por encima del modal. Si el
        // modal se va y el buscador se queda, queda una pantalla flotando
        // sobre el formulario sin nada debajo a lo que escribirle.
        $this->cerrarBuscadorContenedor();

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

        $producto = $this->productoDelBorrador();

        /* -----------------------------------------------------------------
         | CADA CONCEPTO EXIGE LO SUYO
         |
         | Antes se pedian siempre los mismos tres campos: descripcion,
         | cantidad y precio. Eso dejaba pasar una entrega sin millas y
         | una renta sin plazo, que son justo los datos que despues nadie
         | encuentra cuando el cliente reclama.
         |
         | Es la misma tabla de reglas que el presupuesto, a proposito:
         | convertir un presupuesto en factura es copiar, no traducir
         | (RB-033). Si la factura exigiera menos, se podria "perder" un
         | dato al convertir sin que nada avisara.
         * -------------------------------------------------------------- */
        $reglas = [
            'borrador.description' => ['required', 'string', 'max:1000'],
            'borrador.unit_price'  => ['required', 'numeric', 'min:0'],
            'borrador.quantity'    => ['required', 'numeric', 'min:0.01'],
        ];

        if ($producto?->type->requiresContainer()) {
            $reglas['borrador.container_id'] = ['required', 'exists:containers,id'];
        }

        if ($producto?->isRental()) {
            $reglas['borrador.rental_months'] = ['required', 'integer', 'min:1', 'max:120'];
        }

        if ($producto?->isDelivery()) {
            $reglas['borrador.delivery_zip']  = ['required', 'string', 'max:10'];
            $reglas['borrador.miles']         = ['required', 'numeric', 'min:0'];
            $reglas['borrador.rate_per_mile'] = ['required', 'numeric', 'min:0'];
        }

        if ($producto && $producto->code === 'REPAIR') {
            $reglas['borrador.work_details'] = ['required', 'string', 'max:2000'];
        }

        $this->validate($reglas, [
            'borrador.description.required' => 'Escriba que se le esta cobrando. '
                                              .'Es el texto que el cliente va a leer.',
            'borrador.quantity.min'   => 'La cantidad tiene que ser mayor que cero.',
            'borrador.unit_price.min' => 'El precio no puede ser negativo.',
        ], [
            'borrador.description'   => 'descripcion',
            'borrador.unit_price'    => 'precio',
            'borrador.quantity'      => 'cantidad',
            'borrador.container_id'  => 'unidad',
            'borrador.rental_months' => 'plazo',
            'borrador.delivery_zip'  => 'ZIP de destino',
            'borrador.miles'         => 'millas',
            'borrador.rate_per_mile' => 'tarifa por milla',
            'borrador.work_details'  => 'trabajo realizado',
        ]);

        /*
         | Un contenedor no viene en cantidades. Un renglon es una
         | unidad; si hay dos contenedores, hay dos renglones.
         |
         | Se fuerza aqui y no solo en la pantalla porque el dato puede
         | llegar de una conversion de presupuesto o de una duplicacion.
         */
        if ($producto?->type->requiresContainer()) {
            $this->borrador['quantity'] = 1;
        }

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
            1 => ['customer_id', 'sold_by_employee_id', 'type', 'issue_date', 'due_date', 'terms',
                  'service_period_start', 'service_period_end',
                  'bill_to.line1', 'bill_to.city', 'bill_to.state', 'bill_to.zip',
                  'ship_to.line1', 'ship_to.city', 'ship_to.state', 'ship_to.zip'],

            2 => ['lineas', 'lineas.*.description', 'lineas.*.quantity',
                  'lineas.*.unit_price', 'lineas.*.product_id',
                  'lineas.*.container_id', 'lineas.*.grupo', 'lineas.*.service_date', 'lineas.*.use_type'],

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
    protected function aplicarPrecioDeContenedor(
        int $contenedorId,
        ?Product $producto = null,
        bool $forzar = false,
    ): void {
        $contenedor = Container::with(['size:id,name', 'condition:id,name', 'grade:id,name'])
            ->find($contenedorId);

        if (! $contenedor) {
            return;
        }

        $producto ??= $this->productoDelBorrador();

        $esRenta = $producto?->isRental() ?? ($this->type === InvoiceType::Rental->value);

        $precio = $contenedor->suggestedPrice($esRenta);

        /*
         | null = esa unidad no tiene precio cargado para eso. Se deja el
         | campo como esta para que la persona escriba, en vez de meter un
         | cero que se puede guardar por distraccion.
         */
        if ($precio !== null && ($forzar || empty($this->borrador['unit_price']))) {
            $this->borrador['unit_price'] = $precio;
        }

        /*
         | LA DESCRIPCION
         |
         | La arma el concepto, no este archivo: ver Product::autoDescription().
         | Asi el presupuesto y la factura escriben exactamente el mismo
         | texto, que es lo que exige RB-033.
         |
         | Se respeta lo que haya tecleado una persona (desc_manual).
         */
        if (empty($this->borrador['desc_manual'])) {
            $this->borrador['description'] = $producto
                ? $producto->autoDescription($contenedor)
                : $contenedor->lineDescription();
        }

        /*
         | EL CONTENEDOR SI PAGA EL 7% (RB-006), salvo en exportacion.
         |
         | El uso se mira en el propio renglon y no en la cabecera: una
         | factura puede llevar una unidad para almacenaje y otra para
         | exportar, y solo la segunda va sin impuesto.
         |
         | Y transporte nunca lleva impuesto, sea cual sea el uso (RB-005).
         */
        $esExport    = ($this->borrador['use_type'] ?? null) === UseType::Export->value;
        $esTransport = $this->type === InvoiceType::Transport->value;

        $this->borrador['taxable'] = ! $esExport && ! $esTransport;

        $this->resetValidation('borrador.description');
        $this->resetValidation('borrador.unit_price');
    }

    /* =====================================================================
     | LOS PRECIOS DEL TRANSPORTE
     * ================================================================== */

    /**
     * Calcula el importe de una linea de ENTREGA: millas x tarifa.
     *
     * ── DE DONDE SALE LA TARIFA ──
     *
     * De la ficha del transportista si la tiene, y si no del ajuste
     * operations.default_rate_per_mile. Ni un numero escrito en este
     * archivo: si manana sube la tarifa, se cambia en Configuracion.
     *
     * Todo lo que escribe este metodo queda editable en el renglon. Es
     * una sugerencia para no teclear el caso normal, no un candado
     * (RB-029).
     *
     * ── EL PICKUP NO ENTRA ──
     *
     * El pickup es el viaje deposito -> yarda y lo paga FLCHR (RB-031).
     * Nunca se le cobra al cliente, asi que no tiene precio que calcular
     * en una factura.
     */
    protected function aplicarPrecioDeTransporte(Product $producto): void
    {
        if (! $producto->isDelivery()) {
            return;
        }

        $empresa  = app(CompanyContext::class)->get();
        $resolver = app(PricingResolver::class);

        // La tarifa se precarga una sola vez por renglon. Si la persona
        // la piso a mano, se respeta.
        if (($this->borrador['rate_per_mile'] ?? null) === null) {
            $this->borrador['rate_per_mile'] = $resolver->ratePerMile($empresa);
        }

        $millas = (float) ($this->borrador['miles'] ?? 0);

        // Sin millas no hay nada que calcular todavia. En cuanto las
        // escriba, updated() vuelve a pasar por aqui.
        if ($millas <= 0) {
            return;
        }

        $this->borrador['unit_price'] = round(
            $millas * (float) $this->borrador['rate_per_mile'],
            2,
        );

        $this->borrador['quantity'] = 1;

        // RB-005: el transporte NUNCA lleva sales tax en Florida.
        $this->borrador['taxable'] = false;

        $this->resetValidation('borrador.unit_price');
    }

    /** Recalcula el renglon de transporte cuando cambian millas o tarifa. */
    protected function recalcularLineaDeTransporte(): void
    {
        $producto = $this->productoDelBorrador();

        if ($producto && $producto->isDelivery()) {
            $this->aplicarPrecioDeTransporte($producto);
        }
    }

    /* =====================================================================
     | EL BUSCADOR DE UNIDADES
     |
     | ── POR QUE UNA CAPA APARTE Y NO UNA LISTA DENTRO DEL MODAL ──
     |
     | Porque dentro del modal solo cabia media columna: una lista de 220
     | pixeles de alto donde cada unidad se veia en dos renglones
     | apretados. Con doscientos contenedores en yarda, elegir ahi es
     | adivinar.
     |
     | Es exactamente el mismo buscador del presupuesto, y a proposito:
     | quien cotiza y quien factura son la misma persona.
     * ================================================================== */

    public function abrirBuscadorContenedor(int $indice): void
    {
        $this->lineaBuscandoContenedor = $indice;
        $this->buscarContenedor        = '';
    }

    public function cerrarBuscadorContenedor(): void
    {
        $this->lineaBuscandoContenedor = null;
        $this->buscarContenedor        = '';
    }

    /**
     * Las unidades que coinciden con lo que se esta escribiendo.
     *
     * Solo las de la empresa activa y solo las disponibles de verdad:
     * en yarda, sin venta ni renta encima (RB-019). Las compradas pero
     * todavia en el deposito del proveedor NO salen: no se factura lo
     * que no se ha retirado.
     */
    public function getResultadosContenedorProperty()
    {
        $empresa = app(CompanyContext::class)->get();

        return Container::query()
            ->available()
            ->forBillingCompany($empresa?->id)
            ->search($this->buscarContenedor)
            ->with(['size:id,name', 'condition:id,name', 'grade:id,name'])
            ->orderBy('internal_code')
            ->limit(15)
            ->get();
    }

    /**
     * Las unidades ya elegidas en OTROS renglones de esta misma factura.
     *
     * La pantalla las ensena deshabilitadas. Cobrar dos veces el mismo
     * contenedor en la misma factura es un error de dedo que despues se
     * convierte en un contenedor vendido a dos clientes.
     *
     * Devuelve [container_id => numero de renglon].
     */
    public function getContenedoresYaUsadosProperty(): array
    {
        $usados = [];

        foreach ($this->lineas as $i => $linea) {
            // El renglon que se esta editando se salta: su propia unidad
            // no puede salir deshabilitada en su propio buscador.
            if ($i === $this->lineaEditando) {
                continue;
            }

            if (! empty($linea['container_id'])) {
                $usados[(int) $linea['container_id']] = $i + 1;
            }
        }

        return $usados;
    }

    /**
     * Las unidades que estan ofrecidas en un presupuesto todavia abierto.
     *
     * Devuelve [container_id => Estimate].
     *
     * ── POR QUE AVISAR AL FACTURAR ──
     *
     * Porque el presupuesto NO reserva: es una cotizacion que vale tres
     * dias. Si mientras tanto alguien factura esa misma unidad a otro
     * cliente, el presupuesto abierto queda prometiendo algo que ya no
     * existe, y nadie se entera hasta que el cliente acepta.
     *
     * No bloquea, avisa. La factura manda sobre el presupuesto: quien
     * paga primero se lleva la unidad. Pero conviene saber a quien hay
     * que llamar para avisarle.
     *
     * Solo cuentan los presupuestos en borrador o enviados. Uno
     * rechazado, vencido o ya convertido no compite por la unidad.
     */
    public function getCotizadasEnOtrosProperty(): array
    {
        $ids = $this->resultadosContenedor->pluck('id')->all();

        if (empty($ids)) {
            return [];
        }

        return EstimateItem::query()
            ->whereIn('container_id', $ids)
            ->whereHas('estimate', function ($q) {
                $q->whereIn('status', [
                    EstimateStatus::Draft->value,
                    EstimateStatus::Sent->value,
                ]);
            })
            ->with('estimate:id,estimate_number,status,valid_until')
            ->get()
            ->sortByDesc('id')
            ->groupBy('container_id')
            ->map(fn ($items) => $items->first()->estimate)
            ->filter()
            ->all();
    }

    public function seleccionarContenedor(int $contenedorId): void
    {
        if ($this->lineaEditando === null) {
            return;
        }

        /* -----------------------------------------------------------------
         | EL CANDADO DE VERDAD
         |
         | El botón deshabilitado del buscador se puede saltar, y entre que
         | se abrió el buscador y se pulsó, otra persona pudo facturar esa
         | misma unidad. Aquí es donde el dato entra al documento, así que
         | aquí se vuelve a comprobar.
         * -------------------------------------------------------------- */
        if ($factura = ($this->comprometidasEnFacturas[$contenedorId] ?? null)) {
            $this->cerrarBuscadorContenedor();

            session()->flash('error',
                'La unidad ya está facturada en la '.$factura->invoice_number
                .'. Facturarla otra vez sería venderla dos veces.');

            return;
        }

        $this->borrador['container_id'] = $contenedorId;

        $this->aplicarPrecioDeContenedor(
            $contenedorId,
            $this->productoDelBorrador(),
            forzar: true,
        );

        $this->cerrarBuscadorContenedor();
    }

    public function quitarContenedor(): void
    {
        $this->borrador['container_id'] = null;
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

        /* Quedarse sin renglones esta bien: se agregan con el boton. */

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
        /* -----------------------------------------------------------------
         | EL DESPLEGABLE DE TERMINOS
         |
         | Lo que se guarda es $terms, nunca el "__otro__" del
         | desplegable: ese valor es una instruccion para la pantalla, no
         | un termino de pago.
         * -------------------------------------------------------------- */
        if ($campo === 'termsSeleccion' || $campo === 'termsOtro') {
            $this->terms = $this->termsSeleccion === self::TERMINO_OTRO
                ? ($this->termsOtro ?: null)
                : ($this->termsSeleccion ?: null);
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
        if ($campo === 'borrador.product_id') {
            $this->aplicarProducto();
        }

        /* -----------------------------------------------------------------
         | ALGUIEN ESCRIBIO LA DESCRIPCION A MANO
         |
         | A partir de aqui el texto es suyo y cambiar el concepto ya no
         | lo pisa. Si la deja vacia, vuelve a ser automatica.
         * -------------------------------------------------------------- */
        if ($campo === 'borrador.description') {
            $this->borrador['desc_manual'] = filled($this->borrador['description'] ?? null);
        }

        /* -----------------------------------------------------------------
         | CAMBIARON LAS MILLAS O LA TARIFA DE UNA ENTREGA
         |
         | El importe del transporte se calcula, no se teclea. Y cada
         | renglon lleva los suyos: una factura de tres contenedores puede
         | ir a tres direcciones distintas (RB-049).
         * -------------------------------------------------------------- */
        if ($campo === 'borrador.miles' || $campo === 'borrador.rate_per_mile') {
            $this->recalcularLineaDeTransporte();
        }

        /* -----------------------------------------------------------------
         | CAMBIO EL USO PREVISTO DE ESA UNIDAD (RB-006, RB-016)
         |
         | En exportacion no se cobra sales tax de Florida. Se desmarca
         | SOLO este renglon: la factura puede llevar una unidad para
         | almacenaje y otra para exportar.
         * -------------------------------------------------------------- */
        if ($campo === 'borrador.use_type'
            && ($this->borrador['use_type'] ?? null) === UseType::Export->value) {
            $this->borrador['taxable'] = false;
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

            $this->aplicarPrecioDeContenedor(
                (int) $this->borrador['container_id'],
                $this->productoDelBorrador(),
                forzar: true,
            );
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
        /* -----------------------------------------------------------------
         | LA FECHA DE VENCIMIENTO YA NO SE CALCULA SOLA
         |
         | Antes se recalculaba al escribir los terminos. El problema es
         | que no hay ninguna regla documentada: ni las actas ni la hoja
         | de VENTAS del Excel dicen a cuantos dias vence una factura.
         |
         | Poner una fecha inventada es peor que dejarla vacia: nadie la
         | revisa, y el aviso de cobranza empieza a saltar cuando al
         | sistema le parece.
         |
         | Se propone solo la PRIMERA vez, al crear, y desde ahi la
         | escribe quien factura.
         * -------------------------------------------------------------- */
        if (blank($this->due_date) && ($campo === 'terms' || $campo === 'issue_date')) {
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

    /* =====================================================================
     | EL CATALOGO DE CONCEPTOS
     * ================================================================== */

    /**
     * Los conceptos facturables, una sola consulta por peticion.
     *
     * Los metodos del editor tambien lo necesitan y no pueden esperar a
     * que se pinte la pantalla, asi que se resuelve aqui y render() lo
     * reutiliza.
     */
    public function getProductosDisponiblesProperty()
    {
        static $cache = null;

        return $cache ??= Product::query()
            ->active()
            ->forCompany(app(CompanyContext::class)->get()?->id)
            ->usableIn('invoice')
            ->get();
    }

    /** El concepto del renglon que se esta editando. */
    public function productoDelBorrador(): ?Product
    {
        $id = $this->borrador['product_id'] ?? null;

        return $id ? $this->productosDisponibles->firstWhere('id', (int) $id) : null;
    }

    /**
     * Precarga el renglon con los datos del concepto elegido.
     *
     * ── QUE CAMBIO ──
     *
     * Antes recibia el indice del renglon y escribia directo sobre
     * $lineas. Eso obligaba a volcar el borrador, aplicar, y recogerlo
     * de vuelta en cada tecla, y por el camino se perdia lo que la
     * persona estaba escribiendo.
     *
     * Ahora trabaja sobre el borrador, que es lo unico que el modal
     * tiene delante. Es el mismo metodo que el presupuesto.
     */
    protected function aplicarProducto(): void
    {
        $producto = $this->productoDelBorrador();

        if (! $producto) {
            return;
        }

        $defaults = $producto->lineDefaults();

        /* -----------------------------------------------------------------
         | LA DESCRIPCION
         |
         | Se reescribe salvo que la haya tecleado una persona. Si ya hay
         | una unidad elegida, el texto se arma con ella:
         | "Venta de contenedor - 40 ft High Cube - Usado (MSCU...)".
         * -------------------------------------------------------------- */
        if (empty($this->borrador['desc_manual'])) {
            $unidad = ! empty($this->borrador['container_id'])
                ? Container::with(['size:id,name', 'condition:id,name', 'grade:id,name'])
                    ->find($this->borrador['container_id'])
                : null;

            $this->borrador['description'] = $producto->autoDescription($unidad);
        }

        if (empty($this->borrador['unit_price'])) {
            $this->borrador['unit_price'] = $defaults['unit_price'];
        }

        /* -----------------------------------------------------------------
         | EL IMPUESTO
         |
         | Transporte nunca lleva sales tax (RB-005) y exportacion tampoco
         | (RB-016). Fuera de esos dos casos manda lo que diga el catalogo.
         * -------------------------------------------------------------- */
        $esTransport = $this->type === InvoiceType::Transport->value;
        $esExport    = ($this->borrador['use_type'] ?? null) === UseType::Export->value;

        $this->borrador['taxable'] = ($esTransport || $esExport)
            ? false
            : $defaults['taxable'];

        // Si el concepto no lleva contenedor, se limpia el que pudiera
        // haber quedado elegido de antes.
        if (! $producto->type->requiresContainer()) {
            $this->borrador['container_id'] = null;
        }

        /* -----------------------------------------------------------------
         | LOS CAMPOS PROPIOS DE CADA CONCEPTO
         |
         | Se limpian al cambiar de concepto. Sin esto, pasar de una
         | entrega a una renta dejaba el ZIP y las millas de la entrega
         | anterior pegados al renglon, y esos datos terminaban impresos.
         * -------------------------------------------------------------- */
        if ($producto->isRental()) {
            if (empty($this->borrador['rental_months'])) {
                $empresa = app(CompanyContext::class)->get();

                $this->borrador['rental_months'] =
                    (int) ($empresa?->setting('rentals', 'default_months', 1) ?? 1);
            }
        } else {
            $this->borrador['rental_months'] = null;
        }

        if (! $producto->isDelivery()) {
            $this->borrador['delivery_zip']  = null;
            $this->borrador['miles']         = null;
            $this->borrador['rate_per_mile'] = null;
        }

        if ($producto->code !== 'REPAIR') {
            $this->borrador['work_details'] = null;
        }

        // TRANSPORTE: el importe se calcula, no se teclea.
        if ($producto->isDelivery()) {
            $this->aplicarPrecioDeTransporte($producto);
        }

        /*
         | CONTENEDOR: si ya habia uno elegido, se refresca el precio.
         | Pasa al cambiar de "Venta" a "Renta" con la misma unidad: son
         | dos numeros distintos de la misma ficha.
         */
        if ($producto->type->requiresContainer() && ! empty($this->borrador['container_id'])) {
            $this->aplicarPrecioDeContenedor(
                (int) $this->borrador['container_id'],
                $producto,
                forzar: true,
            );
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
     * Que lleva cada grupo y cuanto suma.
     *
     * Es lo que se ensena en el pie de la lista: "Grupo A: renglones 1 y
     * 2 - el cliente ve $2.750,00". Sin ese numero, agrupar es un acto de
     * fe: no se ve el precio consolidado hasta imprimir.
     */
    public function getResumenGruposProperty(): array
    {
        $resumen = [];

        foreach ($this->gruposConVariasLineas() as $grupo) {
            $total   = 0.0;
            $numeros = [];

            foreach ($this->lineas as $i => $linea) {
                if (trim((string) ($linea['grupo'] ?? '')) === $grupo) {
                    $total    += $this->importeLinea($i);
                    $numeros[] = $i + 1;
                }
            }

            $resumen[$grupo] = ['total' => $total, 'lineas' => $numeros];
        }

        return $resumen;
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

    /**
     * El tipo que hereda la factura de sus renglones.
     *
     * Ya no se pregunta en la cabecera. Se mira lo que llevan los
     * conceptos:
     *
     *   solo contenedores          venta
     *   solo rentas                renta
     *   solo transporte            transporte
     *   mezcla                     mixta
     *
     * Es mas fiable que preguntarlo: la persona tendria que elegir antes
     * de saber que va a cobrar, y despues nadie vuelve a corregirlo.
     */
    protected function tipoDelDocumento(): string
    {
        $tipos = collect($this->lineas)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($id) => Product::find($id)?->type?->value)
            ->filter()
            ->unique();

        if ($tipos->isEmpty()) {
            return $this->type ?: InvoiceType::Sale->value;
        }

        if ($tipos->count() > 1) {
            /*
             | No hay un tipo "mixta" en el enum, y Other es lo mas
             | honesto: la factura lleva cosas de varias clases y
             | forzarla a una seria decir algo que no es cierto.
             */
            return InvoiceType::Other->value;
        }

        return match ($tipos->first()) {
            'rental'  => InvoiceType::Rental->value,
            'service' => InvoiceType::Transport->value,
            default   => InvoiceType::Sale->value,
        };
    }

    protected function rules(): array
    {
        return [
            'customer_id'         => ['required', 'exists:customers,id'],
            'sold_by_employee_id' => ['nullable', 'exists:employees,id'],
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
            'lineas.*.use_type'     => ['nullable', 'string', 'max:30'],

            /*
             | Aqui van sueltas a proposito. Lo obligatorio de cada
             | concepto —el plazo de una renta, las millas de una
             | entrega— ya se exige en guardarLinea(), que es donde la
             | persona tiene el campo delante.
             |
             | Repetirlo aqui sacaria el error en la pantalla equivocada:
             | un mensaje rojo sobre un renglon que esta cerrado.
             */
            'lineas.*.delivery_zip'  => ['nullable', 'string', 'max:10'],
            'lineas.*.miles'         => ['nullable', 'numeric', 'min:0'],
            'lineas.*.rate_per_mile' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.rental_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'lineas.*.work_details'  => ['nullable', 'string', 'max:2000'],
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
                'sold_by_employee_id' => $this->sold_by_employee_id ?: null,
                /* Lo decide el contenido, no una pregunta de cabecera. */
                'type'        => $this->tipoDelDocumento(),
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
                    'use_type'     => $linea['use_type'] ?: null,

                    /* -------------------------------------------------
                     | LO QUE ANTES SE PERDIA AL FACTURAR
                     |
                     | Las columnas existen en invoice_items desde la
                     | migracion del 11-sep. Lo que faltaba era guardarlas.
                     |
                     | RB-033: convertir un presupuesto en factura es
                     | copiar, no traducir. Si la factura no tiene donde
                     | poner el plazo o las millas, la copia pierde datos
                     | en silencio.
                     * ---------------------------------------------- */
                    'delivery_zip'  => $linea['delivery_zip']  ?: null,
                    'miles'         => $linea['miles']         !== null && $linea['miles'] !== ''
                                        ? $linea['miles'] : null,
                    'rate_per_mile' => $linea['rate_per_mile'] !== null && $linea['rate_per_mile'] !== ''
                                        ? $linea['rate_per_mile'] : null,
                    'rental_months' => $linea['rental_months'] ?: null,
                    'work_details'  => $linea['work_details']  ?: null,

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

            /* -------------------------------------------------------------
             | SACAR DEL INVENTARIO LO QUE SE ACABA DE COBRAR
             |
             | Aqui faltaba el puente: la factura guardaba el container_id
             | de cada renglon y ahi se acababa. Se rentaba una unidad y en
             | su ficha no pasaba nada: seguia "en yarda", seguia contando
             | como disponible y su historial no mencionaba la renta.
             |
             | El metodo del modelo se encarga de las excepciones: solo
             | mueve lo que sigue en yarda, asi que facturar el mes 5 de
             | una renta no vuelve a mover nada.
             |
             | Va DENTRO de la transaccion: si la factura no se guarda,
             | el contenedor tampoco se mueve.
             * ---------------------------------------------------------- */
            $factura->aplicarEfectoEnContenedores();

            return $factura;
        });

        /* -----------------------------------------------------------------
         | NO SE REDIRIGE: SE QUEDA AQUI
         |
         | Antes saltaba a la ficha, y eso obligaba a mirar el documento
         | en una pantalla y corregirlo en otra.
         |
         | Ahora la factura queda guardada, el numero aparece arriba, y
         | los botones de imprimir, corregir y anular salen en esta misma
         | pantalla, debajo de la vista previa que ya se estaba mirando.
         * -------------------------------------------------------------- */
        $this->invoiceId = $factura->id;
        $this->numero    = $factura->invoice_number;
        $this->yaEnviada = $yEnviar || $this->yaEnviada;
        $this->guardada  = true;

        session()->flash('exito',
            'Factura '.$factura->invoice_number.' guardada'
            .($yEnviar ? ' y marcada como enviada.' : '.'));

        return null;
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

    /* =====================================================================
     | LOS ADJUNTOS
     |
     | Son los mismos tres metodos de la ficha, con una sola diferencia:
     | aqui la factura se busca por $invoiceId en vez de venir inyectada.
     * ================================================================== */

    /** Los documentos ya colgados de esta factura. */
    public function getDocumentosProperty()
    {
        if (! $this->invoiceId) {
            return collect();
        }

        return Invoice::whereKey($this->invoiceId)
            ->with('documents')
            ->first()
            ?->documents ?? collect();
    }

    /**
     * Sube un archivo y lo cuelga de la factura.
     *
     * Los archivos van a storage/app/facturas/{id}/. Agrupar por factura
     * hace que respaldar o limpiar sea trivial, y evita que dos archivos
     * con el mismo nombre de facturas distintas se pisen.
     */
    public function subirArchivo(): void
    {
        // Adjuntar cambia el documento: es edicion, no lectura.
        $this->exigirPermiso('update');

        if (! $this->invoiceId) {
            session()->flash('error',
                'Guarde la factura antes de adjuntar. Un archivo se cuelga '
                .'de un documento que ya existe.');

            return;
        }

        $this->validate([
            'archivo'          => 'required|file|max:10240',
            'categoriaArchivo' => 'required|string',
        ], [
            'archivo.required' => 'Elija un archivo.',
            'archivo.max'      => 'El archivo no puede pasar de 10 MB.',
        ]);

        try {
            $factura = Invoice::findOrFail($this->invoiceId);

            $nombreOriginal = $this->archivo->getClientOriginalName();

            $ruta = $this->archivo->store('facturas/'.$factura->id, 'local');

            $factura->attachDocument(
                path: $ruta,
                nombre: $nombreOriginal,
                disk: 'local',
                categoria: DocumentCategory::tryFrom($this->categoriaArchivo) ?? DocumentCategory::Other,
                viajaConLaFactura: $this->viajaConLaFactura,
                mime: $this->archivo->getMimeType(),
                bytes: $this->archivo->getSize(),
            );

            // Se limpia el formulario para poder subir otro sin recargar.
            $this->reset(['archivo', 'categoriaArchivo']);
            $this->viajaConLaFactura = true;

            session()->flash('exito', 'Documento adjuntado.');

        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo subir el archivo: '.$e->getMessage());
        }
    }

    /** Descarga un adjunto. */
    public function descargar(int $documentId)
    {
        $this->exigirPermiso('view');

        $factura = $this->invoiceId ? Invoice::find($this->invoiceId) : null;

        $documento = $factura?->documents()->find($documentId);

        if (! $documento) {
            session()->flash('error', 'Ese documento no pertenece a esta factura.');

            return null;
        }

        $disco = $documento->disk ?: 'local';

        if (! Storage::disk($disco)->exists($documento->path)) {
            session()->flash('error',
                'El archivo "'.$documento->name.'" ya no esta en el servidor. '
                .'Es posible que se haya borrado a mano.');

            return null;
        }

        return Storage::disk($disco)->download($documento->path, $documento->name);
    }

    /**
     * Quita un adjunto.
     *
     * El modelo Document borra el archivo del disco solo, en su evento
     * "deleted": asi no queda basura ocupando espacio cada vez que
     * alguien se equivoca de archivo.
     */
    public function quitarArchivo(int $documentId): void
    {
        $this->exigirPermiso('update');

        $factura = $this->invoiceId ? Invoice::find($this->invoiceId) : null;

        $documento = $factura?->documents()->find($documentId);

        if (! $documento) {
            return;
        }

        $documento->delete();

        session()->flash('exito', 'Documento quitado.');
    }

    /** Las unidades puestas en algun renglon, indexadas por id. */
    protected function contenedoresElegidos()
    {
        $ids = collect($this->lineas)
            ->pluck('container_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($ids)) {
            return collect();
        }

        return Container::whereIn('id', $ids)
            ->with(['size:id,name', 'condition:id,name', 'grade:id,name'])
            ->get()
            ->keyBy('id');
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        return view('livewire.invoices.form', [

            'terminosDePago' => $this->terminosDePago(),
            'terminoOtro'    => self::TERMINO_OTRO,


            /*
             | Vendedores y administradores de la empresa activa,
             | mas los que trabajan para las dos.
             */
            'vendedores' => \App\Models\Employee::salespeople()
                ->forCompany(app(\App\Support\CompanyContext::class)->get()?->id)
                ->orderBy('first_name')
                ->get(),


            /*
             | Los conceptos FACTURABLES: los compartidos y los propios de
             | esta empresa.
             |
             | usableIn('invoice') es lo que deja entrar "Cargo por mora",
             | "Almacenaje" y "Recargo por tarjeta", que no se cotizan
             | pero si se cobran.
             */
            'productos' => $this->productosDisponibles,

            /*
             | Las unidades YA elegidas en algun renglon, solo para poder
             | ensenar su numero y su clasificacion en la tarjeta.
             |
             | No se trae el inventario entero: para elegir esta el
             | buscador, que consulta al escribir.
             */
            'contenedoresElegidos' => $this->contenedoresElegidos(),

            'tiposDeUso' => UseType::options(),

            // Las categorias del desplegable de adjuntos.
            'categorias' => DocumentCategory::options(),

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
