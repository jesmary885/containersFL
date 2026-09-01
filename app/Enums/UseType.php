<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/**
 * Para qué va a usar el cliente el contenedor.
 *
 * No es cosmético: define el tratamiento fiscal completo de la
 * operación. Es la primera pregunta de la pantalla de venta porque
 * cambia todo lo que viene después.
 *
 *   storage → paga el 7% de sales tax        (RB-006)
 *   export  → exento, pero exige certificado (RB-016)
 *             y normalmente sin delivery     (RB-017)
 */
enum UseType: string
{

    use ConcernsHasOptions;

    case Storage = 'storage';   // almacenamiento en terreno del cliente
    case Export  = 'export';    // sale del país por línea naviera

    public function label(): string
    {
        return match ($this) {
            self::Storage => 'Almacenamiento',
            self::Export  => 'Exportación',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Storage => 'blue',
            self::Export  => 'purple',
        };
    }

    /** RB-006: el sales tax solo aplica al uso local. */
    public function isTaxable(): bool
    {
        return $this === self::Storage;
    }

    /**
     * RB-016: toda venta de exportación requiere certificado CSC
     * emitido en PDF y enviado digitalmente. Sin él no se factura.
     */
    public function requiresExportCertificate(): bool
    {
        return $this === self::Export;
    }

    /**
     * RB-017: en exportación normalmente NO hay delivery (98–99%): el
     * cliente contrata su propia línea naviera.
     *
     * Devuelve false para que la pantalla OCULTE el bloque de delivery
     * por defecto, no para que lo prohíba. La excepción existe:
     * contenedor vacío hacia una terminal, con su cuota de delivery.
     */
    public function expectsDelivery(): bool
    {
        return $this === self::Storage;
    }
}