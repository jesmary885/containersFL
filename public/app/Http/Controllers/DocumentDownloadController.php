<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * DESCARGAR UN DOCUMENTO ADJUNTO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * El archivo no se sirve nunca desde una dirección pública. Se pide por
 * el id del registro, aquí se comprueba quién pide y recién entonces se
 * entrega el contenido.
 *
 * ── POR QUÉ NO SE USA temporaryUrl() ──
 *
 * El modelo Document lo trae y funciona perfecto contra S3. Pero el
 * disco configurado hoy es `local` (FILESYSTEM_DISK=local en el .env), y
 * el disco local de Laravel no sabe generar enlaces temporales: lanza
 * "This driver does not support creating temporary URLs".
 *
 * Un controlador funciona con los dos discos y además permite lo de
 * abajo, que con un enlace de S3 no se puede hacer.
 *
 * ── LAS TRES COMPROBACIONES ──
 *
 * 1. QUE TENGA PERMISO SOBRE EL MÓDULO DEL DUEÑO DEL ARCHIVO.
 *    Un adjunto de un cliente lo ve quien puede ver clientes. Uno de un
 *    gasto, quien puede ver gastos. Sin esto, cualquiera con sesión
 *    abierta descarga cualquier archivo probando números en la
 *    dirección: /documentos/1/descargar, /documentos/2/descargar...
 *
 * 2. QUE EL ARCHIVO SEA DE SU EMPRESA.
 *    `documents.company_id` en null significa "de las dos" —el
 *    certificado de exención del cliente es del cliente—. Con valor,
 *    solo lo ve esa empresa: un contrato firmado con FLCHR no se
 *    descarga desde RST.
 *
 * 3. QUE EL ARCHIVO EXISTA EN EL DISCO.
 *    Si alguien lo borró a mano del servidor, el registro sigue en la
 *    base. Mejor un 404 honesto que una descarga de cero bytes que el
 *    usuario abre y no entiende.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class DocumentDownloadController extends Controller
{
    /**
     * A qué permiso corresponde cada tipo de dueño.
     *
     * La llave es la clase del modelo al que está adjunto el archivo.
     * Lo que no esté en esta lista NO se descarga: es más seguro pedir
     * que alguien agregue un renglón acá el día que adjunte documentos
     * a algo nuevo, que dejar abierto por omisión lo que todavía no se
     * pensó.
     */
    protected const PERMISO_POR_DUENO = [
        \App\Models\Customer::class  => 'customers.view',
        \App\Models\Invoice::class   => 'invoices.view',
        \App\Models\Container::class => 'containers.view',
        \App\Models\Expense::class   => 'expenses.view',
        \App\Models\Purchase::class  => 'purchases.view',
        \App\Models\Supplier::class  => 'suppliers.view',
        \App\Models\Trip::class      => 'trips.view',
        \App\Models\Rental::class    => 'rentals.view',
        \App\Models\Sale::class      => 'sales.view',
    ];

    public function __invoke(Document $document): StreamedResponse
    {
        $usuario = auth()->user();

        /* -----------------------------------------------------------------
         | 1 · EL PERMISO
         * -------------------------------------------------------------- */
        $permiso = self::PERMISO_POR_DUENO[$document->documentable_type] ?? null;

        abort_if($permiso === null, 403, 'Este tipo de documento no se puede descargar desde aquí.');
        abort_unless($usuario?->can($permiso), 403, 'No tiene permiso para ver este documento.');

        /* -----------------------------------------------------------------
         | 2 · LA EMPRESA
         * -------------------------------------------------------------- */
        if ($document->company_id !== null) {
            $empresaActiva = $usuario->currentCompany()?->id;

            abort_if(
                $document->company_id !== $empresaActiva,
                403,
                'Este documento pertenece a la otra empresa. Cambie de empresa arriba para verlo.',
            );
        }

        /* -----------------------------------------------------------------
         | 3 · EL ARCHIVO
         * -------------------------------------------------------------- */
        $disco = Storage::disk($document->disk ?: config('filesystems.default'));

        abort_unless($disco->exists($document->path), 404, 'El archivo ya no está en el servidor.');

        /*
         | download() y no response()->file(): así el navegador lo baja
         | con el nombre original que subió el usuario, y no con el
         | nombre revuelto con el que se guardó en el disco.
         */
        return $disco->download($document->path, $document->name);
    }
}
