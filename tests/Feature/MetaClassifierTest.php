<?php

namespace Tests\Feature;

use App\Recommendation\Llm\ProvedorLlm;
use App\Recommendation\MetaClassifier;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaClassifierTest extends TestCase
{
    private function itens(int $n): array
    {
        return array_map(fn ($i) => ['chave' => "rea{$i}", 'titulo' => "Recurso {$i}", 'descricao' => 'Atividade'], range(1, $n));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // O aquecimento tem teste próprio; aqui ele atrapalharia a contagem de chamadas.
        config(['llm.aquecer' => false]);
    }

    private function classificador(array $config = []): MetaClassifier
    {
        config(['llm' => array_merge(config('llm'), $config)]);

        return app(MetaClassifier::class);
    }

    private function fakeOllama($resposta): void
    {
        Http::fake(['127.0.0.1:11434/*' => $resposta]);
    }

    private function timeout(): \Closure
    {
        return fn (Request $request) => Create::rejectionFor(
            new ConnectException('cURL error 28: Operation timed out', $request->toPsrRequest())
        );
    }

    public function test_classifica_e_gera_criterio_com_modelo_e_duracao(): void
    {
        $this->fakeOllama(Http::response(['response' => '{"meta": "performance_evitacao"}']));

        $c = $this->classificador()->criterios('mpe', $this->itens(1))[0];

        $this->assertSame(['ok', 'Performance Evitação', 'llm', 'gemma3:4b'], [$c['status'], $c['valor'], $c['fonte'], $c['modelo']]);
        $this->assertIsFloat($c['duracao']);
        $this->assertArrayNotHasKey('evidencia', $c);

        Http::assertSent(fn (Request $r) => $r->url() === 'http://127.0.0.1:11434/api/generate'
            && $r['model'] === 'gemma3:4b' && $r['format'] === 'json' && str_contains($r['prompt'], 'Título: Recurso 1'));
    }

    public function test_url_e_modelo_vem_da_configuracao(): void
    {
        Http::fake(['ollama.local:8080/*' => Http::response(['response' => '{"meta": "Aprendizagem"}'])]);
        config(['llm.ollama.url' => 'http://ollama.local:8080/', 'llm.ollama.modelo' => 'qwen2.5:7b']);

        $c = app(MetaClassifier::class)->criterios('ma', $this->itens(1))[0];

        $this->assertSame(['ok', 'qwen2.5:7b'], [$c['status'], $c['modelo']]);
        Http::assertSent(fn (Request $r) => $r->url() === 'http://ollama.local:8080/api/generate' && $r['model'] === 'qwen2.5:7b');
    }

    public function test_json_invalido_ou_ambiguo_fica_nao_avaliado(): void
    {
        foreach (['Aprendizagem', '{"meta": ""}', '{"meta": "Desconhecida"}', '{"meta": "Aprendizagem ou Performance Evitação"}'] as $resposta) {
            $this->assertNull(MetaClassifier::interpretar($resposta), $resposta);
        }

        $this->fakeOllama(Http::response(['response' => 'não sei']));
        $classificador = $this->classificador();

        $c = $classificador->criterios('ma', $this->itens(1))[0];

        $this->assertSame('nao_avaliado', $c['status']);
        $this->assertSame('a IA respondeu fora do formato esperado', $c['evidencia']);
        $this->assertSame(1, $classificador->metricas()['ollama_errors']);
        $this->assertSame(1, $classificador->metricas()['llm_nao_avaliados']);
    }

    public function test_timeout_fica_nao_avaliado_e_nao_quebra(): void
    {
        $this->fakeOllama($this->timeout());
        $classificador = $this->classificador();

        $c = $classificador->criterios('ma', $this->itens(1))[0];

        $this->assertSame('nao_avaliado', $c['status']);
        $this->assertStringContainsString('não respondeu', $c['evidencia']);
        $this->assertSame(['ollama_calls' => 1, 'ollama_errors' => 1], array_intersect_key($classificador->metricas(), ['ollama_calls' => 0, 'ollama_errors' => 0]));
    }

    public function test_provedor_que_lanca_excecao_nao_quebra_o_job(): void
    {
        $this->app->bind(ProvedorLlm::class, fn () => new class implements ProvedorLlm
        {
            public function modelo(): string
            {
                return 'quebrado';
            }

            public function preparar(int $timeout): void
            {
                throw new \RuntimeException('falhou');
            }

            public function gerarJson(array $prompts, int $timeout): array
            {
                throw new \RuntimeException('falhou');
            }
        });

        $c = app(MetaClassifier::class)->criterios('ma', $this->itens(1))[0];

        $this->assertSame(['nao_avaliado', 'quebrado'], [$c['status'], $c['modelo']]);
    }

    public function test_cache_por_chave_evita_reclassificar_entre_buscas(): void
    {
        $this->fakeOllama(Http::response(['response' => '{"meta": "Aprendizagem"}']));

        $this->classificador()->criterios('ma', $this->itens(2));
        Http::assertSentCount(2);

        // Outra busca (outro job), com um REA repetido e um novo; a meta do usuário pode ser outra.
        $segundo = app(MetaClassifier::class);
        $criterios = $segundo->criterios('mpe', [['chave' => 'rea1', 'titulo' => 'Recurso 1'], ['chave' => 'rea9', 'titulo' => 'Novo']]);

        Http::assertSentCount(3);
        $this->assertSame(['falhou', 'Aprendizagem', null], [$criterios[0]['status'], $criterios[0]['valor'], $criterios[0]['duracao']]);
        $this->assertSame('classificação reaproveitada de uma busca anterior', $criterios[0]['evidencia']);
        $this->assertSame(['ollama_calls' => 1, 'llm_cache_hits' => 1], array_intersect_key($segundo->metricas(), ['ollama_calls' => 0, 'llm_cache_hits' => 0]));
    }

    public function test_falha_e_resposta_invalida_nao_entram_no_cache(): void
    {
        Http::fake(['127.0.0.1:11434/*' => Http::sequence()
            ->push('erro', 500)
            ->push(['response' => 'lixo'])
            ->push(['response' => '{"meta": "Aprendizagem"}'])]);

        $item = [['chave' => 'rea1', 'titulo' => 'Recurso 1']];
        $this->assertSame('nao_avaliado', $this->classificador()->criterios('ma', $item)[0]['status']);
        $this->assertSame('nao_avaliado', app(MetaClassifier::class)->criterios('ma', $item)[0]['status']);
        $this->assertSame('ok', app(MetaClassifier::class)->criterios('ma', $item)[0]['status']);
        Http::assertSentCount(3);
    }

    public function test_cache_desligado_e_versionado_pelo_modelo(): void
    {
        $this->fakeOllama(Http::response(['response' => '{"meta": "Aprendizagem"}']));

        $this->classificador(['cache_dias' => 0])->criterios('ma', $this->itens(1));
        app(MetaClassifier::class)->criterios('ma', $this->itens(1));
        Http::assertSentCount(2);

        config(['llm.cache_dias' => 30]);
        app(MetaClassifier::class)->criterios('ma', $this->itens(1));
        config(['llm.ollama.modelo' => 'outro:1b']);
        app(MetaClassifier::class)->criterios('ma', $this->itens(1));
        Http::assertSentCount(4);
    }

    public function test_itens_repetidos_na_mesma_busca_geram_uma_chamada(): void
    {
        $this->fakeOllama(Http::response(['response' => '{"meta": "Aprendizagem"}']));

        $criterios = $this->classificador()->criterios('ma', [
            ['chave' => 'x', 'titulo' => 'A'], ['chave' => 'y', 'titulo' => 'B'], ['chave' => 'x', 'titulo' => 'A'],
        ]);

        Http::assertSentCount(2);
        $this->assertSame([0, 1, 2], array_keys($criterios));
        $this->assertSame('ok', $criterios[2]['status']);
    }

    public function test_para_de_consultar_apos_falhas_seguidas_e_avisa_os_outros_jobs(): void
    {
        $this->fakeOllama($this->timeout());
        $classificador = $this->classificador(['concorrencia' => 2, 'falhas_seguidas_max' => 3]);

        $criterios = $classificador->criterios('ma', $this->itens(10));

        // Dois lotes de 2 (4 falhas >= 3), depois desiste dos 6 restantes sem chamar a LLM.
        $this->assertSame(4, $classificador->metricas()['ollama_calls']);
        $this->assertSame(array_fill(0, 10, 'nao_avaliado'), array_column($criterios, 'status'));
        $this->assertStringContainsString('indisponível', $criterios[9]['evidencia']);
        $this->assertSame(10, $classificador->metricas()['llm_nao_avaliados']);
        $this->assertTrue(Cache::has(MetaClassifier::CHAVE_PAUSA));

        // O próximo job (outro repositório) nem tenta.
        $proximo = app(MetaClassifier::class);
        $this->assertSame('nao_avaliado', $proximo->criterios('ma', $this->itens(3))[0]['status']);
        $this->assertSame(0, $proximo->metricas()['ollama_calls']);
    }

    public function test_uma_falha_isolada_nao_interrompe(): void
    {
        Http::fake(['127.0.0.1:11434/*' => Http::sequence()
            ->push('erro', 500)
            ->push(['response' => '{"meta": "Aprendizagem"}'])
            ->push('erro', 500)
            ->push(['response' => '{"meta": "Aprendizagem"}'])
            ->push('erro', 500)]);

        $criterios = $this->classificador(['concorrencia' => 1, 'falhas_seguidas_max' => 2])->criterios('ma', $this->itens(5));

        Http::assertSentCount(5);
        $this->assertSame(['nao_avaliado', 'ok', 'nao_avaliado', 'ok', 'nao_avaliado'], array_column($criterios, 'status'));
    }

    public function test_orcamento_de_tempo_esgotado_deixa_o_resto_nao_avaliado(): void
    {
        $this->fakeOllama(Http::response(['response' => '{"meta": "Aprendizagem"}']));

        $criterios = $this->classificador(['orcamento_segundos' => 0])->criterios('ma', $this->itens(2));

        Http::assertNothingSent();
        $this->assertSame(['nao_avaliado', 'nao_avaliado'], array_column($criterios, 'status'));
        $this->assertStringContainsString('tempo reservado', $criterios[0]['evidencia']);
    }

    public function test_concorrencia_agrupa_as_chamadas_em_lotes(): void
    {
        $this->fakeOllama(Http::response(['response' => '{"meta": "Aprendizagem"}']));
        $classificador = $this->classificador(['concorrencia' => 4]);

        $criterios = $classificador->criterios('ma', $this->itens(9));

        Http::assertSentCount(9);
        $this->assertSame(array_fill(0, 9, 'ok'), array_column($criterios, 'status'));
        $this->assertSame(9, $classificador->metricas()['ollama_calls']);
    }

    public function test_provedor_nenhum_desativa_sem_chamar_a_llm(): void
    {
        Http::fake();

        $classificador = $this->classificador(['provedor' => 'nenhum']);
        $c = $classificador->criterios('ma', $this->itens(1))[0];

        Http::assertNothingSent();
        $this->assertSame(['nao_avaliado', null], [$c['status'], $c['modelo']]);
        $this->assertStringContainsString('desativada', $c['evidencia']);
    }

    public function test_aquece_o_modelo_uma_vez_por_job_antes_da_primeira_chamada(): void
    {
        $this->fakeOllama(Http::response(['response' => '{"meta": "Aprendizagem"}']));
        $classificador = $this->classificador(['aquecer' => true, 'concorrencia' => 1]);

        $classificador->criterios('ma', $this->itens(2));
        $classificador->criterios('ma', [['chave' => 'outro', 'titulo' => 'Outro']]);

        $enviados = Http::recorded()->map(fn ($par) => $par[0]);
        $this->assertCount(4, $enviados);
        $this->assertFalse(isset($enviados[0]['prompt']));
        $this->assertSame(['gemma3:4b', '10m'], [$enviados[0]['model'], $enviados[0]['keep_alive']]);
        $this->assertSame('10m', $enviados[1]['keep_alive']);
        $this->assertSame(3, $classificador->metricas()['ollama_calls']);

        // Tudo em cache: o próximo job nem aquece.
        Http::fake();
        app(MetaClassifier::class)->criterios('ma', $this->itens(2));
        Http::assertNothingSent();
    }

    public function test_prompt_marca_campos_ausentes(): void
    {
        $prompt = app(MetaClassifier::class)->prompt(['chave' => 'x', 'titulo' => '<b>Plano de aula</b>']);

        $this->assertStringContainsString('Título: Plano de aula', $prompt);
        $this->assertStringContainsString('Descrição: não informado', $prompt);
        $this->assertStringNotContainsString('DType', $prompt);
        $this->assertStringContainsString('{"meta": "<opção>"}', $prompt);
    }
}
