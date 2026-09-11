<?php

namespace App\Models;

use App\Enums\BillingCycle;
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
            'billing_cycle'       => BillingCycle::class,
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

            /*
             | RENTA DE YARDA — cobro por dia (hoja RENTAS YARDA del Excel).
             |
             | daily_rate es null en una renta mensual: no aplica, y eso es
             | distinto de valer 0.
             |
             | paid_days es el contador de dias ya pagados. Existe porque la
             | migracion del Excel trae contratos con años de historia sin el
             | detalle de que dia se pago cada cosa: MODUGO tiene 103 dias
             | pagados y no hay forma de reconstruir cuales fueron.
             */
            'daily_rate'          => 'decimal:2',
            'paid_days'           => 'integer',
            'entry_fee'           => 'decimal:2',
            'exit_fee'            => 'decimal:2',
            'paint_fee'           => 'decimal:2',
            'repair_fee'          => 'decimal:2',
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
    /* =====================================================================
     | RENTA DE YARDA · COBRO POR DÍA
     |
     | Reproduce lo que hoy hacen las hojas RENTAS YARDA y REPORTE YARDA
     | del Excel. Los números del contrato de MODUGO sirven de prueba:
     | 324 días transcurridos, 103 pagados, 221 pendientes, $442 de deuda
     | a $2/día. Y con corte al 28/02/2026: 131, 103, 28, $56.
     * ================================================================== */

    public function isDaily(): bool
    {
        return $this->billing_cycle === BillingCycle::Daily;
    }

    /** La tarifa que aplica, sea diaria o mensual. */
    public function rate(): float
    {
        return (float) ($this->isDaily() ? $this->daily_rate : $this->monthly_rate);
    }

    /**
     * Días que el contenedor lleva en la yarda.
     *
     * Cuenta hasta la fecha de corte, o hasta hoy si no se pasa ninguna.
     * Si el contrato ya terminó, se detiene en end_date: los días no
     * siguen corriendo después de que el cliente se llevó el contenedor.
     *
     * El +1 es porque el primer día cuenta. Un contenedor que entra y
     * sale el mismo día pagó un día, no cero. En el Excel el contrato de
     * MODUGO empieza el 21/10/2025 y al 28/02/2026 da 131 días, que es
     * exactamente la diferencia más uno.
     */
    public function daysElapsed(?Carbon $corte = null): int
    {
        if (! $this->isDaily()) {
            return 0;
        }

        $hasta = $corte ?? Carbon::today();

        if ($this->end_date && $this->end_date->lt($hasta)) {
            $hasta = $this->end_date->copy();
        }

        if ($hasta->lt($this->start_date)) {
            return 0;
        }

        return $this->start_date->diffInDays($hasta) + 1;
    }

    /** Días transcurridos que todavía no se pagaron. */
    public function unpaidDays(?Carbon $corte = null): int
    {
        return max(0, $this->daysElapsed($corte) - (int) $this->paid_days);
    }

    /**
     * Lo que el cliente debe por días, sin los cargos de una sola vez.
     *
     * Es la columna DEUDA ACTUAL de la hoja REPORTE YARDA.
     */
    public function dailyDebt(?Carbon $corte = null): float
    {
        return round($this->unpaidDays($corte) * (float) $this->daily_rate, 2);
    }

    /**
     * Los cargos que no dependen del tiempo.
     *
     * Entrada, salida, pintura y reparación. El reporte de yarda los
     * lleva en columnas propias al lado de la deuda de días, así que se
     * suman aparte y no dentro de dailyDebt().
     */
    public function oneTimeFees(): float
    {
        return round(
            (float) $this->entry_fee
            + (float) $this->exit_fee
            + (float) $this->paint_fee
            + (float) $this->repair_fee,
            2,
        );
    }

    /**
     * Registra días pagados.
     *
     * No permite pasar de los días transcurridos: pagar 400 días de un
     * contenedor que lleva 324 en la yarda es un error de dedo, y si se
     * guarda, la deuda queda en negativo y el reporte deja de cuadrar.
     */
    public function recordPaidDays(int $dias): static
    {
        $tope = $this->daysElapsed();

        $this->paid_days = min((int) $this->paid_days + max(0, $dias), $tope);
        $this->save();

        return $this;
    }

    public function generateNextPeriod(): RentalPeriod
    {
        $last   = $this->periods()->latest('period_number')->first();
        $number = $last ? $last->period_number + 1 : 1;
        $start  = $last ? $last->period_end->copy()->addDay() : $this->start_date->copy();

        /* -----------------------------------------------------------------
        | RENTA DE YARDA: EL PERIODO ES UN DIA
        |
        | Un periodo por dia y no uno por mes, porque el cobro es por dia
        | y RB-023 obliga a que la factura diga que periodo cubre. Con
        | periodos mensuales, una factura de 12 dias no podria decir
        | cuales.
        |
        | El ancla de facturacion no aplica: no hay ciclo que anclar.
        * -------------------------------------------------------------- */
        if ($this->isDaily()) {
            return $this->periods()->create([
                'period_number' => $number,
                'period_start'  => $start,
                'period_end'    => $start->copy(),
                'due_date'      => $this->resolveDueDate($start),
                'amount'        => $this->daily_rate,
                'status'        => 'pending',
            ]);
        }

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
