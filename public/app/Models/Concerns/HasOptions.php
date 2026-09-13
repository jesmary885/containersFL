<?php

namespace App\Models\Concerns;

/**
 * Métodos que todos los enums necesitan.
 * Se escriben una vez acá y se usan en los 25.
 */
trait HasOptions
{
    /**
     * Para llenar un <select> sin escribir las opciones a mano:
     *   ['sold' => 'Vendido', 'in_yard' => 'En yarda', ...]
     *
     * Si mañana agregas un caso al enum, el select se actualiza solo.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    /** Solo algunos casos, para selects filtrados. */
    public static function only(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }

    /** Compara contra uno o varios casos. */
    public function is(self ...$cases): bool
    {
        return in_array($this, $cases, true);
    }

    /* ─────────────────────────────────────────────────────────────────
     | AÑADIDO 1 — los valores pelados
     |
     | Para whereIn, Rule::in y filtros:
     |   Invoice::whereIn('status', InvoiceStatus::values())
     * ────────────────────────────────────────────────────────────── */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /* ─────────────────────────────────────────────────────────────────
     | AÑADIDO 2 — color por defecto
     |
     | Los enums de ESTADO definen su propio color(). Los de TIPO
     | (InvoiceType, PaymentMethod, TripType, DocumentCategory,
     | ProductType, CustomerType) no lo necesitan y heredan el gris
     | de acá.
     |
     | Un método definido en el enum SIEMPRE gana sobre el del trait,
     | así que esto no pisa nada de lo que ya tienes escrito. Solo
     | evita que <x-ui.badge> reviente al recibir un enum sin color.
     * ────────────────────────────────────────────────────────────── */
    public function color(): string
    {
        return 'gray';
    }

    /**
     * El nombre en el idioma de quien está mirando.
     *
     * Cae al español si falta la traducción, en vez de mostrar vacío.
     * Un catálogo a medio traducir se ve raro; uno con huecos en blanco
     * parece roto.
     */
    public function getDisplayNameAttribute(): string
    {
        if (app()->getLocale() === 'en' && ! empty($this->name_en)) {
            return $this->name_en;
        }

        return $this->name;
    }

    
}