<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Depot;
use App\Models\DistanceLookup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * CUÁNTAS MILLAS HAY HASTA ALLÁ
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ── DE DÓNDE SALE ──
 *
 * Reunión del 16 de septiembre. Se acordó calcular las millas automáticamente
 * con la API de Google Maps usando códigos postales, en vez de que alguien
 * las estime a ojo.
 *
 * Denisse lo describió así el 14 de agosto: el cliente manda el zip code,
 * calculan millas, ponen el shipping y dan el precio junto.
 *
 * ── LAS TRES COSAS QUE ESTE SERVICIO GARANTIZA ──
 *
 * 1 · NUNCA TUMBA LA PANTALLA.
 *
 *     Si no hay clave, si Google no responde, si el código postal está mal
 *     escrito: devuelve null y ya. El campo de millas sigue siendo editable
 *     a mano, como hasta ahora. El cálculo automático es una comodidad,
 *     no un requisito para cotizar.
 *
 *     Esto importa más de lo que parece: la yarda está en Florida y ahí se
 *     va la luz y se cae internet como en todas partes. Una cotización no
 *     puede depender de que Google conteste.
 *
 * 2 · NO PAGA DOS VECES POR LA MISMA RESPUESTA.
 *
 *     Cada par origen-destino se guarda en `distance_lookups`. La segunda
 *     vez que alguien cotice a ese código postal, la respuesta sale de la
 *     base y Google no se entera.
 *
 * 3 · GUARDA TAMBIÉN LOS FALLOS.
 *
 *     Un zip mal escrito devuelve "no encontrado". Si eso no se guardara,
 *     cada intento volvería a preguntar y a pagar por el mismo "no".
 *
 * ── CÓMO SE ENCIENDE ──
 *
 * En el `.env`:
 *
 *     GOOGLE_MAPS_KEY=...
 *
 * La clave necesita la Distance Matrix API habilitada en la consola de
 * Google Cloud, y conviene restringirla por IP del servidor. Sin clave, el
 * servicio se queda dormido y el sistema funciona igual que antes.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class DistanceResolver
{
    /** Cuántos días vale una distancia antes de volver a preguntar. */
    private const VIGENCIA_DIAS = 180;

    /**
     * Millas entre dos puntos. null si no se pudo saber.
     *
     * Los dos parámetros son texto: un código postal, una ciudad, una
     * dirección completa. Google los entiende igual.
     */
    public function miles(?string $origen, ?string $destino): ?float
    {
        $origen  = $this->normalizar($origen);
        $destino = $this->normalizar($destino);

        if (! $origen || ! $destino) {
            return null;
        }

        /* -----------------------------------------------------------------
         | 1 · ¿YA LO SABEMOS?
         * -------------------------------------------------------------- */
        $guardado = DistanceLookup::where('origin', $origen)
            ->where('destination', $destino)
            ->first();

        if ($guardado && $this->sigueSirviendo($guardado)) {
            return $guardado->is_usable ? (float) $guardado->miles : null;
        }

        /* -----------------------------------------------------------------
         | 2 · ¿HAY CLAVE?
         |
         | Sin clave no se pregunta y no se guarda nada: mañana puede haber
         | clave y no queremos una tabla llena de "error" de hoy.
         * -------------------------------------------------------------- */
        $clave = config('services.google_maps.key');

        if (blank($clave)) {
            return $guardado?->is_usable ? (float) $guardado->miles : null;
        }

        /* -----------------------------------------------------------------
         | 3 · PREGUNTAR
         * -------------------------------------------------------------- */
        try {
            $respuesta = Http::timeout(8)
                ->retry(2, 300)
                ->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                    'origins'      => $origen,
                    'destinations' => $destino,
                    'units'        => 'imperial',   // el negocio trabaja en millas
                    'key'          => $clave,
                ]);

            if (! $respuesta->successful()) {
                return $this->recordar($origen, $destino, null, null, 'error', $guardado);
            }

            $datos = $respuesta->json();

            $elemento = $datos['rows'][0]['elements'][0] ?? null;

            if (($elemento['status'] ?? '') !== 'OK') {
                /*
                 | ZERO_RESULTS o NOT_FOUND: el código postal no existe o no
                 | hay ruta por tierra. Se guarda el no para no volver a
                 | preguntar lo mismo.
                 */
                return $this->recordar($origen, $destino, null, null, 'not_found', $guardado);
            }

            /*
             | Google devuelve metros. 1 milla = 1609.344 metros.
             | Se usa el valor numérico y no el texto ("62.1 mi"), que viene
             | formateado y habría que desarmarlo.
             */
            $metros   = (float) ($elemento['distance']['value'] ?? 0);
            $segundos = (int) ($elemento['duration']['value'] ?? 0);

            $millas = round($metros / 1609.344, 2);

            $this->recordar($origen, $destino, $millas, (int) round($segundos / 60), 'ok', $guardado);

            return $millas;

        } catch (\Throwable $e) {

            /*
             | Se registra y se sigue. Que no haya internet no puede impedir
             | que alguien cotice: el campo de millas sigue a mano.
             */
            Log::warning('DistanceResolver: falló la consulta a Google Maps', [
                'origen'  => $origen,
                'destino' => $destino,
                'error'   => $e->getMessage(),
            ]);

            return $guardado?->is_usable ? (float) $guardado->miles : null;
        }
    }

    /**
     * Millas desde el punto de salida de una compañía.
     *
     * El origen es la dirección de la compañía activa: la yarda. Si no
     * tiene dirección cargada, no hay de dónde medir.
     */
    public function milesFromCompany(?Company $company, ?string $destino): ?float
    {
        return $this->miles($this->puntoDe($company), $destino);
    }

    /** Millas desde un depósito, para los pickups. */
    public function milesFromDepot(Depot|int|null $depot, ?string $destino): ?float
    {
        $depot = is_int($depot) ? Depot::find($depot) : $depot;

        if (! $depot) {
            return null;
        }

        $origen = $depot->zip
            ?: trim(collect([$depot->city, $depot->state])->filter()->implode(', '));

        return $this->miles($origen, $destino);
    }

    /* =====================================================================
     | INTERNOS
     * ================================================================== */

    /**
     * El punto de salida de una compañía, en texto.
     *
     * Se prefiere el código postal: es más corto, más barato de cachear y
     * Google lo resuelve igual de bien que una dirección completa.
     */
    private function puntoDe(?Company $company): ?string
    {
        if (! $company) {
            return null;
        }

        if (filled($company->zip)) {
            return $company->zip;
        }

        return trim(collect([
            $company->address_line1,
            $company->city,
            $company->state,
        ])->filter()->implode(', ')) ?: null;
    }

    /**
     * Deja el texto en una forma estable.
     *
     * Sin esto, "33122", " 33122" y "33122 " serían tres filas distintas
     * en la caché y se pagarían tres consultas por la misma respuesta.
     */
    private function normalizar(?string $texto): ?string
    {
        $texto = trim((string) $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        return $texto !== '' ? mb_strtoupper($texto) : null;
    }

    private function sigueSirviendo(DistanceLookup $fila): bool
    {
        // Lo corregido a mano no caduca: alguien decidió que es así.
        if ($fila->is_manual) {
            return true;
        }

        if (! $fila->checked_at) {
            return false;
        }

        return $fila->checked_at->gt(now()->subDays(self::VIGENCIA_DIAS));
    }

    private function recordar(
        string $origen,
        string $destino,
        ?float $millas,
        ?int $minutos,
        string $estado,
        ?DistanceLookup $existente,
    ): ?float {
        /*
         | Una fila corregida a mano no se pisa nunca. Si alguien la
         | escribió es porque Google se equivocaba, y una consulta
         | automática no tiene por qué borrar esa corrección.
         */
        if ($existente && $existente->is_manual) {
            return $existente->is_usable ? (float) $existente->miles : null;
        }

        DistanceLookup::updateOrCreate(
            ['origin' => $origen, 'destination' => $destino],
            [
                'miles'      => $millas,
                'minutes'    => $minutos,
                'status'     => $estado,
                'checked_at' => now(),
            ],
        );

        return $millas;
    }
}
