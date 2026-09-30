<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Data;
use App\Recommendation\RuleClassifier;

class ProcessEduplay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $search; 
    public string $profile;
    public $time;
    public ?string $meta;

    public function __construct($search, $profile, $time, $meta)
    {
        $this->search = $search;
        $this->profile = $profile;
        $this->time = $time;
        $this->meta = $meta;
    }

    public function handle(): void
    {
        $start_total_time = microtime(true); 
        $page = 1;
        
        $metrics = [
            'api_time'        => 0,
            'api_calls'       => 0,
            'items_returned'  => 0,
            'items_filtered'  => 0,
            'timeouts_errors' => 0,
            'breakdown'       => [
                'meta_both' => 0, 'meta_one' => 0, 'meta' => 0,
                'both' => 0, 'profile' => 0, 'interest' => 0,
            ]
        ];

        $allData = [];
        $model = Data::query()->where('searched_at', $this->time)->first();

        while ($page < 10) {
            $metrics['api_calls']++;
            $start_api = microtime(true);

            try {
                $response = Http::withOptions(['verify' => false])
                    ->timeout(15)
                    ->get("https://eduplay.rnp.br/api/v1/search?term={$this->search}&page={$page}&quantity=10&type=0&order=0");
                
                $search = $response->json();
                $metrics['api_time'] += (microtime(true) - $start_api);

                if (empty($search) || !isset($search['contents'])) {
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
                'interatividade'       => 'Ativo',
                'nivel_interatividade' => 'Alto / Muito alto',
                'estilo_aprendizagem'  => 'Intuitivo / Ativo / Auditivo/Visual',
                'estrategia'           => 'Ativa / Abstrata / Visual/Verbal',
            ];

            foreach ($search['contents'] as $rea) {
                // Eduplay segue uma regra mais direta nas recomendações atuais do seu sistema
                $recommended = ($this->meta === 'ma' || $this->meta === 'mpa') ? 'meta_one' : 'interest';
                $explicacao = RuleClassifier::explicacao(
                    $this->criterios(),
                    $recommended,
                    'O Eduplay só tem vídeos e não informa etapa; a faixa é definida por política do SisREAd a partir da sua meta.'
                );

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
                    'title'        => $rea['name'],
                    'link'         => $rea['contentUrl'],
                    'type'         => 'Vídeo',
                    'repositorio'  => 'Eduplay',
                    'recommended'  => $recommended,
                    'explicacao'   => $explicacao,
                    'titulo'       => $rea['name'],
                    'descricao'    => $rea['metatagDescription'] ?? '',
                    'tipoConteudo' => 'Vídeo',
                    'dtype'        => 'T',
                ], $interactivityData);
            }
        }

        if (!empty($allData)) {
            $existingData = $model->data ? json_decode($model->data, true) : [];
            $model->update(['data' => json_encode(array_merge($existingData, $allData))]);
        }

        $total_time = microtime(true) - $start_total_time;
        $model->update(['finished' => true, 'time' => $model->time + $total_time]);

        DB::table('search_metrics')->insert([
            'searched_at'     => $this->time,
            'repository'      => 'Eduplay',
            'profile'         => $this->profile,
            'interest'        => $this->search,
            'meta'            => $this->meta,
            'total_time'      => $total_time,
            'api_time'        => $metrics['api_time'],
            'api_calls'       => $metrics['api_calls'],
            'items_returned'  => $metrics['items_returned'],
            'items_filtered'  => $metrics['items_filtered'],
            'timeouts_errors' => $metrics['timeouts_errors'],
            'breakdown'       => json_encode($metrics['breakdown']),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Critérios do Eduplay: nível e tipo não são verificados; a meta segue uma regra fixa do repositório.
     */
    private function criterios(): array
    {
        $criterios = [
            'tema'  => RuleClassifier::criterioTema($this->search, 'Eduplay'),
            'nivel' => [
                'status'    => 'nao_avaliado',
                'valor'     => null,
                'esperado'  => RuleClassifier::normalizar($this->profile),
                'fonte'     => 'padrao_repositorio',
                'evidencia' => 'o Eduplay não informa a etapa de ensino',
            ],
            'tipo'  => [
                'status'    => 'nao_avaliado',
                'valor'     => 'video',
                'esperado'  => [],
                'fonte'     => 'padrao_repositorio',
                'evidencia' => 'o Eduplay só tem vídeos e o tipo não é comparado com os preferidos',
            ],
        ];

        if ($this->meta) {
            $criterios['meta'] = [
                'status'    => in_array($this->meta, ['ma', 'mpa'], true) ? 'ok' : 'falhou',
                'valor'     => 'video',
                'esperado'  => $this->meta,
                'fonte'     => 'padrao_repositorio',
                'evidencia' => 'Vídeos do Eduplay são considerados adequados às metas Aprendizagem e Performance-aproximação.',
            ];
        }

        return $criterios;
    }
}