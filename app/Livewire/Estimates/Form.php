<?php

namespace App\Livewire\Estimates;

use App\Enums\EstimateStatus;
use App\Enums\UseType;
use App\Models\Container;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Depot;
use App\Models\Estimate;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceCalculator;
use App\Services\PricingResolver;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\AuthorizesAccess;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FORMULARIO DE PRESUPUESTO — crear y editar
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── LO QUE SE CORRIGIÓ EN ESTA VERSIÓN ──
 *
 *   1. "Válido hasta" se recalcula al cambiar la fecha de emisión.
 *   2. El precio se llena solo al elegir un contenedor.
 *   3. El contenedor se elige con un buscador, no con un desplegable.
 *   4. Entrega y recogida traen su importe calculado desde la base.
 *   5. Las direcciones traen FL de verdad y ahora se validan.
 *   6. Los errores dicen QUÉ falta y la pantalla salta al primero.
 *   7. ERROR SILENCIOSO: al editar una línea existente se usaba
 *      $items->whereKey(...)->update(), que no dispara los eventos del
 *      modelo. El 'amount' de esa línea no se recalculaba y la cabecera
 *      quedaba descuadrada. Ahora pasa por el modelo.
 *   8. El desplegable de conceptos ya no muestra mora, almacenaje ni
 *      recargo de tarjeta: esos son de factura.
 *   9. El buscador solo muestra los contenedores de la empresa activa.
 *  10. Términos de pago: lista cerrada + opción "Otro" con campo libre.
 *  11. Los grupos de impresión se arman marcando casillas y dándole a un
 *      botón. Ya no hay que escribir la letra a mano.
 *
 * ── LA PIEZA MÁS IMPORTANTE: LOS GRUPOS DE IMPRESIÓN ──
 *
 * RB-006 y RB-007 se contradicen a primera vista:
 *
 *   RB-007: al cliente se le muestra UN precio consolidado.
 *   RB-006: el 7% se cobra solo sobre el contenedor, nunca sobre el
 *           delivery.
 *
 * Se resuelven así: por dentro hay dos líneas, en el papel se imprime una.
 *
 *     Por dentro   Contenedor 40HC ....... 2,400.00   gravable
 *                  Delivery Homestead ....   350.00   no gravable
 *
 *     El cliente   Contenedor 40HC entregado  2,750.00
 *
 *     El impuesto  7% sobre 2,400 = 168.00, no sobre 2,750
 * ═══════════════════════════════════════════════════════════════════════════
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

    protected string $permisoBase = 'estimates';
    /* =====================================================================
     | LOS TÉRMINOS DE PAGO
     |
     | Lista cerrada para que se pueda reportar por término, y una salida
     | de emergencia para el caso raro.
     |
     | La clave es lo que se GUARDA; el valor es lo que se LEE en pantalla.
     | Se guarda el texto corto en inglés porque es lo que se imprime en el
     | documento.
     * ================================================================== */

    public const TERMINOS_FIJOS = [
        'Due on receipt' => 'Due on receipt — pagadero al recibir',
        'Net 15'         => 'Net 15 — 15 días',
        'Net 30'         => 'Net 30 — 30 días',
        '50% deposit'    => '50% de anticipo, saldo contra entrega',
    ];

        /**
     * Las formas de pago que se le pueden ofrecer al cliente.
     *
     * Salen de las que están impresas al pie de la factura real de
     * FLCHR más las del documento de Square.
     *
     * Solo 'credit_card' dispara el recargo de 3.5% (RB-009).
     */
    public array $formasDePago = [];

    /** El valor del <option> que abre el campo libre. */
    public const TERMINO_OTRO = '__otro__';

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

    public string $buscarCliente = '';

    public string $clienteNombre = '';

    /* =====================================================================
     | LA CABECERA DEL DOCUMENTO
     * ================================================================== */

    public string $issue_date   = '';
    public ?string $valid_until = null;
    public string $use_type     = 'storage';
    public ?int $salesperson_id = null;

    /**
     * Los términos de pago viajan en TRES propiedades y se guarda UNA.
     *
     *   $terms           lo que va a la base y se imprime. La única real.
     *   $termsSeleccion  qué se eligió en el desplegable.
     *   $termsOtro       el texto libre, si se eligió "Otro".
     *
     * ── POR QUÉ NO SE ATA EL SELECT DIRECTO A $terms ──
     *
     * Porque el select tiene un valor —"__otro__"— que NO es un término
     * de pago: es una instrucción para la pantalla. Si el select
     * escribiera directo en $terms, ese "__otro__" acabaría guardado en
     * la base e impreso en un documento que ve el cliente.
     *
     * Con tres propiedades, $terms solo recibe texto imprimible. Es más
     * código, pero la basura no llega nunca a la base.
     */
    public string $terms          = '';
    public string $termsSeleccion = '';
    public string $termsOtro      = '';

    /**
     * Las direcciones, como copia congelada.
     *
     * No son una relación a customer_addresses: son una FOTO del día en
     * que se cotizó. Si el cliente se muda antes de aceptar, el documento
     * sigue mostrando la dirección con la que se le cotizó.
     *
     * El 'FL' nace escrito de verdad, no como texto gris del placeholder.
     */
    public array $bill_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => 'FL', 'zip' => ''];
    public array $ship_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => 'FL', 'zip' => ''];

    /** Si el SHIP TO es distinto del BILL TO (RB-035). */
    public bool $envioDistinto = false;

    /**
     * Si hay que guardar la dirección escrita en la ficha del cliente.
     *
     * ── PARA QUÉ ──
     *
     * Hoy la pantalla de clientes no existe todavía, así que no hay dónde
     * cargarle la dirección a nadie. Y sin dirección guardada, cada
     * presupuesto de ese cliente arranca con los campos en blanco.
     *
     * Con esta casilla, la primera vez que alguien escribe la dirección de
     * un cliente queda guardada en su ficha, y a partir del segundo
     * documento se llena sola.
     *
     * Se enciende sola cuando el cliente NO tiene ninguna dirección
     * guardada, que es justo cuando hace falta. Si ya tiene, arranca
     * apagada: nadie quiere pisarle la dirección buena a un cliente por
     * haber puesto una entrega puntual distinta.
     */
    public bool $guardarDireccionEnCliente = false;

    /** Cuántas direcciones tiene guardadas el cliente elegido. */
    public int $direccionesDelCliente = 0;

    /* =====================================================================
     | LA ENTREGA
     * ================================================================== */

   
    /**
     * Con qué dijo el cliente que va a pagar.
     *
     * Precarga el 3.5% (RB-009) y se copia tal cual a la factura al
     * convertir. Sin esto el presupuesto muestra un recargo que no
     * puede explicar.
     */
    public ?string $expected_payment_method = null;

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
     * ================================================================== */

    public array $lineas = [];

    /** El texto que se imprime por cada grupo. */
    public array $gruposDescripcion = [];

    /**
     * Las líneas marcadas con la casilla, por su posición.
     *
     * Solo existe mientras el usuario está eligiendo qué agrupar. No se
     * guarda en ningún lado.
     */
    public array $seleccionadas = [];

    /** Aviso corto del agrupador ("marque al menos dos líneas"). */
    public ?string $avisoAgrupar = null;

    /* =====================================================================
     | EL BUSCADOR DE UNIDADES
     * ================================================================== */

    /* =====================================================================
     | LOS PASOS (diseño C)
     |
     | ── POR QUÉ ──
     |
     | Todo a la vez eran cinco secciones, unos treinta campos y el panel
     | de totales en la misma pantalla. Cabía, pero nadie sabía por dónde
     | empezar y la mitad de los campos no aplicaban todavía.
     |
     | Son dos pasos acá y uno en la ficha:
     |
     |   1 · QUIÉN Y CUÁNDO   cliente, uso, fechas, términos, direcciones
     |   2 · QUÉ LLEVA        conceptos, grupos, notas, totales
     |   3 · REVISAR          ya no es este formulario: es la ficha, con
     |                        el documento armado (estado Processed)
     |
     | El paso 3 no está acá a propósito. Revisar es leer el documento
     | como lo va a ver el cliente, y eso ya existe: es show.blade.php.
     | Duplicarlo dentro del formulario habría sido mantener dos veces la
     | misma plantilla, y ahí es donde se desincronizan.
     |
     | ── EL ESTADO NO SE PIERDE ──
     |
     | Los pasos son un cambio de PANTALLA, no de datos: todas las
     | propiedades siguen cargadas en el componente. Ir y volver no borra
     | nada, y "Guardar borrador" funciona desde cualquiera de los dos.
     * ================================================================== */

    public int $paso = 1;

    public const PASOS = 2;

    /**
     * ¿Se puede saltar directo al paso 3 (la ficha)?
     *
     * Solo si el presupuesto ya existe y ya pasó por Procesar. En ese
     * caso volver a "Qué lleva" a mirar algo y querer regresar no
     * debería obligar a procesar otra vez: no se cambió nada.
     *
     * Si es nuevo, o si sigue en borrador, el paso 3 todavía no existe:
     * no hay documento que revisar.
     */
    public function getPuedeIrARevisarProperty(): bool
    {
        if (! $this->estimateId) {
            return false;
        }

        return Estimate::whereKey($this->estimateId)
            ->whereIn('status', [
                EstimateStatus::Processed->value,
                EstimateStatus::Sent->value,
                EstimateStatus::Accepted->value,
            ])
            ->exists();
    }

    public ?int $lineaBuscandoContenedor = null;

    /* =====================================================================
     | EL EDITOR DE RENGLONES
     |
     | ── POR QUÉ UN MODAL Y NO LA TABLA ──
     |
     | La tabla editable obligaba a enseñarle las mismas ocho columnas a
     | todos los conceptos. Una renta de contenedor no tiene "Cant." —un
     | renglón es un contenedor, si hay dos se agrega otro renglón— y sin
     | embargo ahí estaba el campo, pidiendo un número que no significaba
     | nada. Al mismo tiempo, una reparación necesita contar qué se le
     | hizo al contenedor y no tenía dónde.
     |
     | Cada concepto pide lo suyo. El modal enseña solo eso.
     |
     | ── EL BORRADOR ──
     |
     | Lo que se edita en el modal es una COPIA. Solo al darle a guardar
     | se escribe sobre $lineas. Así "Cancelar" cancela de verdad: sin la
     | copia, cada tecla ya habría modificado el renglón y volver atrás
     | exigiría recordar el estado anterior.
     * ================================================================== */

    public ?int $lineaEditando = null;

    public array $borrador = [];

    /** Si el renglón se acaba de crear: cancelar lo borra en vez de dejarlo vacío. */
    public bool $borradorEsNuevo = false;

    public string $buscarContenedor = '';

    /* =====================================================================
     | ARRANQUE
     * ================================================================== */

    public function mount(?Estimate $estimate = null)
    {
        $empresa = app(CompanyContext::class)->get();
        $calc    = app(InvoiceCalculator::class);

        /* -----------------------------------------------------------------
         | CASO A · EDITAR UNO QUE YA EXISTE
         * -------------------------------------------------------------- */
        if ($estimate && $estimate->exists) {

            $this->exigirPermiso('update');

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
         * -------------------------------------------------------------- */
        $this->exigirPermiso('create');

        $this->issue_date     = now()->toDateString();
        $this->salesperson_id = auth()->id();

        if ($empresa) {
            $this->terms    = $calc->defaultTerms($empresa);
            $this->tax_rate = $calc->defaultTaxRate($empresa);

            // La tarifa por milla, para que la entrega se pueda calcular
            // desde el primer momento (RB-031). Editable.
            $this->rate_per_mile = app(PricingResolver::class)->ratePerMile($empresa);

            $this->recalcularValidez();
        }

        $this->formasDePago = [
            'cash'        => __('payments.cash'),
            'check'       => __('payments.check'),
            'zelle'       => __('payments.zelle'),
            'ach'         => __('payments.ach'),
            'wire'        => __('payments.wire'),
            'credit_card' => __('payments.credit_card'),
        ];

        $this->repartirTerminos();

        // Un renglón en blanco esperando, pero SIN abrir el modal encima:
        // lo primero que hay que elegir es el cliente, no el concepto.
        $this->lineas = [$this->lineaVacia()];

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
        $this->use_type       = $estimate->use_type?->value ?? 'storage';
        $this->salesperson_id = $estimate->salesperson_id;

        $this->terms = (string) $estimate->terms;
        $this->repartirTerminos();

        $this->bill_to = array_merge($this->bill_to, $estimate->bill_to ?: []);
        $this->ship_to = array_merge($this->ship_to, $estimate->ship_to ?: []);

        $this->envioDistinto = ! empty($estimate->ship_to);

        // Al reabrir un presupuesto no se ofrece guardar la dirección:
        // la que está en el documento es una copia congelada de entonces,
        // y no tiene por qué ser la buena de hoy.
        $this->direccionesDelCliente     = $estimate->customer?->addresses()->count() ?? 0;
        $this->guardarDireccionEnCliente = false;

        $this->expected_payment_method = $estimate->expected_payment_method;

        $this->discount_amount = (float) $estimate->discount_amount;
        $this->tax_rate        = (float) $estimate->tax_rate;
        $this->tax_exempt      = (bool) $estimate->tax_exempt;

        $this->credit_card_fee_percent = (float) $estimate->credit_card_fee_percent;
        $this->pagaConTarjeta          = $this->credit_card_fee_percent > 0;

        $this->notes        = $estimate->notes;
        $this->footer_terms = $estimate->footer_terms;

         /*
         | Al abrir uno ya guardado se entra por el paso 2.
         |
         | Editar un presupuesto existente casi nunca es cambiar el
         | cliente: es tocar un precio, agregar un concepto, corregir las
         | millas. Obligar a pasar por el paso 1 sería un clic de peaje
         | en cada corrección.
         */
        $this->paso = 2;

        $this->lineas = $estimate->items->map(fn ($linea) => [
            'id'           => $linea->id,
            'product_id'   => $linea->product_id,
            'container_id' => $linea->container_id,
            'description'  => $linea->description,
            'quantity'     => (float) $linea->quantity,
            'unit_price'   => (float) $linea->unit_price,
            'taxable'      => (bool) $linea->taxable,
            'grupo'        => (string) ($linea->bundle_key ?? ''),

            'delivery_zip'  => $linea->delivery_zip,
            'miles'         => $linea->miles !== null ? (float) $linea->miles : null,
            'rate_per_mile' => $linea->rate_per_mile !== null ? (float) $linea->rate_per_mile : null,

            'rental_months' => $linea->rental_months,
            'work_details'  => $linea->work_details,

            // Lo guardado se respeta siempre: si el texto llegó hasta la
            // base, alguien lo dio por bueno.
            'desc_manual'   => true,
        ])->all();

        foreach ($estimate->items as $linea) {
            if ($linea->bundle_key) {
                $this->gruposDescripcion[$linea->bundle_key] = $linea->bundle_description ?? '';
            }
        }

        if (empty($this->lineas)) {
            $this->lineas = [$this->lineaVacia()];
        }
    }

    /* =====================================================================
     | LOS TÉRMINOS DE PAGO
     * ================================================================== */

    /**
     * De $terms a las dos propiedades de la pantalla.
     *
     * Si lo guardado coincide con una de las opciones fijas, el select se
     * pone en esa. Si no, es porque alguien escribió algo suyo: el select
     * se pone en "Otro" y el texto vuelve al campo libre.
     *
     * Esto es lo que hace que al reabrir un presupuesto viejo con un
     * término raro, la pantalla lo muestre bien en vez de perderlo.
     */
    protected function repartirTerminos(): void
    {
        $guardado = trim($this->terms);

        if ($guardado !== '' && ! array_key_exists($guardado, self::TERMINOS_FIJOS)) {
            $this->termsSeleccion = self::TERMINO_OTRO;
            $this->termsOtro      = $guardado;

            return;
        }

        $this->termsSeleccion = $guardado;
        $this->termsOtro      = '';
    }

    /** De la pantalla a $terms, que es lo único que se guarda. */
    protected function armarTerminos(): void
    {
        $this->terms = $this->termsSeleccion === self::TERMINO_OTRO
            ? trim($this->termsOtro)
            : $this->termsSeleccion;
    }

    /* =====================================================================
     | LA VIGENCIA
     * ================================================================== */

    /**
     * Recalcula "válido hasta" a partir de la fecha de emisión.
     *
     * Antes esto se calculaba UNA sola vez, en mount(). Si el usuario
     * cambiaba la fecha de emisión —cosa normal: se cotizó el viernes y
     * se carga el lunes— la vigencia se quedaba anclada al día en que se
     * abrió la pantalla, y el documento salía venciendo antes de
     * emitirse.
     *
     * Los días salen de Configuración (documents.estimate_valid_days).
     */
    public function recalcularValidez(): void
    {
        $empresa = app(CompanyContext::class)->get();

        if (! $empresa || blank($this->issue_date)) {
            return;
        }

        try {
            $dias = app(InvoiceCalculator::class)->defaultEstimateValidDays($empresa);

            $this->valid_until = Carbon::parse($this->issue_date)
                ->addDays($dias)
                ->toDateString();

            $this->resetValidation('valid_until');
        } catch (\Throwable $e) {
            // Fecha a medio escribir. Se ignora: cuando termine de
            // teclearla, este método vuelve a correr.
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
         | 1 · LAS DIRECCIONES
         |
         | ── QUÉ SE COPIA ──
         |
         | TODO: etiqueta, línea 1, línea 2, ciudad, estado y ZIP. No es
         | un resumen, es la ficha entera.
         |
         | ── CUÁL SE ELIGE ──
         |
         | La marcada como fiscal. Si el cliente no tiene ninguna marcada
         | —porque se cargó de apuro— se toma la primera que tenga, que es
         | mejor que dejar el formulario vacío.
         |
         | ── EL INTERRUPTOR DE ENTREGA ──
         |
         | Si además tiene una dirección de envío distinta, se copia al
         | SHIP TO y el interruptor "la entrega va a otra dirección" se
         | enciende solo. Es el caso de la constructora: factura a su
         | oficina de Doral y recibe el contenedor en la obra de
         | Homestead.
         * -------------------------------------------------------------- */
        $this->direccionesDelCliente = $cliente->addresses->count();

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

        // Si la dirección del cliente venía sin estado, se pone FL.
        $this->bill_to['state'] = $this->bill_to['state'] ?: 'FL';
        $this->ship_to['state'] = $this->ship_to['state'] ?: 'FL';

        /*
         | La casilla de guardar se enciende sola solo si el cliente no
         | tiene ninguna dirección. Es cuando de verdad hace falta.
         |
         | Si ya tiene, arranca apagada: nadie quiere pisarle la dirección
         | buena a un cliente por haber puesto una entrega puntual
         | distinta.
         */
        $this->guardarDireccionEnCliente = $this->direccionesDelCliente === 0;

        /* -----------------------------------------------------------------
         | 2 · LA EXENCIÓN DE IMPUESTO (RB-014)
         * -------------------------------------------------------------- */
        $this->tax_exempt = (bool) $cliente->tax_exempt;

        /* -----------------------------------------------------------------
         | 3 · ¿PUEDE PAGAR CON TARJETA? (RB-012, RB-013)
         * -------------------------------------------------------------- */
        if (! $cliente->allow_credit_card) {
            $this->pagaConTarjeta          = false;
            $this->credit_card_fee_percent = 0;
        }

        $this->resetValidation();
    }

    public function quitarCliente(): void
    {
        $this->customer_id   = null;
        $this->clienteNombre = '';
        $this->bill_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => 'FL', 'zip' => ''];
        $this->ship_to = ['label' => '', 'line1' => '', 'line2' => '', 'city' => '', 'state' => 'FL', 'zip' => ''];
        $this->envioDistinto = false;

        $this->direccionesDelCliente     = 0;
        $this->guardarDireccionEnCliente = false;
    }

    /* =====================================================================
     | LAS LÍNEAS
     * ================================================================== */


    /* =====================================================================
     | EL EDITOR DE RENGLONES
     * ================================================================== */

    /**
     * Abre el modal sobre un renglón nuevo.
     *
     * El renglón se crea YA y el modal trabaja sobre él. Si se cancela,
     * se borra. Es más simple que sostener un renglón "en el aire" que
     * todavía no existe en $lineas: el buscador de unidades necesita un
     * índice real al que escribirle.
     */
    public function agregarLinea(): void
    {
        $this->lineas[] = $this->lineaVacia();

        $this->abrirLinea(array_key_last($this->lineas), esNuevo: true);
    }

    /** Abre el modal sobre un renglón existente. */
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
     * Un renglón recién creado se va con el modal. Dejarlo vacío en la
     * lista sería dejar basura que después hay que borrar a mano.
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

        $this->cerrarBuscadorContenedor();
        $this->resetValidation();
    }

    /**
     * Vuelca el borrador sobre el renglón.
     *
     * Se valida SOLO este renglón. Validar todo el presupuesto acá
     * sacaría errores de la dirección o del cliente mientras el usuario
     * está en un modal que no habla de eso.
     */
    public function guardarLinea(): void
    {
        if ($this->lineaEditando === null) {
            return;
        }

        $producto = $this->productoDelBorrador();

        $reglas = [
            'borrador.description' => ['required', 'string', 'max:500'],
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

        $this->validate($reglas, [], [
            'borrador.description'    => 'descripción',
            'borrador.unit_price'     => 'precio',
            'borrador.quantity'       => 'cantidad',
            'borrador.container_id'   => 'unidad',
            'borrador.rental_months'  => 'plazo',
            'borrador.delivery_zip'   => 'ZIP de destino',
            'borrador.miles'          => 'millas',
            'borrador.rate_per_mile'  => 'tarifa por milla',
            'borrador.work_details'   => 'trabajo a realizar',
        ]);

        /*
         | Un contenedor no viene en cantidades. Un renglón es una
         | unidad; si hay dos contenedores, hay dos renglones. Se fuerza
         | acá y no solo en la pantalla porque el dato puede venir de una
         | duplicación o de una conversión.
         */
        if ($producto?->type->requiresContainer()) {
            $this->borrador['quantity'] = 1;
        }

        $this->lineas[$this->lineaEditando] = $this->borrador;

        $this->cerrarEditor();
    }

    /**
     * El catálogo cotizable, una sola consulta por petición.
     *
     * Antes se armaba dentro de render(). Los métodos del editor también
     * lo necesitan y no pueden esperar a que se pinte la pantalla.
     */
    public function getProductosDisponiblesProperty()
    {
        static $cache = null;

        return $cache ??= Product::query()
            ->active()
            ->forCompany(app(CompanyContext::class)->get()?->id)
            ->usableIn('estimate')
            ->get();
    }

    /** El producto del renglón que se está editando. */
    public function productoDelBorrador(): ?Product
    {
        $id = $this->borrador['product_id'] ?? null;

        return $id ? $this->productosDisponibles->firstWhere('id', $id) : null;
    }

    /** El importe del borrador, para enseñarlo en vivo dentro del modal. */
    public function getImporteBorradorProperty(): float
    {
        return round(
            (float) ($this->borrador['quantity'] ?? 1) * (float) ($this->borrador['unit_price'] ?? 0),
            2,
        );
    }


    /**
     * La forma de una línea, en un solo lugar.
     *
     * Existe como método aparte porque la estructura se arma en tres
     * sitios (agregarLinea, quitarLinea cuando queda vacío, y mount).
     * Con el arreglo escrito tres veces, agregar un campo significaba
     * acordarse de los tres. Ya pasó una vez.
     *
     * ── LOS TRES CAMPOS DE ENTREGA ──
     *
     * Solo se llenan en líneas cuyo producto es DELIVERY. En el resto
     * quedan en null y no se guardan.
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

            // RB-049 · el cálculo del transporte vive acá, no en la cabecera
            'delivery_zip'  => null,
            'miles'         => null,
            'rate_per_mile' => null,

            // Plazo cotizado. Solo en líneas de renta.
            'rental_months' => null,

            // Qué se le hizo al contenedor. Solo en reparación.
            'work_details'  => null,

            /*
             | ¿La descripción la escribió una persona?
             |
             | Mientras sea false, cambiar el concepto reescribe el texto.
             | En cuanto alguien lo edita a mano pasa a true y no se toca
             | nunca más.
             |
             | Sin esta bandera pasaba lo del EST-0003: se elegía "Renta
             | de contenedor", el sistema escribía ese nombre, se cambiaba
             | el concepto a "Entrega / Delivery" y la descripción se
             | quedaba diciendo "Renta de contenedor". El presupuesto salió
             | impreso al cliente con un delivery de $154 llamado renta.
             |
             | El código anterior solo pisaba la descripción si estaba
             | VACÍA, y no podía distinguir "lo escribió el sistema" de
             | "lo escribió una persona". Ahora sí.
             */
            'desc_manual'   => false,
        ];
    }

    /**
     * Quita una línea.
     *
     * array_values() vuelve a numerar el arreglo desde cero. Y por eso
     * hay que vaciar $seleccionadas: guarda POSICIONES, y después de
     * renumerar la posición 3 ya no es la misma línea. Sin esto, borrar
     * una fila del medio agrupa las que no eran.
     */
    public function quitarLinea(int $indice): void
    {
        unset($this->lineas[$indice]);

        $this->lineas = array_values($this->lineas);

        $this->seleccionadas = [];
        $this->avisoAgrupar  = null;

        $this->limpiarGruposHuerfanos();

        // Se puede llamar desde el modal ("Eliminar"). Si queda abierto
        // apuntando a un índice que ya se renumeró, edita el renglón
        // equivocado.
        $this->cerrarEditor();
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
         | CAMBIÓ LA FECHA DE EMISIÓN
         * -------------------------------------------------------------- */
        if ($campo === 'issue_date') {
            $this->recalcularValidez();
        }

        /* -----------------------------------------------------------------
         | CAMBIARON LOS TÉRMINOS DE PAGO
         * -------------------------------------------------------------- */
        if ($campo === 'termsSeleccion' || $campo === 'termsOtro') {
            $this->armarTerminos();

            if ($campo === 'termsSeleccion') {
                $this->resetValidation('termsOtro');
            }
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL PRODUCTO DE UNA LÍNEA
         * -------------------------------------------------------------- */
        if ($campo === 'borrador.product_id') {
            $this->aplicarProducto();
        }

        /* -----------------------------------------------------------------
         | ALGUIEN ESCRIBIÓ LA DESCRIPCIÓN A MANO
         |
         | A partir de acá el texto es suyo y cambiar el concepto ya no lo
         | pisa. Si la deja vacía, vuelve a ser automática.
         * -------------------------------------------------------------- */
        if ($campo === 'borrador.description') {
            $this->borrador['desc_manual'] = filled($this->borrador['description'] ?? null);
        }

        /* -----------------------------------------------------------------
         |  Es el cambio que hace posible cotizar tres contenedores a
         | tres destinos (RB-049).
         * -------------------------------------------------------------- */
        if ($campo === 'borrador.miles' || $campo === 'borrador.rate_per_mile') {
            $this->recalcularLineaDeTransporte();
        }

         /* -----------------------------------------------------------------
         | CAMBIÓ LA FORMA DE PAGO PREVISTA
         |
         | Elegir "tarjeta de crédito" enciende el interruptor del
         | recargo. Elegir otra cosa lo apaga.
         |
         | Es el mismo dato dicho de dos maneras, y antes había que
         | acordarse de marcar las dos. Alguien iba a olvidarse.
         * -------------------------------------------------------------- */
        if ($campo === 'expected_payment_method') {
            $this->pagaConTarjeta = $this->expected_payment_method === 'credit_card';

            $empresa = app(CompanyContext::class)->get();

            $this->credit_card_fee_percent = $this->pagaConTarjeta && $empresa
                ? app(InvoiceCalculator::class)->defaultCreditCardFeePercent($empresa)
                : 0;
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL INTERRUPTOR DE LA TARJETA
         * -------------------------------------------------------------- */
        if ($campo === 'pagaConTarjeta') {
            $empresa = app(CompanyContext::class)->get();

            $this->credit_card_fee_percent = $this->pagaConTarjeta && $empresa
                ? app(InvoiceCalculator::class)->defaultCreditCardFeePercent($empresa)
                : 0;
        }

        /* -----------------------------------------------------------------
         | CAMBIÓ EL TIPO DE USO (RB-006, RB-016, RB-017)
         * -------------------------------------------------------------- */
        if ($campo === 'use_type' && $this->use_type === UseType::Export->value) {
            foreach ($this->lineas as $i => $linea) {
                $this->lineas[$i]['taxable'] = false;
            }
        }
    }

    /** Precarga una línea con los datos del concepto elegido. */
    protected function aplicarProducto(): void
    {
        $productoId = $this->borrador['product_id'] ?? null;

        if (! $productoId) {
            return;
        }

        $producto = Product::find($productoId);

        if (! $producto) {
            return;
        }

        $defaults = $producto->lineDefaults();

        /* -----------------------------------------------------------------
         | LA DESCRIPCIÓN
         |
         | Se reescribe salvo que la haya tecleado una persona. Ver la
         | explicación de 'desc_manual' en lineaVacia().
         * -------------------------------------------------------------- */
        if (empty($this->borrador['desc_manual'])) {
            /*
             | Si ya hay una unidad elegida, el texto se arma con ella:
             | "Venta de contenedor · 40 ft High Cube · Usado (MSCU...)".
             | Cambiar de Renta a Venta con el contenedor ya puesto tiene
             | que reescribir el prefijo, no dejar el de antes.
             */
            $unidad = ! empty($this->borrador['container_id'])
                ? Container::with(['size:id,name', 'condition:id,name', 'grade:id,name'])
                    ->find($this->borrador['container_id'])
                : null;

            $this->borrador['description'] = $producto->autoDescription($unidad);
        }

        if (empty($this->borrador['unit_price'])) {
            $this->borrador['unit_price'] = $defaults['unit_price'];
        }

        // En exportación nada paga impuesto, sin importar el concepto.
        $this->borrador['taxable'] = $this->use_type === UseType::Export->value
            ? false
            : $defaults['taxable'];

        // Si el concepto no es un contenedor, se limpia la unidad que
        // pudiera haber quedado seleccionada de antes.
        if (! $producto->type->requiresContainer()) {
            $this->borrador['container_id'] = null;
        }

        /* -----------------------------------------------------------------
         | EL PLAZO DE LA RENTA
         |
         | Se precarga con el plazo habitual de la empresa y queda
         | editable. En cualquier concepto que no sea renta se limpia: un
         | delivery no dura meses.
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

        // Los campos de transporte tampoco tienen sentido fuera de una
        // entrega. Quedaban con el ZIP y las millas del concepto anterior.
        if (! $producto->isDelivery()) {
            $this->borrador['delivery_zip']  = null;
            $this->borrador['miles']         = null;
            $this->borrador['rate_per_mile'] = null;
        }

        /* -----------------------------------------------------------------
         | TRANSPORTE: el importe se calcula, no se teclea
         * -------------------------------------------------------------- */
        if ($producto->isDelivery() || $producto->isPickup()) {
            $this->aplicarPrecioDeTransporte($producto);
        }

        /* -----------------------------------------------------------------
         | CONTENEDOR: si ya había uno elegido, se refresca el precio
         |
         | Pasa al cambiar de "Venta" a "Renta" con la misma unidad: son
         | dos números distintos de la misma ficha.
         * -------------------------------------------------------------- */
        if ($producto->type->requiresContainer() && $this->borrador['container_id']) {
            $this->aplicarPrecioDeContenedor(
                (int) $this->borrador['container_id'],
                $producto,
                forzar: true,
            );
        }
    }

    /* =====================================================================
     | EL BUSCADOR DE UNIDADES
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
     * Las unidades que coinciden con lo que se está escribiendo.
     *
     * Solo las de la empresa activa, y solo las que de verdad se pueden
     * vender: en yarda, sin venta ni renta encima (RB-019). Las compradas
     * pero todavía en el depósito del proveedor NO salen. No se puede
     * vender lo que no se ha retirado.
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
     * Las unidades ya elegidas en OTRAS líneas.
     *
     * La pantalla las muestra deshabilitadas. Cotizar dos veces el mismo
     * contenedor en el mismo presupuesto es un error de dedo que después
     * se convierte en un contenedor vendido a dos clientes.
     */
    public function getContenedoresYaUsadosProperty(): array
    {
        $usados = [];

        foreach ($this->lineas as $i => $linea) {
            // El renglón que se está editando se salta: su propia unidad
            // no puede salir deshabilitada en su propio buscador.
            if ($i === $this->lineaEditando) {
                continue;
            }

            if (! empty($linea['container_id'])) {
                $usados[(int) $linea['container_id']] = $i + 1;   // número de línea
            }
        }

        return $usados;
    }

    public function seleccionarContenedor(int $contenedorId): void
    {
        if ($this->lineaEditando === null) {
            return;
        }

        $this->borrador['container_id'] = $contenedorId;

        $this->aplicarPrecioDeContenedor($contenedorId, $this->productoDelBorrador());

        $this->cerrarBuscadorContenedor();
    }

    public function quitarContenedor(): void
    {
        $this->borrador['container_id'] = null;
    }

    /**
     * Copia a la línea el precio que tiene cargado esa unidad en el
     * inventario.
     *
     *   Venta  ->  containers.list_price
     *   Renta  ->  containers.monthly_rate   (RB-022: ciclos mensuales)
     *
     * ── POR QUÉ NO PISA UN PRECIO YA ESCRITO ──
     *
     * Si el vendedor ya negoció y tecleó 2,250 y después corrige la
     * unidad elegida, sería muy molesto que el sistema le devolviera los
     * 2,400 de lista. Solo escribe si el campo estaba en cero o vacío.
     *
     * La excepción es $forzar, que se usa al cambiar de venta a renta:
     * ahí el número anterior corresponde a otra cosa y sí hay que
     * cambiarlo.
     *
     * ── SIGUE SIENDO EDITABLE (RB-029) ──
     *
     * El precio varía por temporada y por volumen. Esto solo ahorra
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

        $esRenta = $producto?->isRental() ?? false;

        $precio = $contenedor->suggestedPrice($esRenta);

        // null = esa unidad no tiene precio cargado para eso. Se deja el
        // campo como está para que el vendedor escriba, en vez de meter
        // un cero que se puede guardar por distracción.
        if ($precio !== null && ($forzar || empty($this->borrador['unit_price']))) {
            $this->borrador['unit_price'] = $precio;
        }

        /*
         | LA DESCRIPCIÓN
         |
         | El texto lo arma el concepto, no este archivo: ver
         | Product::autoDescription(). Acá antes el prefijo estaba
         | escrito a mano y solo para renta, y por eso las ventas salían
         | impresas sin decir que eran ventas.
         |
         | Se respeta lo que haya tecleado una persona (desc_manual).
         */
        if (empty($this->borrador['desc_manual'])) {
            $this->borrador['description'] = $producto
                ? $producto->autoDescription($contenedor)
                : $contenedor->lineDescription();
        }

        // El contenedor sí paga el 7% (RB-006), salvo en exportación.
        $this->borrador['taxable'] = $this->use_type !== UseType::Export->value;

        $this->resetValidation('borrador.description');
        $this->resetValidation('borrador.unit_price');
    }

    /* =====================================================================
     | LOS PRECIOS DEL TRANSPORTE
     * ================================================================== */

     /**
     * Calcula el importe de una línea de ENTREGA.
     *
     * ── QUÉ SE FUE DE ACÁ ──
     *
     * Antes este método tenía dos ramas: una para delivery y otra para
     * pickup. La del pickup se eliminó porque el pickup no se le cotiza
     * al cliente: es el viaje depósito -> yarda y lo paga FLCHR
     * (RB-031). Nunca debió estar en un presupuesto.
     *
     * PricingResolver::pickupFee() sigue existiendo y sigue haciendo
     * falta, pero para los viajes y para el invoice semanal de RS a
     * FLCHR, no para acá.
     *
     * ── DE DÓNDE SALE LA TARIFA ──
     *
     * De la ficha del transportista si la tiene, y si no del ajuste
     * operations.default_rate_per_mile. Ni un número escrito en este
     * archivo.
     *
     * Todo lo que escribe este método queda editable en la línea: es
     * una sugerencia para no teclear el caso normal, no un candado.
     */
    protected function aplicarPrecioDeTransporte(Product $producto): void
    {
        if (! $producto->isDelivery()) {
            return;
        }

        $empresa  = app(CompanyContext::class)->get();
        $resolver = app(PricingResolver::class);

        // La tarifa se precarga una sola vez por línea. Si el usuario la
        // pisó a mano, se respeta.
        if (($this->borrador['rate_per_mile'] ?? null) === null) {
            $this->borrador['rate_per_mile'] = $resolver->ratePerMile($empresa);
        }

        $millas = (float) ($this->borrador['miles'] ?? 0);

        // Sin millas no hay nada que calcular todavía. En cuanto las
        // escriba, updated() vuelve a pasar por acá.
        if ($millas <= 0) {
            return;
        }

        $this->borrador['unit_price'] = round(
            $millas * (float) $this->borrador['rate_per_mile'],
            2,
        );

        $this->borrador['quantity'] = 1;

        // RB-005: el transporte NUNCA lleva sales tax.
        $this->borrador['taxable'] = false;

        $this->resetValidation('borrador.unit_price');
    }

    /**
     * Recalcula UNA línea de transporte.
     *
     * Corre cuando el usuario cambia las millas o la tarifa de esa
     * línea. Antes existía recalcularLineasDeTransporte(), que las
     * recorría todas porque los datos eran del documento entero. Ahora
     * cada línea es independiente y tocar una no puede alterar otra.
     */
    protected function recalcularLineaDeTransporte(): void
    {
        $productoId = $this->borrador['product_id'] ?? null;

        if (! $productoId) {
            return;
        }

        $producto = Product::find($productoId);

        if ($producto && $producto->isDelivery()) {
            $this->aplicarPrecioDeTransporte($producto);
        }
    }



    /* =====================================================================
     | LOS GRUPOS DE IMPRESIÓN — ahora con casillas
     * ================================================================== */

    /**
     * Junta en un solo renglón impreso las líneas marcadas.
     *
     * ── POR QUÉ SE CAMBIÓ LA FORMA DE HACERLO ──
     *
     * Antes había una columna donde el usuario escribía una letra a mano.
     * Funcionaba, pero dependía de que escribiera EXACTAMENTE la misma
     * letra en las dos líneas: una "a" minúscula en una y una "A" en la
     * otra, y el agrupamiento no ocurría. Sin ningún aviso.
     *
     * Ahora se marcan las casillas y el sistema pone la etiqueta. La
     * letra sigue existiendo por dentro —es la columna bundle_key de la
     * base, y ahí no cambió nada— pero el usuario ya no la escribe.
     *
     * ── EL TEXTO SE PROPONE SOLO ──
     *
     * Se toma la descripción de la línea más cara del grupo, que casi
     * siempre es el contenedor. Es lo que el vendedor iba a escribir de
     * todos modos, y queda editable.
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
            $this->avisoAgrupar = 'Marque al menos dos líneas. Agrupar una sola no cambia nada al imprimir.';

            return;
        }

        $letra = $this->siguienteLetraDeGrupo();

        foreach ($indices as $i) {
            $this->lineas[$i]['grupo'] = $letra;
        }

        $masCara = $indices
            ->sortByDesc(fn ($i) => $this->importeLinea($i))
            ->first();

        $this->gruposDescripcion[$letra] = trim((string) ($this->lineas[$masCara]['description'] ?? ''));

        $this->seleccionadas = [];

        $this->limpiarGruposHuerfanos();
    }

    /** Deshace un grupo: las líneas vuelven a imprimirse por separado. */
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

    /** Saca una sola línea de su grupo, sin deshacer el resto. */
    public function sacarDelGrupo(int $indice): void
    {
        if (isset($this->lineas[$indice])) {
            $this->lineas[$indice]['grupo'] = '';
        }

        $this->limpiarGruposHuerfanos();
    }

    /**
     * La primera letra libre: A, B, C…
     *
     * Se reutilizan las letras que quedaron libres al deshacer un grupo,
     * para que no salgan documentos con "Grupo A" y "Grupo F" y nada en
     * medio.
     */
    protected function siguienteLetraDeGrupo(): string
    {
        $usadas = collect($this->lineas)
            ->pluck('grupo')
            ->map(fn ($g) => trim((string) $g))
            ->filter()
            ->unique()
            ->all();

        foreach (range('A', 'Z') as $letra) {
            if (! in_array($letra, $usadas, true)) {
                return $letra;
            }
        }

        // Veintiséis grupos en un presupuesto no va a pasar, pero si
        // pasara es mejor un nombre feo que un choque de etiquetas.
        return 'G'.(count($usadas) + 1);
    }

    /**
     * Limpia los grupos que se quedaron con una sola línea o con ninguna.
     *
     * Pasa al borrar una línea de un grupo de dos: la que queda tendría
     * una etiqueta que no agrupa nada, y un texto de grupo esperando a
     * nadie.
     */
    protected function limpiarGruposHuerfanos(): void
    {
        $conVarias = $this->gruposConVariasLineas();

        // Una etiqueta que quedó sola no agrupa: se le quita a la línea.
        foreach ($this->lineas as $i => $linea) {
            $grupo = trim((string) ($linea['grupo'] ?? ''));

            if ($grupo !== '' && ! in_array($grupo, $conVarias, true)) {
                $this->lineas[$i]['grupo'] = '';
            }
        }

        $this->gruposDescripcion = array_intersect_key(
            $this->gruposDescripcion,
            array_flip($conVarias),
        );

        foreach ($conVarias as $grupo) {
            $this->gruposDescripcion[$grupo] ??= '';
        }
    }

    /**
     * Las etiquetas de grupo que tienen DOS O MÁS líneas.
     *
     * Solo esas se imprimen agrupadas.
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

        $letras = array_keys(array_filter($conteo, fn ($n) => $n > 1));

        sort($letras);

        return $letras;
    }

    /**
     * Cuánto suma cada grupo y qué líneas lo forman.
     *
     * Es el número que va a ver el cliente en ese renglón. Mostrarlo en
     * pantalla evita la pregunta de siempre: "¿y esto cuánto le sale al
     * cliente?".
     */
    public function getResumenGruposProperty(): array
    {
        $resumen = [];

        foreach ($this->gruposConVariasLineas() as $letra) {

            $total  = 0.0;
            $lineas = [];

            foreach ($this->lineas as $i => $linea) {
                if (trim((string) ($linea['grupo'] ?? '')) === $letra) {
                    $total   += $this->importeLinea($i);
                    $lineas[] = $i + 1;
                }
            }

            $resumen[$letra] = [
                'total'  => round($total, 2),
                'lineas' => $lineas,
            ];
        }

        return $resumen;
    }

    /* =====================================================================
     | LOS TOTALES EN VIVO
     * ================================================================== */

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
     * ¿Este renglón está vacío?
     *
     * Vacío es sin concepto Y sin descripción Y sin precio. Basta con
     * que tenga una de las tres para que cuente como intento de escribir
     * algo, y entonces sí hay que validarlo.
     */
    protected function lineaEstaVacia(array $linea): bool
    {
        return blank($linea['product_id'] ?? null)
            && blank($linea['description'] ?? null)
            && (float) ($linea['unit_price'] ?? 0) === 0.0
            && blank($linea['container_id'] ?? null);
    }

    /**
     * Descarta los renglones en blanco antes de validar.
     *
     * ══════════════════════════════════════════════════════════════════
     * POR QUÉ
     * ══════════════════════════════════════════════════════════════════
     *
     * El formulario nace con un renglón en blanco esperando. Si el
     * usuario le da a "Agregar línea" y trabaja en el nuevo, el primero
     * se queda vacío — y Procesar moría pidiendo la descripción de un
     * renglón que nadie quiso escribir.
     *
     * El sistema fue el que puso ese renglón ahí. No tiene sentido que
     * después exija que se llene.
     *
     * Se descartan solo si queda al menos uno con contenido: un
     * presupuesto sin ningún concepto sí es un error, y ahí el mensaje
     * de "agrega al menos un concepto" es el correcto.
     * ══════════════════════════════════════════════════════════════════
     */
    protected function descartarLineasVacias(): void
    {
        $conContenido = collect($this->lineas)
            ->reject(fn (array $l) => $this->lineaEstaVacia($l));

        if ($conContenido->isEmpty()) {
            return;
        }

        if ($conContenido->count() === count($this->lineas)) {
            return;
        }

        // Los grupos se reindexan solos: 'grupo' viaja dentro de cada
        // renglón, así que reordenar el array no los rompe.
        $this->lineas = $conContenido->values()->all();

        $this->seleccionadas = [];

        $this->limpiarGruposHuerfanos();
    }

    /* =====================================================================
     | NAVEGACIÓN ENTRE PASOS
     * ================================================================== */

    /**
     * Los campos que se validan en cada paso.
     *
     * Sale de rules() y se queda con las claves que pertenecen a este
     * paso. Se escribe una sola vez y no dos: si mañana se agrega una
     * regla, entra sola en el paso que le toca.
     */
    protected function reglasDelPaso(int $paso): array
    {
        $todas = $this->rules();

        $delUno = [
            'customer_id', 'use_type', 'issue_date', 'valid_until', 'terms',
            'termsSeleccion', 'termsOtro', 'expected_payment_method',
            'salesperson_id',
        ];

        return collect($todas)
            ->filter(function ($reglas, $campo) use ($paso, $delUno) {
                $esDelUno = in_array($campo, $delUno, true)
                    || str_starts_with($campo, 'bill_to.')
                    || str_starts_with($campo, 'ship_to.');

                return $paso === 1 ? $esDelUno : ! $esDelUno;
            })
            ->all();
    }

    /**
     * Avanza al paso siguiente, validando lo del actual.
     *
     * Validar por paso y no todo de golpe es la mitad del sentido de
     * esto: en la pantalla anterior se pintaban en rojo campos de la
     * sección de abajo, que el usuario ni había visto todavía.
     */
    public function siguientePaso(): void
    {
        $this->armarTerminos();
        $this->descartarLineasVacias();

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

    /**
     * Salta a un paso desde la barra de arriba.
     *
     * Hacia atrás es libre. Hacia adelante valida lo que queda en medio,
     * porque el paso 2 sin cliente ni direcciones no significa nada.
     */
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
     * ¿Qué se decidió en el paso 1?
     *
     * Es la tira de contexto que se queda arriba en el paso 2, para no
     * tener que volver solo a comprobar un dato.
     */
    public function getResumenPaso1Property(): array
    {
        $cliente = $this->customer_id
            ? Customer::find($this->customer_id)
            : null;

        $entrega = $this->envioDistinto ? $this->ship_to : $this->bill_to;

        $ciudad = collect([$entrega['city'] ?? null, $entrega['state'] ?? null])
            ->filter()->implode(', ');

        return [
            'cliente'  => $cliente?->name,
            'uso'      => UseType::tryFrom((string) $this->use_type)?->label(),
            'emision'  => $this->issue_date ? Carbon::parse($this->issue_date)->format('d/m/Y') : null,
            'validez'  => $this->valid_until ? Carbon::parse($this->valid_until)->format('d/m/Y') : null,
            'entrega'  => trim($ciudad.' '.($entrega['zip'] ?? '')) ?: null,
            'distinta' => $this->envioDistinto,
        ];
    }

    protected function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'issue_date'  => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'use_type'    => ['required', Rule::in(UseType::values())],

            /* -------------------------------------------------------------
             | LOS TÉRMINOS DE PAGO
             |
             | 'terms' es lo que se guarda, y la columna es varchar(50).
             | 'termsOtro' solo se exige si el select está en "Otro".
             * ---------------------------------------------------------- */
            'terms'          => ['nullable', 'string', 'max:50'],
            'termsSeleccion' => ['nullable', 'string', 'max:20'],
            'termsOtro'      => [
                Rule::requiredIf(fn () => $this->termsSeleccion === self::TERMINO_OTRO),
                'nullable', 'string', 'max:50',
            ],

            /* -------------------------------------------------------------
             | LAS DIRECCIONES  (RB-035)
             |
             | Antes no había NINGUNA regla: se podía guardar un
             | presupuesto sin dirección de facturación. Y un documento
             | fiscal sin BILL TO no sirve para nada.
             |
             | La línea 2 queda opcional: es el "Suite 300" que casi nunca
             | hace falta.
             * ---------------------------------------------------------- */
            'bill_to.line1' => ['required', 'string', 'max:150'],
            'bill_to.line2' => ['nullable', 'string', 'max:150'],
            'bill_to.city'  => ['required', 'string', 'max:100'],
            'bill_to.state' => ['required', 'string', 'size:2'],
            'bill_to.zip'   => ['required', 'string', 'max:10'],

            /*
             | El SHIP TO solo se exige si el interruptor está encendido.
             |
             | Rule::requiredIf con una función y no 'required_if:campo,1'
             | porque el booleano de Livewire llega como true/false real, y
             | required_if compara contra el texto "1". Es de esas reglas
             | que parecen funcionar y fallan en silencio.
             */
            'ship_to.line1' => [Rule::requiredIf(fn () => $this->envioDistinto), 'nullable', 'string', 'max:150'],
            'ship_to.line2' => ['nullable', 'string', 'max:150'],
            'ship_to.city'  => [Rule::requiredIf(fn () => $this->envioDistinto), 'nullable', 'string', 'max:100'],
            'ship_to.state' => [Rule::requiredIf(fn () => $this->envioDistinto), 'nullable', 'string', 'size:2'],
            'ship_to.zip'   => [Rule::requiredIf(fn () => $this->envioDistinto), 'nullable', 'string', 'max:10'],

            'tax_rate'        => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['required', 'numeric', 'min:0'],

            'expected_payment_method' => ['nullable', 'string', 'max:20'],

            'salesperson_id' => ['nullable', 'exists:users,id'],

            // El punto significa "cada elemento del arreglo".
            'lineas'                => ['required', 'array', 'min:1'],
            'lineas.*.description'  => ['required', 'string', 'max:1000'],
            'lineas.*.quantity'     => ['required', 'numeric', 'min:0.01'],
            'lineas.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'lineas.*.product_id'   => ['nullable', 'exists:products,id'],
            'lineas.*.container_id' => ['nullable', 'exists:containers,id'],
            'lineas.*.grupo'        => ['nullable', 'string', 'max:20'],
            'lineas.*.delivery_zip'  => ['nullable', 'string', 'max:10'],
            'lineas.*.rental_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'lineas.*.work_details'  => ['nullable', 'string', 'max:2000'],
            'lineas.*.miles'         => ['nullable', 'numeric', 'min:0'],
            'lineas.*.rate_per_mile' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Los nombres con los que el usuario conoce cada campo.
     *
     * Sin esto, Laravel escribe el nombre técnico: "El campo bill_to.line1
     * es obligatorio". El usuario no tiene por qué saber cómo se llaman
     * nuestras columnas.
     */
    protected function validationAttributes(): array
    {
        return [
            'customer_id'     => 'cliente',
            'issue_date'      => 'fecha de emisión',
            'valid_until'     => 'válido hasta',
            'use_type'        => 'tipo de uso',
            'terms'           => 'términos de pago',
            'termsOtro'       => 'términos de pago',
            'bill_to.line1'   => 'dirección de facturación',
            'bill_to.city'    => 'ciudad de facturación',
            'bill_to.state'   => 'estado de facturación',
            'bill_to.zip'     => 'ZIP de facturación',
            'ship_to.line1'   => 'dirección de entrega',
            'ship_to.city'    => 'ciudad de entrega',
            'ship_to.state'   => 'estado de entrega',
            'ship_to.zip'     => 'ZIP de entrega',
            'tax_rate'        => 'porcentaje de impuesto',
            'discount_amount' => 'descuento',
            'lineas.*.miles'         => 'millas',
            'lineas.*.rate_per_mile' => 'tarifa por milla',
            'lineas.*.delivery_zip'  => 'ZIP de entrega',
            'lineas.*.rental_months' => 'plazo de la renta',
            'expected_payment_method' => 'forma de pago prevista',
        ];
    }

    /**
     * Los mensajes, escritos como se los diría una persona a otra.
     *
     * El número de línea va en el mensaje (:position) porque con ocho
     * conceptos "falta la descripción" no dice en cuál.
     */
    protected function messages(): array
    {
        return [
            'customer_id.required' => 'Elija un cliente.',
            'customer_id.exists'   => 'Ese cliente ya no existe. Vuelva a elegirlo.',

            'issue_date.required'        => 'La fecha de emisión es obligatoria.',
            'valid_until.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la de emisión.',

            'termsOtro.required' => 'Eligió "Otro" en los términos de pago: escriba cuál.',
            'termsOtro.max'      => 'Los términos de pago no pueden pasar de 50 caracteres.',

            'bill_to.line1.required' => 'Falta la dirección de facturación (BILL TO).',
            'bill_to.city.required'  => 'Falta la ciudad de facturación.',
            'bill_to.state.required' => 'Falta el estado de facturación (por ejemplo FL).',
            'bill_to.state.size'     => 'El estado se escribe con dos letras: FL, GA, NY.',
            'bill_to.zip.required'   => 'Falta el ZIP de facturación.',

            'ship_to.line1.required' => 'Marcó que la entrega va a otra dirección: falta la dirección de entrega.',
            'ship_to.city.required'  => 'Falta la ciudad de entrega.',
            'ship_to.state.required' => 'Falta el estado de entrega.',
            'ship_to.state.size'     => 'El estado se escribe con dos letras: FL, GA, NY.',
            'ship_to.zip.required'   => 'Falta el ZIP de entrega.',

            'tax_rate.required'        => 'Escriba el porcentaje de impuesto, o cero si no aplica.',
            'discount_amount.required' => 'Escriba el descuento, o cero si no hay.',

            'lineas.required' => 'El presupuesto necesita al menos una línea.',
            'lineas.min'      => 'El presupuesto necesita al menos una línea.',

            'lineas.*.description.required' => 'Línea :position: escriba qué se está cotizando.',
            'lineas.*.quantity.required'    => 'Línea :position: falta la cantidad.',
            'lineas.*.quantity.min'         => 'Línea :position: la cantidad tiene que ser mayor que cero.',
            'lineas.*.quantity.numeric'     => 'Línea :position: la cantidad tiene que ser un número.',
            'lineas.*.unit_price.required'  => 'Línea :position: falta el precio.',
            'lineas.*.unit_price.numeric'   => 'Línea :position: el precio tiene que ser un número.',
        ];
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    /**
     * @param  bool  $yEnviar  si además hay que marcarlo como enviado
     * @param  bool  $procesar si hay que dejarlo listo para revisar
     */
    public function guardar(bool $yEnviar = false, bool $procesar = false)
    {
        /* -----------------------------------------------------------------
         | LOS PERMISOS, ANTES DE TOCAR NADA
         |
         | Dos comprobaciones y no una, porque son dos actos distintos:
         |
         |   crear o corregir   estimates.create / estimates.update
         |   marcarlo enviado   estimates.send
         |
         | El vendedor que puede cotizar no necesariamente es quien manda
         | el documento al cliente. Separarlo cuesta dos lineas y permite
         | que el rol lo decida.
         * -------------------------------------------------------------- */
        $this->exigirPermiso($this->estimateId ? 'update' : 'create');

        if ($yEnviar) {
            $this->exigirPermiso('send');
        }

        // El renglón en blanco que puso el sistema no puede bloquear el
        // guardado. Ver descartarLineasVacias().
        $this->descartarLineasVacias();

        // Por si el usuario cambió el select y le dio a guardar sin que
        // llegara a salir el evento: se rearman los términos antes de
        // validar.
        $this->armarTerminos();

        /* -----------------------------------------------------------------
         | LA VALIDACIÓN, CON AVISO A LA PANTALLA
         |
         | Si algo falla, se le dispara un evento al navegador para que
         | haga scroll hasta el primer campo en rojo.
         |
         | Sin esto pasaba lo que describiste: el usuario está abajo, da a
         | Guardar, no ve nada, y concluye que el botón está roto. El
         | aviso estaba arriba del todo, fuera de la pantalla.
         |
         | El throw al final es obligatorio: sin él, Livewire creería que
         | la validación pasó y seguiría guardando.
         * -------------------------------------------------------------- */
        try {
            $this->validate();
        } catch (ValidationException $e) {
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
         | O se guarda la cabecera Y las líneas Y se recalcula, o no se
         | guarda nada. Sin esto, un fallo en la línea 3 de 5 dejaría un
         | presupuesto con dos líneas y un total que no corresponde a
         | ninguna venta real. Y nadie se enteraría hasta imprimirlo.
         * -------------------------------------------------------------- */
        /*
         | $procesar tiene que entrar en el use().
         |
         | Una closure de PHP no ve el ámbito de fuera: lo que no está en
         | use() no existe dentro. Faltaba, y el elseif de abajo reventaba
         | con "Undefined variable $procesar" justo al darle a Procesar.
         */
        $presupuesto = DB::transaction(function () use ($empresa, $yEnviar, $procesar) {

            $datos = [
                'company_id'  => $empresa->id,
                'customer_id' => $this->customer_id,
                'issue_date'  => $this->issue_date,
                'valid_until' => $this->valid_until ?: null,
                'terms'       => $this->terms ?: null,
                'use_type'    => $this->use_type,

                'bill_to' => $this->limpiarDireccion($this->bill_to),
                'ship_to' => $this->envioDistinto ? $this->limpiarDireccion($this->ship_to) : null,

                'expected_payment_method' => $this->expected_payment_method ?: null,

                /* -------------------------------------------------------------
                 | EL TOTAL DE ENTREGA
                 |
                 | Ya no se captura: se suma de las líneas de entrega.
                 |
                 | Sigue existiendo como columna porque la venta lo
                 | necesita (RB-030: "cuánto cobró de delivery") y porque
                 | es la base del cálculo de ganancia del viaje.
                 |
                 | Antes esta columna estaba declarada pero nadie la
                 | llenaba nunca: siempre valía 0.
                 * ---------------------------------------------------------- */
                'delivery_amount' => $this->totalDeEntrega(),

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

                // Solo se guarda el grupo si de verdad agrupa algo.
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

                    // RB-049 · solo tienen valor en líneas de entrega
                    'delivery_zip'  => $linea['delivery_zip'] ?: null,
                    'miles'         => $linea['miles'] ?: null,
                    'rate_per_mile' => $linea['rate_per_mile'] ?: null,
                    'rental_months' => $linea['rental_months'] ?: null,
                    'work_details'  => $linea['work_details'] ?: null,

                    'bundle_key'         => $grupoValido,
                    'bundle_description' => $grupoValido
                        ? ($this->gruposDescripcion[$grupoValido] ?? null)
                        : null,
                ];

                /* ---------------------------------------------------------
                 | ⚠️ AQUÍ ESTABA EL ERROR SILENCIOSO
                 |
                 | Antes esta rama decía:
                 |
                 |     $presupuesto->items()
                 |         ->whereKey($linea['id'])
                 |         ->update($atributos);
                 |
                 | Ese update() es del QUERY BUILDER, no del modelo: manda
                 | un UPDATE directo a la base y NO dispara los eventos de
                 | Eloquent.
                 |
                 | Y EstimateItem calcula su columna 'amount'
                 | (cantidad × precio) precisamente en un evento 'saving'.
                 |
                 | Efecto: al editar la cantidad o el precio de una línea
                 | YA GUARDADA, el importe de esa línea no se recalculaba.
                 | La cabecera decía un total y las líneas sumaban otro.
                 |
                 | Y no avisaba de nada. El documento simplemente estaba
                 | mal, y solo se descubría comparando a mano.
                 |
                 | Con find() + fill() + save() pasa por el modelo, se
                 | recalcula el amount y además despierta al
                 | EstimateItemObserver, que vuelve a sumar la cabecera.
                 * ------------------------------------------------------ */
                if (! empty($linea['id'])) {

                    $item = $presupuesto->items()->whereKey($linea['id'])->first();

                    if ($item) {
                        $item->fill($atributos)->save();
                    } else {
                        // La línea traía un id que ya no existe (alguien la
                        // borró desde otra pestaña). Se crea de nuevo en
                        // vez de perderla.
                        $presupuesto->items()->create($atributos);
                    }

                } else {
                    $presupuesto->items()->create($atributos);
                }
            }

            // 3 · Los totales, una vez más al final.
            $presupuesto->load('items')->recalculate();

            /* -------------------------------------------------------------
             | EL ESTADO
             |
             | Procesar NO envía. Deja el documento armado y listo para
             | que alguien lo mire. Enviar es un segundo acto, ya con el
             | documento delante, desde la pantalla de revisión.
             |
             | Antes el único botón decía "Guardar y enviar correo" y se
             | pulsaba sin haber visto nunca cómo quedaba el documento.
             * ---------------------------------------------------------- */
            if ($yEnviar) {
                $presupuesto->markAsSent();
            } elseif ($procesar) {
                $presupuesto->markAsProcessed();
            }

            return $presupuesto;
        });

        /* -----------------------------------------------------------------
         | GUARDAR LA DIRECCIÓN EN LA FICHA DEL CLIENTE
         |
         | Va FUERA de la transacción a propósito.
         |
         | Si esto fallara —por un dato raro, por un límite de columna—
         | no tiene por qué tumbar un presupuesto que ya está bien
         | guardado. Es una comodidad, no parte del documento.
         * -------------------------------------------------------------- */
        $avisoDireccion = $this->guardarDireccionEnCliente
            ? $this->guardarDireccionDelCliente()
            : null;

        $queHizo = match (true) {
            $yEnviar  => ' guardado y marcado como enviado.',
            $procesar => ' procesado. Revisa que todo esté bien y envíalo.',
            default   => ' guardado.',
        };

        session()->flash('exito',
            'Presupuesto '.$presupuesto->estimate_number.$queHizo
            .($avisoDireccion ? ' '.$avisoDireccion : ''));

        return redirect()->route('comercial.presupuestos.show', $presupuesto);
    }

    /**
     * Copia la dirección escrita a la ficha del cliente, para que el
     * próximo documento se llene solo.
     *
     * ── POR QUÉ EXISTE ──
     *
     * Porque la pantalla de clientes todavía no está hecha, y sin ella no
     * hay ningún sitio donde cargarle la dirección a nadie. Sin esto, el
     * cliente número 40 sigue obligando a teclear la dirección completa en
     * su documento número 15.
     *
     * ── QUÉ HACE EXACTAMENTE ──
     *
     *   · Si el cliente no tenía ninguna dirección, la guarda y la marca
     *     como la fiscal por defecto.
     *   · Si ya tenía una igual, no hace nada.
     *   · Si tenía otras distintas, agrega esta SIN marcarla por defecto:
     *     una entrega puntual no debería cambiarle la dirección fiscal a
     *     nadie.
     *   · Si además se marcó "la entrega va a otra dirección", esa se
     *     guarda también.
     *
     * Devuelve un texto para el aviso verde, o null si no guardó nada.
     */
    protected function guardarDireccionDelCliente(): ?string
    {
        $cliente = Customer::find($this->customer_id);

        if (! $cliente) {
            return null;
        }

        $guardadas = 0;

        $tenia = $cliente->addresses()->count();

        // ── La fiscal ──
        $fiscal = $this->limpiarDireccion($this->bill_to);

        if ($fiscal && ! empty($fiscal['line1'])) {

            // ¿Ya está esta misma? Se compara por línea 1 y ZIP, que es
            // lo que de verdad identifica un domicilio.
            $yaExiste = $cliente->addresses()
                ->where('line1', $fiscal['line1'])
                ->where('zip', $fiscal['zip'] ?? null)
                ->exists();

            if (! $yaExiste) {
                CustomerAddress::create($fiscal + [
                    'customer_id'        => $cliente->id,
                    'label'              => $fiscal['label'] ?? 'Facturación',
                    'type'               => 'billing',
                    'country'            => 'US',
                    'is_default_billing' => $tenia === 0,
                ]);

                $guardadas++;
            }
        }

        // ── La de entrega, si es distinta ──
        if ($this->envioDistinto) {

            $entrega = $this->limpiarDireccion($this->ship_to);

            if ($entrega && ! empty($entrega['line1'])) {

                $yaExiste = $cliente->addresses()
                    ->where('line1', $entrega['line1'])
                    ->where('zip', $entrega['zip'] ?? null)
                    ->exists();

                if (! $yaExiste) {
                    CustomerAddress::create($entrega + [
                        'customer_id'         => $cliente->id,
                        'label'               => $entrega['label'] ?? 'Entrega',
                        'type'                => 'shipping',
                        'country'             => 'US',
                        'is_default_shipping' => $tenia === 0,
                    ]);

                    $guardadas++;
                }
            }
        }

        if ($guardadas === 0) {
            return null;
        }

        return $guardadas === 1
            ? 'La dirección quedó guardada en la ficha del cliente.'
            : 'Las 2 direcciones quedaron guardadas en la ficha del cliente.';
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

        /**
     * Suma de todas las líneas de entrega del documento.
     *
     * Se recorre por producto y no por si la línea tiene millas,
     * porque una entrega con precio negociado a mano no lleva millas
     * y aun así es delivery.
     */
    protected function totalDeEntrega(): float
    {
        $ids = collect($this->lineas)->pluck('product_id')->filter()->unique();

        if ($ids->isEmpty()) {
            return 0.0;
        }

        $productos = Product::whereIn('id', $ids)->get()->keyBy('id');

        return (float) collect($this->lineas)
            ->filter(function ($linea) use ($productos) {
                $producto = $productos->get($linea['product_id'] ?? null);

                return $producto && $producto->isDelivery();
            })
            ->sum(fn ($linea) => (float) $linea['quantity'] * (float) $linea['unit_price']);
    }
    
    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        /* -----------------------------------------------------------------
         | LOS CONTENEDORES YA ELEGIDOS
         |
         | Solo los que están puestos en alguna línea, para poder mostrar
         | su nombre. Ya no se trae el inventario entero: para elegir uno
         | está el buscador.
         * -------------------------------------------------------------- */
        $idsElegidos = collect($this->lineas)
            ->pluck('container_id')
            ->filter()
            ->unique()
            ->all();

        $contenedoresElegidos = $idsElegidos
            ? Container::whereIn('id', $idsElegidos)
                ->with(['size:id,name', 'condition:id,name', 'grade:id,name'])
                ->get()
                ->keyBy('id')
            : collect();

        return view('livewire.estimates.form', [

            /*
             | Los conceptos disponibles EN UN PRESUPUESTO: los
             | compartidos y los propios de esta empresa, y solo los
             | marcados como cotizables.
             |
             | Es lo que deja fuera "Cargo por mora", "Almacenaje" y
             | "Recargo por tarjeta": esos tres nacen de hechos
             | posteriores a la venta, no se cotizan.
             */
            'productos' => $this->productosDisponibles,

            'contenedoresElegidos' => $contenedoresElegidos,

            'depositos'  => Depot::query()->active()->orderBy('name')->get(),
            'vendedores' => User::query()->active()->orderBy('name')->get(['id', 'name']),

            'tiposDeUso' => UseType::options(),
            'grupos'     => $this->gruposConVariasLineas(),

            'terminosDePago' => self::TERMINOS_FIJOS,
            'terminoOtro'    => self::TERMINO_OTRO,
        ]);
    }
}
