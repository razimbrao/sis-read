<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Emapre;
use App\Livewire\Preferencias;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/privacidade', 'privacidade')->name('privacidade');

Route::get('/login', Login::class)->name('login');
Route::get('/register', Register::class)->name('register');

Route::middleware('auth')->group(function () {
    Route::post('/logout', function () {
        Auth::logout();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/emapre', Emapre::class)->name('emapre');
    Route::get('/conta/preferencias', Preferencias::class)->name('preferencias');
});
