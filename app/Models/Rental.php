<?php

namespace App\Models;

use App\Enums\RentalStatus;
use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    use HasFactory, \App\Models\Concerns\HasDocumentAddresses, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status'              => RentalStatus::class,
            'start_date'          => 'date',
            'end_date'            => 'date',

            /*
             | Direcciones congeladas del dia del contrato (RB-035).
             | Mismos nombres que estimate, sale e invoice: convertir un
             | documento en otro es copiar, no traducir.
             */
            'bill_to'             => 'array',
            'ship_to'             => 'array',

            'monthly_rate'        => 'decimal:2',
            'tax_rate'            => 'decimal:2',
            'pickup_fee'          => 'decimal:2',
            'delivery_amount'     => 'decimal:2',
            'late_fee_amount'     => 'decimal:2',
            'auto_apply_late_fee' => 'boolean',
            'auto_invoice'        => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function customer() { return $this->belongsTo(Customer::class); }
    public function depot()    { return $this->belongsTo(Depot::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
    public function trips()    { return $this->hasMany(Trip::class); }

    public function periods()
    {
        return $this->hasMany(RentalPeriod::class)->orderBy('period_number');
    }

    public function containers()
    {
        return $this->belongsToMany(Container::class, 'rental_containers')
            ->withPivot('monthly_rate', 'from_date', 'to_date')
            ->withTimestamps();
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', RentalStatus::Active);
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Crea el siguiente período de cobro.
     *
     * El ciclo se ancla al día del delivery, no al primero de mes.
     * addMonthNoOverflow() más el min() con daysInMonth resuelven el
     * caso feo: un delivery el 31 de enero no puede generar "31 de
     * febrero", cae al último día del mes.
     */
    public function generateNextPeriod(): RentalPeriod
    {
        $last   = $this->periods()->latest('period_number')->first();
        $number = $last ? $last->period_number + 1 : 1;
        $start  = $last ? $last->period_end->copy()->addDay() : $this->start_date->copy();

        $anchor   = $this->billing_anchor_day;
        $end      = $start->copy()->addMonthNoOverflow();
        $end->day = min($anchor, $end->daysInMonth);
        $end      = $end->subDay();

        return $this->periods()->create([
            'period_number' => $number,
            'period_start'  => $start,
            'period_end'    => $end,
            'due_date'      => $this->resolveDueDate($start),
            'amount'        => $this->monthly_rate,
            'status'        => 'pending',
        ]);
    }

    /** Día de vencimiento configurable por compañía (default: día 5). */
    protected function resolveDueDate(Carbon $periodStart): Carbon
    {
        $dueDay = (int) $this->company->setting('rentals', 'due_day', 5);

        return $periodStart->copy()->startOfMonth()->addDays($dueDay - 1);
    }
}
