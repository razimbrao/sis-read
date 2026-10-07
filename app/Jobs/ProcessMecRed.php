<?php

namespace App\Jobs;

use App\Models\Data;
use App\Recommendation\MetaClassifier;
use App\Recommendation\RuleClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ProcessMecRed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $search;

    public $time;

    public array $types;

    public string $profile;

    public string $interest;

    public ?string $meta;

    public function __construct($search, $types, $profile, $interest, $time, $meta)
    {
        $this->search = $search;
        $this->types = $types;
        $this->profile = $profile;
        $this->interest = $interest;
        $this->time = $time;
        $this->meta = $meta;
    }

    public function handle()
    {
        $start_total_time = microtime(true);
        $offset = 0;
        $allData = [];

        $metrics = [
            'api_time' => 0,
            'items_returned' => 0,
            'items_filtered' => 0,
            'timeouts_errors' => 0,
            'breakdown' => [
                'meta_both' => 0, 'meta_one' => 0, 'meta' => 0,
                'both' => 0, 'profile' => 0, 'interest' => 0,
            ],
        ];

        $model = Data::query()->where('searched_at', $this->time)->first();
        $classificador = app(MetaClassifier::class);

        $start_api = microtime(true);
        try {
            $search = Http::withOptions(['verify' => false])
                ->timeout(15)
                ->get(getMecRedURL(str_replace(' ', '+', $this->search), $offset, $this->profile, $this->meta))
                ->json();

            $metrics['api_time'] = microtime(true) - $start_api;
            $metrics['items_returned'] = is_array($search) ? count($search) : 0;
        } catch (\Exception $e) {
            $metrics['api_time'] = microtime(true) - $start_api;
            $metrics['timeouts_errors']++;
            $search = [];
        }

        $interactivity = 'Não especificado';
        $interactivity_level = 'Não especificado';
        $learning_style = 'Não especificado';
        $strategy = 'Não especificado';

        if ($this->meta && ($this->meta === 'ma' || $this->meta === 'mpa')) {
            $interactivity = 'Ativo';
            $interactivity_level = 'Alto / Muito alto';
            $learning_style = 'Intuitivo / Ativo / Auditivo/Visual';
            $strategy = 'Ativa / Abstrata / Visual/Verbal';
        } elseif ($this->meta && $this->meta === 'mpe') {
            $interactivity = 'Expositivo';
            $interactivity_level = 'Baixo / Muito baixo';
            $learning_style = 'Sensorial / Reflexivo / Auditivo/Visual';
            $strategy = 'Passiva / Concreta / Visual/Verbal';
        }

        $interactivityData = [
            'fonte_interatividade' => $this->meta ? 'meta_usuario' : 'indisponivel',
            'interatividade' => $interactivity,
            'nivel_interatividade' => $interactivity_level,
            'estilo_aprendizagem' => $learning_style,
            'estrategia' => $strategy,
        ];

        $search = is_array($search) ? array_values(array_filter($search, 'is_array')) : [];

        // Com meta, todos os itens vão para a LLM de uma vez. O MEC RED só devolve o nome do recurso.
        $metas = $this->meta ? $classificador->criterios($this->meta, array_map(fn ($rea) => [
            'chave' => $this->chave($rea),
            'titulo' => $rea['name'] ?? null,
            'evidencia' => 'classificado só pelo título, porque o MEC RED não informa descrição nem tipo; a busca já pediu tipos de objeto associados à sua meta (object_type='
                .implode(',', mecRedTiposObjeto($this->meta)).')',
        ], $search)) : [];

        if ($search) {
            foreach ($search as $i => $rea) {
                // Mesma regra dos outros repositórios: rótulo e grau vêm só dos critérios conferidos.
                $criterios = $this->criterios($rea, $metas[$i] ?? null);
                $recommended = RuleClassifier::rotular($criterios, (bool) $this->meta);
                $explicacao = RuleClassifier::explicacao($criterios, $recommended, $i + 1);

                // 📊 1. Incrementa a radiografia
                if (isset($metrics['breakdown'][$recommended])) {
                    $metrics['breakdown'][$recommended]++;
                }

                // 🎯 2. Avalia relevância
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
                    'title' => $rea['name'] ?? 'Sem título',
                    'id' => $rea['id'] ?? null,
                    'link' => self::linkPublico($rea['id'] ?? null),
                    'type' => '',
                    'repositorio' => 'MECRED',
                    'recommended' => $recommended,
                    'explicacao' => $explicacao,
                    'titulo' => $rea['name'] ?? '',
                    'descricao' => $rea['description'] ?? '',
                    'tipoConteudo' => '',
                    'dtype' => '',
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
            'repository' => 'MecRed',
            'profile' => $this->profile,
            'interest' => $this->interest,
            'meta' => $this->meta,
            'total_time' => $total_time,
            'api_time' => $metrics['api_time'],
            'api_calls' => 1, // Considerando que faz apenas uma chamada na API aqui
            'items_returned' => $metrics['items_returned'],
            'items_filtered' => $metrics['items_filtered'],
            'timeouts_errors' => $metrics['timeouts_errors'],
            'breakdown' => json_encode($metrics['breakdown']),
            'created_at' => now(),
            'updated_at' => now(),
        ] + $classificador->metricas());
    }

    /**
     * Página pública do recurso no MEC RED; null sem id (a busca não devolve outro link).
     */
    public static function linkPublico($id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        return rtrim((string) config('app.mecred.site'), '/').'/recurso/'.$id;
    }

    private function chave(array $rea): string
    {
        return RuleClassifier::chave('MECRED', (string) ($rea['id'] ?? ''), $rea['name'] ?? null);
    }

    /**
     * Critérios do MEC RED. A API não devolve a etapa nem o tipo de cada item (e, em 2026-09-18, ignorava
     * os filtros da URL): o nível é estimado pelo mesmo regex do Aquarela sobre título e descrição, e o tipo
     * fica como não avaliado, com o pedido feito como evidência. A meta é classificada por IA a partir do
     * título (MetaClassifier), como nos outros repositórios. Nada pontua sem conferência.
     */
    private function criterios(array $rea, ?array $meta): array
    {
        $criterios = ['tema' => RuleClassifier::criterioTema($this->search, 'MEC RED')];

        $criterios['nivel'] = RuleClassifier::criterioNivel(
            $this->profile,
            RuleClassifier::inferirNivel($rea['name'] ?? '', $rea['description'] ?? '')
        );

        $criterios['tipo'] = [
            'status' => 'nao_avaliado',
            'valor' => null,
            'esperado' => RuleClassifier::normalizarTipos($this->types),
            'fonte' => 'filtro_api',
            'evidencia' => 'o MEC RED não informa o tipo nesta busca',
        ];

        if ($this->meta && $meta !== null) {
            $criterios['meta'] = $meta;
        }

        return $criterios;
    }
}
