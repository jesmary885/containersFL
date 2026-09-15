<?php

namespace App\Livewire\Purchases;

use App\Enums\PurchaseStatus;
use App\Enums\PurchaseType;
use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Purchase;
use App\Support\CompanyContext;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * COMPRAS Y RELEASES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Compras › Compras y releases.
 *
 * ── EL PROBLEMA QUE ESTE MÓDULO RESUELVE ──
 *
 * El Excel dice 416 contenedores en stock. Físicamente no están.
 *
 * No es un error de tecleo: es que el Excel cuenta lo COMPRADO y no lo
 * RECIBIDO, y son dos números distintos. Se compran siete de una vez, se
 * recoge uno, y los otros seis siguen en el patio del proveedor. En el
 * Excel los siete ya cuentan como inventario.
 *
 * Aquí cada renglón de compra lleva dos cantidades:
 *
 *   COMPRADA    lo que se pagó
 *   RECIBIDA    lo que ya está en la yarda
 *
 * El inventario sale de los contenedores registrados, uno por uno, y un
 * contenedor solo se registra cuando llega. La diferencia entre las dos
 * cantidades es exactamente lo que falta traer.
 *
 * ── QUÉ ES UN RELEASE ──
 *
 * Cuando se compran varios de golpe, el proveedor no los entrega: da un
 * papel que autoriza a retirarlos de su depósito, con un plazo. Pasado
 * el plazo, el depósito cobra almacenaje por día.
 *
 * Por eso el contador de "vencen pronto" no es un adorno: cada día que
 * pasa después del plazo es dinero.
 *
 * ── FILTRA POR EMPRESA ──
 *
 * `purchases` sí tiene company_id: una compra la paga una empresa
 * concreta y su numeración es independiente.
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination, AuthorizesAccess;

    protected string $permisoBase = 'purchases';

    /** A cuántos días vista se avisa de que el plazo se acaba. */
    public const DIAS_DE_AVISO = 5;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(as: 'estado', except: '')]
    public string $estado = '';

    #[Url(as: 'tipo', except: '')]
    public string $tipo = '';

    #[Url(as: 'marca', except: '')]
    public string $marca = '';

    public int $porPagina = 15;

    public function mount(): void
    {
        $this->exigirPermiso('view');
    }

    public function updatingBuscar(): void { $this->resetPage(); }
    public function updatingEstado(): void { $this->resetPage(); }
    public function updatingTipo(): void   { $this->resetPage(); }

    public function filtrarPor(string $marca): void
    {
        $this->marca  = $this->marca === $marca ? '' : $marca;
        $this->estado = '';
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['buscar', 'estado', 'tipo', 'marca']);
        $this->resetPage();
    }

    public function getHayFiltrosProperty(): bool
    {
        return filled($this->buscar) || filled($this->estado)
            || filled($this->tipo) || filled($this->marca);
    }

    public function render()
    {
        $empresa = app(CompanyContext::class)->get();

        $base = fn () => Purchase::query()->where('company_id', $empresa?->id);

        $compras = $base()
            ->with(['supplier:id,name', 'depot:id,name'])
            ->withSum('items as compradas', 'quantity')
            ->withSum('items as recibidas', 'received_quantity')

            ->when($this->buscar, fn ($q) => $q->where(function ($qq) {
                $t = '%'.trim($this->buscar).'%';
                $qq->where('purchase_number', 'like', $t)
                   ->orWhere('reference', 'like', $t)
                   ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $t));
            }))

            ->when($this->estado, fn ($q) => $q->where('status', $this->estado))
            ->when($this->tipo,   fn ($q) => $q->where('type', $this->tipo))

            /* -------------------------------------------------------------
             | LOS FILTROS DE LOS CONTADORES
             |
             | "pendientes" NO es un estado: es todo lo que tiene algo sin
             | traer, esté abierto o parcialmente recibido. Es la lista de
             | lo que hay que ir a buscar.
             * ---------------------------------------------------------- */
            ->when($this->marca === 'pendientes', fn ($q) => $q->whereIn('status', [
                PurchaseStatus::Open->value,
                PurchaseStatus::PartiallyReceived->value,
            ]))

            ->when($this->marca === 'vencidos', fn ($q) => $q->overdueForPickup())

            ->when($this->marca === 'por_vencer', fn ($q) => $q
                ->whereIn('status', [
                    PurchaseStatus::Open->value,
                    PurchaseStatus::PartiallyReceived->value,
                ])
                ->whereNotNull('pickup_deadline_at')
                ->whereDate('pickup_deadline_at', '>=', now()->toDateString())
                ->whereDate('pickup_deadline_at', '<=', now()->addDays(self::DIAS_DE_AVISO)->toDateString()))

            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate($this->porPagina);

        return view('livewire.purchases.index', [
            'compras' => $compras,
            'empresa' => $empresa,

            'estados' => PurchaseStatus::options(),
            'tipos'   => PurchaseType::options(),

            'resumen' => [
                /*
                 | Cuántas unidades están compradas y todavía no llegaron.
                 |
                 | Es EL número de esta pantalla: la diferencia entre lo
                 | que dice el papel y lo que hay en el patio.
                 */
                'porTraer' => (int) $base()
                    ->whereIn('status', [
                        PurchaseStatus::Open->value,
                        PurchaseStatus::PartiallyReceived->value,
                    ])
                    ->withSum('items as c', 'quantity')
                    ->withSum('items as r', 'received_quantity')
                    ->get()
                    ->sum(fn ($p) => max(0, (int) $p->c - (int) $p->r)),

                'pendientes' => $base()->whereIn('status', [
                    PurchaseStatus::Open->value,
                    PurchaseStatus::PartiallyReceived->value,
                ])->count(),

                'vencidos' => $base()->overdueForPickup()->count(),

                'porVencer' => $base()
                    ->whereIn('status', [
                        PurchaseStatus::Open->value,
                        PurchaseStatus::PartiallyReceived->value,
                    ])
                    ->whereNotNull('pickup_deadline_at')
                    ->whereDate('pickup_deadline_at', '>=', now()->toDateString())
                    ->whereDate('pickup_deadline_at', '<=', now()->addDays(self::DIAS_DE_AVISO)->toDateString())
                    ->count(),

                'delMes' => (float) $base()
                    ->whereYear('purchase_date', now()->year)
                    ->whereMonth('purchase_date', now()->month)
                    ->sum('total'),
            ],
        ]);
    }
}
