<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TRABAJADOR DE LA EMPRESA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * La persona, no el usuario del sistema. Miguelito vende y cobra
 * comision; puede que nunca abra el sistema.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hired_at'                   => 'date',
            'default_commission_amount'  => 'decimal:2',
            'default_commission_percent' => 'decimal:2',
            'is_active'                  => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function company() { return $this->belongsTo(Company::class); }
    public function driver()  { return $this->belongsTo(Driver::class); }
    public function user()    { return $this->belongsTo(User::class); }

    public function sales()   { return $this->hasMany(Invoice::class, 'sold_by_employee_id'); }

    /* =====================================================================
     | COMO SE LLAMA
     * ================================================================== */

    /** Nombre y apellido. */
    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public const ROLES = [
        'vendedor'      => 'Vendedor',
        'chofer'        => 'Chofer',
        'secretaria'    => 'Secretaria',
        'administrador' => 'Administrador',
        'taller'        => 'Taller y reparación',
        'limpieza'      => 'Limpieza',
        'otro'          => 'Otro',
    ];

    /* =====================================================================
     | CONSULTAS
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Los que pueden aparecer como vendedor en una factura. */
    public function scopeSalespeople(Builder $q): Builder
    {
        /*
         | Vendedores y administradores.
         |
         | Los administradores entran porque Denisse y Michael venden
         | tambien. Dejarlos fuera obligaria a registrarlos dos veces, una
         | como administrador y otra como vendedor.
         */
        return $q->active()->whereIn('role', ['vendedor', 'administrador']);
    }

    /**
     * Los de la empresa activa, mas los que trabajan para las dos.
     *
     * El `orWhereNull` es lo que hace que Denisse aparezca esté donde
     * esté parado el usuario.
     */
    public function scopeForCompany(Builder $q, ?int $companyId): Builder
    {
        return $q->where(fn ($qq) => $qq
            ->whereNull('company_id')
            ->orWhere('company_id', $companyId));
    }
}
