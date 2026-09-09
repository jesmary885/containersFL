<?php

namespace App\Livewire\Payments;

use App\Enums\DocumentCategory;
use Livewire\Attributes\Url;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CreditCardAuthorization;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
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
        $this->received_at        = now()->toDateString();
        $this->nuevaAuthFirmadaEl = now()->toDateString();

        $this->precargarDesdeFactura();
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

    public function updatedMethod(): void
    {
        if ($this->method !== PaymentMethod::CreditCard->value) {
            $this->credit_card_authorization_id = null;
            $this->fee_amount = 0;
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
