<?php

namespace App\Livewire\Users;

use App\Livewire\Concerns\AuthorizesAccess;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ALTA Y EDICIÓN DE USUARIOS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Tres bloques: quién es, a qué empresas entra, y qué puede hacer.
 *
 * ── LOS TRES SE GUARDAN JUNTOS O NO SE GUARDA NINGUNO ──
 *
 * Un usuario sin empresa no puede iniciar sesión: `Login` lo rechaza
 * con "su usuario no tiene ninguna empresa asignada". Y un usuario sin
 * rol entra pero no ve nada, porque ahora todas las rutas piden permiso.
 *
 * Si se guardaran por separado, un fallo a mitad dejaría una cuenta
 * creada e inservible, y la persona llamaría diciendo que el sistema
 * está roto. Por eso va todo en una transacción.
 *
 * ── LA CONTRASEÑA ──
 *
 * Al crear es obligatoria. Al editar se deja en blanco y no se toca:
 * nadie tiene por qué reescribir la contraseña de otro para corregirle
 * el teléfono. El `hashed` del modelo User la cifra sola, así que aquí
 * se asigna en claro y nunca se llama a Hash::make().
 * ═══════════════════════════════════════════════════════════════════════════
 */
#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesAccess;

    protected string $permisoBase = 'users';

    /** null = usuario nuevo. */
    public ?int $userId = null;

    /* =====================================================================
     | QUIÉN ES
     * ================================================================== */

    public string $name     = '';
    public string $email    = '';
    public ?string $phone   = null;
    public string $locale   = 'es';
    public bool $is_active  = true;

    public string $password              = '';
    public string $password_confirmation = '';

    /* =====================================================================
     | A QUÉ EMPRESAS ENTRA
     |
     | $empresas guarda los ids marcados. $empresaPorDefecto es cuál se
     | abre al iniciar sesión — el `is_default` de la tabla pivote.
     * ================================================================== */

    public array $empresas = [];

    public ?int $empresaPorDefecto = null;

    /* =====================================================================
     | QUÉ PUEDE HACER
     |
     | Un solo rol por persona, no varios.
     |
     | Spatie permite varios y el sistema lo soportaría, pero con seis
     | roles y cuatro empleados, "Denisse es admin y además ventas" no
     | significa nada claro: los permisos se suman y nadie sabe de dónde
     | salió cada uno. Un rol por persona se explica en una frase.
     * ================================================================== */

    public string $rol = '';

    /* =====================================================================
     | ARRANQUE
     * ================================================================== */

    public function mount(?User $user = null)
    {
        if ($user && $user->exists) {
            $this->exigirPermiso('update');
            $this->cargarDesde($user);

            return null;
        }

        $this->exigirPermiso('create');

        /*
         | Un usuario nuevo nace con la empresa activa ya marcada. Es la
         | que está usando quien lo está creando, así que acierta casi
         | siempre y ahorra el clic.
         */
        $empresaActiva = app(\App\Support\CompanyContext::class)->get();

        if ($empresaActiva) {
            $this->empresas          = [$empresaActiva->id];
            $this->empresaPorDefecto = $empresaActiva->id;
        }

        return null;
    }

    protected function cargarDesde(User $user): void
    {
        $this->userId = $user->id;

        $this->name      = $user->name;
        $this->email     = $user->email;
        $this->phone     = $user->phone;
        $this->locale    = $user->locale ?: 'es';
        $this->is_active = (bool) $user->is_active;

        $this->empresas = $user->companies->pluck('id')->all();

        $this->empresaPorDefecto = $user->companies
            ->firstWhere('pivot.is_default', true)?->id
            ?? ($this->empresas[0] ?? null);

        $this->rol = $user->roles->first()?->name ?? '';
    }

    /* =====================================================================
     | REACCIONES
     * ================================================================== */

    /**
     * Si se desmarca la empresa que era la predeterminada, hay que
     * elegir otra. Sin esto, `is_default` quedaría apuntando a una
     * empresa a la que el usuario ya no tiene acceso, y
     * `defaultCompany()` devolvería null: la persona inicia sesión y
     * cae en una pantalla sin empresa activa.
     */
    public function updatedEmpresas(): void
    {
        $this->empresas = array_values(array_map('intval', $this->empresas));

        if (! in_array($this->empresaPorDefecto, $this->empresas, true)) {
            $this->empresaPorDefecto = $this->empresas[0] ?? null;
        }
    }

    /* =====================================================================
     | LAS REGLAS
     * ================================================================== */

    protected function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:120'],

            /*
             | `ignore` deja pasar el propio correo al editar. Sin él,
             | guardar sin cambiar nada fallaría diciendo que el correo
             | ya existe — y existe, es el suyo.
             */
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],

            'phone'  => ['nullable', 'string', 'max:40'],
            'locale' => ['required', Rule::in(['es', 'en'])],

            'password' => [
                $this->userId ? 'nullable' : 'required',
                'string', 'min:8', 'confirmed',
            ],

            'empresas'   => ['required', 'array', 'min:1'],
            'empresas.*' => ['integer', 'exists:companies,id'],

            'empresaPorDefecto' => ['required', 'integer', Rule::in($this->empresas)],

            'rol' => ['required', 'string', Rule::exists('roles', 'name')],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name'              => 'nombre',
            'email'             => 'correo',
            'phone'             => 'teléfono',
            'locale'            => 'idioma',
            'password'          => 'contraseña',
            'empresas'          => 'empresas',
            'empresaPorDefecto' => 'empresa predeterminada',
            'rol'               => 'rol',
        ];
    }

    protected function messages(): array
    {
        return [
            'empresas.required'          => 'Marque al menos una empresa. Sin empresa el usuario no puede iniciar sesión.',
            'empresaPorDefecto.required' => 'Elija con qué empresa abre el sistema.',
            'empresaPorDefecto.in'       => 'La empresa predeterminada tiene que ser una de las marcadas.',
            'rol.required'               => 'Elija un rol. Sin rol el usuario entra pero no ve ninguna pantalla.',
            'password.required'          => 'Escriba una contraseña para el usuario nuevo.',
            'password.confirmed'         => 'Las dos contraseñas no coinciden.',
            'password.min'               => 'La contraseña necesita al menos 8 caracteres.',
        ];
    }

    /* =====================================================================
     | GUARDAR
     * ================================================================== */

    public function guardar()
    {
        $this->exigirPermiso($this->userId ? 'update' : 'create');

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->dispatch('errores-de-validacion');

            throw $e;
        }

        /* -----------------------------------------------------------------
         | LAS DOS PROTECCIONES SOBRE UNO MISMO
         |
         | Editando tu propia ficha puedes hacer dos cosas que te dejan
         | fuera del sistema sin poder volver a entrar:
         |
         |   · quitarte el rol de super admin siendo el único
         |   · desactivarte
         |
         | Las dos se hacen sin querer, y las dos obligan a entrar por la
         | base de datos a arreglarlo.
         * -------------------------------------------------------------- */
        if ($this->userId === auth()->id()) {

            if (! $this->is_active) {
                session()->flash('error', 'No puede desactivar su propio usuario.');

                return null;
            }

            $usuarioActual = auth()->user();

            if ($usuarioActual->hasRole('super_admin')
                && $this->rol !== 'super_admin'
                && User::role('super_admin')->where('is_active', true)->count() <= 1) {

                session()->flash('error',
                    'Es el único super administrador activo. Asigne ese rol a otra '
                    .'persona antes de quitárselo.');

                return null;
            }
        }

        $usuario = DB::transaction(function () {

            $datos = [
                'name'      => $this->name,
                'email'     => $this->email,
                'phone'     => $this->phone ?: null,
                'locale'    => $this->locale,
                'is_active' => $this->is_active,
            ];

            /*
             | La contraseña solo entra si se escribió. El caste 'hashed'
             | del modelo la cifra al asignarla — llamar a Hash::make()
             | aquí la cifraría dos veces y nadie podría iniciar sesión.
             */
            if (filled($this->password)) {
                $datos['password'] = $this->password;
            }

            $usuario = $this->userId
                ? tap(User::findOrFail($this->userId))->update($datos)
                : User::create($datos);

            /* -------------------------------------------------------------
             | LAS EMPRESAS Y CUÁL ES LA PREDETERMINADA
             |
             | sync() con el pivote armado de una vez: se manda un arreglo
             | id => [columnas del pivote]. Así se resuelven en una sola
             | operación las que se agregan, las que se quitan y el
             | is_default, sin dejar un instante en el que el usuario no
             | tenga ninguna.
             * ---------------------------------------------------------- */
            $pivote = collect($this->empresas)
                ->mapWithKeys(fn ($id) => [
                    (int) $id => ['is_default' => (int) $id === (int) $this->empresaPorDefecto],
                ])
                ->all();

            $usuario->companies()->sync($pivote);

            /* -------------------------------------------------------------
             | EL ROL
             |
             | syncRoles() y no assignRole(): el primero reemplaza, el
             | segundo suma. Con assignRole, cambiar a alguien de
             | "ventas" a "contabilidad" lo dejaría con los dos.
             * ---------------------------------------------------------- */
            $usuario->syncRoles([$this->rol]);

            return $usuario;
        });

        session()->flash('exito',
            $this->userId
                ? 'Usuario '.$usuario->name.' actualizado.'
                : 'Usuario '.$usuario->name.' creado. Ya puede iniciar sesión.');

        return redirect()->route('administracion.usuarios.index');
    }

    /* =====================================================================
     | LO QUE SE PINTA
     * ================================================================== */

    public function render()
    {
        return view('livewire.users.form', [

            'empresasDisponibles' => Company::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),

            /*
             | Los roles con su descripción en castellano. El nombre
             | técnico ("operations") es el que guarda Spatie; el texto
             | de al lado es lo que hace que alguien sepa cuál elegir sin
             | tener que abrir la pantalla de permisos.
             */
            'rolesDisponibles' => Role::orderBy('name')->get(['id', 'name'])
                ->map(fn ($rol) => [
                    'name'  => $rol->name,
                    'label' => self::ETIQUETAS[$rol->name]['label'] ?? $rol->name,
                    'ayuda' => self::ETIQUETAS[$rol->name]['ayuda'] ?? '',
                ]),
        ]);
    }

    /**
     * Los seis roles del RoleSeeder, explicados.
     *
     * Vive aquí y no en la base porque la tabla `roles` de Spatie no
     * tiene columna de descripción, y agregársela obligaría a mantener
     * una migración propia de la librería. Si mañana hacen falta roles
     * a medida desde la pantalla, entonces sí.
     */
    public const ETIQUETAS = [
        'super_admin' => [
            'label' => 'Super administrador',
            'ayuda' => 'Todo, sin excepciones. Incluye crear usuarios y cambiar la configuración.',
        ],
        'admin' => [
            'label' => 'Administrador',
            'ayuda' => 'Opera el sistema completo salvo la administración de usuarios.',
        ],
        'operations' => [
            'label' => 'Operaciones',
            'ayuda' => 'Contenedores, rentas, viajes, choferes y compras. No ve la facturación.',
        ],
        'sales' => [
            'label' => 'Ventas',
            'ayuda' => 'Clientes, presupuestos y ventas. No registra pagos ni gastos.',
        ],
        'accounting' => [
            'label' => 'Contabilidad',
            'ayuda' => 'Facturas, pagos, gastos y comisiones. No toca el inventario.',
        ],
        'driver' => [
            'label' => 'Chofer',
            'ayuda' => 'Solo sus viajes y sus gastos. Pensado para el panel del chofer.',
        ],
    ];
}
