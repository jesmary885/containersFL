<?php

namespace App\Observers;

use App\Models\Container;
use App\Models\Expense;

/**
 * Mantiene el costo de reacondicionamiento de cada contenedor.
 *
 * ¿Por qué importa? Porque el margen de una venta se calcula contra el
 * costo real puesto en yarda:
 *
 *   costo real = compra + recogida + reacondicionamiento
 *
 * Si pintas un contenedor por 400 y cambias el piso por 600, esos 1,000
 * tienen que estar en el costo antes de que alguien mire el margen. Si
 * no, el reporte dice que ganaste 1,000 más de lo que ganaste.
 */

class ExpenseObserver
{

/***Qué vigila:** los gastos.
**Qué hace:** mantiene actualizado el costo real de cada contenedor. */

   public function saved(Expense $expense): void
    {
        /* -----------------------------------------------------------
         | Puede haber cambiado el contenedor al que apunta el gasto.
         |
         | Ejemplo: registraste un gasto de pintura contra el
         | contenedor 47 y era el 52. Al corregirlo hay que:
         |   - bajarle el costo al 47
         |   - subírselo al 52
         |
         | Por eso recalculamos los DOS: el viejo y el nuevo.
         | array_unique + array_filter descartan nulos y repetidos.
         * -------------------------------------------------------- */
        $afectados = array_unique(array_filter([
            $expense->container_id,
            $expense->getOriginal('container_id'),
        ]));

        foreach ($afectados as $containerId) {
            $this->recalcularCosto($containerId);
        }
    }

    /** Si se borra un gasto, el costo del contenedor tiene que bajar. */
    public function deleted(Expense $expense): void
    {
        if ($expense->container_id) {
            $this->recalcularCosto($expense->container_id);
        }
    }

    /**
     * Suma todos los gastos del contenedor y los guarda en su columna.
     *
     * ¿Por qué guardarlo si se puede calcular al vuelo? Porque la
     * pantalla de inventario lista 300 contenedores con su margen. Sin
     * la columna, serían 300 consultas SUM(). Con la columna, una sola.
     *
     * El precio de guardarlo es tener que mantenerlo: eso es lo que
     * hace este observer.
     */
    protected function recalcularCosto(int $containerId): void
    {
        $container = Container::find($containerId);

        if (! $container) {
            return;   // se borró el contenedor: no hay nada que actualizar
        }

        /* -----------------------------------------------------------
         | OJO: allCompanies().
         |
         | Un contenedor de FLCHR puede tener gastos registrados por
         | RS Transport (por ejemplo, una reparación que hizo el taller
         | de la transportista). El contenedor es un maestro compartido;
         | los gastos no.
         |
         | Sin allCompanies(), el filtro global por compañía dejaría
         | fuera esos gastos y el costo saldría más bajo de lo real.
         * -------------------------------------------------------- */
        $total = Expense::query()
            ->allCompanies()
            ->where('container_id', $containerId)
            ->where('status', '!=', 'cancelled')
            ->sum('amount');

        $container->reconditioning_cost = round((float) $total, 2);

        // saveQuietly: no queremos despertar al ContainerObserver, que
        // crearía un movimiento de historial por un cambio de costo.
        $container->saveQuietly();
    }
}
