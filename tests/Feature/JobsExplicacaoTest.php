<?php

namespace Tests\Feature;

use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessEduplay;
use App\Jobs\ProcessMecRed;
use App\Models\Data;
use App\Recommendation\RuleClassifier;
use App\Recommendation\TiposPreferidos;
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

    public function test_aquarela_com_tipos_da_conta_grava_fonte_usuario(): void
    {
        $this->fakeAquarela();

        (new ProcessAquarela('algoritmos', ['jogo'], 'Ensino fundamental', $this->searchedAt, null, TiposPreferidos::CONTA))->handle();

        $tipos = array_map(fn ($rea) => $rea['explicacao']['criterios']['tipo'], $this->reas());

        $this->assertSame(['usuario', 'usuario', 'usuario'], array_column($tipos, 'fonte'));
        $this->assertSame([false, false, false], array_column($tipos, 'inclui_colaboradores'));
        $this->assertSame(['falhou', 'falhou', 'ok'], array_column($tipos, 'status'));
        $this->assertSame(['profile', 'interest', 'interest'], array_column($this->reas(), 'recommended'));
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

    /**
     * Mesma checagem para os três repositórios: rótulo, faixa e grau vêm dos critérios gravados.
     */
    private function assertGrauCoerente(array $reas, bool $comMeta): void
    {
        foreach ($reas as $i => $rea) {
            $criterios = $rea['explicacao']['criterios'];
            $this->assertSame(RuleClassifier::VERSAO_REGRAS, $rea['explicacao']['versao_regras']);
            $this->assertNull($rea['explicacao']['observacao']);
            $this->assertSame($rea['recommended'], $rea['explicacao']['faixa']);
            $this->assertSame($rea['recommended'], RuleClassifier::rotular($criterios, $comMeta));
            $this->assertSame(RuleClassifier::grau($criterios, $i + 1), $rea['explicacao']['grau']);
        }
    }

    private function graus(array $reas): array
    {
        return array_map(fn ($r) => $r['explicacao']['grau']['total'], $reas);
    }

    public function test_aquarela_grava_grau_e_posicao(): void
    {
        $this->fakeAquarela('{"meta": "Aprendizagem"}');

        (new ProcessAquarela('algoritmos', ['Vídeo'], 'Ensino fundamental', $this->searchedAt, 'ma'))->handle();

        $reas = $this->reas();
        $this->assertGrauCoerente($reas, true);
        $this->assertSame([7, 5, 4], $this->graus($reas));
        $this->assertSame([1, 2, 3], array_map(fn ($r) => $r['explicacao']['grau']['posicao'], $reas));
    }

    public function test_mec_red_sem_prioridade_fixa_e_meta_por_ia(): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => $this->ollama('{"meta": "Performance Evitação"}'),
            '*' => Http::response([
                ['id' => 1, 'name' => 'Recurso 1'],
                ['id' => 2, 'name' => 'Algoritmos para o ensino fundamental', 'description' => 'Atividade do 6º ano'],
            ]),
        ]);

        (new ProcessMecRed('algoritmos', ['Vídeo'], 'Ensino fundamental', 'Algoritmos', $this->searchedAt, 'mpe'))->handle();

        $reas = $this->reas();
        $this->assertCount(2, $reas);
        $this->assertGrauCoerente($reas, true);

        // Sem política: rótulo e grau saem dos critérios. Tipo não é conferido e não pontua.
        $this->assertSame(['meta', 'meta_one'], array_column($reas, 'recommended'));
        $this->assertSame([4, 6], $this->graus($reas));
        $this->assertTrue($reas[0]['explicacao']['criterios']['nivel']['assumido']);
        $this->assertSame('nao_avaliado', $reas[0]['explicacao']['criterios']['tipo']['status']);

        $meta = $reas[0]['explicacao']['criterios']['meta'];
        $this->assertSame(['ok', 'Performance Evitação', 'llm', 'gemma3:4b'], [$meta['status'], $meta['valor'], $meta['fonte'], $meta['modelo']]);
        $this->assertStringContainsString('só pelo título', $meta['evidencia']);
        $this->assertStringContainsString('object_type=17,22,6,18,13', $meta['evidencia']);

        // O nível é estimado pelo mesmo regex dos outros repositórios, e pode ser corrigido.
        $nivel = $reas[1]['explicacao']['criterios']['nivel'];
        $this->assertSame(['ok', 'fundamental', 'regex'], [$nivel['status'], $nivel['evidencia'], $nivel['fonte']]);
        $this->assertTrue(RuleClassifier::corrigivel($nivel));
        $this->assertSame('Atividade do 6º ano', $reas[1]['descricao']);
        $this->assertSame('meta_usuario', $reas[0]['fonte_interatividade']);

        // D8: o typo obkect_type não existe mais na URL.
        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'obkect') && str_contains($r->url(), 'object_type=17'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '11434') && str_contains($r['prompt'] ?? '', 'Título: Recurso 1'));

        $metricas = DB::table('search_metrics')->where('repository', 'MecRed')->first();
        $this->assertSame([2, 0, 0], [(int) $metricas->ollama_calls, (int) $metricas->ollama_errors, (int) $metricas->llm_nao_avaliados]);
    }

    public function test_mec_red_sem_meta(): void
    {
        Http::fake(['*' => Http::response([['name' => 'Recurso 1']])]);

        (new ProcessMecRed('algoritmos', [], 'Professor', 'Algoritmos', $this->searchedAt, null))->handle();

        $rea = $this->reas()[0];
        $this->assertGrauCoerente([$rea], false);
        $this->assertSame('interest', $rea['recommended']);
        $this->assertSame(3, $rea['explicacao']['grau']['maximo']);
        $this->assertArrayNotHasKey('meta', $rea['explicacao']['criterios']);
        $this->assertSame('indisponivel', $rea['fonte_interatividade']);
        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'educational_stages'));
    }

    private function fakeEduplay(?string $respostaOllama, array $conteudos = [['name' => 'Vídeo 1', 'contentUrl' => 'http://v1', 'metatagDescription' => 'Aula']]): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => $this->ollama($respostaOllama),
            // O fake avalia todos os padrões a cada requisição: a sequência casa só com o Eduplay.
            'eduplay.rnp.br/*' => Http::sequence()
                ->push(['contents' => $conteudos])
                ->push(['contents' => []]),
        ]);
    }

    public function test_eduplay_usa_a_mesma_regra(): void
    {
        $this->fakeEduplay(null, [
            ['name' => 'Vídeo 1', 'contentUrl' => 'http://v1'],
            ['name' => 'Aula de ensino médio', 'contentUrl' => 'http://v2', 'metatagDescription' => 'Revisão'],
        ]);

        (new ProcessEduplay('algoritmos', 'Ensino medio', $this->searchedAt, null, [['Vídeo'], 'Jogo']))->handle();

        $reas = $this->reas();
        $this->assertGrauCoerente($reas, false);

        // Nível pelo regex e tipo vídeo comparado com os preferidos, como nos outros repositórios.
        $tipo = $reas[0]['explicacao']['criterios']['tipo'];
        $this->assertSame(['ok', 'video', 'colaboradores'], [$tipo['status'], $tipo['valor'], $tipo['fonte']]);
        $this->assertSame(['interest', 'both'], array_column($reas, 'recommended'));
        $this->assertSame([1, 3], $this->graus($reas));
        $this->assertSame('padrao_repositorio', $reas[0]['fonte_interatividade']);
    }

    public function test_eduplay_com_tipos_da_conta_grava_fonte_usuario_e_soma_o_tipo_no_grau(): void
    {
        $this->fakeEduplay(null, [['name' => 'Aula de ensino médio', 'contentUrl' => 'http://v2']]);

        (new ProcessEduplay('algoritmos', 'Ensino medio', $this->searchedAt, null, ['video'], TiposPreferidos::CONTA))->handle();

        $rea = $this->reas()[0];
        $tipo = $rea['explicacao']['criterios']['tipo'];
        $this->assertSame(['ok', 'usuario', false], [$tipo['status'], $tipo['fonte'], $tipo['inclui_colaboradores']]);
        $this->assertSame('both', $rea['recommended']);
        $this->assertSame(1, $rea['explicacao']['grau']['pontos']['tipo']);
    }

    public function test_eduplay_classifica_a_meta_por_ia_e_rotula_pela_regra_comum(): void
    {
        $this->fakeEduplay('{"meta": "Aprendizagem"}');

        (new ProcessEduplay('algoritmos', 'Ensino fundamental', $this->searchedAt, 'ma'))->handle();

        $rea = $this->reas()[0];
        $criterios = $rea['explicacao']['criterios'];
        $this->assertGrauCoerente([$rea], true);
        // Nível assumido e nenhum tipo preferido: com a meta atendida, a faixa é `meta` (grau 4).
        $this->assertSame('meta', $rea['recommended']);
        $this->assertSame(4, $rea['explicacao']['grau']['total']);
        $this->assertSame(['ok', 'llm'], [$criterios['meta']['status'], $criterios['meta']['fonte']]);
        $this->assertTrue($criterios['nivel']['assumido']);
        $this->assertSame('padrao_repositorio', $rea['fonte_interatividade']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '11434') && str_contains($r['prompt'] ?? '', 'Descrição: Aula'));

        $metricas = DB::table('search_metrics')->where('repository', 'Eduplay')->first();
        $this->assertSame(1, (int) $metricas->ollama_calls);
    }

    public function test_eduplay_meta_incompativel_fica_fora_das_faixas_de_meta(): void
    {
        $this->fakeEduplay('{"meta": "Aprendizagem"}');

        (new ProcessEduplay('algoritmos', 'Ensino fundamental', $this->searchedAt, 'mpe'))->handle();

        $rea = $this->reas()[0];
        $this->assertSame('interest', $rea['recommended']);
        $this->assertSame('falhou', $rea['explicacao']['criterios']['meta']['status']);
        $this->assertSame(0, $rea['explicacao']['grau']['total']);
    }

    public function test_eduplay_com_ollama_fora_do_ar_nao_marca_meta_como_atendida(): void
    {
        $this->fakeEduplay(null);

        (new ProcessEduplay('algoritmos', 'Ensino fundamental', $this->searchedAt, 'ma'))->handle();

        $rea = $this->reas()[0];
        $this->assertSame('interest', $rea['recommended']);
        $this->assertSame('nao_avaliado', $rea['explicacao']['criterios']['meta']['status']);
        $this->assertSame(0, $rea['explicacao']['grau']['pontos']['meta']);
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
        $this->assertSame('falhou', $rea['explicacao']['criterios']['tipo']['status']);
        $this->assertSame(0, $rea['explicacao']['grau']['total']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '11434'));
    }
}
