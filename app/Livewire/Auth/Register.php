<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Register extends Component
{
    /** Tamanho mínimo da senha: a regra de validação e a lista de requisitos da tela usam este valor. */
    public const SENHA_MINIMA = 6;

    public $name;

    public $email;

    public $password;

    public $password_confirmation;

    public function register()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:'.self::SENHA_MINIMA.'|confirmed',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        Auth::login($user);

        return redirect(comParams(url('/')));
    }

    public function render()
    {
        return view('livewire.auth.register', ['senhaMinima' => self::SENHA_MINIMA])
            ->layout('layouts.app', ['title' => 'Cadastro']);
    }
}
