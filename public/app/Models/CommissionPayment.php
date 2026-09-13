<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Un abono a una comisión de vendedor. */

class CommissionPayment extends Model
{
    use HasFactory;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'method'  => PaymentMethod::class,
            'amount'  => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    public function commission() { return $this->belongsTo(Commission::class); }
    public function createdBy()  { return $this->belongsTo(User::class, 'created_by'); }
}
