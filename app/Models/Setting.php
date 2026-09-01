<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Configuración que el cliente puede cambiar sin llamarte.
 *
 * Cascada de tres niveles:
 *   1. ¿La compañía tiene su propio valor?     → ese
 *   2. ¿Hay un valor global (company_id null)? → ese
 *   3. ¿Ninguno?                               → el default del código
 */
class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value'     => 'json',
            'is_public' => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /** null = ajuste global, con valor = ajuste propio de esa compañía. */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    /**
     * El valor efectivo de una clave.
     *
     * ── EL CAMBIO IMPORTANTE FRENTE A LA VERSIÓN ANTERIOR ──
     *
     * Antes se cacheaba el resultado final bajo la clave de QUIEN
     * PREGUNTÓ. O sea: si la compañía 1 heredaba el valor global, ese
     * valor quedaba cacheado como "setting:grupo:clave:1". Y cuando el
     * global cambiaba, se limpiaba solo "setting:grupo:clave:global",
     * así que la compañía 1 seguía leyendo el valor viejo para siempre
     * (rememberForever no expira).
     *
     * Ahora se cachean las DOS FILAS por separado, cada una bajo su
     * propia clave, y la cascada se resuelve fuera de la caché. Cuando
     * el global cambia, la clave del global se limpia y todas las
     * compañías que lo heredaban ven el valor nuevo en la siguiente
     * consulta. No hay que limpiar nada más.
     *
     * Y el default ya NO se cachea: es un valor del código, no de la
     * base, y no tiene sentido guardarlo.
     */
    public static function resolve(
        string $group,
        string $key,
        ?int $companyId = null,
        mixed $default = null,
    ): mixed {
        // Nivel 1: el valor propio de la compañía.
        if ($companyId) {
            $own = static::cachedRow($group, $key, $companyId);

            if ($own !== null) {
                return $own;
            }
        }

        // Nivel 2: el valor global.
        $global = static::cachedRow($group, $key, null);

        if ($global !== null) {
            return $global;
        }

        // Nivel 3: el default del código. Sin caché.
        return $default;
    }

    /**
     * Busca UNA fila concreta y la cachea.
     *
     * Devuelve null si la fila no existe. Ese null es lo que hace que
     * resolve() baje al siguiente nivel de la cascada.
     *
     * ── Por qué se envuelve el valor en un array ──
     *
     * Un ajuste puede valer legítimamente `false` o `0`. Si guardáramos
     * el valor pelado, no podríamos distinguir "la fila existe y vale
     * false" de "la fila no existe". Guardando ['v' => false] la
     * diferencia queda clara: array = existe, null = no existe.
     *
     * Es el mismo motivo por el que se usa Cache::get con centinela y
     * no Cache::has: una consulta en vez de dos.
     */
    protected static function cachedRow(string $group, string $key, ?int $companyId): mixed
    {
        $cacheKey = static::cacheKey($group, $key, $companyId);

        $envuelto = Cache::get($cacheKey);

        if ($envuelto === null) {
            $fila = static::query()
                ->when($companyId,
                    fn ($q) => $q->where('company_id', $companyId),
                    fn ($q) => $q->whereNull('company_id'),
                )
                ->where('group', $group)
                ->where('key', $key)
                ->first();

            // Se cachea también la ausencia: ['v' => null] significa
            // "ya busqué y no hay fila". Sin esto, cada consulta a una
            // clave inexistente golpearía la base, y el
            // InvoiceCalculator consulta dos por cada factura.
            $envuelto = ['v' => $fila?->value];

            Cache::put($cacheKey, $envuelto, now()->addDay());
        }

        return $envuelto['v'];
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Guarda o actualiza una clave.
     *
     * SIEMPRE usar esto, nunca insert() directo. Motivo: MySQL trata
     * cada NULL como distinto, así que el índice único
     * (company_id, group, key) NO impide dos ajustes globales con la
     * misma clave. updateOrCreate sí lo impide.
     *
     * El parámetro $type no afecta al valor (de eso se encarga el cast
     * json). Sirve para que la pantalla de Configuración sepa qué
     * control pintar: checkbox, número o texto.
     */
    public static function put(
        string $group,
        string $key,
        mixed $value,
        ?int $companyId = null,
        ?string $type = null,
    ): self {
        $atributos = ['value' => $value];

        if ($type !== null) {
            $atributos['type'] = $type;
        }

        return static::updateOrCreate(
            ['company_id' => $companyId, 'group' => $group, 'key' => $key],
            $atributos,
        );
        // La caché la limpia el hook saved(). No hace falta acá.
    }

    /* =====================================================================
     | EVENTOS
     * ================================================================== */

    protected static function booted(): void
    {
        /**
         * Cualquier cambio invalida SU propia clave. Y solo la suya.
         *
         * Con el diseño nuevo eso basta: como cada fila se cachea por
         * separado y la cascada se resuelve fuera de la caché, limpiar
         * el global hace que todas las compañías que lo heredaban vean
         * el valor nuevo automáticamente.
         */
        static::saved(fn (Setting $s) => Cache::forget(
            static::cacheKey($s->group, $s->key, $s->company_id),
        ));

        /**
         * Borrar un ajuste propio de una compañía la devuelve a heredar
         * el global. Sin este hook, seguiría leyendo el valor borrado.
         */
        static::deleted(fn (Setting $s) => Cache::forget(
            static::cacheKey($s->group, $s->key, $s->company_id),
        ));
    }

    protected static function cacheKey(string $group, string $key, ?int $companyId): string
    {
        return "setting:{$group}:{$key}:".($companyId ?? 'global');
    }
}
