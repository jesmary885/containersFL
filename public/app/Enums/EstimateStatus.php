<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

enum EstimateStatus: string
{
  

     use ConcernsHasOptions;

    case Draft     = 'draft';

    /*
     | PROCESADO — armado y revisado, todavia no enviado.
     |
     | Es el paso que faltaba. Antes solo habia "borrador" y "enviada",
     | y el boton decia "Guardar y enviar correo" cuando el usuario
     | todavia no habia VISTO como quedaba el documento. Se enviaba a
     | ciegas y despues se corregia por telefono.
     |
     | Ahora: procesar arma el documento y lo enseña. Enviar es un
     | segundo acto deliberado, ya con el documento delante.
     */
    case Processed = 'processed';

    case Sent      = 'sent';
    case Accepted  = 'accepted';
    case Rejected  = 'rejected';
    case Expired   = 'expired';
    case Converted = 'converted';   // ya se convirtió en factura

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Borrador',
            self::Processed => 'Por revisar',
            self::Sent      => 'Enviada',
            self::Accepted  => 'Aceptada',
            self::Rejected  => 'Rechazada',
            self::Expired   => 'Vencida',
            self::Converted => 'Convertida a factura',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Accepted, self::Converted => 'green',
            self::Sent                      => 'blue',
            self::Rejected                  => 'red',
            self::Expired                   => 'yellow',
            self::Processed                 => 'purple',
            self::Draft                     => 'gray',
        };
    }

    /** Solo se convierte una vez, y solo si el cliente la aceptó o la recibió. */
    public function canConvert(): bool
    {
        return $this->is(self::Sent, self::Accepted);
    }

    /**
     * ¿Ya salio del escritorio?
     *
     * Es la linea que separa lo que se puede hacer de lo que no.
     * Imprimir, duplicar, convertir en factura y registrar la respuesta
     * del cliente son cosas que solo tienen sentido con un documento que
     * el cliente RECIBIO. Antes estaban todas disponibles sobre un
     * borrador a medio armar.
     */
    public function isOut(): bool
    {
        return $this->is(self::Sent, self::Accepted, self::Rejected, self::Converted, self::Expired);
    }

    /** Esperando que alguien lo revise y le de a enviar. */
    public function isPendingReview(): bool
    {
        return $this === self::Processed;
    }

    /** Ya no se puede editar. */
    public function isClosed(): bool
    {
        return $this->is(self::Converted, self::Rejected, self::Expired);
    }
}