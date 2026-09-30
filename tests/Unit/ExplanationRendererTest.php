<?php

namespace Tests\Unit;

use App\Recommendation\ExplanationRenderer;
use App\Recommendation\RuleClassifier;
use PHPUnit\Framework\TestCase;

class ExplanationRendererTest extends TestCase
{
    private function explicacaoAquarela(): array
    {
        return RuleClassifier::explicacao([
            'tema' => RuleClassifier::criterioTema('algoritmos', 'Aquarela'),
            'nivel' => RuleClassifier::criterioNivel('Ensino fundamental', RuleClassifier::inferirNivel('Algoritmos no sexto ano', '')),
            'tipo' => RuleClassifier::criterioTipo('Jogo', ['video', 'livro digital']),
            'meta' => RuleClassifier::criterioMeta('ma', 'Não classificado'),
        ], 'profile');
    }

    private function linha(array $linhas, string $criterio): array
    {
        foreach ($linhas as $linha) {
            if ($linha['criterio'] === $criterio) {
                return $linha;
            }
        }

        $this->fail("Critério {$criterio} ausente");
    }

    public function test_linhas_por_criterio(): void
    {
        $linhas = ExplanationRenderer::linhas($this->explicacaoAquarela());

        $this->assertCount(4, $linhas);
        $this->assertSame('Resultado da busca por “algoritmos” no Aquarela.', $this->linha($linhas, 'tema')['texto']);

        $nivel = $this->linha($linhas, 'nivel');
        $this->assertSame('✓', $nivel['icone']);
        $this->assertSame(
            'Nível ensino fundamental, igual ao seu perfil: identificado pelo trecho “sexto ano” no título ou na descrição.',
            $nivel['texto']
        );

        $tipo = $this->linha($linhas, 'tipo');
        $this->assertSame('✗', $tipo['icone']);
        $this->assertSame('Tipo jogo não está entre os tipos preferidos (video, livro digital).', $tipo['texto']);

        $meta = $this->linha($linhas, 'meta');
        $this->assertSame('?', $meta['icone']);
        $this->assertStringContainsString('Não foi possível classificar', $meta['texto']);
    }

    public function test_nunca_marca_como_atendido_o_que_nao_foi_avaliado(): void
    {
        $explicacao = ['criterios' => [
            'nivel' => ['status' => 'nao_avaliado', 'fonte' => 'padrao_repositorio', 'evidencia' => 'o Eduplay não informa a etapa de ensino'],
            'tipo' => ['status' => 'nao_avaliado', 'fonte' => 'padrao_repositorio', 'evidencia' => 'só vídeos'],
        ]];

        foreach (ExplanationRenderer::linhas($explicacao) as $linha) {
            $this->assertNotSame('✓', $linha['icone']);
        }
        $this->assertSame('Não verificado: nível, tipo.', ExplanationRenderer::resumo($explicacao));
    }

    public function test_nivel_assumido_e_declarado(): void
    {
        $c = RuleClassifier::criterioNivel('Ensino superior', RuleClassifier::inferirNivel('Grafos', ''));
        $texto = ExplanationRenderer::linhas(['criterios' => ['nivel' => $c]])[0]['texto'];
        $this->assertStringContainsString('assumido', $texto);

        $c = RuleClassifier::criterioNivel('Ensino médio', RuleClassifier::inferirNivel('Grafos', ''));
        $texto = ExplanationRenderer::linhas(['criterios' => ['nivel' => $c]])[0]['texto'];
        $this->assertSame('O texto não menciona nenhuma etapa; o sistema assume ensino superior, diferente do seu perfil (ensino médio).', $texto);
    }

    public function test_filtro_do_repositorio(): void
    {
        $linhas = ExplanationRenderer::linhas(['criterios' => [
            'nivel' => ['status' => 'filtro_api', 'esperado' => 'ensino fundamental', 'fonte' => 'filtro_api'],
            'meta' => ['status' => 'filtro_api', 'esperado' => 'mpa', 'fonte' => 'filtro_api'],
        ]]);

        $this->assertSame('▽', $linhas[0]['icone']);
        $this->assertSame('O MEC RED filtrou a busca pela etapa do seu perfil (ensino fundamental).', $linhas[0]['texto']);
        $this->assertStringContainsString('Performance-aproximação', $linhas[1]['texto']);
    }

    public function test_meta_nao_verificada_mostra_o_pedido_feito(): void
    {
        $linhas = ExplanationRenderer::linhas(['criterios' => [
            'meta' => ['status' => 'nao_avaliado', 'esperado' => 'ma', 'fonte' => 'filtro_api', 'evidencia' => 'o SisREAd pediu X'],
        ]]);

        $this->assertSame('?', $linhas[0]['icone']);
        $this->assertSame('Meta não verificada: o SisREAd pediu X.', $linhas[0]['texto']);
    }

    public function test_meta_por_padrao_do_repositorio(): void
    {
        $linhas = ExplanationRenderer::linhas(['criterios' => [
            'meta' => ['status' => 'falhou', 'esperado' => 'mpe', 'fonte' => 'padrao_repositorio', 'evidencia' => 'Vídeos servem a ma e mpa.'],
        ]]);

        $this->assertSame('✗', $linhas[0]['icone']);
        $this->assertSame('Vídeos servem a ma e mpa. Sua meta é Performance-evitação.', $linhas[0]['texto']);
    }

    public function test_resumo_inclui_observacao(): void
    {
        $explicacao = $this->explicacaoAquarela();
        $explicacao['observacao'] = 'Política do repositório.';

        $this->assertSame(
            'Atende: tema, nível. Não atende: tipo. Não verificado: meta. Política do repositório.',
            ExplanationRenderer::resumo($explicacao)
        );
    }

    public function test_aceita_objeto_vindo_do_json(): void
    {
        $objeto = json_decode(json_encode($this->explicacaoAquarela()));

        $this->assertCount(4, ExplanationRenderer::linhas($objeto));
    }

    public function test_item_antigo_sem_explicacao(): void
    {
        $this->assertSame('Explicação indisponível para esta busca.', ExplanationRenderer::linhas(null)[0]['texto']);
        $this->assertSame('Explicação indisponível para esta busca.', ExplanationRenderer::resumo(null));
        $this->assertSame([], ExplanationRenderer::avisos(null));
    }

    public function test_avisos_so_para_estimativas_automaticas(): void
    {
        $this->assertNotEmpty(ExplanationRenderer::avisos($this->explicacaoAquarela()));
        $this->assertSame([], ExplanationRenderer::avisos(['criterios' => [
            'tema' => RuleClassifier::criterioTema('x', 'Eduplay'),
        ]]));
    }

    public function test_texto_da_ia_declara_modelo_e_tempo(): void
    {
        $c = RuleClassifier::criterioMeta('ma', 'Aprendizagem', 'gemma3:4b', 1.234);
        $texto = ExplanationRenderer::linhas(['criterios' => ['meta' => $c]])[0]['texto'];

        $this->assertStringContainsString('Modelo: gemma3:4b, em 1.23s.', $texto);

        $semModelo = ExplanationRenderer::linhas(['criterios' => ['meta' => RuleClassifier::criterioMeta('ma', 'Aprendizagem')]])[0]['texto'];
        $this->assertStringNotContainsString('Modelo:', $semModelo);
    }

    public function test_aviso_menciona_o_vies_da_ia(): void
    {
        $avisos = ExplanationRenderer::avisos(['criterios' => ['meta' => RuleClassifier::criterioMeta('ma', 'Aprendizagem', 'gemma3:4b', 1.0)]]);

        $this->assertStringContainsString('Aprendizagem', $avisos[0]);
    }

    public function test_faixa(): void
    {
        $this->assertSame('Nível', ExplanationRenderer::faixa('profile')['titulo']);
        $this->assertSame('Sem faixa', ExplanationRenderer::faixa(null)['titulo']);

        $politica = ExplanationRenderer::faixa('both', ['observacao' => 'Política do MEC RED.', 'criterios' => []]);
        $this->assertSame('Nível e tipo (política)', $politica['titulo']);
        $this->assertStringNotContainsString('tipo entre os preferidos', $politica['descricao']);
    }
}
