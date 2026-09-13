<?php

namespace App\Livewire\Customers;

use App\Enums\CertificateStatus;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Customer;
use App\Models\TaxExemptionCertificate;
use App\Support\CompanyContext;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DEL CLIENTE — lo que se consulta, no lo que se edita
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Cuatro cosas: sus datos, sus direcciones y contactos, su historial y
 * sus certificados de exención.
 *
 * ── EL HISTORIAL ES LA MITAD DEL VALOR DE ESTA PANTALLA ──
 *
 * Las reglas del negocio piden "historial: qué compró y qué rentó", y
 * hasta ahora eso solo se podía contestar buscando al cliente en cuatro
 * listados distintos.
 *
 * ── POR QUÉ EL HISTORIAL SÍ FILTRA POR EMPRESA ──
 *
 * El cliente es compartido entre FLCHR y RST, pero sus documentos no:
 * `Estimate`, `Invoice` y `Rental` usan el trait `BelongsToCompany`, que
 * les pone el filtro de la empresa activa a todas sus consultas.
 *
 * O sea que esta ficha enseña "lo que este cliente tiene CON la empresa
 * en la que estoy". Es lo correcto y conviene que se lea en pantalla,
 * porque si no, alguien en RST ve tres facturas y jura que faltan las
 * de FLCHR.
 *
 * ── LOS CERTIFICADOS SE REGISTRAN AQUÍ Y NO EN EL FORMULARIO ──
 *
 * Porque no son un campo del cliente: son documentos con número, año y
 * fechas de vigencia, y de ellos depende `customers.tax_exempt`, que es
 * un caché que mantiene solo el TaxExemptionCertificateObserver.
 *
 * Registrar uno es lo único que hace que un cliente pase a estar exento.
 * Sin esta sección, RB-014 y RB-015 estarían construidos y muertos: la
 * factura sabe leer el certificado vigente y no habría forma de crear
 * ninguno.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'customers';

    public Customer $customer;

    /** Qué acción espera confirmación: 'desactivar' | null */
    public ?string $confirmando = null;

    /* =====================================================================
     | EL MODAL DEL CERTIFICADO
     * ================================================================== */

    public bool $modalCertificado = false;

    public string $certificate_number = '';
    public ?int $issued_year          = null;
    public string $valid_from         = '';
    public string $valid_until        = '';
    public ?string $certNotas         = null;

    /** El certificado que se está renovando, si viene de un botón "Renovar". */
    public ?int $renovandoDesde = null;

    public function mount(Customer $customer): void
    {
        $this->exigirPermiso('view');

        $this->customer = $customer;
    }

    /* =====================================================================
     | DESACTIVAR
     * ================================================================== */

    public function pedirDesactivar(): void
    {
        $this->confirmando = 'desactivar';
    }

    public function cancelar(): void
    {
        $this->confirmando = null;
    }

    public function cambiarEstado(): void
    {
        $this->exigirPermiso('update');

        $this->customer->update(['is_active' => ! $this->customer->is_active]);

        session()->flash('exito',
            $this->customer->is_active
                ? $this->customer->name.' quedó activo.'
                : $this->customer->name.' quedó desactivado. Ya no aparece al crear documentos nuevos.');

        $this->confirmando = null;
    }

    /* =====================================================================
     | LOS CERTIFICADOS DE EXENCIÓN
     * ================================================================== */

    public function abrirCertificado(?int $renovar = null): void
    {
        $this->exigirPermiso('update');

        $this->resetValidation();
        $this->reset(['certificate_number', 'certNotas']);

        $this->renovandoDesde = $renovar;

        /* -----------------------------------------------------------------
         | LAS FECHAS SE PROPONEN SOLAS
         |
         | RB-014: el Annual Resale Certificate de Florida vence el 31 de
         | diciembre, siempre. No es una fecha que el usuario elija: es
         | una característica del documento.
         |
         | Así que se propone el año en curso completo y queda editable
         | para el caso raro —un certificado emitido a mitad de año
         | empieza el día que lo emitieron, no el 1 de enero.
         * -------------------------------------------------------------- */
        $anio = (int) now()->year;

        /*
         | Si se está renovando en diciembre, lo que hace falta casi
         | siempre es el certificado del año QUE VIENE. Proponer el del
         | año en curso obligaría a corregir las tres fechas a mano justo
         | en el mes en que esto se hace.
         */
        if ($renovar && now()->month === 12) {
            $anio++;
        }

        $this->issued_year = $anio;
        $this->valid_from  = Carbon::create($anio, 1, 1)->toDateString();
        $this->valid_until = Carbon::create($anio, 12, 31)->toDateString();

        // Al renovar se arrastra el número: suele ser el mismo.
        if ($renovar) {
            $anterior = $this->customer->certificates()->find($renovar);

            $this->certificate_number = $anterior?->certificate_number ?? '';
        }

        $this->modalCertificado = true;
    }

    public function cerrarCertificado(): void
    {
        $this->modalCertificado = false;
        $this->renovandoDesde   = null;
        $this->resetValidation();
    }

    public function guardarCertificado(): void
    {
        $this->exigirPermiso('update');

        $this->validate([
            'certificate_number' => ['required', 'string', 'max:40'],
            'issued_year'        => ['required', 'integer', 'min:2000', 'max:2100'],
            'valid_from'         => ['required', 'date'],
            'valid_until'        => ['required', 'date', 'after_or_equal:valid_from'],
            'certNotas'          => ['nullable', 'string', 'max:1000'],
        ], [
            'certificate_number.required' => 'Escriba el número del certificado.',
            'valid_until.after_or_equal'  => 'La fecha de vencimiento no puede ser anterior a la de inicio.',
        ], [
            'certificate_number' => 'número del certificado',
            'issued_year'        => 'año',
            'valid_from'         => 'vigente desde',
            'valid_until'        => 'vigente hasta',
        ]);

        /* -----------------------------------------------------------------
         | EL ÍNDICE ÚNICO ES (cliente, número, año)
         |
         | Se comprueba aquí antes de intentar el INSERT. Dejar que lo
         | rechace MySQL daría una pantalla de error de SQL en vez de un
         | mensaje: la excepción de clave duplicada no dice qué campo
         | repetir ni qué hacer.
         * -------------------------------------------------------------- */
        $repetido = $this->customer->certificates()
            ->where('certificate_number', $this->certificate_number)
            ->where('issued_year', $this->issued_year)
            ->exists();

        if ($repetido) {
            $this->addError('certificate_number',
                'Este cliente ya tiene registrado el certificado '
                .$this->certificate_number.' del año '.$this->issued_year.'.');

            return;
        }

        $empresa = app(CompanyContext::class)->get();

        $this->customer->certificates()->create([
            'company_id'         => $empresa?->id,
            'type'               => 'annual_resale',
            'certificate_number' => $this->certificate_number,
            'issued_year'        => $this->issued_year,
            'valid_from'         => $this->valid_from,
            'valid_until'        => $this->valid_until,

            /*
             | Nace verificado porque quien lo teclea está mirando el
             | papel. El estado 'pending' del enum existe para el caso de
             | un certificado que llegó por correo y todavía no se
             | comprobó contra el portal del estado; ese flujo todavía no
             | tiene pantalla.
             */
            'status'      => CertificateStatus::Active->value,
            'verified_at' => now(),
            'verified_by' => auth()->id(),

            'notes' => $this->certNotas ?: null,
        ]);

        /*
         | El observer del certificado ya recalculó `customers.tax_exempt`.
         | Hay que releer el modelo: la instancia que tiene este componente
         | en memoria sigue con el valor de antes, y la pantalla mostraría
         | "no exento" justo después de haberlo hecho exento.
         */
        $this->customer->refresh();

        $this->cerrarCertificado();

        session()->flash('exito',
            'Certificado registrado. '.$this->customer->name
            .' queda exento de impuesto hasta el '
            .Carbon::parse($this->valid_until)->format('d/m/Y').'.');
    }

    /**
     * Revoca un certificado.
     *
     * No se borra: una factura vieja puede estar apuntando a él como
     * prueba de por qué no se le cobró impuesto (RB-015). Borrarlo
     * dejaría esa factura sin respaldo ante una auditoría del estado.
     *
     * ── POR QUÉ LA CONFIRMACIÓN VIVE EN EL COMPONENTE ──
     *
     * Livewire trae `wire:confirm`, que resolvería esto en un atributo.
     * Pero en todo el proyecto no se usa ni una vez —los listados de
     * presupuestos y de pagos confirman con una propiedad y un par de
     * botones— y no hay `composer.json` en el paquete para comprobar
     * qué versión de Livewire está instalada.
     *
     * Si la versión fuera anterior a la que lo trae, `wire:confirm`
     * no daría error: sería un atributo HTML que el navegador ignora, y
     * el botón revocaría el certificado sin preguntar nada. Ese es
     * exactamente el tipo de fallo que nadie reporta hasta que ya pasó.
     */
    public ?int $revocando = null;

    public function pedirRevocar(int $certificadoId): void
    {
        $this->revocando = $certificadoId;
    }

    public function cancelarRevocar(): void
    {
        $this->revocando = null;
    }

    public function revocar(): void
    {
        $this->exigirPermiso('update');

        $certificado = $this->customer->certificates()->find($this->revocando);

        if (! $certificado) {
            $this->revocando = null;

            return;
        }

        $certificado->update(['status' => CertificateStatus::Revoked->value]);

        $this->customer->refresh();

        $this->revocando = null;

        session()->flash('exito',
            'Certificado '.$certificado->certificate_number.' revocado. '
            .'Las facturas que ya lo usaban lo conservan como respaldo.');
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        $this->customer->load([
            'addresses',
            'contacts',
            'certificates' => fn ($q) => $q->orderByDesc('valid_until'),
        ]);

        /* -----------------------------------------------------------------
         | EL HISTORIAL
         |
         | Los tres modelos llevan BelongsToCompany, así que esto ya sale
         | filtrado por la empresa activa sin escribir ningún where.
         |
         | Se limita a los últimos cinco de cada uno: la ficha es para
         | hacerse una idea, no para auditar. Quien necesite la lista
         | completa tiene el listado de cada módulo con el filtro por
         | cliente.
         * -------------------------------------------------------------- */
        $presupuestos = $this->customer->estimates()
            ->latest('issue_date')->limit(5)->get();

        $facturas = $this->customer->invoices()
            ->latest('issue_date')->limit(5)->get();

        /* -----------------------------------------------------------------
         | LOS DOCUMENTOS DEL CLIENTE
         |
         | Los que vencen primero. Un papel con fecha cercana es lo que
         | hay que ver al abrir la ficha; los que no vencen nunca pueden
         | esperar al final.
         |
         | El orden lo hace la base y no PHP: `expires_at` en null va al
         | final en MySQL con este truco del IS NULL, que ordena primero
         | por "tiene fecha o no" y despues por la fecha.
         * -------------------------------------------------------------- */
        $documentos = $this->customer->documents()
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->get();

        return view('livewire.customers.show', [

            'presupuestos' => $presupuestos,
            'facturas'     => $facturas,
            'documentos'   => $documentos,

            /* -------------------------------------------------------------
             | LOS NÚMEROS DE ARRIBA
             |
             | `balance_due` de las facturas abiertas es el que importa:
             | es lo que este cliente debe hoy, y es la primera pregunta
             | que alguien se hace al abrir su ficha.
             * ---------------------------------------------------------- */
            'totales' => [
                'presupuestos' => $this->customer->estimates()->count(),
                'facturas'     => $this->customer->invoices()->count(),
                'debe'         => (float) $this->customer->invoices()->sum('balance_due'),
                'facturado'    => (float) $this->customer->invoices()->sum('total'),
            ],

            /*
             | El certificado vigente HOY, que es el que la factura usaría
             | si se emitiera en este momento.
             */
            'certificadoVigente' => $this->customer->activeExemptionCertificate(),

            'empresaActiva' => app(CompanyContext::class)->get(),
        ]);
    }
}
