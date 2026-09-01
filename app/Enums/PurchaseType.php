<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum PurchaseType: string
{

    use ConcernsHasOptions;

    case Single  = 'single';    // se paga y se retira de una vez
    case Release = 'release';   // se paga un lote y se retira por partes, con plazo

    public function label(): string
    {
        return match ($this) {
            self::Single  => 'Compra directa',
            self::Release => 'Release',
        };
    }

    /**
     * El release tiene fecha límite de retiro. Pasada esa fecha el
     * depósito cobra fee diario, y eso se registra como gasto.
     */
    public function requiresDeadline(): bool
    {
        return $this === self::Release;
    }

    /** Solo el release admite recepción parcial. */
    public function allowsPartialReceipt(): bool
    {
        return $this === self::Release;
    }
}