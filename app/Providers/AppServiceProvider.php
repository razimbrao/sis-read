<?php

namespace App\Providers;

use App\Experimento\Experimento;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Experimento::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @explicabilidade('flag') ... @else ... @endexplicabilidade (docs/feature-flags.md)
        Blade::if('explicabilidade', fn (string $flag) => Experimento::ativa($flag));
    }
}
