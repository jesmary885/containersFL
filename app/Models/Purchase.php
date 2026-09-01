<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Enums\PurchaseType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory, BelongsToCompany;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type'                 => PurchaseType::class,
            'status'               => PurchaseStatus::class,
            'purchase_date'        => 'date',
            'pickup_deadline_at'   => 'date',
            'original_deadline_at' => 'date',  // se guarda para auditar prórrogas
            'subtotal'             => 'decimal:2',
            'pickup_fee'           => 'decimal:2',
            'daily_late_fee'       => 'decimal:2',
            'tax_amount'           => 'decimal:2',
            'total'                => 'decimal:2',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function depot()    { return $this->belongsTo(Depot::class); }
    public function items()    { return $this->hasMany(PurchaseItem::class); }
    public function trips()    { return $this->hasMany(Trip::class); }

    /** Fees que cobró el depósito por pasarse del plazo de recogida. */
    public function expenses() { return $this->hasMany(Expense::class); }

    public function containers()
    {
        return $this->hasManyThrough(Container::class, PurchaseItem::class);
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /** Unidades compradas que todavía no se han retirado del depósito. */
    public function getPendingQuantityAttribute(): int
    {
        return (int) $this->items->sum('quantity')
             - (int) $this->items->sum('received_quantity');
    }

    /** Releases vencidos: el depósito ya empezó a cobrar fee diario. */
    public function scopeOverdueForPickup(Builder $q): Builder
    {
        return $q->where('type', PurchaseType::Release)
            ->whereIn('status', ['open', 'partially_received'])
            ->whereDate('pickup_deadline_at', '<', now());
    }

    public function getOverdueDaysAttribute(): int
    {
        return $this->pickup_deadline_at && $this->pickup_deadline_at->isPast()
            ? $this->pickup_deadline_at->diffInDays(now())
            : 0;
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * El estado se DERIVA de las unidades recibidas, nunca se toca a mano.
     * Lo dispara el observer de PurchaseItem.
     */
    public function refreshStatus(): static
    {
        $total    = (int) $this->items()->sum('quantity');
        $received = (int) $this->items()->sum('received_quantity');

        $this->status = match (true) {
            $received === 0     => PurchaseStatus::Open,
            $received >= $total => PurchaseStatus::Received,
            default             => PurchaseStatus::PartiallyReceived,
        };

        $this->saveQuietly();

        return $this;
    }
}
