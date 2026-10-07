<?php

namespace Tests\Feature;

use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessEduplay;
use App\Jobs\ProcessMecRed;
use App\Models\Data;
use App\Recommendation\RuleClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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

    private function fakeAquarela(?string $respostaOllama = '{"meta": "Aprendizagem"}'): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => $respostaOllama === null
                ? Http::response('erro', 500)
                : Http::response(['response' => $respostaOllama]),
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

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '11434') && str_contains($r['prompt'], '{"meta"'));
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

    public function test_mec_red_sem_prioridade_fixa(): void
    {
        Http::fake(['*' => Http::response([
            ['name' => 'Recurso 1'],
            ['name' => 'Algoritmos para o ensino fundamental', 'description' => 'Atividade do 6º ano'],
        ])]);

        (new ProcessMecRed('algoritmos', ['Vídeo'], 'Ensino fundamental', 'Algoritmos', $this->searchedAt, 'mpe'))->handle();

        $reas = $this->reas();
        $this->assertCount(2, $reas);
        $this->assertGrauCoerente($reas, true);

        // Sem conferência, nada pontua: o MEC RED não fica mais na faixa mais alta.
        $this->assertSame('interest', $reas[0]['recommended']);
        $this->assertSame([0, 2], $this->graus($reas));
        $this->assertTrue($reas[0]['explicacao']['criterios']['nivel']['assumido']);
        foreach (['tipo', 'meta'] as $criterio) {
            $this->assertSame('nao_avaliado', $reas[0]['explicacao']['criterios'][$criterio]['status']);
        }
        $this->assertStringContainsString('object_type=17,22,6,18,13', $reas[0]['explicacao']['criterios']['meta']['evidencia']);

        // O nível é estimado pelo mesmo regex dos outros repositórios, e pode ser corrigido.
        $nivel = $reas[1]['explicacao']['criterios']['nivel'];
        $this->assertSame(['ok', 'fundamental', 'regex'], [$nivel['status'], $nivel['evidencia'], $nivel['fonte']]);
        $this->assertSame('profile', $reas[1]['recommended']);
        $this->assertTrue(RuleClassifier::corrigivel($nivel));
        $this->assertSame('Atividade do 6º ano', $reas[1]['descricao']);
        $this->assertSame('meta_usuario', $reas[0]['fonte_interatividade']);

        // D8: o typo obkect_type não existe mais na URL.
        Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'obkect') && str_contains($r->url(), 'object_type=17'));
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

    public function test_eduplay_usa_a_mesma_regra(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['contents' => [
                ['name' => 'Vídeo 1', 'contentUrl' => 'http://v1'],
                ['name' => 'Aula de ensino médio', 'contentUrl' => 'http://v2', 'metatagDescription' => 'Revisão'],
            ]])
            ->push(['contents' => []])]);

        (new ProcessEduplay('algoritmos', 'Ensino medio', $this->searchedAt, 'ma', [['Vídeo'], 'Jogo']))->handle();

        $reas = $this->reas();
        $this->assertGrauCoerente($reas, true);

        // A meta não é conferida no Eduplay: não pontua e não oculta (nada de faixa fixa por meta).
        $this->assertSame('nao_avaliado', $reas[0]['explicacao']['criterios']['meta']['status']);
        $tipo = $reas[0]['explicacao']['criterios']['tipo'];
        $this->assertSame(['ok', 'video', 'colaboradores'], [$tipo['status'], $tipo['valor'], $tipo['fonte']]);
        $this->assertSame(['interest', 'both'], array_column($reas, 'recommended'));
        $this->assertSame([1, 3], $this->graus($reas));
        $this->assertSame('padrao_repositorio', $reas[0]['fonte_interatividade']);
    }

    public function test_eduplay_sem_tipos_preferidos_nao_pontua_o_tipo(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['contents' => [['name' => 'Vídeo 1', 'contentUrl' => 'http://v1']]])
            ->push(['contents' => []])]);

        (new ProcessEduplay('algoritmos', 'Ensino fundamental', $this->searchedAt, null))->handle();

        $rea = $this->reas()[0];
        $this->assertSame('falhou', $rea['explicacao']['criterios']['tipo']['status']);
        $this->assertSame(0, $rea['explicacao']['grau']['total']);
    }
}
