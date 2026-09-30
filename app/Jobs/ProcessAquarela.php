<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Data;
use App\Recommendation\RuleClassifier;

class ProcessAquarela implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $search;
    public $time;
    public array $types;
    public string $profile;
    public ?string $meta;

    private array $metrics = [
        'api_time'        => 0,
        'api_calls'       => 0,
        'ollama_time'     => 0,
        'ollama_calls'    => 0,
        'items_returned'  => 0,
        'items_filtered'  => 0,
        'timeouts_errors' => 0,
        'ollama_errors'   => 0,
        'breakdown'       => [
            'meta_both' => 0, 'meta_one' => 0, 'meta' => 0,
            'both' => 0, 'profile' => 0, 'interest' => 0,
        ]
    ];

    public function __construct($search, $types, $profile, $time, $meta = null)
    {
        $this->search = $search;
        $this->types = $types;
        $this->profile = $profile;
        $this->time = $time;
        $this->meta = $meta;
    }

    public function handle()
    {   
        $start_total_time = microtime(true); 
        $page = 0;
        $allData = []; 

        $model = Data::query()->where('searched_at', $this->time)->first();

        while ($page < 3) {
            $this->metrics['api_calls']++;
            $start_api = microtime(true);

            try {
                $response = Http::withOptions(['verify' => false])
                    ->timeout(15) 
                    ->get(config('app.aquarela.api'), [
                        'string' => $this->search,
                        'page'   => $page
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

            foreach ($search as $rea) {
                $interactivityData = $this->definirInteratividade($rea['dtype'] ?? '');
                
                $criterios = $this->avaliarCriterios($rea);
                $recommended = RuleClassifier::rotular($criterios, (bool) $this->meta);

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
                    'title'        => $rea['titulo'],
                    'link'         => $rea['links'][0]['href'] ?? null,
                    'type'         => $rea['tipoConteudo'],
                    'repositorio'  => 'Aquarela',
                    'recommended'  => $recommended,
                    'explicacao'   => RuleClassifier::explicacao($criterios, $recommended),
                    'titulo'       => $rea['titulo'],
                    'descricao'    => $rea['descricao'],
                    'tipoConteudo' => $rea['tipoConteudo'],
                    'dtype'        => $rea['dtype'],
                ], $interactivityData);
            }

            $page++;
        }

        if (!empty($allData)) {
            $existingData = $model->data ? json_decode($model->data, true) : [];
            $mergedData = array_merge($existingData, $allData);
            $model->update(['data' => json_encode($mergedData)]);
        }

        $total_time = microtime(true) - $start_total_time; 
        $model->update(['finished' => true, 'time' => $model->time + $total_time]);

        DB::table('search_metrics')->insert([
            'searched_at'     => $this->time,
            'repository'      => 'Aquarela',
            'profile'         => $this->profile,
            'interest'        => $this->search,
            'meta'            => $this->meta,
            'total_time'      => $total_time,
            'api_time'        => $this->metrics['api_time'],
            'api_calls'       => $this->metrics['api_calls'],
            'ollama_time'     => $this->metrics['ollama_time'],
            'ollama_calls'    => $this->metrics['ollama_calls'],
            'items_returned'  => $this->metrics['items_returned'],
            'items_filtered'  => $this->metrics['items_filtered'],
            'timeouts_errors' => $this->metrics['timeouts_errors'],
            'ollama_errors'   => $this->metrics['ollama_errors'],
            'breakdown'       => json_encode($this->metrics['breakdown']),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function definirInteratividade(string $dtype): array
    {
        if ($dtype === 'T') {
            return [
                'fonte_interatividade' => 'dtype',
                'interatividade'       => 'Ativo',
                'nivel_interatividade' => 'Alto / Muito alto',
                'estilo_aprendizagem'  => 'Intuitivo / Ativo / Auditivo/Visual',
                'estrategia'           => 'Ativa / Abstrata / Visual/Verbal',
            ];
        } 
        if ($dtype === 'D') {
            return [
                'fonte_interatividade' => 'dtype',
                'interatividade'       => 'Expositivo',
                'nivel_interatividade' => 'Baixo / Muito baixo',
                'estilo_aprendizagem'  => 'Sensorial / Reflexivo / Auditivo/Visual',
                'estrategia'           => 'Passiva / Concreta / Visual/Verbal',
            ];
        }
        return [
            'fonte_interatividade' => 'indisponivel',
            'interatividade'       => 'Não especificado',
            'nivel_interatividade' => 'Não especificado',
            'estilo_aprendizagem'  => 'Não especificado',
            'estrategia'           => 'Não especificado',
        ];
    }

    /**
     * Avalia tema, nível, tipo e (com meta) meta; o rótulo é derivado destes critérios.
     */
    private function avaliarCriterios(array $rea): array
    {
        $criterios = [
            'tema'  => RuleClassifier::criterioTema($this->search, 'Aquarela'),
            'nivel' => RuleClassifier::criterioNivel(
                $this->profile,
                RuleClassifier::inferirNivel($rea['titulo'] ?? '', $rea['descricao'] ?? '')
            ),
            'tipo'  => RuleClassifier::criterioTipo(
                $rea['tipoConteudo'] ?? '',
                RuleClassifier::normalizarTipos($this->types)
            ),
        ];

        // Só aciona o Ollama se o cenário atual exigir meta.
        if ($this->meta) {
            $criterios['meta'] = RuleClassifier::criterioMeta($this->meta, $this->classificarMetaComLLM($rea));
        }

        return $criterios;
    }

    private function classificarMetaComLLM(array $rea): string
    {
        $prompt = <<<PROMPT
        Classifique o REA em APENAS uma das metas abaixo.

        Título: {$rea['titulo']}
        Descrição: {$rea['descricao']}
        Tipo: {$rea['tipoConteudo']}
        DType: {$rea['dtype']}

        Critérios:

        - Aprendizagem: prioriza compreensão profunda, construção de conhecimento, investigação, criação de projetos, resolução de problemas, desenvolvimento de habilidades e autonomia.
        - Performance Aproximação: prioriza demonstrar desempenho, alcançar resultados, competir, testar conhecimentos, desafios, jogos, avaliações ou obtenção de reconhecimento.
        - Performance Evitação: prioriza reduzir dificuldades, facilitar a entrada no tema, apresentar conceitos introdutórios, básicos ou simplificados, minimizando erros e insegurança.

        Escolha a meta predominante considerando principalmente o objetivo pedagógico do recurso, não apenas palavras isoladas do título.

        Responda SOMENTE com um JSON no formato {"meta": "<opção>"}, em que <opção> é uma de:
        Aprendizagem
        Performance Aproximação
        Performance Evitação
        PROMPT;

        $this->metrics['ollama_calls']++;
        $start_ollama = microtime(true);

        try {
            $response = Http::timeout(10)
                ->post("http://127.0.0.1:11434/api/generate", [
                    'model'  => 'gemma3:4b',
                    'prompt' => $prompt,
                    'format' => 'json', 
                    'stream' => false,
                ]);

            $this->metrics['ollama_time'] += (microtime(true) - $start_ollama);

            if ($response->successful()) {
                $result = json_decode((string) $response->json('response'), true);
                $meta = is_array($result) ? ($result['meta'] ?? null) : null;

                return is_string($meta) && $meta !== '' ? $meta : 'Não classificado';
            }

            $this->metrics['ollama_errors']++;
            return 'Não classificado';

        } catch (\Exception $e) {
            $this->metrics['ollama_time'] += (microtime(true) - $start_ollama);
            $this->metrics['ollama_errors']++;
            return 'Não classificado';
        }
    }
}