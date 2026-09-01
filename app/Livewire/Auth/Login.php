<?php

namespace App\Livewire\Auth;


use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * LOGIN EN DOS PASOS
 * ==================
 *
 * Paso 1: email y contraseña.
 * Paso 2: en qué empresa quiere entrar.
 *
 * El paso 2 se salta solo si el usuario tiene acceso a una sola
 * empresa. No tiene sentido preguntarle algo que solo tiene una
 * respuesta posible.
 *
 * ── POR QUÉ EN DOS PASOS Y NO TODO JUNTO ──
 *
 * Poner el selector de empresa arriba del formulario le mostraría a
 * cualquiera que abra la página cuáles empresas existen y cuántas son.
 * Es poco, pero es información que no hace falta regalar. Primero
 * demuestras quién eres, después ves las opciones.
 */

class Login extends Component
{

    /* =====================================================================
     | LO QUE ESCRIBE EL USUARIO
     * ================================================================== */

    public string $email    = '';
    public string $password = '';
    public bool   $remember = false;

    /* =====================================================================
     | EL ESTADO DE LA PANTALLA
     * ================================================================== */

    /** 1 = pidiendo credenciales. 2 = pidiendo empresa. */
    public int $paso = 1;

    /** La empresa que marcó en el paso 2. */
    public ?int $companyId = null;

    /**
     * Las empresas a las que tiene acceso, ya convertidas a arreglo
     * simple para pintarlas en la vista.
     *
     * Se guardan como arreglo y no como objetos de Eloquent porque
     * Livewire tiene que mandar esto al navegador y traerlo de vuelta
     * en cada clic. Un arreglo plano viaja bien; un objeto con
     * relaciones cargadas, no siempre.
     */
    public array $companies = [];

    /* =====================================================================
     | PASO 1 — VERIFICAR CREDENCIALES
     * ================================================================== */

    public function verificarCredenciales(): void
    {
        $this->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required'    => 'Escriba su correo.',
            'email.email'       => 'Ese correo no tiene un formato válido.',
            'password.required' => 'Escriba su contraseña.',
        ]);

        /* -----------------------------------------------------------
         | FRENO CONTRA INTENTOS REPETIDOS
         |
         | Cinco intentos fallidos y la cuenta queda bloqueada un
         | minuto. Sin esto, un programa puede probar miles de
         | contraseñas por minuto hasta acertar.
         |
         | La llave mezcla el correo con la dirección IP: bloquear solo
         | por correo permitiría que alguien bloquee a propósito la
         | cuenta de un compañero fallando cinco veces a mano.
         * -------------------------------------------------------- */
        $llave = Str::lower($this->email) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            $segundos = RateLimiter::availableIn($llave);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Espere {$segundos} segundos.",
            ]);
        }

        /* -----------------------------------------------------------
         | Auth::validate() comprueba la contraseña SIN iniciar sesión.
         |
         | Es lo que queremos: todavía falta que elija empresa. Si
         | usáramos Auth::attempt() aquí, quedaría dentro del sistema a
         | medias, con sesión abierta pero sin compañía activa.
         * -------------------------------------------------------- */
        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($llave, 60);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        RateLimiter::clear($llave);

        $usuario = User::where('email', $this->email)->first();

        /* -----------------------------------------------------------
         | Usuario dado de baja.
         |
         | Se revisa aquí y no en Auth::validate() porque la
         | contraseña sigue siendo correcta: no es un problema de
         | credenciales, es que la persona ya no trabaja aquí.
         | Merece un mensaje distinto.
         * -------------------------------------------------------- */
        if (! $usuario->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Este usuario está desactivado. Contacte al administrador.',
            ]);
        }

        /* -----------------------------------------------------------
         | Sus empresas.
         |
         | Se ordenan poniendo primero la marcada como predeterminada
         | en company_user, para que la que usa siempre le quede a la
         | mano.
         * -------------------------------------------------------- */
        $empresas = $usuario->companies()
            ->where('is_active', true)
            ->orderByPivot('is_default', 'desc')
            ->orderBy('name')
            ->get();

        if ($empresas->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => 'Su usuario no tiene ninguna empresa asignada. '
                         . 'Contacte al administrador del sistema.',
            ]);
        }

        /* -----------------------------------------------------------
         | Una sola empresa: no hay nada que preguntar. Entra directo.
         * -------------------------------------------------------- */
        if ($empresas->count() === 1) {
            $this->companyId = $empresas->first()->id;
            $this->entrar();

            return;
        }

        /* -----------------------------------------------------------
         | Varias empresas: se pasa al paso 2.
         |
         | brand_color viene de la migración de companies. Es lo que
         | permite que los dos botones se vean distintos de un
         | vistazo, que es toda la gracia del asunto.
         * -------------------------------------------------------- */
        $this->companies = $empresas->map(fn ($empresa) => [
            'id'    => $empresa->id,
            'name'  => $empresa->name,
            'code'  => $empresa->code,
            'color' => $empresa->brand_color ?: '#334155',
        ])->all();

        // Se preselecciona la predeterminada: un clic menos.
        $this->companyId = $this->companies[0]['id'];

        $this->paso = 2;
    }

    /* =====================================================================
     | PASO 2 — ENTRAR CON LA EMPRESA ELEGIDA
     * ================================================================== */

    public function entrar()
    {
        if (! $this->companyId) {
            $this->addError('companyId', 'Seleccione una empresa para continuar.');

            return null;
        }

        /* -----------------------------------------------------------
         | Se vuelven a mandar las credenciales.
         |
         | Puede parecer redundante, pero no lo es: entre el paso 1 y
         | el paso 2 pasó tiempo y una petición nueva. Confiar en que
         | "ya lo validamos hace un momento" es exactamente lo que
         | permite entrar al sistema manipulando la petición del
         | segundo paso.
         |
         | Esta es la única llamada que abre sesión de verdad.
         * -------------------------------------------------------- */
        if (! Auth::attempt(
            ['email' => $this->email, 'password' => $this->password],
            $this->remember,
        )) {
            $this->reset(['password', 'paso', 'companies', 'companyId']);
            $this->paso = 1;

            throw ValidationException::withMessages([
                'email' => 'La sesión expiró. Vuelva a iniciar sesión.',
            ]);
        }

        /* -----------------------------------------------------------
         | switchCompany() verifica que el usuario tenga acceso real a
         | esa empresa antes de guardarla en la sesión.
         |
         | Sin esa verificación, alguien podría cambiar el número que
         | viaja en la petición y entrar a la contabilidad de la otra
         | empresa. El método ya está escrito en el modelo User.
         * -------------------------------------------------------- */
        if (! auth()->user()->switchCompany($this->companyId)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'No tiene acceso a la empresa seleccionada.',
            ]);
        }

        /* -----------------------------------------------------------
         | regenerate() le cambia el identificador a la sesión.
         |
         | Es contra un ataque en el que alguien te hace usar un
         | identificador que él ya conoce, y después entra con él.
         | Cambiarlo justo al autenticarse lo deja inservible.
         * -------------------------------------------------------- */
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /** Botón "volver" del paso 2. */
    public function volver(): void
    {
        $this->paso      = 1;
        $this->password  = '';
        $this->companies = [];
        $this->companyId = null;

        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
