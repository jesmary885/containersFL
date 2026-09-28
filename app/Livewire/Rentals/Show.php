<?php

namespace App\Livewire\Rentals;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Rental;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL DETALLE DE UN CONTRATO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── LO QUE CAMBIA SEGÚN EL TIPO ──
 *
 *   MENSUAL    la lista de períodos: cuáles se cobraron, cuáles están
 *              vencidos, y el botón para generar el siguiente.
 *
 *   DE YARDA   el reporte de días: transcurridos, pagados, pendientes y
 *              la deuda. Es la hoja REPORTE YARDA del Excel.
 *
 * ── LAS ACCIONES ──
 *
 * Generar el siguiente período y registrar días pagados. Las dos son de un
 * clic porque se hacen a diario.
 *
 * Generar un período NO factura: crea el renglón de cobro y lo deja
 * pendiente. Facturarlo es otro acto, y el contrato decide si ocurre solo
 * o a mano.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'rentals';

    public Rental $rental;

    /** Días que se van a registrar como pagados. Solo en renta de yarda. */
    public int $diasAPagar = 0;

    public function mount(Rental $rental): void
    {
        $this->exigirPermiso('view');

        $this->rental = $rental;
    }

    /**
     * Crear el siguiente período de cobro.
     *
     * El modelo ya sabe cómo: para una mensual toma el ancla y el mes
     * siguiente; para una de yarda crea un período de un día. Acá solo se
     * comprueba que tenga sentido pedirlo.
     */
    public function generarPeriodo(): void
    {
        $this->exigirPermiso('update');

        if ($this->rental->status?->value !== 'active') {
            $this->addError('periodo',
                'Solo un contrato activo genera períodos. Este está en "'
                .$this->rental->status?->label().'".');

            return;
        }

        $periodo = $this->rental->generateNextPeriod();

        $this->rental->refresh();

        session()->flash('exito',
            'Período '.$periodo->period_number.' creado, del '
            .$periodo->period_start->format('d/m/Y').' al '
            .$periodo->period_end->format('d/m/Y').'.');
    }

    /**
     * Registrar días pagados en una renta de yarda.
     *
     * El modelo no deja pasar de los días transcurridos: pagar 400 días de
     * un contenedor que lleva 324 en la yarda dejaría la deuda en negativo
     * y el reporte dejaría de cuadrar.
     */
    public function registrarDias(): void
    {
        $this->exigirPermiso('update');

        if ($this->diasAPagar <= 0) {
            $this->addError('diasAPagar', 'Escriba cuántos días se pagaron.');

            return;
        }

        $antes = (int) $this->rental->paid_days;

        $this->rental->recordPaidDays($this->diasAPagar);
        $this->rental->refresh();

        $aplicados = (int) $this->rental->paid_days - $antes;

        /*
         | Se compara ANTES de limpiar el campo. Si se limpiara primero,
         | $this->diasAPagar valdría 0 y la comparación nunca sería cierta:
         | el aviso de "no se aplicó todo" no saldría nunca.
         */
        $pedidos = $this->diasAPagar;

        $this->diasAPagar = 0;

        if ($aplicados < $pedidos) {
            session()->flash('exito',
                'Se registraron '.$aplicados.' de '.$pedidos.' días. El resto no se aplicó '
                .'porque el contrato no lleva tantos días transcurridos.');

            return;
        }

        session()->flash('exito', 'Se registraron '.$aplicados.' días pagados.');
    }

    public function render()
    {
        $renta = $this->rental->load(['customer', 'depot', 'containers']);

        return view('livewire.rentals.show', [
            'renta' => $renta,

            'periodos' => $renta->periods()
                ->orderByDesc('period_number')
                ->limit(24)
                ->get(),

            /*
             | El reporte de yarda, calculado al vuelo.
             |
             | No se guarda porque cambia solo con el paso del tiempo: un
             | número guardado ayer ya estaría mal hoy.
             */
            'yarda' => $renta->isDaily() ? [
                'transcurridos' => $renta->daysElapsed(),
                'pagados'       => (int) $renta->paid_days,
                'pendientes'    => $renta->unpaidDays(),
                'deudaDias'     => $renta->dailyDebt(),
                'cargosFijos'   => $renta->oneTimeFees(),
            ] : null,
        ]);
    }
}
