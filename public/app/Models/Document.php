<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;


/**
 * Archivo adjunto a cualquier cosa: cliente, contenedor, factura, gasto.
 *
 * La relación morphTo es lo que permite una sola tabla para todo, en vez
 * de customer_documents, container_documents, invoice_documents...
 */

class Document extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'category'   => DocumentCategory::class,
            'size_bytes' => 'integer',
            'expires_at' => 'date',
            'attach_to_invoice' => 'boolean',
        ];
    }

    /* =====================================================================
     | EVENTOS
     * ================================================================== */

    /** Al borrar el registro, borra también el archivo del disco. */
    protected static function booted(): void
    {
        static::deleted(function (Document $doc) {
            Storage::disk($doc->disk ?? 'local')->delete($doc->path);
        });
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /** A qué está adjunto. Puede ser cualquier modelo. */
    public function documentable() { return $this->morphTo(); }

    public function uploadedBy() { return $this->belongsTo(User::class, 'uploaded_by'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeExpiringSoon(Builder $q, int $days = 30): Builder
    {
        return $q->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', now())
            ->whereDate('expires_at', '<=', now()->addDays($days));
    }

    public function scopeExpired(Builder $q): Builder
    {
        return $q->whereNotNull('expires_at')->whereDate('expires_at', '<', now());
    }

    public function scopeCategory(Builder $q, DocumentCategory $category): Builder
    {
        return $q->where('category', $category);
    }

    /**
     * Enlace temporal de descarga (60 min).
     * Nunca se expone la ruta real del archivo.
     */
    public function temporaryUrl(int $minutes = 60): string
    {
        return Storage::disk($this->disk ?? 'local')
            ->temporaryUrl($this->path, now()->addMinutes($minutes));
    }

    /** "2.4 MB" para la pantalla. */
    public function getReadableSizeAttribute(): string
    {
        $bytes = (int) $this->size_bytes;

        return match (true) {
            $bytes >= 1048576 => round($bytes / 1048576, 1).' MB',
            $bytes >= 1024    => round($bytes / 1024).' KB',
            default           => $bytes.' B',
        };
    }
}
