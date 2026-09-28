<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * La memoria de las consultas de distancia a Google.
 *
 * Ver la migración para el porqué. En corto: Google cobra por consulta y
 * la distancia entre dos códigos postales no cambia.
 */
class DistanceLookup extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'miles'      => 'decimal:2',
            'is_manual'  => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    public function getIsUsableAttribute(): bool
    {
        return $this->status === 'ok' && $this->miles !== null;
    }
}
