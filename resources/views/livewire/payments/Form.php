<?php

namespace App\Livewire\Payments;

use App\Enums\DocumentCategory;
use Livewire\Attributes\Url;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CreditCardAuthorization;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceCalculator;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use App\Livewire\Concerns\AuthorizesAccess;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * REGISTRAR UN COBRO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * No es un formulario de factura: es un formulario de DINERO QUE YA
 * ENTRÓ. El cheque ya está en la mano, la transferencia ya llegó al
 * banco. Lo que hace esta pantalla es dejar constancia de eso y decidir
 * a qué factura (o facturas) se le descuenta.
 *
 * ── LAS TRES PARTES DEL FORMULARIO ──
 *
 *   1. EL CLIENTE       de quién es el dinero
 *   2. EL PAGO           cuánto, cómo, cuándo — y si es tarjeta, con qué
 *                        autorización (RB-011, RB-012, RB-013)
 *   3. LA APLICACIÓN     a qué facturas se le descuenta. Puede ser a
 *                        ninguna todavía: eso es un anticipo (is_deposit)
 *                        y el dinero queda "sin aplicar" hasta que exista
 *                        la factura.
 *
 * ── SOBRE LA AUTORIZACIÓN DE TARJETA ──
 *
 * El módulo de clientes (con su propia pantalla para administrar
 * tarjetas) todavía no existe. Mientras tanto, este formulario trae una
 * versión mínima: elegir una autorización ya firmada, o crear una nueva
 * al vuelo con su documento adjunto. Cuando se construya la ficha del
 * cliente, esa pantalla se vuelve el lugar principal para esto y aquí
 * solo quedará el selector.
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

    protected string $permisoBase = 'payments';
    use WithFileUploads;

     /**
     * Id de una factura que llega por la dirección del navegador.
     *
     * Lo pone el botón de cobrar del listado de facturas:
     * /finanzas/pagos/nuevo?factura=123
     *
     * #[Url] hace que Livewire lo lea de ahí solo. Y como el enlace es
     * una dirección normal, se puede copiar, mandar por chat o guardar
     * en favoritos.
     */
    #[Url(as: 'factura', except: null)]
    public ?int $facturaId = null;

    /* =====================================================================
     | EL CLIENTE
     * ================================================================== */

    public ?int $customer_id     = null;
    public string $buscarCliente = '';
    public ?Customer $cliente    = null;

    /* =====================================================================
     | EL PAGO
     * ================================================================== */

    public string $method      = 'check';
    public float $amount       = 0;
    public string $received_at;
    public ?string $reference  = null;
    public ?string $notes      = null;
    public bool $is_deposit    = false;

    /** Lo que retuvo Square. Casi nunca se sabe al momento de cobrar. */
    public float $fee_amount = 0;

    /* =====================================================================
     | TARJETA DE CRÉDITO (RB-011, RB-012, RB-013)
     * ================================================================== */

    /** 'existing' o 'new'. */
    public string $modoTarjeta = 'existing';

    public ?int $credit_card_authorization_id = null;

    // Autorización nueva, capturada aquí mismo.
    public string $nuevaAuthNombre     = '';
    public string $nuevaAuthMarca      = '';
    public string $nuevaAuthUltimos4   = '';
    public ?int $nuevaAuthMes          = null;
    public ?int $nuevaAuthAnio         = null;
    public float $nuevaAuthMontoAutorizado = 0;
    public string $nuevaAuthFirmadaEl;
    public $nuevaAuthArchivo = null;   // el PDF/foto del formulario firmado

    /** RB-013: para personas físicas, la tarjeta solo se acepta presente en la yarda. */
    public bool $clientePresenteEnYarda = false;

    /* =====================================================================
     | LA APLICACIÓN A FACTURAS
     |
     | 'aplicaciones' es [invoice_id => monto]. Solo entran ahí las
     | facturas con algo escrito; una fila en cero no genera asignación.
     * ================================================================== */

    public array $aplicaciones = [];

    public function mount(): void
    {
        $this->exigirPermiso('create');

        $this->received_at        = now()->toDateString();
        $this->nuevaAuthFirmadaEl = now()->toDateString();

        $this->precargarDesdeFactura();

        /*
         | Si se llegó desde el botón de cobrar de una factura, esa factura
         | ya dijo cómo iba a pagar el cliente. Se trae aquí para no
         | teclear dos veces el mismo dato.
         */
        $this->tomarMetodoDeLaFactura();
    }

      /**
     * Si se llegó desde el botón de cobrar de una factura, deja todo
     * listo: cliente elegido, monto igual al saldo, y el dinero ya
     * asignado a esa factura.
     *
     * ── LAS TRES SALIDAS SILENCIOSAS ──
     *
     * Si no viene factura, si no existe, o si ya no tiene saldo, este
     * método no hace nada y el formulario se abre en blanco. No lanza
     * error a propósito: que alguien edite la dirección del navegador a
     * mano no es un fallo del sistema, y una pantalla de error ahí sería
     * peor que un formulario vacío.
     *
     * Invoice::find() respeta el filtro de compañía, así que una factura
     * de la otra empresa tampoco entra por aquí.
     */
    protected function precargarDesdeFactura(): void
    {
        if (! $this->facturaId) {
            return;
        }

        $factura = Invoice::find($this->facturaId);

        if (! $factura || (float) $factura->balance_due <= 0) {
            return;
        }

        // Va PRIMERO: seleccionarCliente() vacía las aplicaciones.
        $this->seleccionarCliente($factura->customer_id);

        $this->amount = (float) $factura->balance_due;

        // Y el dinero, ya repartido. Todo editable: si el cliente pagó
        // de menos, se corrige el monto y la asignación se ajusta a mano.
        $this->aplicaciones = [
            $factura->id => round((float) $factura->balance_due, 2),
        ];
    }

    /* =====================================================================
     | EL CLIENTE — buscar y elegir
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
        $this->cliente      = Customer::findOrFail($id);
        $this->customer_id  = $this->cliente->id;
        $this->buscarCliente = '';
        $this->aplicaciones  = [];

        // Si el cliente no puede pagar con tarjeta, no lo dejamos elegir
        // ese método (mismo criterio que el formulario de facturas).
        if (! $this->cliente->allow_credit_card && $this->method === PaymentMethod::CreditCard->value) {
            $this->method = 'check';
        }

        $this->resetValidation('customer_id');
    }

    public function quitarCliente(): void
    {
        $this->customer_id = null;
        $this->cliente      = null;
        $this->aplicaciones = [];
    }

    /* =====================================================================
     | LAS FACTURAS PENDIENTES DE ESE CLIENTE
     |
     | Solo las de la compañía activa: si el pago se está registrando
     | dentro de FLCHR, aquí no deben aparecer facturas de RS Transport
     | aunque sea el mismo cliente (RB-001, y el trait BelongsToCompany
     | ya lo filtra sin que haya que pedirlo).
     * ================================================================== */

    public function getFacturasPendientesProperty()
    {
        if (! $this->customer_id) {
            return collect();
        }

        return Invoice::query()
            ->where('customer_id', $this->customer_id)
            ->unpaid()
            ->orderBy('due_date')
            ->get();
    }

    /* =====================================================================
     | EL RECARGO DE TARJETA QUE LA FACTURA NO TRAE (RB-009)
     |
     | El caso: se emite la factura sin saber cómo va a pagar el cliente,
     | así que sale sin el 3.5%. Días después el cliente llama y dice que
     | paga con tarjeta.
     |
     | Sin esto no había salida: o se cobraba lo que dice la factura y el
     | 3.5% lo ponía la empresa, o se cobraba de más de lo que dice el
     | documento, que es justo lo que provoca un chargeback.
     |
     | EL RECARGO VA EN LA FACTURA, NO EN EL PAGO. La factura es el
     | documento que el cliente recibe y el importe que autoriza al
     | firmar. Por eso aquí no se ajusta nada en silencio: se detectan las
     | facturas sin recargo, se enseñan los números antes y después, y se
     | ofrece corregirlas.
     * ================================================================== */

    /** Las facturas de este cobro que todavía no llevan el 3.5%. */
    public function getFacturasSinRecargoProperty(): array
    {
        if ($this->method !== PaymentMethod::CreditCard->value) {
            return [];
        }

        $empresa = app(CompanyContext::class)->get();

        if (! $empresa) {
            return [];
        }

        $porcentaje = app(InvoiceCalculator::class)->defaultCreditCardFeePercent($empresa);

        if ($porcentaje <= 0) {
            return [];
        }

        $pendientes = [];

        foreach ($this->facturasPendientes as $factura) {

            // Solo las que están en este cobro.
            if (! array_key_exists($factura->id, $this->aplicaciones)) {
                continue;
            }

            // Ya lo trae: nada que hacer.
            if ((float) $factura->credit_card_fee_percent > 0) {
                continue;
            }

            $recargo = round((float) $factura->total * $porcentaje / 100, 2);

            $pendientes[] = [
                'id'         => $factura->id,
                'numero'     => $factura->invoice_number,
                'saldo'      => (float) $factura->balance_due,
                'recargo'    => $recargo,
                'nuevoSaldo' => round((float) $factura->balance_due + $recargo, 2),
                'porcentaje' => $porcentaje,
            ];
        }

        return $pendientes;
    }

    /**
     * Agrega el 3.5% a esas facturas y ajusta el cobro.
     *
     * Deja escrito en la factura que el cliente paga con tarjeta —sin eso
     * el documento mostraría un recargo sin decir de dónde sale—, pone el
     * porcentaje, recalcula con el InvoiceCalculator, y sube lo aplicado y
     * el monto del pago al saldo nuevo.
     *
     * No toca una factura bloqueada: si ya está pagada o anulada, el
     * InvoiceObserver lo impide desde el modelo y hace bien.
     */
    public function agregarRecargoDeTarjeta(): void
    {
        $this->exigirPermiso('create');

        $empresa = app(CompanyContext::class)->get();

        if (! $empresa) {
            return;
        }

        $porcentaje = app(InvoiceCalculator::class)->defaultCreditCardFeePercent($empresa);

        $tocadas = [];

        foreach ($this->facturasSinRecargo as $fila) {

            $factura = Invoice::find($fila['id']);

            if (! $factura || $factura->isLocked()) {
                continue;
            }

            $factura->expected_payment_method = PaymentMethod::CreditCard->value;
            $factura->credit_card_fee_percent = $porcentaje;

            $factura->load('items')->recalculate();
            $factura->refresh();

            $this->aplicaciones[$factura->id] = round((float) $factura->balance_due, 2);

            $tocadas[] = $factura->invoice_number;
        }

        if (empty($tocadas)) {
            return;
        }

        $this->amount = $this->totalAplicado;

        // El importe que hay que autorizar en el formulario es ese mismo.
        $this->nuevaAuthMontoAutorizado = $this->amount;

        session()->flash('exito',
            'Se agregó el recargo de tarjeta a '
            .(count($tocadas) === 1 ? 'la factura ' : 'las facturas ')
            .implode(', ', $tocadas)
            .'. Reenvíe'.(count($tocadas) === 1 ? 'la' : 'las')
            .' al cliente: el importe cambió.');
    }

    /* =====================================================================
     | EL SALDO A FAVOR DEL CLIENTE
     |
     | ── EL PROBLEMA ──
     |
     | Se registra un anticipo de $2.000 marcando "es un depósito o
     | anticipo". Ese dinero queda a favor del cliente, sin aplicar a
     | ninguna factura: para eso existe la casilla.
     |
     | Después se le emite la renta y, al ir a cobrarla, ese anticipo no
     | aparecía POR NINGÚN LADO. El dinero estaba guardado y bien
     | guardado, pero esta pantalla no lo mencionaba.
     |
     | Y aquí está lo que lo hacía invisible: aplicar un anticipo NO es
     | registrar un pago nuevo. El dinero ya entró. Lo que falta es
     | repartirlo, y eso se hacía solo desde la ficha de aquel pago, a la
     | que nadie llega si no sabe que existe.
     |
     | ── LO QUE SE HACE ──
     |
     | En cuanto se elige el cliente, si tiene dinero sin aplicar sale un
     | aviso con el importe y un botón que lo reparte entre las facturas
     | de esta pantalla. Sin registrar nada nuevo: se crean las
     | aplicaciones sobre el pago que ya existía.
     * ================================================================== */

    /** Los pagos de este cliente que todavía tienen dinero sin repartir. */
    public function getPagosAFavorProperty()
    {
        if (! $this->customer_id) {
            return collect();
        }

        return Payment::query()
            ->where('customer_id', $this->customer_id)
            ->withUnapplied()
            ->orderBy('received_at')
            ->get();
    }

    /** Cuánto dinero tiene a favor, en total. */
    public function getSaldoAFavorProperty(): float
    {
        return round((float) $this->pagosAFavor->sum('unapplied_amount'), 2);
    }

    /**
     * Reparte el saldo a favor entre las facturas pendientes.
     *
     * Va de la factura más vieja a la más nueva, y del anticipo más
     * antiguo al más reciente. Es el orden con el que trabaja cualquier
     * contabilidad: lo que entró primero se usa primero, y lo que se
     * debe desde hace más tiempo se salda antes.
     *
     * No crea ningún pago: usa applyTo() sobre los que ya existen, que
     * es el mismo método de la ficha del pago. Por eso los totales
     * cuadran solos y no hay dinero contado dos veces.
     */
    public function aplicarSaldoAFavor(): void
    {
        $this->exigirPermiso('create');

        $facturas = $this->facturasPendientes
            ->sortBy(fn ($f) => $f->due_date ?? $f->issue_date)
            ->values();

        if ($facturas->isEmpty()) {
            session()->flash('error', 'Este cliente no tiene facturas con saldo.');

            return;
        }

        $aplicado = 0.0;
        $tocadas  = [];

        foreach ($this->pagosAFavor as $pago) {

            foreach ($facturas as $factura) {

                $factura->refresh();

                $porCobrar   = round((float) $factura->balance_due, 2);
                $disponible  = round((float) $pago->available_amount, 2);

                if ($porCobrar <= 0.001 || $disponible <= 0.001) {
                    continue;
                }

                $cuanto = min($porCobrar, $disponible);

                try {
                    $pago->applyTo($factura, $cuanto);

                    $pago->refresh();
                    $aplicado += $cuanto;
                    $tocadas[$factura->invoice_number] = true;

                } catch (\Throwable $e) {
                    session()->flash('error', 'No se pudo aplicar: '.$e->getMessage());

                    return;
                }
            }
        }

        if ($aplicado <= 0.001) {
            session()->flash('error', 'No había nada que aplicar.');

            return;
        }

        /*
         | Se recargan las facturas de la pantalla: sus saldos acaban de
         | cambiar y el reparto de abajo tiene que partir de los nuevos.
         */
        unset($this->facturasPendientes);
        $this->aplicaciones = [];

        session()->flash('exito',
            'Se aplicaron $'.number_format($aplicado, 2).' del saldo a favor a '
            .implode(', ', array_keys($tocadas)).'. '
            .($this->saldoAFavor > 0.001
                ? 'Le quedan $'.number_format($this->saldoAFavor, 2).' a favor.'
                : 'No le queda saldo a favor.'));
    }

    public function getTotalAplicadoProperty(): float
    {
        return round(array_sum(array_map('floatval', $this->aplicaciones)), 2);
    }

    public function getSaldoSinAsignarProperty(): float
    {
        return round((float) $this->amount - $this->totalAplicado, 2);
    }

    /**
     * Reparte el monto del pago entre las facturas pendientes, la más
     * vieja primero, hasta que se acabe el dinero o las facturas.
     *
     * Es un atajo, no una obligación: cualquier casilla se puede editar
     * a mano después de usarlo.
     */
    public function aplicarAutomatico(): void
    {
        $restante = (float) $this->amount;
        $nuevas   = [];

        foreach ($this->facturasPendientes as $factura) {
            if ($restante <= 0.001) {
                break;
            }

            $monto = min($restante, (float) $factura->balance_due);
            $nuevas[$factura->id] = round($monto, 2);
            $restante -= $monto;
        }

        $this->aplicaciones = $nuevas;
    }

    public function limpiarAplicaciones(): void
    {
        $this->aplicaciones = [];
    }

    /* =====================================================================
     | LA AUTORIZACIÓN DE TARJETA
     * ================================================================== */

    public function getAutorizacionesDisponiblesProperty()
    {
        if (! $this->cliente) {
            return collect();
        }

        return $this->cliente->cardAuths()
            ->get()
            ->filter(fn (CreditCardAuthorization $a) => $a->isUsable())
            ->values();
    }

    /* =====================================================================
     | EL MONTO SE COMPLETA SOLO
     * ================================================================== */

    /**
     * Cuando se reparte sin haber escrito el monto, el monto se pone solo.
     *
     * ── QUE PASABA ──
     *
     * La gente no llena esta pantalla de arriba a abajo. Llega con un
     * cheque en la mano, mira la lista de facturas de la derecha y
     * empieza a repartir: 1000 aqui, 200 alla. El campo "Monto" de la
     * izquierda se queda en cero, porque todavia no lo miro nadie.
     *
     * Y entonces el cuadre daba -$1,200.00 y el boton se bloqueaba, con
     * un cartel rojo diciendo que repartia de mas. Tecnicamente cierto y
     * completamente inutil: el sistema sabia perfectamente cuanto sumaba
     * lo repartido, y en vez de ponerlo, acusaba.
     *
     * ── QUE HACE AHORA ──
     *
     * Si el monto esta en cero y se reparte algo, el monto pasa a ser esa
     * suma. Es lo unico que podia significar.
     *
     * En cuanto alguien escribe un monto a mano, esto deja de actuar: si
     * el cheque es de $1,200 y solo se reparten $900, el resto queda como
     * saldo a favor, y pisarlo seria inventar.
     */
    /**
     * Si alguien eligió la forma de pago a mano.
     *
     * Mientras sea false, elegir una factura trae la forma de pago que
     * esa factura tenía prevista. En cuanto la persona toca el
     * desplegable, su decisión manda.
     */
    public bool $metodoElegidoAMano = false;

    public function updated(string $campo): void
    {
        /* -----------------------------------------------------------------
         | CAMBIO EL MONTO DEL PAGO
         |
         | ── QUE PASABA ──
         |
         | Se entraba desde una factura de $490 y el reparto venia
         | precargado con esos $490. Se corregia el monto a $400 —un abono,
         | el cliente queda debiendo $90— y el reparto seguia diciendo 490.
         |
         | Resultado: un cartel rojo diciendo que se repartian $90 de mas,
         | sobre una operacion perfectamente normal. El sistema acusaba a
         | la persona de un descuadre que habia creado el propio sistema.
         |
         | Ahora el reparto se recorta al monto nuevo. Un abono de $400
         | sobre una factura de $490 queda aplicado 400 y la factura
         | conserva $90 de saldo, que es exactamente como lo lleva el
         | Excel de hoy (hoja CUENTAS: columnas MONTO / PAGADO / DEBE).
         |
         | Solo se recorta hacia abajo. Si el monto sube, no se reparte
         | dinero solo: a que factura va es una decision de quien cobra.
         * -------------------------------------------------------------- */
        if ($campo === 'amount') {
            $this->ajustarRepartoAlMonto();

            return;
        }

        if (! str_starts_with($campo, 'aplicaciones.')) {
            return;
        }

        /*
         | Se eligió una factura: trae la forma de pago que esa factura
         | tenía prevista, salvo que alguien ya la haya elegido a mano.
         */
        $this->tomarMetodoDeLaFactura();

        if ((float) $this->amount <= 0.001) {
            $this->amount = $this->totalAplicado;
        }
    }

    /**
     * Recorta lo repartido para que nunca pase del monto del pago.
     *
     * Va de arriba abajo respetando el orden en que estan las facturas:
     * la primera cobra lo que pueda, la siguiente lo que quede, y las
     * que se quedan en cero salen del reparto.
     */
    protected function ajustarRepartoAlMonto(): void
    {
        $monto = round((float) $this->amount, 2);

        // Sin monto no hay nada que repartir.
        if ($monto <= 0.001) {
            $this->aplicaciones = [];

            return;
        }

        // Si lo repartido cabe dentro del monto, no se toca nada: puede
        // ser a proposito que sobre dinero sin asignar.
        if ($this->totalAplicado <= $monto + 0.001) {
            return;
        }

        $restante = $monto;
        $nuevas   = [];

        foreach ($this->aplicaciones as $facturaId => $importe) {
            if ($restante <= 0.001) {
                break;
            }

            $cabe = min($restante, round((float) $importe, 2));

            if ($cabe > 0.001) {
                $nuevas[$facturaId] = round($cabe, 2);
                $restante -= $cabe;
            }
        }

        $this->aplicaciones = $nuevas;
    }

    /**
     * Iguala el monto del pago a la suma de lo aplicado.
     *
     * Es la salida para cuando se subieron los importes de las facturas
     * a mano por encima del monto. Ahi no se puede saber cual de los dos
     * numeros es el correcto, asi que se ofrece la correccion en vez de
     * decidirla.
     */
    public function usarSumaComoMonto(): void
    {
        $this->amount = $this->totalAplicado;
    }

    public function updatedMethod(): void
    {
        /*
         | Alguien eligió la forma de pago a mano. A partir de aquí, elegir
         | una factura ya no la pisa: lo que dice la persona manda sobre lo
         | que esperaba la factura.
         */
        $this->metodoElegidoAMano = true;

        if ($this->method !== PaymentMethod::CreditCard->value) {
            $this->credit_card_authorization_id = null;
            $this->fee_amount = 0;

            return;
        }

        /* -----------------------------------------------------------------
         | EL MONTO AUTORIZADO SE PRECARGA CON LO QUE SE VA A COBRAR
         |
         | El formulario que firma el cliente autoriza UN IMPORTE CONCRETO.
         | Si el papel dice 490 y en la tarjeta le pasan 507, el cliente
         | tiene razón al reclamarlo al banco y el papel deja de proteger a
         | la empresa: lo contrario de para lo que se pide (RB-011).
         |
         | Queda editable: el cliente puede autorizar un tope mayor.
         * -------------------------------------------------------------- */
        if ($this->nuevaAuthMontoAutorizado <= 0) {
            $this->nuevaAuthMontoAutorizado = (float) $this->amount;
        }
    }

    /**
     * Toma la forma de pago que la factura tenía prevista.
     *
     * ── POR QUÉ ──
     *
     * Al emitir la factura ya se dijo cómo iba a pagar el cliente. Volver
     * a elegirlo al cobrar es teclear dos veces el mismo dato, y es donde
     * se cuela el error: se cobra con tarjeta una factura que no lleva el
     * 3.5%, o al revés.
     *
     * Solo actúa si nadie lo eligió a mano. Si la persona ya tocó el
     * desplegable, su decisión manda: el cliente pudo cambiar de idea.
     */
    protected function tomarMetodoDeLaFactura(): void
    {
        if ($this->metodoElegidoAMano) {
            return;
        }

        // La primera factura del cobro con algo asignado.
        $primera = collect($this->aplicaciones)
            ->filter(fn ($importe) => (float) $importe > 0.001)
            ->keys()
            ->first();

        if (! $primera) {
            return;
        }

        $factura = $this->facturasPendientes->firstWhere('id', (int) $primera);

        $previsto = $factura?->expected_payment_method;

        if (! $previsto) {
            return;
        }

        $this->method = $previsto instanceof PaymentMethod
            ? $previsto->value
            : $previsto;

        if ($this->method === PaymentMethod::CreditCard->value
            && $this->nuevaAuthMontoAutorizado <= 0) {
            $this->nuevaAuthMontoAutorizado = (float) $this->amount;
        }
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    protected function rules(): array
    {
        $reglas = [
            'customer_id' => ['required', 'exists:customers,id'],
            'method'      => ['required', 'in:'.implode(',', PaymentMethod::values())],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'received_at' => ['required', 'date'],
            'reference'   => ['nullable', 'string', 'max:100'],
            'notes'       => ['nullable', 'string'],
        ];

        $metodo = PaymentMethod::tryFrom($this->method);

        if ($metodo?->requiresReference()) {
            $reglas['reference'] = ['required', 'string', 'max:100'];
        }

        if ($this->method === PaymentMethod::CreditCard->value) {
            if ($this->modoTarjeta === 'existing') {
                $reglas['credit_card_authorization_id'] = ['required', 'exists:credit_card_authorizations,id'];
            } else {
                $reglas['nuevaAuthNombre']   = ['required', 'string', 'max:150'];
                $reglas['nuevaAuthUltimos4'] = ['required', 'digits:4'];
                $reglas['nuevaAuthMes']      = ['required', 'integer', 'between:1,12'];
                $reglas['nuevaAuthAnio']     = ['required', 'integer', 'min:'.now()->year];
                $reglas['nuevaAuthFirmadaEl'] = ['required', 'date'];
                $reglas['nuevaAuthArchivo']  = ['required', 'file', 'max:10240'];
            }
        }

        return $reglas;
    }

    /**
     * Los nombres con los que el usuario conoce cada campo.
     *
     * Los de la tarjeta son los que más falta hacían: sin esto, el error
     * decía "El campo nuevaAuthUltimos4 es obligatorio", que es el nombre
     * de una variable de PHP y no significa nada para nadie.
     */
    protected function validationAttributes(): array
    {
        return [
            'customer_id'        => 'cliente',
            'method'             => 'forma de pago',
            'amount'             => 'monto',
            'received_at'        => 'fecha de recepción',
            'reference'          => 'número de referencia',
            'credit_card_authorization_id' => 'autorización de tarjeta',
            'nuevaAuthNombre'    => 'nombre del titular',
            'nuevaAuthUltimos4'  => 'últimos 4 dígitos',
            'nuevaAuthMes'       => 'mes de vencimiento',
            'nuevaAuthAnio'      => 'año de vencimiento',
            'nuevaAuthFirmadaEl' => 'fecha de la firma',
            'nuevaAuthArchivo'   => 'formulario firmado',
        ];
    }

    protected function messages(): array
    {
        return [
            'customer_id.required' => 'Elija el cliente que hizo el pago.',
            'customer_id.exists'   => 'Ese cliente ya no existe. Vuelva a elegirlo.',

            'method.required' => 'Elija con qué se pagó.',

            'amount.required' => 'Escriba cuánto se recibió.',
            'amount.min'      => 'El monto tiene que ser mayor que cero.',
            'amount.numeric'  => 'El monto tiene que ser un número.',

            'received_at.required' => 'Escriba qué día entró el dinero.',
            'received_at.date'     => 'La fecha de recepción no es una fecha válida.',

            /*
             | La referencia es lo que permite cuadrar con el banco. Un
             | cheque sin número o una transferencia sin confirmación no se
             | pueden conciliar después: el dinero aparece en el extracto y
             | nadie sabe a qué factura corresponde.
             */
            'reference.required' => 'Esta forma de pago necesita el número de referencia (cheque, confirmación o autorización). Sin él no se puede cuadrar con el banco.',
            'reference.max'      => 'La referencia no puede pasar de 100 caracteres.',

            'credit_card_authorization_id.required' => 'Elija cuál autorización de tarjeta respalda este cobro, o cargue una nueva.',

            'nuevaAuthNombre.required'    => 'Escriba el nombre del titular, tal como aparece en la tarjeta.',
            'nuevaAuthUltimos4.required'  => 'Faltan los últimos 4 dígitos de la tarjeta.',
            'nuevaAuthUltimos4.digits'    => 'Son exactamente 4 dígitos, sin espacios.',
            'nuevaAuthMes.required'       => 'Falta el mes de vencimiento de la tarjeta.',
            'nuevaAuthMes.between'        => 'El mes va del 1 al 12.',
            'nuevaAuthAnio.required'      => 'Falta el año de vencimiento de la tarjeta.',
            'nuevaAuthAnio.min'           => 'Esa tarjeta ya está vencida.',
            'nuevaAuthFirmadaEl.required' => 'Escriba qué día firmó el cliente la autorización.',

            /*
             | RB-011: no se procesa tarjeta sin el formulario firmado.
             |
             | Esta no es una validación de formulario, es la protección
             | contra un contracargo. Si el cliente desconoce el cobro y no
             | hay firma, el dinero se pierde.
             */
            'nuevaAuthArchivo.required' => 'Sin el formulario de autorización firmado no se puede procesar la tarjeta. Súbalo escaneado o fotografiado.',
            'nuevaAuthArchivo.file'     => 'Suba un archivo (PDF o imagen).',
            'nuevaAuthArchivo.max'      => 'El archivo no puede pasar de 10 MB.',
        ];
    }

    public function guardar()
    {
        /*
         | Este formulario solo crea: no hay pantalla de editar un cobro
         | ya registrado. Si algun dia la hay, aqui habra que distinguir
         | entre create y update como en factura.
         */
        $this->exigirPermiso('create');

         try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('errores-de-validacion');

            throw $e;
        }

        /* -----------------------------------------------------------------
         | RB-012 / RB-013 — quién puede pagar con tarjeta
         * -------------------------------------------------------------- */
        if ($this->method === PaymentMethod::CreditCard->value) {

            throw_unless(
                $this->cliente?->allow_credit_card,
                new \RuntimeException('Este cliente no está habilitado para pagar con tarjeta.'),
            );

            if ($this->cliente->type?->verifiesInSunbiz() && ! $this->cliente->sunbiz_verified) {
                $this->addError('customer_id', 'Esta empresa todavía no está verificada en Sunbiz (RB-012). No se puede procesar la tarjeta.');

                return;
            }

            if (! $this->cliente->type?->verifiesInSunbiz() && ! $this->clientePresenteEnYarda) {
                $this->addError('clientePresenteEnYarda', 'Con personas físicas, la tarjeta solo se acepta con el cliente presente en la yarda (RB-013).');

                return;
            }
        }

        $pago = DB::transaction(function () {

            $authId = $this->credit_card_authorization_id;

            if ($this->method === PaymentMethod::CreditCard->value && $this->modoTarjeta === 'new') {
                $authId = $this->crearAutorizacionRapida()->id;
            }

            $auth = $authId ? CreditCardAuthorization::find($authId) : null;

            $pago = Payment::create([
                'customer_id'                  => $this->customer_id,
                'method'                        => $this->method,
                'amount'                        => $this->amount,
                'fee_amount'                    => $this->fee_amount,
                'received_at'                   => $this->received_at,
                'reference'                     => $this->reference,
                'notes'                         => $this->notes,
                'is_deposit'                    => $this->is_deposit,
                'provider'                      => 'manual',
                'credit_card_authorization_id'  => $auth?->id,
                'card_brand'                    => $auth?->card_brand,
                'card_last4'                    => $auth?->card_last4,
                'created_by'                    => auth()->id(),
            ]);

            foreach ($this->is_deposit ? [] : $this->aplicaciones as $invoiceId => $monto) {
                $monto = round((float) $monto, 2);

                if ($monto <= 0) {
                    continue;
                }

                $factura = Invoice::find($invoiceId);

                if ($factura) {
                    $pago->applyTo($factura, $monto);
                }
            }

            return $pago;
        });

        session()->flash('exito', 'Pago '.$pago->payment_number.' registrado.');

        $this->redirectRoute('finanzas.pagos.show', $pago, navigate: true);
    }

    /**
     * Crea la autorización de tarjeta "al vuelo", con su documento
     * firmado adjunto. Se guarda directamente en 'active': la revisión
     * formal de este flujo llega con el módulo de clientes.
     */
    protected function crearAutorizacionRapida(): CreditCardAuthorization
    {
        $ruta = $this->nuevaAuthArchivo->store(
            'autorizaciones-tarjeta/'.$this->cliente->id,
            'local',
        );

        $auth = CreditCardAuthorization::create([
            'customer_id'         => $this->cliente->id,
            'cardholder_name'     => $this->nuevaAuthNombre,
            'card_brand'          => $this->nuevaAuthMarca ?: null,
            'card_last4'          => $this->nuevaAuthUltimos4,
            'exp_month'           => $this->nuevaAuthMes,
            'exp_year'            => $this->nuevaAuthAnio,
            'authorized_amount'   => $this->nuevaAuthMontoAutorizado ?: $this->amount,
            'authorization_type'  => 'single_use',
            'signed_at'           => $this->nuevaAuthFirmadaEl,
            'sunbiz_verified'     => (bool) $this->cliente->sunbiz_verified,
            'status'              => 'active',
            'created_by'          => auth()->id(),
        ]);

        $auth->documents()->create([
            /*
             | Se etiqueta con la compañía activa. La autorización en sí
             | es del cliente (maestro compartido), pero el PAPEL firmado
             | lo tomó una de las dos oficinas, y así lo ve solo esa
             | compañía en el listado de documentos — igual que un
             | contrato tomado por FLCHR no le aparece a RS Transport.
             */
            'company_id'        => app(\App\Support\CompanyContext::class)->id(),
            'category'          => DocumentCategory::CcAuthorization,
            'name'              => $this->nuevaAuthArchivo->getClientOriginalName(),
            'path'              => $ruta,
            'disk'              => 'local',
            'mime_type'         => $this->nuevaAuthArchivo->getMimeType(),
            'size_bytes'        => $this->nuevaAuthArchivo->getSize(),
            'attach_to_invoice' => false,
            'uploaded_by'       => auth()->id(),
        ]);

        $auth->signature_document_id = $auth->documents()->latest()->value('id');
        $auth->saveQuietly();

        return $auth;
    }

    public function render()
    {
        return view('livewire.payments.form', [
            'metodos' => PaymentMethod::options(),
        ]);
    }
}
