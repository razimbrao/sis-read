<?php

namespace Tests\Unit;

use App\Recommendation\ExplanationRenderer;
use App\Recommendation\RuleClassifier;
use App\Recommendation\UserCorrections;
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

    /**
     * REA do Aquarela com nível assumido (superior), tipo jogo e meta não classificada pela IA.
     */
    private function reaAquarela(): array
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema('algoritmos', 'Aquarela'),
            'nivel' => RuleClassifier::criterioNivel('Ensino fundamental', RuleClassifier::inferirNivel('Grafos', '')),
            'tipo' => RuleClassifier::criterioTipo('Jogo', ['video']),
            'meta' => RuleClassifier::criterioMeta('ma', 'Não classificado', 'gemma3:4b', 1.0),
        ];
        $rotulo = RuleClassifier::rotular($criterios, true);

        return ['recommended' => $rotulo, 'explicacao' => RuleClassifier::explicacao($criterios, $rotulo)];
    }

    public function test_nivel_corrigido_declara_o_usuario_e_a_estimativa_anterior(): void
    {
        $rea = UserCorrections::corrigirNivel($this->reaAquarela(), 'ensino fundamental');
        $nivel = $this->linha(ExplanationRenderer::linhas($rea['explicacao']), 'nivel');

        $this->assertSame('✓', $nivel['icone']);
        $this->assertTrue($nivel['corrigido']);
        $this->assertTrue($nivel['corrigivel']);
        $this->assertSame('Nível ensino fundamental, informado por você (o sistema tinha assumido ensino superior). Igual ao seu perfil.', $nivel['texto']);

        $rea = UserCorrections::corrigirNivel($this->reaAquarela(), 'ensino medio');
        $this->assertSame(
            'Nível ensino médio, informado por você (o sistema tinha assumido ensino superior). Diferente do seu perfil (ensino fundamental).',
            $this->linha(ExplanationRenderer::linhas($rea['explicacao']), 'nivel')['texto']
        );
    }

    public function test_meta_corrigida_declara_o_que_a_ia_tinha_dito(): void
    {
        $rea = UserCorrections::corrigirMeta($this->reaAquarela(), 'ma');
        $meta = $this->linha(ExplanationRenderer::linhas($rea['explicacao']), 'meta');

        $this->assertSame('✓', $meta['icone']);
        $this->assertSame('Meta Aprendizagem, informada por você (a IA não tinha conseguido classificar). Compatível com a sua meta (Aprendizagem).', $meta['texto']);

        $classificado = $this->reaAquarela();
        $classificado['explicacao']['criterios']['meta'] = RuleClassifier::criterioMeta('ma', 'Aprendizagem');
        $rea = UserCorrections::corrigirMeta($classificado, 'mpe');

        $this->assertSame(
            'Meta Performance-evitação, informada por você (a IA tinha classificado como Aprendizagem). Sua meta é Aprendizagem.',
            $this->linha(ExplanationRenderer::linhas($rea['explicacao']), 'meta')['texto']
        );
    }

    public function test_tipos_definidos_pelo_usuario(): void
    {
        $linha = fn (array $tipos) => $this->linha(
            ExplanationRenderer::linhas(UserCorrections::redefinirTipos($this->reaAquarela(), $tipos)['explicacao']),
            'tipo'
        );

        $this->assertSame('Tipo jogo, entre os tipos preferidos que você definiu (jogo, video).', $linha(['jogo', 'video'])['texto']);
        $this->assertTrue($linha(['jogo'])['corrigido']);
        $this->assertFalse($linha(['jogo'])['corrigivel']);
        $this->assertSame('Tipo jogo não está entre os tipos preferidos que você definiu (livro).', $linha(['livro'])['texto']);
        $this->assertSame('Tipo jogo: você não definiu nenhum tipo preferido.', $linha([])['texto']);
    }

    public function test_corrigivel_so_para_nivel_e_meta_estimados(): void
    {
        $linhas = ExplanationRenderer::linhas($this->reaAquarela()['explicacao']);

        $this->assertSame(
            ['tema' => false, 'nivel' => true, 'tipo' => false, 'meta' => true],
            array_column($linhas, 'corrigivel', 'criterio')
        );
        $this->assertSame([false, false, false, false], array_column($linhas, 'corrigido'));

        $politica = ['observacao' => 'Política.', 'criterios' => [
            'nivel' => ['status' => 'nao_avaliado', 'fonte' => 'regex'],
            'meta' => ['status' => 'ok', 'fonte' => 'padrao_repositorio'],
        ]];
        $this->assertSame([false, false], array_column(ExplanationRenderer::linhas($politica), 'corrigivel'));
    }

    public function test_resumo_marca_criterio_corrigido(): void
    {
        $rea = UserCorrections::corrigirNivel($this->reaAquarela(), 'ensino fundamental');

        $this->assertSame(
            'Atende: tema, nível (corrigido por você). Não atende: tipo. Não verificado: meta.',
            ExplanationRenderer::resumo($rea['explicacao'])
        );
    }

    public function test_aviso_de_estimativa_some_quando_tudo_foi_corrigido(): void
    {
        $rea = $this->reaAquarela();
        $this->assertNotEmpty(ExplanationRenderer::avisos($rea['explicacao']));

        // A meta não avaliada não gera aviso; o nível estimado gera, até ser corrigido.
        $rea = UserCorrections::corrigirNivel($rea, 'ensino medio');
        $this->assertSame([], ExplanationRenderer::avisos($rea['explicacao']));
    }

    public function test_mudanca_de_faixa(): void
    {
        $rea = $this->reaAquarela();
        $this->assertNull(ExplanationRenderer::mudancaFaixa($rea['explicacao']));

        $subiu = UserCorrections::corrigirMeta($rea, 'ma');
        $this->assertSame('Faixa alterada pela sua correção: antes Tipo ou só tema, agora Só meta.', ExplanationRenderer::mudancaFaixa($subiu['explicacao']));

        $igual = UserCorrections::corrigirNivel($rea, 'ensino medio');
        $this->assertSame('Sua correção não mudou a faixa deste REA.', ExplanationRenderer::mudancaFaixa($igual['explicacao']));

        $this->assertNull(ExplanationRenderer::mudancaFaixa(json_decode(json_encode(UserCorrections::desfazer($subiu, 'meta')['explicacao']))));
    }

    public function test_grau_mostra_a_conta_e_o_desempate(): void
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema('algoritmos', 'Eduplay'),
            'nivel' => RuleClassifier::criterioNivel('Ensino superior', RuleClassifier::inferirNivel('Grafos', '')),
            'tipo' => RuleClassifier::criterioTipo('Vídeo', ['video']),
            'meta' => ['status' => 'nao_avaliado', 'valor' => null, 'esperado' => 'ma', 'fonte' => 'padrao_repositorio'],
        ];
        $grau = ExplanationRenderer::grau(RuleClassifier::explicacao($criterios, 'interest', 2));

        $this->assertSame(1, $grau['total']);
        $this->assertSame(7, $grau['maximo']);
        $this->assertSame('Grau 1 de 7', $grau['selo']);
        $this->assertSame('Grau 1 de 7: meta 0 (não verificado), nível 0 (assumido, não conferido), tipo +1.', $grau['conta']);
        $this->assertSame('Entre REAs de mesmo grau, vale a posição no repositório: este é o 2º resultado do Eduplay.', $grau['desempate']);

        $falhou = ExplanationRenderer::grau(RuleClassifier::explicacao(['nivel' => ['status' => 'falhou'], 'tipo' => ['status' => 'ok']], 'interest'));
        $this->assertSame('Grau 1 de 3: nível 0 (não atende), tipo +1.', $falhou['conta']);
        $this->assertNull($falhou['desempate']);
    }

    public function test_grau_indisponivel_para_rea_antigo(): void
    {
        $this->assertNull(ExplanationRenderer::grau(null));
        $this->assertNull(ExplanationRenderer::grau(['versao_regras' => 1, 'faixa' => 'both', 'criterios' => ['tema' => ['status' => 'ok']]]));
    }

    public function test_grau_segue_a_correcao(): void
    {
        $rea = UserCorrections::corrigirMeta($this->reaAquarela(), 'ma');

        $this->assertSame('Grau 4 de 7', ExplanationRenderer::grau($rea['explicacao'])['selo']);
        $this->assertSame(4, $rea['explicacao']['grau']['total']);
    }

    public function test_nivel_assumido_nao_aparece_como_atendido(): void
    {
        $explicacao = RuleClassifier::explicacao([
            'tema' => RuleClassifier::criterioTema('algoritmos', 'Aquarela'),
            'nivel' => RuleClassifier::criterioNivel('Ensino superior', RuleClassifier::inferirNivel('Grafos', '')),
        ], 'interest');

        $nivel = $this->linha(ExplanationRenderer::linhas($explicacao), 'nivel');

        $this->assertSame('?', $nivel['icone']);
        $this->assertSame('nao_avaliado', $nivel['status']);
        $this->assertSame('Atende: tema. Não verificado: nível.', ExplanationRenderer::resumo($explicacao));
    }

    public function test_faixa_com_meta_nao_conferida_ou_diferente(): void
    {
        // Sem o REA (painel), a faixa reúne os dois grupos abaixo dos compatíveis.
        $this->assertSame('Nível e tipo, meta não conferida ou diferente da sua', ExplanationRenderer::faixa('both', null, true)['titulo']);
        $diferente = UserCorrections::corrigirMeta($this->reaAquarela(), 'mpe');
        $this->assertSame('Tipo ou só tema, meta diferente da sua', ExplanationRenderer::faixa('interest', $diferente['explicacao'])['titulo']);
        $this->assertStringContainsString('no fim da lista', ExplanationRenderer::faixa('interest', $diferente['explicacao'])['descricao']);
        $this->assertSame('Nível e tipo', ExplanationRenderer::faixa('both')['titulo']);
        $this->assertSame('Só meta', ExplanationRenderer::faixa('meta', null, true)['titulo']);
        $this->assertSame('Tipo ou só tema, meta não conferida', ExplanationRenderer::faixa('interest', $this->reaAquarela()['explicacao'])['titulo']);
        $this->assertSame('5 a 6', ExplanationRenderer::faixa('meta_one')['graus']);
    }

    public function test_faixas_cobrem_os_rotulos_do_ranking(): void
    {
        $this->assertSame(\App\Recommendation\Ranking::ORDEM_COM_META, array_keys(ExplanationRenderer::FAIXAS));
    }
}
