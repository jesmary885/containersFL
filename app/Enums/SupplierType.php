<?php

namespace App\Enums;


use App\Models\Concerns\HasOptions as ConcernsHasOptions;

/**
 * Qué nos vende este proveedor.
 *
 * Sirve para filtrar los desplegables: en una compra de contenedores no
 * tiene sentido que aparezca el taller mecánico.
 */
enum SupplierType: string
{
  
    use ConcernsHasOptions;

    case Depot = 'depot';   // vende contenedores

    case ContainerSupplier = 'container_supplier';   // vende contenedores
     case Materials = 'materials';   // vende contenedores
    case Parts             = 'parts';                // repuestos, candados, pisos
    case Services          = 'services';             // pintura, reparación, soldadura
    case Transport         = 'transport';            // transportista externo
      case Service       = 'service';            // transportista externo
    case Other             = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Materials => 'Materiales',
            self::Depot => 'Depositos',
            self::ContainerSupplier => 'Proveedor de contenedores',
            self::Parts             => 'Repuestos y materiales',
            self::Services          => 'Servicios',
            self::Transport         => 'Transporte',
            self::Service       => 'Servicio',
            self::Other             => 'Otro',
        };
    }

    /**
     * Solo estos aparecen al crear una compra o un release.
     *
     * Se usa así en la pantalla:
     *   SupplierType::only(SupplierType::forPurchases())
     */
    public static function forPurchases(): array
    {
        return [self::ContainerSupplier, self::Parts];
    }

    /** Puede tener depósitos asociados (RB-031: solo hay 3). */
    public function hasDepots(): bool
    {
        return $this === self::ContainerSupplier;
    }

    /**
     * Se reporta en el 1099 anual por defecto.
     *
     * Es solo el valor que se precarga al crear el proveedor; en la
     * ficha se puede cambiar. Los servicios y el transporte suelen ser
     * pagos a personas o empresas pequeñas, que es justo lo que el
     * 1099 persigue.
     */
    public function default1099Reportable(): bool
    {
        return $this->is(self::Services, self::Transport);
    }
}