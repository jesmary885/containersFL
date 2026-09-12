<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LOGIN EN UN SOLO PASO
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Correo, contraseña y adentro. Nada más.
 *
 * ── QUÉ CAMBIÓ ──
 *
 * Antes había un segundo paso que preguntaba a qué empresa quería
 * entrar, y salía cada vez que el usuario tenía acceso a las dos.
 *
 * Se quitó. Ahora entra siempre a su empresa predeterminada —la que
 * tiene marcada en `company_user.is_default`— y si quiere trabajar en la
 * otra la cambia desde el selector de arriba del menú, que ya existe y
 * ya funciona.
 *
 * ── POR QUÉ ES MEJOR ASÍ ──
 *
 * Preguntar en el login costaba un clic a todo el mundo todos los días
 * para resolver algo que casi nadie cambia. Y era un clic que se hacía
 * sin mirar: quien entra ochenta veces al mes a FLCHR aprende la
 * posición del botón y lo pulsa sin leer. El día que necesita RST, lo
 * pulsa igual.
 *
 * El selector de arriba es mejor sitio para esa decisión porque está
 * siempre a la vista y porque dice, todo el tiempo, en cuál está.
 *
 * ── LO QUE NO CAMBIÓ ──
 *
 * El freno de cinco intentos, el aviso distinto para el usuario dado de
 * baja, y la verificación de que tenga al menos una empresa asignada.
 * Todo eso sigue igual.
 * ═══════════════════════════════════════════════════════════════════════════
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
     | ENTRAR
     * ================================================================== */

    public function entrar()
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
        $llave = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($llave, 5)) {
            $segundos = RateLimiter::availableIn($llave);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Espere {$segundos} segundos.",
            ]);
        }

        /* -----------------------------------------------------------
         | Auth::validate() comprueba la contraseña SIN abrir sesión.
         |
         | Se usa a propósito en vez de Auth::attempt(): todavía faltan
         | dos comprobaciones —que el usuario esté activo y que tenga
         | empresa asignada— y no queremos dejar la sesión abierta a
         | medias si alguna de las dos falla.
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
         | Se revisa aparte porque la contraseña sigue siendo correcta:
         | no es un problema de credenciales, es que la persona ya no
         | trabaja aquí. Merece un mensaje distinto.
         * -------------------------------------------------------- */
        if (! $usuario->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Este usuario está desactivado. Contacte al administrador.',
            ]);
        }

        /* -----------------------------------------------------------
         | SU EMPRESA DE ENTRADA
         |
         | defaultCompany() devuelve la marcada como predeterminada en
         | company_user y, si no hay ninguna marcada, la primera que
         | tenga. Nunca inventa una: si no tiene ninguna, devuelve null
         | y aquí se corta con un mensaje claro.
         |
         | Ojo: se filtra por empresas activas. Entrar a una empresa
         | desactivada dejaría al usuario mirando listados vacíos sin
         | entender por qué.
         * -------------------------------------------------------- */
        $empresa = $usuario->companies()
            ->where('is_active', true)
            ->orderByPivot('is_default', 'desc')
            ->orderBy('name')
            ->first();

        if (! $empresa) {
            throw ValidationException::withMessages([
                'email' => 'Su usuario no tiene ninguna empresa asignada. '
                         .'Contacte al administrador del sistema.',
            ]);
        }

        /* -----------------------------------------------------------
         | Esta es la única llamada que abre sesión de verdad.
         * -------------------------------------------------------- */
        if (! Auth::attempt(
            ['email' => $this->email, 'password' => $this->password],
            $this->remember,
        )) {
            throw ValidationException::withMessages([
                'email' => 'No se pudo iniciar la sesión. Intente de nuevo.',
            ]);
        }

        /* -----------------------------------------------------------
         | switchCompany() verifica que el usuario tenga acceso real a
         | esa empresa antes de guardarla en la sesión.
         |
         | Aquí la empresa la eligió el servidor, no el navegador, así
         | que no puede venir manipulada. Se llama igual porque es el
         | único sitio que escribe `current_company_id` y conviene que
         | siga siendo el único.
         * -------------------------------------------------------- */
        if (! auth()->user()->switchCompany($empresa->id)) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'No tiene acceso a la empresa asignada. Contacte al administrador.',
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

    public function render()
    {
        return view('livewire.auth.login');
    }
}
