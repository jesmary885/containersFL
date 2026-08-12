<?php

namespace App\Livewire\Auth;

use Livewire\Component;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Login extends Component
{

    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt(
            ['email' => $this->email, 'password' => $this->password],
            $this->remember
        )) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }


    public function render()
    {
        return view('livewire.auth.login');
    }
}
