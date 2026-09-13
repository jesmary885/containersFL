<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * El contador de cada tipo de documento por compañía.
 *
 * IMPORTANTE: nunca se toca directo. El único que la modifica es
 * Company::nextNumber(), que la bloquea antes de leerla. Editarla
 * a mano puede producir números duplicados.
 */

class DocumentSequence extends Model
{
       use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'next_number'   => 'integer',
            'padding'       => 'integer',
            'current_year'  => 'integer',
            'resets_yearly' => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function company() { return $this->belongsTo(Company::class); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Cómo se vería el próximo número, SIN consumirlo. Solo para mostrar. */
    public function preview(): string
    {
        return ($this->prefix ?? '')
            .str_pad((string) $this->next_number, $this->padding, '0', STR_PAD_LEFT);
    }
}
