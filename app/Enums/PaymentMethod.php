<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum PaymentMethod: string
{

    use ConcernsHasOptions;

    case Cash       = 'cash';
    case Check      = 'check';
    case Zelle      = 'zelle';
    case Ach        = 'ach';
    case Wire       = 'wire';
    case CreditCard = 'credit_card';

    /*
     | Square es LA plataforma de pago de la empresa. Lo confirmo Denisse
     | en el levantamiento del 14 de agosto y en el documento
     | "Invoice_Square_information": "La Plataforma de pago es Square",
     | con 3.5% de recargo en tarjeta de credito.
     |
     | Va aparte de CreditCard porque no es lo mismo: CreditCard es como
     | pago el cliente; Square es por donde entro el dinero. Un cobro con
     | tarjeta por telefono y uno por link de Square se conciliana
     | distinto en el banco.
     */
    case Square     = 'square';
    case Other      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash       => 'Efectivo',
            self::Check      => 'Cheque',
            self::Zelle      => 'Zelle',
            self::Ach        => 'ACH',
            self::Wire       => 'Transferencia',
            self::CreditCard => 'Tarjeta de crédito',
            self::Square     => 'Square',
            self::Other      => 'Otro',
        };
    }

    /** Si lleva recargo por procesamiento. Solo tarjeta. */
    public function hasFee(): bool
    {
        return $this === self::CreditCard;
    }

    /** El dinero entra al instante o hay que esperar a que compense. */
    public function isInstant(): bool
    {
        return $this->is(self::Cash, self::Zelle, self::CreditCard);
    }

    /** Estos exigen número de referencia para conciliar con el banco. */
    public function requiresReference(): bool
    {
        return $this->is(self::Check, self::Ach, self::Wire, self::Zelle);
    }
}