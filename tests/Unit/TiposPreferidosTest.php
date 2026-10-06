<?php

namespace Tests\Unit;

use App\Recommendation\ExplanationRenderer;
use App\Recommendation\RuleClassifier;
use App\Recommendation\TiposPreferidos;
use App\Recommendation\UserCorrections;
use PHPUnit\Framework\TestCase;

/**
 * Tipos preferidos salvos na conta (docs/plano-escrutabilidade.md §15).
 */
class TiposPreferidosTest extends TestCase
{
    public function test_sem_preferencia_valem_os_colaboradores(): void
    {
        $this->assertSame(
            ['tipos' => ['video', 'e-book', 'livro digital'], 'origem' => TiposPreferidos::COLABORADORES],
            TiposPreferidos::resolver(null, true, ['Vídeo', 'E-book'])
        );
        $this->assertSame(TiposPreferidos::COLABORADORES, TiposPreferidos::resolver([], false, ['video'])['origem']);
    }

    public function test_preferencia_da_conta_substitui_os_colaboradores(): void
    {
        $this->assertSame(
            ['tipos' => ['jogo'], 'origem' => TiposPreferidos::CONTA],
            TiposPreferidos::resolver(['Jogo'], false, ['video'])
        );
    }

    public function test_preferencia_da_conta_unida_aos_colaboradores(): void
    {
        $this->assertSame(
            ['tipos' => ['jogo', 'video'], 'origem' => TiposPreferidos::CONTA_E_COLABORADORES],
            TiposPreferidos::resolver(['jogo'], true, ['Vídeo', 'jogo'])
        );
    }

    public function test_criterio_de_tipo_registra_a_fonte_usuario(): void
    {
        $this->assertSame(
            ['status' => 'ok', 'valor' => 'jogo', 'esperado' => ['jogo'], 'fonte' => 'usuario', 'inclui_colaboradores' => false],
            RuleClassifier::criterioTipo('Jogo', ['jogo'], TiposPreferidos::CONTA)
        );
        $this->assertTrue(RuleClassifier::criterioTipo('Jogo', ['jogo'], TiposPreferidos::CONTA_E_COLABORADORES)['inclui_colaboradores']);
        $this->assertSame('colaboradores', RuleClassifier::criterioTipo('Jogo', ['jogo'])['fonte']);
        $this->assertArrayNotHasKey('inclui_colaboradores', RuleClassifier::criterioTipo('Jogo', ['jogo']));
    }

    public function test_tipo_comparavel(): void
    {
        $this->assertTrue(RuleClassifier::tipoComparavel(['fonte' => 'colaboradores']));
        $this->assertTrue(RuleClassifier::tipoComparavel(['fonte' => 'usuario']));
        $this->assertFalse(RuleClassifier::tipoComparavel(['fonte' => 'filtro_api']));
        $this->assertFalse(RuleClassifier::tipoComparavel(null));
    }

    public function test_explicacao_diz_que_os_tipos_vieram_da_conta(): void
    {
        $texto = fn (string $tipo, string $origem) => collect(ExplanationRenderer::linhas(RuleClassifier::explicacao(
            ['tipo' => RuleClassifier::criterioTipo($tipo, ['jogo', 'video'], $origem)], 'interest'
        )))->firstWhere('criterio', 'tipo')['texto'];

        $this->assertSame('Tipo jogo, entre os tipos preferidos da sua conta (jogo, video).', $texto('Jogo', TiposPreferidos::CONTA));
        $this->assertSame('Tipo livro não está entre os tipos preferidos da sua conta (jogo, video).', $texto('Livro', TiposPreferidos::CONTA));
        $this->assertSame('Tipo jogo, entre os tipos preferidos da sua conta e dos colaboradores (jogo, video).', $texto('Jogo', TiposPreferidos::CONTA_E_COLABORADORES));
    }

    public function test_editar_e_desfazer_na_busca_volta_a_lista_da_conta(): void
    {
        $criterios = [
            'nivel' => RuleClassifier::criterioNivel('Ensino fundamental', RuleClassifier::inferirNivel('Sexto ano', '')),
            'tipo' => RuleClassifier::criterioTipo('Jogo', ['jogo'], TiposPreferidos::CONTA),
        ];
        $rea = ['recommended' => 'both', 'explicacao' => RuleClassifier::explicacao($criterios, 'both')];

        $editado = UserCorrections::redefinirTipos($rea, ['video']);
        $tipo = $editado['explicacao']['criterios']['tipo'];
        $this->assertSame(['profile', ['video'], ['jogo'], 'usuario'], [$editado['recommended'], $tipo['esperado'], $tipo['esperado_original'], $tipo['origem_esperado']]);
        $this->assertSame('usuario', $tipo['fonte']);

        $this->assertSame($rea, UserCorrections::desfazer($editado, 'tipo'));
    }
}
