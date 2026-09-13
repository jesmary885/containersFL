<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\TaxExemptionCertificate;

class TaxExemptionCertificateObserver
{
    /**
     *  **Qué vigila:** los certificados de exención de impuestos.
        **Qué hace:** activa o desactiva la bandera `tax_exempt` del cliente.
     */



        /**
         * Sincroniza el flag customer.tax_exempt con los certificados reales
         * del cliente (RB-014).
         *
         * ¿Por qué existe el flag si se puede consultar la tabla? Porque en la
         * pantalla de nueva venta hay que saber, al instante y sin consultas
         * extra, si hay que cobrar el 7% o no.
         *
         * El flag es la respuesta rápida; los certificados son la verdad. Este
         * observer se asegura de que la respuesta rápida nunca mienta.
         *
         * IMPORTANTE: el flag NO decide nada por sí solo. Al emitir la factura
         * se busca el certificado vigente y se guarda su id en la factura
         * (RB-015). El flag solo precarga el formulario.
         */

     public function saved(TaxExemptionCertificate $certificate): void
    {
        $this->sincronizar($certificate);
    }

    public function deleted(TaxExemptionCertificate $certificate): void
    {
        $this->sincronizar($certificate);
    }

    protected function sincronizar(TaxExemptionCertificate $certificate): void
    {
        // Si el certificado se movió de un cliente a otro, hay que
        // revisar a los dos.
        $afectados = array_unique(array_filter([
            $certificate->customer_id,
            $certificate->getOriginal('customer_id'),
        ]));

        foreach ($afectados as $customerId) {
            $customer = Customer::find($customerId);

            if (! $customer) {
                continue;
            }

            /* -------------------------------------------------------
             | ¿Le queda algún certificado vigente HOY?
             |
             | Vigente = estado activo Y la fecha de hoy cae dentro del
             | rango de validez. Un certificado de 2024 que ya venció
             | el 12/31 no cuenta, aunque siga marcado "active" porque
             | nadie corrió el comando que los expira.
             |
             | exists() no trae los registros, solo pregunta si hay
             | alguno. Es una consulta mucho más barata que get().
             * ---------------------------------------------------- */
            $tieneVigente = $customer->certificates()
                ->where('status', 'active')
                ->whereDate('valid_from', '<=', now())
                ->whereDate('valid_until', '>=', now())
                ->exists();

            // Solo escribimos si el valor cambió de verdad. Evita
            // escrituras inútiles en la base cada vez que alguien
            // edita una nota del certificado.
            if ($customer->tax_exempt !== $tieneVigente) {
                $customer->tax_exempt = $tieneVigente;
                $customer->saveQuietly();
            }
        }
    }
}
