<?php

namespace App\Recommendation;

use App\Recommendation\Llm\ProvedorLlm;
use Illuminate\Support\Facades\Cache;

/**
 * Classifica a meta de realização (EMAPRE) de cada REA por LLM, para os três repositórios.
 * Gera o critério `meta` via RuleClassifier::criterioMeta, com modelo, duração e, quando a IA não
 * classificou, o motivo. Uma instância por job: as métricas e o orçamento de tempo são do job.
 *
 * Desempenho (docs/integracoes.md): cache por chave do REA entre buscas, chamadas em lotes
 * simultâneos, orçamento de tempo por job e parada após falhas seguidas. Nenhum desses caminhos
 * marca a meta como atendida: o que não foi classificado fica `nao_avaliado`, com o motivo.
 */
class MetaClassifier
{
    // Faz parte da chave do cache: mude ao alterar o prompt ou a interpretação da resposta.
    public const VERSAO_PROMPT = 1;

    public const CLASSES = [
        'ma' => 'Aprendizagem',
        'mpa' => 'Performance Aproximação',
        'mpe' => 'Performance Evitação',
    ];

    public const CHAVE_PAUSA = 'meta_llm:pausa';

    private array $metricas = [
        'ollama_time' => 0.0,
        'ollama_calls' => 0,
        'ollama_errors' => 0,
        'llm_cache_hits' => 0,
        'llm_nao_avaliados' => 0,
    ];

    private int $falhasSeguidas = 0;

    private bool $desistiu = false;

    private bool $preparado = false;

    /**
     * @param  ProvedorLlm|null  $provedor  null quando a classificação está desativada (LLM_PROVEDOR=nenhum)
     */
    public function __construct(private ?ProvedorLlm $provedor, private array $config = [])
    {
        $this->config += [
            'timeout' => 10,
            'concorrencia' => 4,
            'orcamento_segundos' => 240,
            'falhas_seguidas_max' => 3,
            'pausa_apos_falha_segundos' => 60,
            'cache_dias' => 30,
            'aquecer' => true,
            'timeout_aquecimento' => 60,
        ];
    }

    public function modelo(): ?string
    {
        return $this->provedor?->modelo();
    }

    /**
     * Métricas no formato das colunas de `search_metrics`.
     */
    public function metricas(): array
    {
        return $this->metricas;
    }

    /**
     * Critério de meta de cada item, na mesma ordem e com os mesmos índices.
     *
     * @param  array<int, array{chave: string, titulo: ?string, descricao?: ?string, tipo?: ?string, dtype?: ?string, evidencia?: ?string}>  $itens
     * @return array<int, array>
     */
    public function criterios(string $metaUsuario, array $itens): array
    {
        $criterios = [];

        foreach ($this->classificar($itens) as $i => $c) {
            $criterios[$i] = RuleClassifier::criterioMeta($metaUsuario, $c['valor'], $this->modelo(), $c['duracao'], $c['evidencia']);
        }

        return $criterios;
    }

    /**
     * @return array<int, array{valor: ?string, duracao: ?float, evidencia: ?string}>
     */
    public function classificar(array $itens): array
    {
        $resultado = [];
        $pendentes = []; // chave do REA => índices dos itens com essa chave

        foreach ($itens as $i => $item) {
            $chave = (string) $item['chave'];

            if (isset($pendentes[$chave])) {
                $pendentes[$chave][] = $i;

                continue;
            }

            $emCache = $this->lerCache($chave);
            if ($emCache !== null) {
                $this->metricas['llm_cache_hits']++;
                $resultado[$i] = $this->classificado($emCache, null, $item, 'classificação reaproveitada de uma busca anterior');

                continue;
            }

            $pendentes[$chave] = [$i];
        }

        foreach (array_chunk($pendentes, max(1, (int) $this->config['concorrencia']), true) as $lote) {
            $motivo = $this->motivoParaNaoConsultar();

            if ($motivo !== null) {
                foreach (array_merge(...array_values($lote)) as $i) {
                    $resultado[$i] = $this->naoAvaliado($motivo);
                }

                continue;
            }

            $this->preparar();

            $grupos = array_values($lote);
            $prompts = array_map(fn (array $indices) => $this->prompt($itens[$indices[0]]), $grupos);

            $inicio = microtime(true);
            try {
                $textos = $this->provedor->gerarJson($prompts, (int) $this->config['timeout']);
            } catch (\Throwable $e) {
                // Um provedor com defeito não pode derrubar a busca: o lote inteiro conta como falha.
                $textos = [];
            }
            $duracao = microtime(true) - $inicio;

            $this->metricas['ollama_time'] += $duracao;
            $this->metricas['ollama_calls'] += count($prompts);

            foreach ($grupos as $j => $indices) {
                $item = $itens[$indices[0]];
                $texto = $textos[$j] ?? null;

                if ($texto === null) {
                    $this->metricas['ollama_errors']++;
                    $this->falhasSeguidas++;
                    $r = $this->naoAvaliado('a IA não respondeu (erro ou tempo esgotado)');
                } else {
                    $this->falhasSeguidas = 0;
                    $valor = self::interpretar($texto);

                    if ($valor === null) {
                        $this->metricas['ollama_errors']++;
                        $r = $this->naoAvaliado('a IA respondeu fora do formato esperado');
                    } else {
                        $this->gravarCache((string) $item['chave'], $valor);
                        $r = $this->classificado($valor, $duracao, $item);
                    }
                }

                foreach ($indices as $i) {
                    $resultado[$i] = $r;
                }
            }

            if ($this->falhasSeguidas >= (int) $this->config['falhas_seguidas_max']) {
                $this->desistiu = true;
                Cache::put(self::CHAVE_PAUSA, true, max(1, (int) $this->config['pausa_apos_falha_segundos']));
            }
        }

        foreach ($resultado as $r) {
            if ($r['valor'] === null) {
                $this->metricas['llm_nao_avaliados']++;
            }
        }

        ksort($resultado);

        return $resultado;
    }

    /**
     * Converte a resposta JSON na classe canônica; null se inválida ou ambígua.
     */
    public static function interpretar(string $texto): ?string
    {
        $json = json_decode($texto, true);
        $meta = is_array($json) ? ($json['meta'] ?? null) : null;

        if (! is_string($meta) || trim($meta) === '') {
            return null;
        }

        $casam = array_filter(array_keys(self::CLASSES), fn (string $codigo) => RuleClassifier::casaMeta($meta, $codigo));

        return count($casam) === 1 ? self::CLASSES[reset($casam)] : null;
    }

    public function prompt(array $item): string
    {
        $titulo = self::campo($item['titulo'] ?? null);
        $descricao = self::campo($item['descricao'] ?? null);
        $tipo = self::campo($item['tipo'] ?? null);
        $dtype = empty($item['dtype']) ? '' : "\nDType: {$item['dtype']}";

        return <<<PROMPT
        Classifique o REA em APENAS uma das metas abaixo.

        Título: {$titulo}
        Descrição: {$descricao}
        Tipo: {$tipo}{$dtype}

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
    }

    /**
     * Carrega o modelo uma vez por job, só quando há algo a classificar (tudo em cache não aquece).
     * O tempo entra em `ollama_time` e, portanto, no orçamento.
     */
    private function preparar(): void
    {
        if ($this->preparado || ! $this->config['aquecer']) {
            return;
        }

        $this->preparado = true;
        $inicio = microtime(true);

        try {
            $this->provedor->preparar((int) $this->config['timeout_aquecimento']);
        } catch (\Throwable $e) {
            // As chamadas seguintes revelam a falha.
        }

        $this->metricas['ollama_time'] += microtime(true) - $inicio;
    }

    private function motivoParaNaoConsultar(): ?string
    {
        if ($this->provedor === null) {
            return 'a classificação por IA está desativada nesta instalação';
        }

        if ($this->desistiu || Cache::has(self::CHAVE_PAUSA)) {
            return 'a IA está indisponível (falhou várias vezes seguidas) e não foi consultada para este recurso';
        }

        if ($this->metricas['ollama_time'] >= (float) $this->config['orcamento_segundos']) {
            return "o tempo reservado à classificação por IA ({$this->config['orcamento_segundos']}s por repositório) acabou antes deste recurso";
        }

        return null;
    }

    private function classificado(string $valor, ?float $duracao, array $item, ?string $evidencia = null): array
    {
        $partes = array_filter([$evidencia, $item['evidencia'] ?? null]);

        return [
            'valor' => $valor,
            'duracao' => $duracao,
            'evidencia' => $partes ? implode('; ', $partes) : null,
        ];
    }

    private function naoAvaliado(string $motivo): array
    {
        return ['valor' => null, 'duracao' => null, 'evidencia' => $motivo];
    }

    private function chaveCache(string $chave): string
    {
        return 'meta_llm:v'.self::VERSAO_PROMPT.':'.$this->modelo().':'.$chave;
    }

    private function lerCache(string $chave): ?string
    {
        if ($this->provedor === null || (int) $this->config['cache_dias'] <= 0) {
            return null;
        }

        $valor = Cache::get($this->chaveCache($chave));

        return in_array($valor, self::CLASSES, true) ? $valor : null;
    }

    private function gravarCache(string $chave, string $valor): void
    {
        if ((int) $this->config['cache_dias'] > 0) {
            Cache::put($this->chaveCache($chave), $valor, now()->addDays((int) $this->config['cache_dias']));
        }
    }

    private static function campo(?string $valor): string
    {
        $valor = trim(strip_tags((string) $valor));

        return $valor === '' ? 'não informado' : $valor;
    }
}
