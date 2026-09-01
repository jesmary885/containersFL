<?php

namespace App\Support;

use App\Models\Company;

/**
 * LA CAJA DE LA COMPAÑÍA ACTIVA
 * ==============================
 *
 * Esta clase guarda una sola cosa: en cuál de las dos empresas está
 * trabajando el usuario en este momento.
 *
 * Parece poco, pero es la pieza de la que cuelga todo el aislamiento
 * entre FLCHR y RS Transport. El filtro automático de BelongsToCompany
 * le pregunta a esta clase en cada consulta a la base de datos.
 *
 * Se registra como "singleton" en AppServiceProvider, lo que significa
 * que durante toda una petición existe UNA sola caja y todos leen y
 * escriben en la misma. Cuando la respuesta termina, se destruye. No
 * se guarda nada de una visita a la siguiente.
 *
 * Lo que NO hace esta clase:
 *   - No guarda nada en la base de datos.
 *   - No decide quién puede entrar a qué (eso son los permisos).
 *   - No lee la sesión. Eso lo hace el middleware SetCompanyContext,
 *     que es quien le pone el papelito adentro.
 */
class CompanyContext
{
    /**
     * La compañía activa. Empieza vacía a propósito.
     *
     * Vacía significa "todavía nadie me dijo dónde estamos", y el
     * filtro de las consultas lo interpreta como "no muestres nada".
     * Es más seguro que asumir una por defecto: si asumiéramos FLCHR,
     * un error de configuración mostraría datos de FLCHR a alguien que
     * debería estar viendo RS Transport.
     */
    protected ?Company $company = null;

    /* =====================================================================
     | ESCRIBIR EN LA CAJA
     * ================================================================== */

    /**
     * Mete la compañía en la caja.
     *
     * Acepta tres cosas para que quien la llame no tenga que
     * preocuparse por el formato:
     *   - Un objeto Company completo (lo normal desde el middleware).
     *   - Un número, el id (cómodo desde comandos y pruebas).
     *   - null, para vaciarla.
     *
     * Cuando le pasas un número, busca la compañía en la base. Si no
     * existe ese id, la caja queda vacía en vez de reventar: el
     * resultado será una pantalla sin datos, que se nota enseguida.
     */
    public function set(Company|int|null $company): static
    {
        if (is_int($company)) {
            $company = Company::find($company);
        }

        $this->company = $company;

        return $this;
    }

    /** Vacía la caja. Se usa sobre todo en las pruebas automáticas. */
    public function clear(): static
    {
        $this->company = null;

        return $this;
    }

    /* =====================================================================
     | LEER LA CAJA
     * ================================================================== */

    /**
     * La compañía completa, con su nombre, su color, su EIN.
     * Devuelve null si todavía no se definió ninguna.
     */
    public function get(): ?Company
    {
        return $this->company;
    }

    /**
     * Solo el número (el id). Este es el método que llama el filtro
     * automático de las consultas, miles de veces por pantalla.
     *
     * El signo de interrogación en $this->company?->id significa
     * "si la caja está vacía, devuelve null en vez de intentar leer
     * el id de la nada".
     */
    public function id(): ?int
    {
        return $this->company?->id;
    }

    /** ¿Hay algo en la caja? Útil para los @if de las vistas. */
    public function has(): bool
    {
        return $this->company !== null;
    }

    /**
     * El código corto: FLCHR o RST.
     * Para mostrar en el header sin cargar todo el objeto.
     */
    public function code(): ?string
    {
        return $this->company?->code;
    }

    /**
     * El color de la marca, con un gris de reserva por si la compañía
     * todavía no lo tiene configurado. Sin el gris, la barra superior
     * saldría transparente y parecería que la página cargó mal.
     */
    public function color(): string
    {
        return $this->company?->brand_color ?: '#334155';
    }

    /* =====================================================================
     | EJECUTAR ALGO EN OTRA COMPAÑÍA
     * ================================================================== */

    /**
     * Corre un bloque de código como si la compañía activa fuera otra,
     * y al terminar deja la caja exactamente como estaba.
     *
     * ── PARA QUÉ SIRVE ──
     *
     * Este es el método que resuelve el problema del "scope que se
     * apaga fuera del navegador".
     *
     * Un comando programado no tiene sesión, así que la caja está
     * vacía y todas las consultas devuelven cero filas en silencio.
     * Con este método, el comando dice explícitamente en qué compañía
     * está trabajando:
     *
     *     $context = app(CompanyContext::class);
     *
     *     foreach (Company::active()->get() as $empresa) {
     *         $context->runAs($empresa, function () {
     *             // Aquí adentro, Rental::all() sí devuelve las
     *             // rentas de $empresa y de ninguna otra.
     *         });
     *     }
     *
     * También sirve para la factura intercompañía de RB-003: RS
     * Transport tiene que leer los viajes que hizo para FLCHR y
     * emitir la factura desde RS Transport. Son dos compañías en la
     * misma operación.
     *
     * ── POR QUÉ EL try/finally ──
     *
     * finally se ejecuta pase lo que pase: si el bloque termina bien,
     * si lanza un error, si hace return a mitad de camino. Sin eso,
     * un error dentro del bloque dejaría la caja apuntando a la
     * compañía equivocada, y lo que viniera después escribiría en la
     * empresa incorrecta. Ese es un error de los que no se descubren
     * hasta que un contador reclama.
     */
    public function runAs(Company|int $company, callable $callback): mixed
    {
        $anterior = $this->company;

        $this->set($company);

        try {
            return $callback();
        } finally {
            $this->company = $anterior;
        }
    }
}