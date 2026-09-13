<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum TripStatus: string
{
    
    use ConcernsHasOptions;

    case Scheduled  = 'scheduled';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled  => 'Programado',
            self::InProgress => 'En curso',
            self::Completed  => 'Completado',
            self::Cancelled  => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed  => 'green',
            self::InProgress => 'blue',
            self::Scheduled  => 'yellow',
            self::Cancelled  => 'red',
        };
    }

    /**
     * Solo los completados entran en la factura intercompañía semanal
     * y en la liquidación del chofer.
     */
    public function isBillable(): bool
    {
        return $this === self::Completed;
    }
}