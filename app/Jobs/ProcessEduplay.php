<?php

namespace App\Jobs;

use App\Models\Data;
use App\Recommendation\MetaClassifier;
use App\Recommendation\RuleClassifier;
use App\Recommendation\TiposPreferidos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProcessEduplay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $search;

    public string $profile;

    public $time;

    public ?string $meta;

    public array $types;

    /**
     * De onde veio a lista de tipos preferidos (TiposPreferidos::ORIGENS). Jobs enfileirados antes
     * desta propriedade existir ficam com o padrão, os colaboradores.
     */
    public string $origemTipos = TiposPreferidos::COLABORADORES;

    public function __construct($search, $profile, $time, $meta, array $types = [], string $origemTipos = TiposPreferidos::COLABORADORES)
    {
        $this->search = $search;
        $this->profile = $profile;
        $this->time = $time;
        $this->meta = $meta;
        $this->types = $types;
        $this->origemTipos = $origemTipos;
    }

    public function handle(): void
    {
        $start_total_time = microtime(true);
        $page = 1;

        $metrics = [
            'api_time' => 0,
            'api_calls' => 0,
            'items_returned' => 0,
            'items_filtered' => 0,
            'timeouts_errors' => 0,
            'breakdown' => [
                'meta_both' => 0, 'meta_one' => 0, 'meta' => 0,
                'both' => 0, 'profile' => 0, 'interest' => 0,
            ],
        ];

        $allData = [];
        $posicao = 0;
        $model = Data::query()->where('searched_at', $this->time)->first();
        $classificador = app(MetaClassifier::class);

        while ($page < 10) {
            $metrics['api_calls']++;
            $start_api = microtime(true);

            try {
                $response = Http::withOptions(['verify' => false])
                    ->timeout(15)
                    ->get("https://eduplay.rnp.br/api/v1/search?term={$this->search}&page={$page}&quantity=10&type=0&order=0");

                $search = $response->json();
                $metrics['api_time'] += (microtime(true) - $start_api);

                if (empty($search) || ! isset($search['contents'])) {
                    break;
                }

                $metrics['items_returned'] += count($search['contents']);

            } catch (\Exception $e) {
                $metrics['api_time'] += (microtime(true) - $start_api);
                $metrics['timeouts_errors']++;
                break;
            }

            $page++;

            $interactivityData = [
                'fonte_interatividade' => 'padrao_repositorio',
                'interatividade' => 'Ativo',
                'nivel_interatividade' => 'Alto / Muito alto',
                'estilo_aprendizagem' => 'Intuitivo / Ativo / Auditivo/Visual',
                'estrategia' => 'Ativa / Abstrata / Visual/Verbal',
            ];

            $conteudos = array_values(array_filter($search['contents'], 'is_array'));

            // Com meta, a página inteira vai para a LLM de uma vez (cache e chamadas simultâneas).
            $metas = $this->meta ? $classificador->criterios($this->meta, array_map(fn ($rea) => [
                'chave' => $this->chave($rea),
                'titulo' => $rea['name'] ?? null,
                'descricao' => $rea['metatagDescription'] ?? null,
                'tipo' => 'Vídeo',
            ], $conteudos)) : [];

            foreach ($conteudos as $i => $rea) {
                // Mesma regra dos outros repositórios: rótulo e grau vêm só dos critérios conferidos.
                $criterios = $this->criterios($rea, $metas[$i] ?? null);
                $recommended = RuleClassifier::rotular($criterios, (bool) $this->meta);
                $explicacao = RuleClassifier::explicacao($criterios, $recommended, ++$posicao);

                // 📊 1. Incrementa a radiografia
                if (isset($metrics['breakdown'][$recommended])) {
                    $metrics['breakdown'][$recommended]++;
                }

                // 🎯 2. Avalia se é útil baseado na intenção do usuário
                if ($this->meta) {
                    if (in_array($recommended, ['meta_both', 'meta_one', 'meta'])) {
                        $metrics['items_filtered']++;
                    }
                } else {
                    if (in_array($recommended, ['both', 'profile', 'interest'])) {
                        $metrics['items_filtered']++;
                    }
                }

                $allData[] = array_merge([
                    'chave' => $this->chave($rea),
                    'title' => $rea['name'],
                    'link' => $rea['contentUrl'],
                    'type' => 'Vídeo',
                    'repositorio' => 'Eduplay',
                    'recommended' => $recommended,
                    'explicacao' => $explicacao,
                    'titulo' => $rea['name'],
                    'descricao' => $rea['metatagDescription'] ?? '',
                    'tipoConteudo' => 'Vídeo',
                    'dtype' => 'T',
                ], $interactivityData);
            }
        }

        if (! empty($allData)) {
            $existingData = $model->data ? json_decode($model->data, true) : [];
            $model->update(['data' => json_encode(array_merge($existingData, $allData))]);
        }

        $total_time = microtime(true) - $start_total_time;
        $model->update(['finished' => true, 'time' => $model->time + $total_time]);

        DB::table('search_metrics')->insert([
            'searched_at' => $this->time,
            'repository' => 'Eduplay',
            'profile' => $this->profile,
            'interest' => $this->search,
            'meta' => $this->meta,
            'total_time' => $total_time,
            'api_time' => $metrics['api_time'],
            'api_calls' => $metrics['api_calls'],
            'items_returned' => $metrics['items_returned'],
            'items_filtered' => $metrics['items_filtered'],
            'timeouts_errors' => $metrics['timeouts_errors'],
            'breakdown' => json_encode($metrics['breakdown']),
            'created_at' => now(),
            'updated_at' => now(),
        ] + $classificador->metricas());
    }

    private function chave(array $rea): string
    {
        return RuleClassifier::chave('Eduplay', $rea['contentUrl'] ?? null, $rea['name'] ?? null);
    }

    /**
     * Critérios do Eduplay, pela mesma regra dos outros repositórios: nível pelo regex sobre título e
     * descrição, tipo (sempre vídeo) comparado com os preferidos e meta classificada por IA (MetaClassifier).
     */
    private function criterios(array $rea, ?array $meta): array
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema($this->search, 'Eduplay'),
            'nivel' => RuleClassifier::criterioNivel(
                $this->profile,
                RuleClassifier::inferirNivel($rea['name'] ?? '', $rea['metatagDescription'] ?? '')
            ),
            'tipo' => RuleClassifier::criterioTipo('Vídeo', RuleClassifier::normalizarTipos($this->types), $this->origemTipos),
        ];

        if ($this->meta && $meta !== null) {
            $criterios['meta'] = $meta;
        }

        return $criterios;
    }
}
