<?php

namespace App\Enums;

use App\Concerns\HasOptions;
use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/**
 * Estado del certificado de exención de impuestos (RB-014).
 *
 * OJO con la diferencia entre el ESTADO y la VIGENCIA:
 *
 *   - El estado dice si nosotros lo damos por bueno.
 *   - La vigencia la dan las fechas valid_from / valid_until.
 *
 * Un certificado puede estar en 'active' y aun así haber vencido el
 * 12/31, si nadie corrió el comando que los expira. Por eso
 * TaxExemptionCertificate::isValid() comprueba las DOS cosas, y el
 * TaxExemptionCertificateObserver también.
 *
 * Nunca decidir la exención solo por el estado.
 */
enum CertificateStatus: string
{
    use ConcernsHasOptions;

    case Pending = 'pending';   // recibido, sin verificar
    case Active  = 'active';    // verificado y en vigor
    case Expired = 'expired';   // pasó su valid_until
    case Revoked = 'revoked';   // el estado de Florida lo anuló

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por verificar',
            self::Active  => 'Vigente',
            self::Expired => 'Vencido',
            self::Revoked => 'Revocado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active  => 'green',
            self::Pending => 'yellow',
            self::Expired => 'gray',
            self::Revoked => 'red',
        };
    }

    /**
     * Puede justificar que no se cobre impuesto.
     *
     * Es condición NECESARIA, no suficiente: además hay que comprobar
     * que la fecha de la factura caiga dentro del rango de validez.
     */
    public function canExempt(): bool
    {
        return $this === self::Active;
    }

    /**
     * RB-014: el certificado se renueva cada año y vence el 12/31.
     *
     * Solo tiene sentido renovar los que estaban bien. Un revocado no
     * se renueva: hay que pedir uno nuevo desde cero.
     */
    public function isRenewable(): bool
    {
        return $this->is(self::Active, self::Expired);
    }

    /** Estados que ya no cambian solos. */
    public function isFinal(): bool
    {
        return $this === self::Revoked;
    }
}