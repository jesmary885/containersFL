<?php

namespace App\Livewire\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LISTADO DE PAGOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Se abre desde Finanzas › Pagos.
 *
 * Si el listado de facturas responde "¿quién me debe?", este responde
 * "¿qué entró y adónde fue a parar?". Por eso el filtro más útil no es
 * el de estado sino el de "sin aplicar": dinero que ya está en el banco
 * pero todavía no se descontó de ninguna factura.
 */

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'metodo', except: '')]
    public string $metodo = '';

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'sinAplicar', except: false)]
    public bool $soloSinAplicar = false;

    #[Url(as: 'orden', except: 'received_at')]
    public string $ordenarPor = 'received_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $direccion = 'desc';

    public int $porPagina = 15;

    public function updatedBuscar(): void         { $this->resetPage(); }
    public function updatedMetodo(): void         { $this->resetPage(); }
    public function updatedEstado(): void         { $this->resetPage(); }
    public function updatedSoloSinAplicar(): void { $this->resetPage(); }

    public function ordenar(string $columna): void
    {
        if ($this->ordenarPor === $columna) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->direccion  = 'desc';
        }

        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'metodo', 'estado', 'soloSinAplicar']);
        $this->resetPage();
    }

    public function verSinAplicar(): void
    {
        $this->soloSinAplicar = true;
        $this->resetPage();
    }

    public function render()
    {
        $columnasValidas = ['payment_number', 'received_at', 'amount', 'unapplied_amount', 'status'];

        $columna = in_array($this->ordenarPor, $columnasValidas, true) ? $this->ordenarPor : 'received_at';
        $sentido = $this->direccion === 'asc' ? 'asc' : 'desc';

        $pagos = Payment::query()
            ->with(['customer:id,display_name,company_name,customer_number'])
            ->search($this->buscar)
            ->methodIs($this->metodo)
            ->statusIs($this->estado)
            ->when($this->soloSinAplicar, fn ($q) => $q->withUnapplied())
            ->orderBy($columna, $sentido)
            ->orderBy('id', 'desc')
            ->paginate($this->porPagina);

        $resumen = [
            'delMes' => (float) Payment::query()
                ->where('status', PaymentStatus::Completed->value)
                ->whereBetween('received_at', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])
                ->sum('amount'),

            'sinAplicar'     => (float) Payment::query()->withUnapplied()->sum('unapplied_amount'),
            'sinAplicarCant' => Payment::query()->withUnapplied()->count(),

            'conProblema' => Payment::query()
                ->whereIn('status', [
                    PaymentStatus::Failed->value,
                    PaymentStatus::Disputed->value,
                ])
                ->count(),
        ];

        return view('livewire.payments.index', [
            'pagos'   => $pagos,
            'resumen' => $resumen,
            'metodos' => PaymentMethod::options(),
            'estados' => PaymentStatus::options(),
        ]);
    }
}
