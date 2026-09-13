<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;


enum DocumentCategory: string
{

 use ConcernsHasOptions;

    case TaxExemption       = 'tax_exemption';
    case ExportCertificate  = 'export_certificate';
    case CcAuthorization    = 'cc_authorization';
    case Contract           = 'contract';
    case SupplierInvoice    = 'supplier_invoice';
    case ContainerPhoto     = 'container_photo';
    case Pod                = 'pod';                // prueba de entrega firmada
    case ExpenseReceipt     = 'expense_receipt';
    /*
     | El comprobante del registro de la empresa en Sunbiz.
     |
     | Se verifica antes de darle credito o procesarle una tarjeta, y
     | hasta ahora la verificacion quedaba como una casilla marcada sin
     | nada que la respalde. Si manana alguien pregunta quien verifico y
     | con que, la casilla no prueba nada.
     */
    case SunbizRecord       = 'sunbiz_record';

    case Other              = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TaxExemption      => 'Certificado de exención',
            self::ExportCertificate => 'Certificado de exportación',
            self::CcAuthorization   => 'Autorización de tarjeta',
            self::Contract          => 'Contrato',
            self::SupplierInvoice   => 'Factura de proveedor',
            self::ContainerPhoto    => 'Foto de contenedor',
            self::Pod               => 'Prueba de entrega',
            self::ExpenseReceipt    => 'Recibo de gasto',
            self::SunbizRecord      => 'Registro de Sunbiz',
            self::Other             => 'Otro',
        };
    }

    /** Estos vencen y hay que avisar antes. */
    public function expires(): bool
    {
        return $this->is(
            self::TaxExemption,
            self::ExportCertificate,
            self::CcAuthorization,
            self::Contract,
        );
    }

    /** Datos sensibles: acceso restringido y no se muestran en listados. */
    public function isSensitive(): bool
    {
        return $this === self::CcAuthorization;
    }
}