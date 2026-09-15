<?php

namespace App\Livewire\Invoices;

use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Livewire\Concerns\AuthorizesAccess;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * FICHA DE LA FACTURA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Aquí se ve la factura como la va a recibir el cliente, y desde aquí se
 * hacen las tres cosas que se le pueden hacer a un documento fiscal:
 * enviarlo, anularlo y adjuntarle papeles (RB-034).
 *
 * ── LO QUE NO HAY, Y ES A PROPÓSITO ──
 *
 * No hay botón de borrar. Una factura no se borra nunca. Se anula, que
 * es distinto: queda ahí, con su número gastado y el motivo escrito.
 *
 * Un hueco en la numeración es exactamente lo que busca una auditoría
 * del estado, y "se borró por error" no es una respuesta.
 */
#[Layout('layouts.app')]
class Show extends Component
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
    /*
     | WithFileUploads le da al componente la capacidad de recibir
     | archivos. Sin este trait, wire:model sobre un <input type="file">
     | no hace nada.
     */
    use WithFileUploads;

    public Invoice $invoice;

    /** Qué acción está esperando confirmación: 'anular' | null */
    public ?string $confirmando = null;

    /** El motivo de la anulación. Obligatorio. */
    public string $motivoAnulacion = '';

    /* =====================================================================
     | LOS ADJUNTOS (RB-034)
     * ================================================================== */

    /**
     * El archivo que el usuario acaba de elegir.
     *
     * El atributo #[Validate] pone la regla justo encima del campo, que
     * es más fácil de leer que tenerla en un método aparte.
     *
     * 10 MB de tope: un PDF escaneado de dos páginas pesa dos o tres.
     * Más que eso normalmente es alguien subiendo una foto sin comprimir.
     */
    #[Validate('nullable|file|max:10240')]
    public $archivo = null;

    public string $categoriaArchivo = 'other';

    /** Si el documento viaja CON la factura al cliente o se queda interno. */
    public bool $viajaConLaFactura = true;

    /* =====================================================================
     | ARRANQUE
     * ================================================================== */

    /**
     * La factura llega ya resuelta por la ruta, y llega filtrada por
     * compañía: si alguien escribe a mano el id de una factura de la otra
     * empresa, Laravel responde 404 antes de llegar hasta aquí.
     */
    public function mount(Invoice $invoice): void
    {
        $this->exigirPermiso('view');

        $this->invoice = $invoice->load([
            'items.product',
            'items.container.size',
            'customer',
            'estimate',
            'createdBy',
            'exemptionCertificate',
            'documents.uploadedBy',
            'payments',
        ]);
    }

    /* =====================================================================
     | ENVIAR
     * ================================================================== */

    /**
     * Deja constancia de que la factura salió.
     *
     * Todavía no manda el correo: el envío real lo hará el módulo de
     * notificaciones, y cuando exista llamará a este mismo método.
     *
     * Se permite reenviar una ya enviada porque los clientes piden
     * copias, y cada envío actualiza la fecha.
     */
    public function marcarEnviada(): void
    {
        $this->exigirPermiso('send');

        try {
            $this->invoice->markAsSent();
            $this->invoice->refresh();

            session()->flash('exito', 'Factura marcada como enviada.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /* =====================================================================
     | ANULAR
     * ================================================================== */

    public function confirmar(string $accion): void
    {
        $this->confirmando     = $accion;
        $this->motivoAnulacion = '';
        $this->resetValidation();
    }

    public function cancelarConfirmacion(): void
    {
        $this->confirmando = null;
    }

    /**
     * Anula la factura.
     *
     * El trabajo de verdad está en Invoice::void(), no aquí: comprueba
     * que no esté ya anulada, que no tenga pagos aplicados y que el
     * motivo no venga vacío. Aquí solo se atrapan esos avisos y se le
     * muestran al usuario en palabras.
     *
     * Es a propósito. Una regla escrita en la pantalla se salta llamando
     * al modelo desde otro lado; una escrita en el modelo no.
     */
    public function anular(): void
    {
        /*
         | Anular es el acto mas caro de esta pantalla: el numero queda
         | consumido para siempre y el documento no se puede recuperar.
         | Tiene su propio permiso justo por eso, y el RoleSeeder solo se
         | lo da a contabilidad.
         */
        $this->exigirPermiso('void');

        $this->validate(
            ['motivoAnulacion' => 'required|string|min:10|max:255'],
            [
                'motivoAnulacion.required' => 'Escriba por qué se anula esta factura.',
                'motivoAnulacion.min'      => 'Explique un poco más: dentro de dos años este texto '
                                            . 'tiene que servir para entender qué pasó.',
            ],
        );

        try {
            $this->invoice->void($this->motivoAnulacion);
            $this->invoice->refresh();

            $this->confirmando = null;

            session()->flash('exito',
                'Factura '.$this->invoice->invoice_number.' anulada. El número queda '
                .'consumido y no se reutiliza.');

        } catch (\Throwable $e) {
            $this->confirmando = null;

            session()->flash('error', $e->getMessage());
        }
    }

    /* =====================================================================
     | ADJUNTOS (RB-034)
     * ================================================================== */

    /**
     * Sube un archivo y lo cuelga de la factura.
     *
     * ── DÓNDE SE GUARDA ──
     *
     * En el disco 'local', que en Laravel es storage/app/private: una
     * carpeta a la que el navegador NO llega directamente.
     *
     * Eso es a propósito. Si los archivos estuvieran en la carpeta
     * pública, cualquiera que adivinara la dirección podría descargar la
     * autorización de tarjeta firmada de un cliente, con los números
     * completos. Guardándolos fuera, la única forma de bajarlos es por el
     * método descargar() de más abajo, que primero comprueba quién está
     * pidiendo el archivo.
     *
     * ── LA RUTA ──
     *
     * facturas/{id}/{nombre}. Agrupar por factura hace que respaldar o
     * limpiar sea trivial, y evita que dos archivos con el mismo nombre
     * de dos facturas distintas se pisen.
     */
    public function subirArchivo(): void
    {
        // Adjuntar cambia el documento: es edicion, no lectura.
        $this->exigirPermiso('update');

        $this->validate([
            'archivo'          => 'required|file|max:10240',
            'categoriaArchivo' => 'required|string',
        ], [
            'archivo.required' => 'Elija un archivo.',
            'archivo.max'      => 'El archivo no puede pasar de 10 MB.',
        ]);

        try {
            $nombreOriginal = $this->archivo->getClientOriginalName();

            $ruta = $this->archivo->store(
                'facturas/'.$this->invoice->id,
                'local',
            );

            $this->invoice->attachDocument(
                path: $ruta,
                nombre: $nombreOriginal,
                disk: 'local',
                categoria: DocumentCategory::tryFrom($this->categoriaArchivo) ?? DocumentCategory::Other,
                viajaConLaFactura: $this->viajaConLaFactura,
                mime: $this->archivo->getMimeType(),
                bytes: $this->archivo->getSize(),
            );

            // Se limpia el formulario para que se pueda subir otro sin
            // tener que recargar la pantalla.
            $this->reset(['archivo', 'categoriaArchivo', 'viajaConLaFactura']);
            $this->viajaConLaFactura = true;

            $this->invoice->load('documents.uploadedBy');

            session()->flash('exito', 'Documento adjuntado.');

        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo subir el archivo: '.$e->getMessage());
        }
    }

    /**
     * Descarga un adjunto.
     *
     * ── POR QUÉ PASA POR AQUÍ Y NO POR UN ENLACE DIRECTO ──
     *
     * Porque este método comprueba dos cosas antes de entregar nada:
     *
     *   1. Que el documento pertenezca a ESTA factura. Sin eso, cambiar
     *      el número en la petición dejaría bajar cualquier archivo del
     *      sistema.
     *
     *   2. Que el archivo siga existiendo en el disco. Si alguien lo
     *      borró a mano, sale un aviso en vez de una pantalla de error.
     *
     * Y la factura, a su vez, ya está filtrada por compañía. Así que un
     * usuario de RS Transport no puede bajar un adjunto de FLCHR ni
     * conociendo el número exacto.
     */
    public function descargar(int $documentId)
    {
        /*
         | Bajar un adjunto es leer, y el mount() ya exigio 'view'. Se
         | repite igual porque este metodo DEVUELVE UN ARCHIVO: si algun
         | dia se llama desde otro sitio sin pasar por el mount, el
         | permiso tiene que seguir estando.
         */
        $this->exigirPermiso('view');

        $documento = $this->invoice->documents()->find($documentId);

        if (! $documento) {
            session()->flash('error', 'Ese documento no pertenece a esta factura.');

            return null;
        }

        $disco = $documento->disk ?: 'local';

        if (! Storage::disk($disco)->exists($documento->path)) {
            session()->flash('error',
                'El archivo "'.$documento->name.'" ya no está en el servidor. '
                .'Es posible que se haya borrado a mano.');

            return null;
        }

        return Storage::disk($disco)->download($documento->path, $documento->name);
    }

    /**
     * Quita un adjunto.
     *
     * El modelo Document borra el archivo del disco solo, en su evento
     * "deleted". Así no queda basura ocupando espacio cada vez que
     * alguien se equivoca de archivo.
     */
    public function quitarArchivo(int $documentId): void
    {
        $this->exigirPermiso('update');

        $documento = $this->invoice->documents()->find($documentId);

        if (! $documento) {
            return;
        }

        /* -----------------------------------------------------------------
         | LOS CERTIFICADOS NO SE QUITAN DESDE AQUÍ.
         |
         | Un certificado de exención o de exportación no es un adjunto
         | cualquiera: es la prueba de por qué no se cobró impuesto
         | (RB-015) o de que la venta salió del país (RB-016).
         |
         | Se administran desde la ficha del cliente y desde la venta, que
         | es donde se les puede poner fecha de vencimiento y llevarles el
         | control. Borrarlos desde aquí dejaría una factura exenta sin
         | nada que la justifique.
         * -------------------------------------------------------------- */
        if ($documento->category->is(
            DocumentCategory::TaxExemption,
            DocumentCategory::ExportCertificate,
        )) {
            session()->flash('error',
                'Los certificados no se quitan desde la factura: son la prueba de por qué '
                .'no se cobró impuesto. Se administran desde la ficha del cliente.');

            return;
        }

        $documento->delete();

        $this->invoice->load('documents.uploadedBy');

        session()->flash('exito', 'Documento eliminado.');
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        return view('livewire.invoices.show', [
            /*
             | Las líneas ya agrupadas, tal como las ve el cliente
             | (RB-007). El desglose interno viaja dentro de cada renglón,
             | en la clave 'detalle', para poder mostrarlo en pantalla sin
             | que salga impreso.
             */
            'renglones'  => $this->invoice->printableLines(),
            'categorias' => DocumentCategory::options(),
        ]);
    }
}
