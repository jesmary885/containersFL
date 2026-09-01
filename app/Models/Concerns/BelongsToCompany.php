<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla los datos de cada compañía.
 *
 * El modelo que use este trait queda filtrado automáticamente por la
 * compañía activa en TODAS sus consultas, sin que haya que escribirlo.
 *
 * Aplicar SOLO a modelos transaccionales:
 *   Estimate, Invoice, Sale, Rental, Payment, Expense, Trip,
 *   Purchase, Commission, DriverSettlement
 *
 * NO aplicar a maestros compartidos:
 *   Customer, Supplier, Depot, Container, Product, Driver, Vehicle, catálogos
 */
trait BelongsToCompany
{
    /* =====================================================================
     | EVENTOS
     * ================================================================== */

    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = app(\App\Support\CompanyContext::class)->id();

            // Sin compañía activa NO se devuelve todo: se devuelve nada.
            // Es la diferencia entre "olvidé filtrar" (fuga de datos) y
            // "olvidé filtrar" (pantalla vacía que se nota enseguida).
            if ($companyId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where(
                $builder->getModel()->getTable().'.company_id',
                $companyId,
            );
        });

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $model->company_id = app(\App\Support\CompanyContext::class)->id();
            }
        });
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /**
     * Apaga el filtro. Solo para reportes consolidados de las dos compañías.
     * Úsalo consciente: cualquier pantalla operativa debe respetar el scope.
     */
    public function scopeAllCompanies(Builder $query): Builder
    {
        return $query->withoutGlobalScope('company');
    }
}