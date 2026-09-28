<?php

namespace App\Livewire\DeliveryRates;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\DeliveryRate;
use App\Support\CompanyContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * TARIFAS DE ENTREGA POR RANGO DE MILLAS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DÓNDE SALE ──
 *
 * Reunión del 16 de septiembre. Denisse dio los rangos —de 100 a 200 millas
 * a $4.50, de 200 en adelante a $5.00— y se acordó mantener las tarifas
 * EDITABLES sobre una base estándar, para poder ajustarlas según fluctúen
 * los costos.
 *
 * Esa palabra, "editables", es el motivo de que esta pantalla exista. El
 * precio del combustible se mueve y la tarifa detrás tiene que moverse con
 * él sin que nadie tenga que llamarnos.
 *
 * ── TODO SE EDITA EN LA MISMA PANTALLA ──
 *
 * Son tres o cuatro filas. Un formulario aparte para crear y otro para
 * editar sería tres clics para cambiar un número. Acá se escribe encima y
 * se guarda.
 *
 * ── LA VALIDACIÓN QUE IMPORTA ──
 *
 * Que los rangos no se pisen ni dejen huecos. Un hueco entre 100 y 120
 * significa que una entrega de 110 millas no encuentra tarifa y cae al
 * valor general sin que nadie se entere. Un solapamiento significa dos
 * tarifas posibles para la misma entrega.
 *
 * Las dos cosas se avisan, pero el hueco solo se avisa: a veces es
 * deliberado mientras se está reorganizando la tabla.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'settings';

    /** Las filas que se están editando. */
    public array $filas = [];

    /** El recargo por combustible, que vive en ajustes y no en la tabla. */
    public ?string $recargoCombustible = '0.00';

    public function mount(): void
    {
        $this->exigirPermiso('view');

        $this->cargar();
    }

    private function cargar(): void
    {
        $empresa = app(CompanyContext::class)->get();

        $this->filas = DeliveryRate::query()
            ->forCompany($empresa?->id)
            ->orderBy('min_miles')
            ->get()
            ->map(fn (DeliveryRate $r) => [
                'id'        => $r->id,
                'label'     => $r->label,
                'min'       => (string) (float) $r->min_miles,
                'max'       => $r->max_miles === null ? '' : (string) (float) $r->max_miles,
                'tarifa'    => (string) (float) $r->rate_per_mile,
                'activo'    => (bool) $r->is_active,
                'notas'     => $r->notes,
            ])
            ->all();

        $this->recargoCombustible = (string) (float) (
            $empresa?->setting('operations', 'fuel_surcharge_per_mile', 0.00) ?? 0.00
        );
    }

    public function agregar(): void
    {
        $this->exigirPermiso('update');

        $this->filas[] = [
            'id'     => null,
            'label'  => null,
            'min'    => '0',
            'max'    => '',
            'tarifa' => '0',
            'activo' => true,
            'notas'  => null,
        ];
    }

    public function quitar(int $i): void
    {
        $this->exigirPermiso('update');

        $fila = $this->filas[$i] ?? null;

        if ($fila && $fila['id']) {
            DeliveryRate::whereKey($fila['id'])->delete();
        }

        unset($this->filas[$i]);

        $this->filas = array_values($this->filas);

        session()->flash('exito', __('delivery_rates.removed'));
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    public function guardar(): void
    {
        $this->exigirPermiso('update');

        $this->validate([
            'filas'          => ['array', 'min:1'],
            'filas.*.min'    => ['required', 'numeric', 'min:0', 'max:99999'],
            'filas.*.max'    => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'filas.*.tarifa' => ['required', 'numeric', 'min:0', 'max:9999'],
            'filas.*.label'  => ['nullable', 'string', 'max:60'],

            'recargoCombustible' => ['nullable', 'numeric', 'min:0', 'max:99'],
        ], [], [
            'filas.*.min'    => 'el desde',
            'filas.*.max'    => 'el hasta',
            'filas.*.tarifa' => 'la tarifa',
        ]);

        /* -----------------------------------------------------------------
         | EL HASTA TIENE QUE SER MAYOR QUE EL DESDE
         |
         | Un rango de 200 a 100 no es un rango: no lo cumple ninguna
         | entrega y el sistema se salta la fila en silencio.
         * -------------------------------------------------------------- */
        foreach ($this->filas as $i => $f) {
            if ($f['max'] !== '' && $f['max'] !== null
                && (float) $f['max'] <= (float) $f['min']) {

                $this->addError('filas.'.$i.'.max', __('delivery_rates.max_must_be_greater'));

                return;
            }
        }

        /* -----------------------------------------------------------------
         | SOLO UN RANGO ABIERTO
         |
         | "Sin tope" significa de aquí en adelante. Dos filas sin tope se
         | pisan por definición y la tarifa dependería del orden de la
         | consulta, que es la peor clase de error: intermitente.
         * -------------------------------------------------------------- */
        $abiertos = collect($this->filas)
            ->filter(fn ($f) => ($f['max'] ?? '') === '' || $f['max'] === null)
            ->count();

        if ($abiertos > 1) {
            $this->addError('filas', __('delivery_rates.one_open_range'));

            return;
        }

        $empresa = app(CompanyContext::class)->get();

        /*
         | ¿A quién pertenecen estas filas?
         |
         | Si la compañía activa ya tenía las suyas, se siguen guardando
         | como suyas. Si estaba usando las generales, se siguen editando
         | las generales: es lo que la persona está viendo en pantalla, y
         | guardar en otro sitio haría que el cambio pareciera perdido.
         */
        $tienePropias = $empresa
            && DeliveryRate::where('company_id', $empresa->id)->exists();

        $companyId = $tienePropias ? $empresa->id : null;

        foreach ($this->filas as $f) {

            $datos = [
                'company_id'    => $companyId,
                'min_miles'     => (float) $f['min'],
                'max_miles'     => ($f['max'] === '' || $f['max'] === null) ? null : (float) $f['max'],
                'rate_per_mile' => (float) $f['tarifa'],
                'label'         => $f['label'] ?: null,
                'is_active'     => (bool) $f['activo'],
                'notes'         => $f['notas'] ?: null,
            ];

            if ($f['id']) {
                DeliveryRate::whereKey($f['id'])->update($datos);
            } else {
                DeliveryRate::create($datos);
            }
        }

        /* -----------------------------------------------------------------
         | EL RECARGO POR COMBUSTIBLE
         |
         | No va en la tabla porque no depende del rango: es un número
         | suelto que se suma a cualquier tarifa. Vive en ajustes, donde
         | viven los demás números sueltos del sistema.
         * -------------------------------------------------------------- */
        if ($empresa) {
            \App\Models\Setting::updateOrCreate(
                [
                    'company_id' => $empresa->id,
                    'group'      => 'operations',
                    'key'        => 'fuel_surcharge_per_mile',
                ],
                [
                    'value' => (float) ($this->recargoCombustible ?: 0),
                    'type'  => 'decimal',
                    'label' => 'Recargo por combustible ($/milla)',
                ],
            );
        }

        $this->cargar();

        session()->flash('exito', __('delivery_rates.saved'));
    }

    /* =====================================================================
     | AVISOS QUE NO BLOQUEAN
     * ================================================================== */

    /**
     * Huecos entre rangos.
     *
     * Devuelve los tramos de millas que no cubre ninguna fila. No impide
     * guardar: a veces el hueco es momentáneo mientras se reorganiza.
     */
    public function getHuecosProperty(): array
    {
        $orden = collect($this->filas)
            ->filter(fn ($f) => $f['activo'])
            ->sortBy(fn ($f) => (float) $f['min'])
            ->values();

        $huecos = [];
        $tope   = 0.0;

        foreach ($orden as $f) {
            $min = (float) $f['min'];

            if ($min > $tope) {
                $huecos[] = ['desde' => $tope, 'hasta' => $min];
            }

            if (($f['max'] ?? '') === '' || $f['max'] === null) {
                return $huecos;   // rango abierto: de aquí en adelante está cubierto
            }

            $tope = max($tope, (float) $f['max']);
        }

        $huecos[] = ['desde' => $tope, 'hasta' => null];

        return $huecos;
    }

    public function render()
    {
        return view('livewire.delivery-rates.index', [
            'empresa' => app(CompanyContext::class)->get(),
            'huecos'  => $this->huecos,
        ]);
    }
}
