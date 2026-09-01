<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\Concerns\BelongsToCompany;
use App\Services\PricingResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type'                => TripType::class,
            'status'              => TripStatus::class,
            'origin_address'      => 'array',
            'destination_address' => 'array',
            'scheduled_at'        => 'datetime',
            'completed_at'        => 'datetime',
            'miles'               => 'decimal:2',
            'rate_per_mile'       => 'decimal:2',
            'pickup_fee'          => 'decimal:2',
            'customer_price'      => 'decimal:2',
            'carrier_cost'        => 'decimal:2',
            'driver_pay_percent'  => 'decimal:2',
            'driver_pay'          => 'decimal:2',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function carrier()   { return $this->belongsTo(Carrier::class); }
    public function driver()    { return $this->belongsTo(Driver::class); }
    public function vehicle()   { return $this->belongsTo(Vehicle::class); }
    public function customer()  { return $this->belongsTo(Customer::class); }
    public function depot()     { return $this->belongsTo(Depot::class); }
    public function sale()      { return $this->belongsTo(Sale::class); }
    public function rental()    { return $this->belongsTo(Rental::class); }
    public function purchase()  { return $this->belongsTo(Purchase::class); }
    public function container() { return $this->belongsTo(Container::class); }
    public function expenses()  { return $this->hasMany(Expense::class); }

    public function settlement()
    {
        return $this->belongsTo(DriverSettlement::class, 'driver_settlement_id');
    }

    public function intercompanyInvoice()
    {
        return $this->belongsTo(Invoice::class, 'intercompany_invoice_id');
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Viajes terminados que la transportista todavía no le facturó a la otra compañía. */
    public function scopePendingIntercompanyBilling(Builder $q): Builder
    {
        return $q->where('status', TripStatus::Completed)
            ->whereNull('intercompany_invoice_id');
    }

    /** Viajes terminados que todavía no entraron en una liquidación de chofer. */
    public function scopePendingDriverPayment(Builder $q): Builder
    {
        return $q->where('status', TripStatus::Completed)
            ->where('driver_payment_status', 'pending')
            ->whereNotNull('driver_id');
    }

    /** Lo que quedó del viaje: cobrado − costo del transportista − gastos. */
    public function getMarginAttribute(): float
    {
        return (float) $this->customer_price
             - (float) $this->carrier_cost
             - (float) $this->expenses()->sum('amount');
    }

    /* =====================================================================
     | SUGERENCIAS PARA EL FORMULARIO
     |
     | Estos métodos NO guardan nada. Solo proponen un número que el
     | usuario puede aceptar o sobrescribir antes de guardar.
     * ================================================================== */

    /** Pago del chofer sugerido: % del precio cobrado al cliente. */
    public function suggestDriverPay(): float
    {
        $percent = app(PricingResolver::class)
            ->driverPayPercent($this->company, $this->driver, $this);

        return round((float) $this->customer_price * $percent / 100, 2);
    }

    /** Pickup: fee del depósito. Delivery: millas × tarifa. */
    public function suggestCustomerPrice(): float
    {
        if ($this->type === TripType::Pickup) {
            return app(PricingResolver::class)->pickupFee($this->company, $this->depot);
        }

        $rate = (float) ($this->rate_per_mile
            ?? app(PricingResolver::class)->ratePerMile($this->company, $this->carrier_id));

        return round((float) $this->miles * $rate, 2);
    }
}
