<?php

namespace App\Livewire\Concerns;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL PERMISO SE COMPRUEBA EN EL COMPONENTE, NO SOLO EN LA RUTA
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── POR QUÉ NO ALCANZA CON EL `can:` DE LA RUTA ──
 *
 * Livewire NO vuelve a pasar por la ruta original. Cuando el usuario
 * hace clic en un botón, esa acción viaja a `/livewire/update`, que es
 * una ruta distinta y que no lleva ningún `can:` encima.
 *
 * O sea: el `can:invoices.void` de la ruta impide ABRIR la pantalla.
 * No impide llamar al método `anular()` desde la consola del navegador
 * con la pantalla ya abierta.
 *
 * Es el agujero clásico de Livewire y por eso este trait existe.
 *
 * ── CÓMO SE USA ──
 *
 *     class Show extends Component
 *     {
 *         use AuthorizesAccess;
 *
 *         protected string $permisoBase = 'invoices';
 *
 *         public function mount() {
 *             $this->exigirPermiso('view');
 *         }
 *
 *         public function anular() {
 *             $this->exigirPermiso('void');   // ← la que importa
 *             ...
 *         }
 *     }
 *
 * La regla práctica: TODO método público que cambie algo empieza con una
 * línea de `exigirPermiso()`. Si el método solo lee, basta con el de
 * `mount()`.
 * ═══════════════════════════════════════════════════════════════════════════
 */
trait AuthorizesAccess
{
    /**
     * Exige un permiso o corta la petición con un 403.
     *
     * Acepta las dos formas:
     *
     *     $this->exigirPermiso('update');            // usa $permisoBase
     *     $this->exigirPermiso('invoices.update');   // completo
     *
     * La primera es la que se usa casi siempre. La segunda hace falta
     * cuando un componente toca dos módulos: la ficha de la factura
     * tiene un botón de registrar pago, y ese es `payments.create`.
     */
    protected function exigirPermiso(string $permiso): void
    {
        if (! $this->puede($permiso)) {
            throw new AuthorizationException(
                'No tiene permiso para realizar esta acción.'
            );
        }
    }

    /**
     * ¿Puede, sí o no? Sin cortar nada.
     *
     * Para esconder un botón en la vista en vez de dejar que el usuario
     * lo pulse y se coma un 403. Las dos cosas hacen falta: esta para
     * que la pantalla sea honesta, exigirPermiso() para que sea segura.
     */
    public function puede(string $permiso): bool
    {
        return auth()->user()?->can($this->permisoCompleto($permiso)) ?? false;
    }

    /**
     * Completa el nombre del permiso con el módulo del componente.
     *
     * Si ya trae un punto se deja como está: alguien escribió el permiso
     * completo a propósito.
     */
    protected function permisoCompleto(string $permiso): string
    {
        if (str_contains($permiso, '.')) {
            return $permiso;
        }

        $base = property_exists($this, 'permisoBase') ? $this->permisoBase : null;

        if (! $base) {
            /*
             | Sin $permisoBase no se puede adivinar el módulo, y adivinar
             | mal aquí significa dejar pasar a quien no debe.
             |
             | Se rompe ruidosamente en vez de devolver false: un false
             | silencioso escondería el botón y nadie reportaría nada,
             | mientras que un permiso mal escrito seguiría suelto en otra
             | pantalla.
             */
            throw new \LogicException(
                static::class.' usa AuthorizesAccess sin declarar $permisoBase. '
                .'Agregue: protected string $permisoBase = \'invoices\';'
            );
        }

        return $base.'.'.$permiso;
    }
}
