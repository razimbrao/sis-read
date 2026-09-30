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
            'filtro conta como atende' => ['filtro_api', 'ok', null, false, 'both'],
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

    public function test_explicacao_tem_versao_e_faixa(): void
    {
        $explicacao = RuleClassifier::explicacao(['tema' => ['status' => 'ok']], 'interest', 'obs');

        $this->assertSame(RuleClassifier::VERSAO_REGRAS, $explicacao['versao_regras']);
        $this->assertSame('interest', $explicacao['faixa']);
        $this->assertSame('obs', $explicacao['observacao']);
    }
}
