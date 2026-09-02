<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\Company;
use App\Models\Depot;
use App\Models\Driver;
use App\Models\Trip;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * EL RESOLVEDOR DE PRECIOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * El modelo Trip ya llama a esta clase en suggestDriverPay() y en
 * suggestCustomerPrice(), pero el archivo no existía todavía. Aquí está.
 *
 * ── QUÉ RESUELVE ──
 *
 * Tres números que hacen falta al programar un viaje:
 *
 *   1. Cuánto se le cobra al cliente por el delivery
 *   2. Cuánto cobra el depósito por dejarnos sacar un contenedor
 *   3. Cuánto se le paga al chofer
 *
 * Ninguno de los tres es un número fijo del sistema. Todos siguen la
 * misma idea: se busca de lo MÁS específico a lo MÁS general, y el
 * primero que tenga valor gana.
 *
 * ── LA REGLA DE ORO ──
 *
 * Todo lo que devuelve esta clase es una SUGERENCIA para llenar el
 * formulario. El usuario puede cambiarla antes de guardar, y una vez
 * guardado el viaje, el sistema lee del viaje y no vuelve nunca aquí.
 *
 * Eso es lo que permite que si el año que viene suben la tarifa por
 * milla, los viajes del año pasado sigan mostrando lo que se cobró ese
 * día. Un precio guardado es un hecho; un precio calculado al vuelo es
 * una opinión que cambia sola.
 * ═══════════════════════════════════════════════════════════════════════════
 */
class PricingResolver
{
    /* =====================================================================
     | 1 · LA TARIFA POR MILLA
     * ================================================================== */

    /**
     * Cuánto se cobra por milla recorrida (RB-031: los deliveries se
     * calculan por millas).
     *
     * Cascada, en este orden:
     *
     *   1. ¿Este transportista tiene su propia tarifa negociada?
     *      Es lo normal con los externos: cada uno cobra lo suyo.
     *
     *   2. ¿No? Entonces la tarifa general de la compañía, que vive en
     *      Configuración y el cliente puede cambiar sin llamarnos.
     *
     *   3. ¿Tampoco? $3.50, que es lo que se dejó cargado en el seeder.
     */
    public function ratePerMile(?Company $company, Carrier|int|null $carrier = null): float
    {
        $carrier = $this->comoCarrier($carrier);

        if ($carrier && $carrier->default_rate_per_mile !== null) {
            return (float) $carrier->default_rate_per_mile;
        }

        return (float) ($company?->setting('operations', 'default_rate_per_mile', 3.50) ?? 3.50);
    }

    /* =====================================================================
     | 2 · EL FEE DE RECOGIDA DEL DEPÓSITO
     * ================================================================== */

    /**
     * Cuánto cobra el depósito por entregarnos un contenedor (RB-031:
     * los pickups tienen fee FIJO por depósito, y solo hay 3 depósitos).
     *
     * Por eso este número NO se calcula por millas: es una tarifa que
     * cobra el depósito, no un costo de transporte. Maritime Container
     * cobra lo que cobra, quede cerca o lejos.
     *
     * Cascada:
     *   1. El fee propio del depósito (lo normal, cada uno tiene el suyo)
     *   2. El fee general de la compañía, desde Configuración
     *   3. $150, el valor del seeder
     */
    public function pickupFee(?Company $company, Depot|int|null $depot = null): float
    {
        $depot = $this->comoDepot($depot);

        if ($depot && $depot->default_pickup_fee !== null) {
            return (float) $depot->default_pickup_fee;
        }

        return (float) ($company?->setting('operations', 'default_pickup_fee', 150.00) ?? 150.00);
    }

    /* =====================================================================
     | 3 · EL PAGO DEL CHOFER
     * ================================================================== */

    /**
     * Qué porcentaje del viaje se lleva el chofer (RB-032).
     *
     * ── OJO, ESTA REGLA ESTÁ PENDIENTE DE CONFIRMAR ──
     *
     * De la reunión solo salió un ejemplo: "30% de $400". No quedó claro
     * si el 30% es igual para todos, si cada chofer tiene el suyo, o si
     * se acuerda viaje por viaje.
     *
     * Por eso la cascada soporta las TRES posibilidades a la vez, y no
     * hay que elegir hoy:
     *
     *   1. ¿Este viaje trae un % escrito a mano? Ese manda.
     *      (cubre el caso "se acuerda viaje por viaje")
     *
     *   2. ¿Este chofer tiene su propio %? Ese.
     *      (cubre el caso "cada chofer tiene el suyo")
     *
     *   3. El % general de la compañía, desde Configuración.
     *      (cubre el caso "es 30% para todos")
     *
     * Cuando el cliente confirme cuál de las tres es, no hay que tocar
     * código: basta con llenar o dejar vacías las columnas
     * correspondientes.
     */
    public function driverPayPercent(
        ?Company $company,
        Driver|int|null $driver = null,
        ?Trip $trip = null,
    ): float {
        // 1. El viaje, si trae el dato escrito.
        if ($trip && $trip->driver_pay_percent !== null) {
            return (float) $trip->driver_pay_percent;
        }

        // 2. El chofer, si tiene tarifa propia.
        $driver = $this->comoDriver($driver);

        if ($driver && $driver->default_pay_percent !== null) {
            return (float) $driver->default_pay_percent;
        }

        // 3. Lo general de la compañía.
        return (float) ($company?->setting('operations', 'driver_pay_percent', 30.00) ?? 30.00);
    }

    /**
     * Algunos choferes cobran monto fijo por viaje en vez de porcentaje.
     *
     * Devuelve null cuando el chofer no trabaja así. Ese null es
     * importante: significa "este cobra por porcentaje, usa el método de
     * arriba". Si devolviera 0 no se podría distinguir de un chofer que
     * cobra cero, que no existe.
     */
    public function driverFlatPay(Driver|int|null $driver = null): ?float
    {
        $driver = $this->comoDriver($driver);

        return $driver?->default_pay_amount !== null
            ? (float) $driver->default_pay_amount
            : null;
    }

    /* =====================================================================
     | AYUDANTES INTERNOS
     |
     | Los tres métodos de abajo hacen lo mismo: aceptan tanto el objeto
     | completo como su número de id, y devuelven siempre el objeto.
     |
     | Así, quien llama a esta clase no tiene que preocuparse por el
     | formato. Desde el modelo Trip es cómodo pasar $this->driver (el
     | objeto); desde un comando o una prueba es cómodo pasar un id.
     * ================================================================== */

    protected function comoCarrier(Carrier|int|null $carrier): ?Carrier
    {
        return is_int($carrier) ? Carrier::find($carrier) : $carrier;
    }

    protected function comoDepot(Depot|int|null $depot): ?Depot
    {
        return is_int($depot) ? Depot::find($depot) : $depot;
    }

    protected function comoDriver(Driver|int|null $driver): ?Driver
    {
        return is_int($driver) ? Driver::find($driver) : $driver;
    }
}
