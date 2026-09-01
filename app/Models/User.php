<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /* =====================================================================
     | CONFIGURACIÓN
     * ================================================================== */

    protected $fillable = ['name', 'email', 'password', 'phone', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    /* =====================================================================
     | RELACIONES
     * ================================================================== */

    /**
     * Un usuario puede trabajar en las dos compañías.
     * El pivot marca cuál se abre por defecto al iniciar sesión;
     * no limita el acceso a la otra.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function driver()      { return $this->hasOne(Driver::class); }
    public function commissions() { return $this->hasMany(Commission::class, 'salesperson_id'); }

    /* =====================================================================
     | LECTURA
     * ================================================================== */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** La compañía que se selecciona al entrar. */
    public function defaultCompany(): ?Company
    {
        return $this->companies()->wherePivot('is_default', true)->first()
            ?? $this->companies()->first();
    }

    /** La compañía activa en este momento.
     *  Si el id de la sesión no sirve, cae a la compañía
     * predeterminada en vez de dejarlo colgado.
     */
    public function currentCompany(): ?Company
    {
        $idEnSesion = session('current_company_id');

        if ($idEnSesion) {
            // find() sobre la relación ya verifica la pertenencia:
            // si el usuario no está en company_user para esa empresa,
            // no la encuentra. Es la protección contra que alguien
            // manipule el id guardado en la sesión.
            $company = $this->companies()->find($idEnSesion);

            if ($company) {
                return $company;
            }

            // El id guardado ya no sirve. Se limpia para no volver a
            // consultarlo en cada petición.
            session()->forget('current_company_id');
        }

        return $this->defaultCompany();
    }

    public function belongsToCompany(int $companyId): bool
    {
        return $this->companies()->whereKey($companyId)->exists();
    }

    /* =====================================================================
     | ESCRITURA
     * ================================================================== */

    /**
     * Cambia la compañía activa. Verifica primero que tenga acceso:
     * sin esto, cualquiera podría cambiar el id en la sesión y ver
     * los datos de la otra empresa.
     */
    public function switchCompany(int $companyId): bool
    {
        if (! $this->belongsToCompany($companyId)) {
            return false;
        }

        session(['current_company_id' => $companyId]);

        return true;
    }

}
