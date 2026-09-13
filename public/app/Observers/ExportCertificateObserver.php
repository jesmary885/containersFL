<?php

namespace App\Observers;

use App\Models\ExportCertificate;
use App\Models\Container;


class ExportCertificateObserver
{
    /**
     * **Qué vigila:** los certificados CSC de exportación.
        **Qué hace:** marca si el contenedor puede exportarse y hasta cuándo.
     */



        /**
         * Sincroniza la aptitud de exportación del contenedor (RB-016).
         *
         * Sin certificado CSC vigente, un contenedor NO se puede exportar. La
         * pantalla de nueva venta de exportación tiene que poder filtrar la
         * lista de unidades disponibles al instante, sin cruzar tablas.
         *
         * Mismo criterio que el observer anterior: el contenedor guarda la
         * respuesta rápida (is_export_eligible + csc_valid_through), los
         * certificados guardan la verdad, y este observer los mantiene iguales.
         */

    public function saved(ExportCertificate $certificate): void
    {
        $this->sincronizar($certificate);
    }

    public function deleted(ExportCertificate $certificate): void
    {
        $this->sincronizar($certificate);
    }

    protected function sincronizar(ExportCertificate $certificate): void
    {
        $afectados = array_unique(array_filter([
            $certificate->container_id,
            $certificate->getOriginal('container_id'),
        ]));

        foreach ($afectados as $containerId) {
            $container = Container::find($containerId);

            if (! $container) {
                continue;
            }

            /* -------------------------------------------------------
             | Se toma la fecha de vencimiento MÁS LEJANA de todos los
             | certificados del contenedor.
             |
             | Un contenedor puede tener varios certificados a lo largo
             | de su vida: la inspección de 2024, la de 2029... El que
             | manda es el más nuevo.
             |
             | max() devuelve un string de fecha, no un objeto. Se
             | asigna directo porque el cast 'date' del modelo lo
             | convierte al guardar.
             * ---------------------------------------------------- */
            $vencimiento = $container->exportCertificates()->max('valid_through');

            $container->csc_valid_through = $vencimiento;

            /* -------------------------------------------------------
             | Apto para exportar = tiene certificado Y no ha vencido.
             |
             | Fíjate que esto puede DESACTIVAR la marca: si borras el
             | último certificado, el contenedor deja de ser apto. Es
             | lo correcto: vender una exportación sin certificado
             | detiene el contenedor en el puerto.
             * ---------------------------------------------------- */
            $container->is_export_eligible = $vencimiento !== null
                && \Carbon\Carbon::parse($vencimiento)->gte(now()->startOfDay());

            $container->saveQuietly();
        }
    }
}
