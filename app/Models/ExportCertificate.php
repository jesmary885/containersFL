<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Certificado CSC del contenedor: sin él no se puede exportar.
 * Vence, y hay que avisar antes de que pase.
 */

class ExportCertificate extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'inspected_at'         => 'date',
            'valid_through'        => 'date',
            'sent_to_customer_at'  => 'datetime',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function container() { return $this->belongsTo(Container::class); }
    public function sale()      { return $this->belongsTo(Sale::class); }
    public function invoice()   { return $this->belongsTo(Invoice::class); }

    /** El PDF del certificado que se le manda al cliente (RB-016). */
    public function document()  { return $this->belongsTo(Document::class); }

    public function documents() { return $this->morphMany(Document::class, 'documentable'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeValid(Builder $q): Builder
    {
        return $q->whereDate('valid_through', '>=', now());
    }

    public function scopeExpiringSoon(Builder $q, int $days = 60): Builder
    {
        return $q->whereDate('valid_through', '>=', now())
            ->whereDate('valid_through', '<=', now()->addDays($days));
    }

    public function isValid(): bool
    {
        return $this->valid_through->gte(now()->startOfDay());
    }
}
