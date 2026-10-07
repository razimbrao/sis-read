<?php

namespace Tests\Unit;

use App\Recommendation\RuleClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RuleClassifierTest extends TestCase
{
    public function test_normaliza_tipos_achatando_listas_e_deduplicando(): void
    {
        $tipos = RuleClassifier::normalizarTipos(['Vídeo', ['vídeo'], ['E-book'], '', ['Jogo'], null]);

        $this->assertSame(['video', 'e-book', 'livro digital', 'jogo'], $tipos);
    }

    public function test_inferir_nivel_devolve_o_trecho_que_casou(): void
    {
        $nivel = RuleClassifier::inferirNivel('Frações para o sexto ano', 'Atividade de matemática');

        $this->assertSame('ensino fundamental', $nivel['valor']);
        $this->assertSame('sexto ano', $nivel['evidencia']);
        $this->assertFalse($nivel['assumido']);
    }

    public function test_inferir_nivel_sem_casamento_assume_ensino_superior(): void
    {
        $nivel = RuleClassifier::inferirNivel('Grafos', 'Teoria de grafos');

        $this->assertSame(['valor' => 'ensino superior', 'evidencia' => null, 'assumido' => true], $nivel);
    }

    public function test_inferir_nivel_respeita_a_prioridade_das_regras(): void
    {
        $this->assertSame('educacao infantil', RuleClassifier::inferirNivel('Jogo infantil', 'ensino médio')['valor']);
        $this->assertSame('ensino medio', RuleClassifier::inferirNivel('Física', 'para o ensino médio')['valor']);
    }

    public function test_criterio_nivel_compara_com_o_perfil_normalizado(): void
    {
        $inferido = RuleClassifier::inferirNivel('Química no ensino médio', '');

        $this->assertSame('ok', RuleClassifier::criterioNivel('Ensino Médio', $inferido)['status']);

        $falhou = RuleClassifier::criterioNivel('Ensino fundamental', $inferido);
        $this->assertSame('falhou', $falhou['status']);
        $this->assertSame('ensino fundamental', $falhou['esperado']);
        $this->assertSame('regex', $falhou['fonte']);
        $this->assertSame('médio', $falhou['evidencia']);
    }

    public function test_criterio_tipo(): void
    {
        $this->assertSame('ok', RuleClassifier::criterioTipo('Vídeo', ['video'])['status']);
        $this->assertSame('falhou', RuleClassifier::criterioTipo('Jogo', ['video'])['status']);
        $this->assertSame('falhou', RuleClassifier::criterioTipo('', [''])['status']);
    }

    public function test_criterio_meta_nao_classificado_fica_nao_avaliado(): void
    {
        $this->assertSame('nao_avaliado', RuleClassifier::criterioMeta('ma', 'Não classificado')['status']);
        $this->assertSame('nao_avaliado', RuleClassifier::criterioMeta('ma', null)['status']);
        $this->assertSame('ok', RuleClassifier::criterioMeta('ma', 'Aprendizagem')['status']);
        $this->assertSame('falhou', RuleClassifier::criterioMeta('mpe', 'Aprendizagem')['status']);
    }

    public static function classificacoesDeMeta(): array
    {
        return [
            'aprendizagem' => ['Aprendizagem', 'ma', true],
            'aproximacao com acento' => ['Performance Aproximação', 'mpa', true],
            'aproximacao underscore' => ['performance_aproximacao', 'mpa', true],
            'evitacao com hifen' => ['Performance-Evitação', 'mpe', true],
            'meta diferente' => ['Performance Evitação', 'mpa', false],
            'meta desconhecida' => ['Aprendizagem', 'xyz', false],
        ];
    }

    #[DataProvider('classificacoesDeMeta')]
    public function test_casa_meta(string $classificacao, string $meta, bool $esperado): void
    {
        $this->assertSame($esperado, RuleClassifier::casaMeta($classificacao, $meta));
    }

    public static function tabelaDeRotulos(): array
    {
        // [nivel, tipo, meta|null, comMeta, rótulo]
        return [
            'nivel e tipo' => ['ok', 'ok', null, false, 'both'],
            'so nivel' => ['ok', 'falhou', null, false, 'profile'],
            'so tipo' => ['falhou', 'ok', null, false, 'interest'],
            'nenhum' => ['falhou', 'falhou', null, false, 'interest'],
            'filtro sem conferencia nao conta' => ['filtro_api', 'ok', null, false, 'interest'],
            'nao avaliado nao conta' => ['nao_avaliado', 'ok', null, false, 'interest'],
            'meta + ambos' => ['ok', 'ok', 'ok', true, 'meta_both'],
            'meta + um' => ['falhou', 'ok', 'ok', true, 'meta_one'],
            'so meta' => ['falhou', 'falhou', 'ok', true, 'meta'],
            'meta falhou cai no padrao' => ['ok', 'ok', 'falhou', true, 'both'],
            'meta nao avaliada' => ['ok', 'falhou', 'nao_avaliado', true, 'profile'],
        ];
    }

    #[DataProvider('tabelaDeRotulos')]
    public function test_rotular(string $nivel, string $tipo, ?string $meta, bool $comMeta, string $esperado): void
    {
        $criterios = ['nivel' => ['status' => $nivel], 'tipo' => ['status' => $tipo]];
        if ($meta) {
            $criterios['meta'] = ['status' => $meta];
        }

        $this->assertSame($esperado, RuleClassifier::rotular($criterios, $comMeta));
    }

    public function test_chave_e_estavel_e_distingue_repositorio_link_e_titulo(): void
    {
        $chave = RuleClassifier::chave('Aquarela', 'http://a', 'Grafos');

        $this->assertSame($chave, RuleClassifier::chave('Aquarela', 'http://a', 'Grafos'));
        $this->assertSame(12, strlen($chave));
        $this->assertNotSame($chave, RuleClassifier::chave('Eduplay', 'http://a', 'Grafos'));
        $this->assertNotSame($chave, RuleClassifier::chave('Aquarela', 'http://b', 'Grafos'));
        $this->assertNotSame($chave, RuleClassifier::chave('Aquarela', 'http://a', 'Árvores'));
    }

    public function test_corrigivel_so_para_estimativas(): void
    {
        $this->assertTrue(RuleClassifier::corrigivel(['fonte' => 'regex']));
        $this->assertTrue(RuleClassifier::corrigivel(['fonte' => 'llm']));
        $this->assertTrue(RuleClassifier::corrigivel(['fonte' => 'usuario', 'original' => ['fonte' => 'regex']]));
        $this->assertFalse(RuleClassifier::corrigivel(['fonte' => 'colaboradores']));
        $this->assertFalse(RuleClassifier::corrigivel(['fonte' => 'filtro_api']));
        $this->assertFalse(RuleClassifier::corrigivel(['fonte' => 'padrao_repositorio']));
        $this->assertFalse(RuleClassifier::corrigivel(null));
    }

    public function test_niveis_validos_incluem_o_nivel_padrao(): void
    {
        $this->assertSame(['educacao infantil', 'ensino fundamental', 'ensino medio', 'ensino superior'], RuleClassifier::NIVEIS_VALIDOS);
    }

    public function test_explicacao_tem_versao_faixa_e_grau(): void
    {
        $criterios = ['tema' => ['status' => 'ok'], 'nivel' => ['status' => 'ok'], 'tipo' => ['status' => 'falhou']];
        $explicacao = RuleClassifier::explicacao($criterios, 'profile', 3);

        $this->assertSame(2, RuleClassifier::VERSAO_REGRAS);
        $this->assertSame(RuleClassifier::VERSAO_REGRAS, $explicacao['versao_regras']);
        $this->assertSame('profile', $explicacao['faixa']);
        $this->assertNull($explicacao['observacao']);
        $this->assertSame(['total' => 2, 'maximo' => 3, 'pontos' => ['nivel' => 2, 'tipo' => 0], 'posicao' => 3], $explicacao['grau']);
    }

    public function test_nivel_assumido_nao_conta_como_atendido(): void
    {
        $criterios = [
            'nivel' => RuleClassifier::criterioNivel('Ensino superior', RuleClassifier::inferirNivel('Grafos', '')),
            'tipo' => RuleClassifier::criterioTipo('Vídeo', ['video']),
        ];

        $this->assertSame('ok', $criterios['nivel']['status']);
        $this->assertTrue($criterios['nivel']['assumido']);
        $this->assertFalse(RuleClassifier::atende($criterios, 'nivel'));
        $this->assertSame('interest', RuleClassifier::rotular($criterios, false));
        $this->assertSame(1, RuleClassifier::grau($criterios)['total']);
    }

    public static function graus(): array
    {
        // [nivel, tipo, meta (null = busca sem meta), grau, maximo]
        return [
            'sem meta, nada' => ['falhou', 'falhou', null, 0, 3],
            'sem meta, so tipo' => ['falhou', 'ok', null, 1, 3],
            'sem meta, so nivel' => ['ok', 'falhou', null, 2, 3],
            'sem meta, ambos' => ['ok', 'ok', null, 3, 3],
            'nao avaliado vale zero' => ['nao_avaliado', 'nao_avaliado', null, 0, 3],
            'meta nao avaliada vale zero' => ['ok', 'ok', 'nao_avaliado', 3, 7],
            'meta falhou vale zero' => ['ok', 'ok', 'falhou', 3, 7],
            'so meta' => ['falhou', 'falhou', 'ok', 4, 7],
            'meta e tipo' => ['falhou', 'ok', 'ok', 5, 7],
            'meta e nivel' => ['ok', 'falhou', 'ok', 6, 7],
            'tudo' => ['ok', 'ok', 'ok', 7, 7],
        ];
    }

    #[DataProvider('graus')]
    public function test_grau(string $nivel, string $tipo, ?string $meta, int $grau, int $maximo): void
    {
        $criterios = ['tema' => ['status' => 'ok'], 'nivel' => ['status' => $nivel], 'tipo' => ['status' => $tipo]];
        if ($meta !== null) {
            $criterios['meta'] = ['status' => $meta];
        }

        $calculado = RuleClassifier::grau($criterios);

        $this->assertSame($grau, $calculado['total']);
        $this->assertSame($maximo, $calculado['maximo']);
        $this->assertArrayNotHasKey('tema', $calculado['pontos']);
    }

    public function test_grau_e_rotulo_concordam(): void
    {
        // A faixa é um intervalo do grau: nenhum REA de faixa mais baixa tem grau maior que um de faixa mais alta.
        $faixas = ['meta_both' => [7, 7], 'meta_one' => [5, 6], 'meta' => [4, 4], 'both' => [3, 3], 'profile' => [2, 2], 'interest' => [0, 1]];
        $status = ['ok', 'falhou', 'nao_avaliado'];

        foreach ($status as $nivel) {
            foreach ($status as $tipo) {
                foreach ([null, ...$status] as $meta) {
                    $criterios = ['nivel' => ['status' => $nivel], 'tipo' => ['status' => $tipo]];
                    if ($meta !== null) {
                        $criterios['meta'] = ['status' => $meta];
                    }

                    $rotulo = RuleClassifier::rotular($criterios, $meta !== null);
                    $grau = RuleClassifier::grau($criterios)['total'];

                    $this->assertGreaterThanOrEqual($faixas[$rotulo][0], $grau, "{$nivel}/{$tipo}/{$meta}");
                    $this->assertLessThanOrEqual($faixas[$rotulo][1], $grau, "{$nivel}/{$tipo}/{$meta}");
                }
            }
        }
    }
}
