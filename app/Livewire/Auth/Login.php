<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

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
