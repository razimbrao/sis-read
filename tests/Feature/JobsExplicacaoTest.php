<?php

namespace Tests\Feature;

use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessEduplay;
use App\Jobs\ProcessMecRed;
use App\Models\Data;
use App\Recommendation\RuleClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobsExplicacaoTest extends TestCase
{
    use RefreshDatabase;

    private string $searchedAt = '2026-09-18 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Data::create(['searched_at' => $this->searchedAt]);
    }

    private function reas(): array
    {
        $reas = json_decode(Data::where('searched_at', $this->searchedAt)->first()->data, true);

        // Escrutabilidade: todo item precisa de uma chave para receber correções.
        foreach ($reas as $rea) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $rea['chave'] ?? '');
        }

        return $reas;
    }

    /**
     * Uma resposta nova por requisição: o job aquece o modelo (chamada síncrona) antes do lote (pool).
     */
    private function ollama(?string $resposta): \Closure
    {
        return fn () => $resposta === null
            ? Http::response('erro', 500)
            : Http::response(['response' => $resposta]);
    }

    private function fakeAquarela(?string $respostaOllama = '{"meta": "Aprendizagem"}'): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => $this->ollama($respostaOllama),
            '*' => function (Request $request) {
                if (($request->data()['page'] ?? null) !== 0) {
                    return Http::response(['reas' => []]);
                }

                return Http::response(['reas' => [
                    ['titulo' => 'Algoritmos no sexto ano', 'descricao' => 'Atividade', 'tipoConteudo' => 'Vídeo', 'dtype' => 'T', 'links' => [['href' => 'http://a']]],
                    ['titulo' => 'Algoritmos no ensino médio', 'descricao' => '', 'tipoConteudo' => 'Vídeo', 'dtype' => 'D', 'links' => []],
                    ['titulo' => 'Algoritmos', 'descricao' => 'Jogo sem etapa', 'tipoConteudo' => 'Jogo', 'dtype' => '', 'links' => []],
                ]]);
            },
        ]);
    }

    public function test_aquarela_sem_meta_grava_explicacao_coerente_com_o_rotulo(): void
    {
        $this->fakeAquarela();

        (new ProcessAquarela('algoritmos', [['Vídeo'], 'e-book'], 'Ensino fundamental', $this->searchedAt))->handle();

        $reas = $this->reas();
        $this->assertCount(3, $reas);
        $this->assertSame(['both', 'interest', 'interest'], array_column($reas, 'recommended'));
        $this->assertSame(RuleClassifier::chave('Aquarela', 'http://a', 'Algoritmos no sexto ano'), $reas[0]['chave']);
        $this->assertCount(3, array_unique(array_column($reas, 'chave')));

        foreach ($reas as $rea) {
            $this->assertSame($rea['recommended'], $rea['explicacao']['faixa']);
            $this->assertSame($rea['recommended'], RuleClassifier::rotular($rea['explicacao']['criterios'], false));
            $this->assertArrayNotHasKey('meta', $rea['explicacao']['criterios']);
        }

        $nivel = $reas[0]['explicacao']['criterios']['nivel'];
        $this->assertSame(['ok', 'sexto ano', 'regex'], [$nivel['status'], $nivel['evidencia'], $nivel['fonte']]);
        $this->assertSame(['video', 'e-book', 'livro digital'], $reas[0]['explicacao']['criterios']['tipo']['esperado']);
        $this->assertSame('dtype', $reas[0]['fonte_interatividade']);
        $this->assertSame('indisponivel', $reas[2]['fonte_interatividade']);
        $this->assertTrue($reas[2]['explicacao']['criterios']['nivel']['assumido']);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '11434'));
    }

    public function test_aquarela_com_meta_usa_a_classificacao_do_llm(): void
    {
        $this->fakeAquarela('{"meta": "Aprendizagem"}');

        (new ProcessAquarela('algoritmos', ['Vídeo'], 'Ensino fundamental', $this->searchedAt, 'ma'))->handle();

        $reas = $this->reas();
        $this->assertSame(['meta_both', 'meta_one', 'meta'], array_column($reas, 'recommended'));
        $this->assertSame('ok', $reas[0]['explicacao']['criterios']['meta']['status']);
        $this->assertSame('Aprendizagem', $reas[0]['explicacao']['criterios']['meta']['valor']);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '11434') && str_contains($r['prompt'] ?? '', '{"meta"'));
    }

    public function test_aquarela_reconhece_performance_aproximacao_com_acento(): void
    {
        $this->fakeAquarela('{"meta": "Performance Aproximação"}');

        (new ProcessAquarela('algoritmos', ['Vídeo'], 'Ensino fundamental', $this->searchedAt, 'mpa'))->handle();

        $this->assertSame('meta_both', $this->reas()[0]['recommended']);
    }

    public function test_aquarela_com_ollama_fora_do_ar_marca_meta_como_nao_avaliada(): void
    {
        $this->fakeAquarela(null);

        (new ProcessAquarela('algoritmos', ['Vídeo'], 'Ensino fundamental', $this->searchedAt, 'ma'))->handle();

        $reas = $this->reas();
        $this->assertSame('nao_avaliado', $reas[0]['explicacao']['criterios']['meta']['status']);
        $this->assertSame('both', $reas[0]['recommended']);
    }

    public function test_aquarela_com_resposta_invalida_do_llm(): void
    {
        $this->fakeAquarela('Aprendizagem');

        (new ProcessAquarela('algoritmos', ['Vídeo'], 'Ensino fundamental', $this->searchedAt, 'ma'))->handle();

        $this->assertSame('nao_avaliado', $this->reas()[0]['explicacao']['criterios']['meta']['status']);
    }

    public function test_mec_red_explica_os_filtros_enviados_e_classifica_a_meta_por_ia(): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => $this->ollama('{"meta": "Performance Evitação"}'),
            '*' => Http::response([['id' => 1, 'name' => 'Recurso 1'], ['id' => 2, 'name' => 'Recurso 2']]),
        ]);

        (new ProcessMecRed('algoritmos', ['Vídeo'], 'Ensino fundamental', 'Algoritmos', $this->searchedAt, 'mpe'))->handle();

        $reas = $this->reas();
        $this->assertCount(2, $reas);
        $explicacao = $reas[0]['explicacao'];
        // A faixa do MEC RED continua definida por política (sessão de ordenação por grau).
        $this->assertSame('meta_both', $reas[0]['recommended']);
        // A API não devolve etapa/tipo dos itens: nível e tipo não são marcados como atendidos.
        foreach (['nivel', 'tipo'] as $criterio) {
            $this->assertSame('nao_avaliado', $explicacao['criterios'][$criterio]['status']);
        }
        $this->assertStringContainsString('educational_stages=2,3', $explicacao['criterios']['nivel']['evidencia']);

        $meta = $explicacao['criterios']['meta'];
        $this->assertSame(['ok', 'Performance Evitação', 'llm', 'gemma3:4b'], [$meta['status'], $meta['valor'], $meta['fonte'], $meta['modelo']]);
        $this->assertStringContainsString('só pelo título', $meta['evidencia']);
        $this->assertStringContainsString('object_type=17,22,6,18,13', $meta['evidencia']);
        $this->assertNotNull($explicacao['observacao']);
        $this->assertSame('meta_usuario', $reas[0]['fonte_interatividade']);

        // D8: o typo obkect_type não existe mais na URL.
        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'obkect') && str_contains($r->url(), 'object_type=17'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '11434') && str_contains($r['prompt'] ?? '', 'Título: Recurso 1'));

        $metricas = DB::table('search_metrics')->where('repository', 'MecRed')->first();
        $this->assertSame([2, 0, 0], [(int) $metricas->ollama_calls, (int) $metricas->ollama_errors, (int) $metricas->llm_nao_avaliados]);
    }

    public function test_mec_red_perfil_sem_etapa_nao_finge_filtro_de_nivel(): void
    {
        Http::fake(['*' => Http::response([['name' => 'Recurso 1']])]);

        (new ProcessMecRed('algoritmos', [], 'Professor', 'Algoritmos', $this->searchedAt, null))->handle();

        $rea = $this->reas()[0];
        $this->assertSame('both', $rea['recommended']);
        $this->assertSame('nao_avaliado', $rea['explicacao']['criterios']['nivel']['status']);
        $this->assertArrayNotHasKey('meta', $rea['explicacao']['criterios']);
        $this->assertSame('indisponivel', $rea['fonte_interatividade']);
        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'educational_stages'));
    }

    private function fakeEduplay(?string $respostaOllama): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => $this->ollama($respostaOllama),
            // O fake avalia todos os padrões a cada requisição: a sequência casa só com o Eduplay.
            'eduplay.rnp.br/*' => Http::sequence()
                ->push(['contents' => [['name' => 'Vídeo 1', 'contentUrl' => 'http://v1', 'metatagDescription' => 'Aula']]])
                ->push(['contents' => []]),
        ]);
    }

    public function test_eduplay_classifica_a_meta_por_ia_e_rotula_pela_regra_comum(): void
    {
        $this->fakeEduplay('{"meta": "Aprendizagem"}');

        (new ProcessEduplay('algoritmos', 'Ensino fundamental', $this->searchedAt, 'ma'))->handle();

        $rea = $this->reas()[0];
        $criterios = $rea['explicacao']['criterios'];
        // Nível e tipo não são conferidos: com a meta atendida, a faixa é `meta`.
        $this->assertSame('meta', $rea['recommended']);
        $this->assertSame($rea['recommended'], RuleClassifier::rotular($criterios, true));
        $this->assertSame(['ok', 'llm'], [$criterios['meta']['status'], $criterios['meta']['fonte']]);
        $this->assertNull($rea['explicacao']['observacao']);
        $this->assertSame('nao_avaliado', $criterios['nivel']['status']);
        $this->assertSame('padrao_repositorio', $rea['fonte_interatividade']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '11434') && str_contains($r['prompt'] ?? '', 'Descrição: Aula'));

        $metricas = DB::table('search_metrics')->where('repository', 'Eduplay')->first();
        $this->assertSame(1, (int) $metricas->ollama_calls);
    }

    public function test_eduplay_meta_incompativel_fica_em_interest(): void
    {
        $this->fakeEduplay('{"meta": "Aprendizagem"}');

        (new ProcessEduplay('algoritmos', 'Ensino fundamental', $this->searchedAt, 'mpe'))->handle();

        $rea = $this->reas()[0];
        $this->assertSame('interest', $rea['recommended']);
        $this->assertSame('falhou', $rea['explicacao']['criterios']['meta']['status']);
    }

    public function test_eduplay_com_ollama_fora_do_ar_nao_marca_meta_como_atendida(): void
    {
        $this->fakeEduplay(null);

        (new ProcessEduplay('algoritmos', 'Ensino fundamental', $this->searchedAt, 'ma'))->handle();

        $rea = $this->reas()[0];
        $this->assertSame('interest', $rea['recommended']);
        $this->assertSame('nao_avaliado', $rea['explicacao']['criterios']['meta']['status']);
        $this->assertNotEmpty($rea['explicacao']['criterios']['meta']['evidencia']);

        $metricas = DB::table('search_metrics')->where('repository', 'Eduplay')->first();
        $this->assertSame([1, 1], [(int) $metricas->ollama_errors, (int) $metricas->llm_nao_avaliados]);
    }

    public function test_eduplay_sem_meta_nao_chama_a_llm(): void
    {
        $this->fakeEduplay('{"meta": "Aprendizagem"}');

        (new ProcessEduplay('algoritmos', 'Ensino fundamental', $this->searchedAt, null))->handle();

        $rea = $this->reas()[0];
        $this->assertSame('interest', $rea['recommended']);
        $this->assertArrayNotHasKey('meta', $rea['explicacao']['criterios']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '11434'));
    }
}
