<?php

namespace App\Providers;

use App\Experimento\Experimento;
use App\Recommendation\Llm\OllamaProvedor;
use App\Recommendation\Llm\ProvedorLlm;
use App\Recommendation\MetaClassifier;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Provedor da LLM de classificação de meta (config/llm.php). Um provedor novo entra neste match.
        $this->app->bind(ProvedorLlm::class, fn () => match (config('llm.provedor')) {
            'ollama' => new OllamaProvedor(config('llm.ollama.url'), config('llm.ollama.modelo'), (string) config('llm.ollama.keep_alive', '10m')),
        });

        // Uma instância por job: as métricas e o orçamento de tempo são de cada job.
        $this->app->bind(MetaClassifier::class, function ($app) {
            $provedor = config('llm.provedor');

            if ($provedor !== 'ollama') {
                if ($provedor !== 'nenhum') {
                    Log::warning("LLM_PROVEDOR desconhecido ({$provedor}); a classificação de meta fica desativada.");
                }

                return new MetaClassifier(null, config('llm', []));
            }

            return new MetaClassifier($app->make(ProvedorLlm::class), config('llm', []));
        });

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
