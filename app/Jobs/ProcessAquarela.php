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

class ProcessAquarela implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $search;

    public $time;

    public array $types;

    public string $profile;

    public ?string $meta;

    /**
     * De onde veio a lista de tipos preferidos (TiposPreferidos::ORIGENS). Jobs enfileirados antes
     * desta propriedade existir ficam com o padrão, os colaboradores.
     */
    public string $origemTipos = TiposPreferidos::COLABORADORES;

    private array $metrics = [
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

    public function __construct($search, $types, $profile, $time, $meta = null, string $origemTipos = TiposPreferidos::COLABORADORES)
    {
        $this->search = $search;
        $this->types = $types;
        $this->profile = $profile;
        $this->time = $time;
        $this->meta = $meta;
        $this->origemTipos = $origemTipos;
    }

    public function handle()
    {
        $start_total_time = microtime(true);
        $page = 0;
        $allData = [];
        $posicao = 0; // ordem do REA no Aquarela, usada no desempate do grau

        $model = Data::query()->where('searched_at', $this->time)->first();
        $classificador = app(MetaClassifier::class);

        while ($page < 3) {
            $this->metrics['api_calls']++;
            $start_api = microtime(true);

            try {
                $response = Http::withOptions(['verify' => false])
                    ->timeout(15)
                    ->get(config('app.aquarela.api'), [
                        'string' => $this->search,
                        'page' => $page,
                    ]);

                $search = $response->json('reas', []);
                $this->metrics['api_time'] += (microtime(true) - $start_api);

                if (empty($search)) {
                    break;
                }

                $this->metrics['items_returned'] += count($search);

            } catch (\Exception $e) {
                $this->metrics['api_time'] += (microtime(true) - $start_api);
                $this->metrics['timeouts_errors']++;
                break;
            }

            // Com meta, a página inteira vai para a LLM de uma vez (cache e chamadas simultâneas).
            $metas = $this->meta ? $classificador->criterios($this->meta, array_map(fn ($rea) => [
                'chave' => $this->chave($rea),
                'titulo' => $rea['titulo'] ?? null,
                'descricao' => $rea['descricao'] ?? null,
                'tipo' => $rea['tipoConteudo'] ?? null,
                'dtype' => $rea['dtype'] ?? null,
            ], $search)) : [];

            foreach ($search as $i => $rea) {
                $interactivityData = $this->definirInteratividade($rea['dtype'] ?? '');

                $criterios = $this->avaliarCriterios($rea, $metas[$i] ?? null);
                $recommended = RuleClassifier::rotular($criterios, (bool) $this->meta);
                $posicao++;

                // 📊 1. Incrementa a radiografia exata
                if (isset($this->metrics['breakdown'][$recommended])) {
                    $this->metrics['breakdown'][$recommended]++;
                }

                // 🎯 2. Define se é um "Item Filtrado/Aproveitado" baseado no contexto
                if ($this->meta) {
                    if (in_array($recommended, ['meta_both', 'meta_one', 'meta'])) {
                        $this->metrics['items_filtered']++;
                    }
                } else {
                    if (in_array($recommended, ['both', 'profile', 'interest'])) {
                        $this->metrics['items_filtered']++;
                    }
                }

                $allData[] = array_merge([
                    'chave' => $this->chave($rea),
                    'title' => $rea['titulo'],
                    'link' => $rea['links'][0]['href'] ?? null,
                    'type' => $rea['tipoConteudo'],
                    'repositorio' => 'Aquarela',
                    'recommended' => $recommended,
                    'explicacao' => RuleClassifier::explicacao($criterios, $recommended, $posicao),
                    'titulo' => $rea['titulo'],
                    'descricao' => $rea['descricao'],
                    'tipoConteudo' => $rea['tipoConteudo'],
                    'dtype' => $rea['dtype'],
                ], $interactivityData);
            }

            $page++;
        }

        if (! empty($allData)) {
            $existingData = $model->data ? json_decode($model->data, true) : [];
            $mergedData = array_merge($existingData, $allData);
            $model->update(['data' => json_encode($mergedData)]);
        }

        $total_time = microtime(true) - $start_total_time;
        $model->update(['finished' => true, 'time' => $model->time + $total_time]);

        DB::table('search_metrics')->insert([
            'searched_at' => $this->time,
            'repository' => 'Aquarela',
            'profile' => $this->profile,
            'interest' => $this->search,
            'meta' => $this->meta,
            'total_time' => $total_time,
            'api_time' => $this->metrics['api_time'],
            'api_calls' => $this->metrics['api_calls'],
            'items_returned' => $this->metrics['items_returned'],
            'items_filtered' => $this->metrics['items_filtered'],
            'timeouts_errors' => $this->metrics['timeouts_errors'],
            'breakdown' => json_encode($this->metrics['breakdown']),
            'created_at' => now(),
            'updated_at' => now(),
        ] + $classificador->metricas());
    }

    private function definirInteratividade(string $dtype): array
    {
        if ($dtype === 'T') {
            return [
                'fonte_interatividade' => 'dtype',
                'interatividade' => 'Ativo',
                'nivel_interatividade' => 'Alto / Muito alto',
                'estilo_aprendizagem' => 'Intuitivo / Ativo / Auditivo/Visual',
                'estrategia' => 'Ativa / Abstrata / Visual/Verbal',
            ];
        }
        if ($dtype === 'D') {
            return [
                'fonte_interatividade' => 'dtype',
                'interatividade' => 'Expositivo',
                'nivel_interatividade' => 'Baixo / Muito baixo',
                'estilo_aprendizagem' => 'Sensorial / Reflexivo / Auditivo/Visual',
                'estrategia' => 'Passiva / Concreta / Visual/Verbal',
            ];
        }

        return [
            'fonte_interatividade' => 'indisponivel',
            'interatividade' => 'Não especificado',
            'nivel_interatividade' => 'Não especificado',
            'estilo_aprendizagem' => 'Não especificado',
            'estrategia' => 'Não especificado',
        ];
    }

    private function chave(array $rea): string
    {
        return RuleClassifier::chave('Aquarela', $rea['links'][0]['href'] ?? null, $rea['titulo']);
    }

    /**
     * Avalia tema, nível, tipo e (com meta) meta; o rótulo é derivado destes critérios.
     * O critério de meta vem pronto do MetaClassifier.
     */
    private function avaliarCriterios(array $rea, ?array $meta): array
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema($this->search, 'Aquarela'),
            'nivel' => RuleClassifier::criterioNivel(
                $this->profile,
                RuleClassifier::inferirNivel($rea['titulo'] ?? '', $rea['descricao'] ?? '')
            ),
            'tipo' => RuleClassifier::criterioTipo(
                $rea['tipoConteudo'] ?? '',
                RuleClassifier::normalizarTipos($this->types),
                $this->origemTipos
            ),
        ];

        if ($this->meta && $meta !== null) {
            $criterios['meta'] = $meta;
        }

        return $criterios;
    }
}
