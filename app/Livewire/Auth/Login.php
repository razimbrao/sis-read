<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public $email = '';

    public $password = '';

    public function login()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            session()->regenerate();

            return redirect(comParams(url('/')));
        }

        $this->addError('email', 'E-mail ou senha inválidos.');
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.app', ['title' => 'Login']);
    }
}
