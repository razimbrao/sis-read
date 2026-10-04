<?php

namespace Tests\Unit;

use App\Recommendation\RuleClassifier;
use App\Recommendation\UserCorrections;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserCorrectionsTest extends TestCase
{
    /**
     * REA do Aquarela como o job grava: nível assumido (superior) para perfil fundamental, tipo jogo.
     */
    private function rea(?string $meta = null, ?string $classificacaoMeta = 'Aprendizagem'): array
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema('algoritmos', 'Aquarela'),
            'nivel' => RuleClassifier::criterioNivel('Ensino fundamental', RuleClassifier::inferirNivel('Grafos', '')),
            'tipo' => RuleClassifier::criterioTipo('Jogo', ['video']),
        ];

        if ($meta) {
            $criterios['meta'] = RuleClassifier::criterioMeta($meta, $classificacaoMeta, 'gemma3:4b', 1.2);
        }

        $rotulo = RuleClassifier::rotular($criterios, (bool) $meta);

        return [
            'chave' => 'abc',
            'recommended' => $rotulo,
            'explicacao' => RuleClassifier::explicacao($criterios, $rotulo),
        ];
    }

    private function reaDePolitica(): array
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema('algoritmos', 'MEC RED'),
            'nivel' => ['status' => 'nao_avaliado', 'valor' => null, 'esperado' => 'ensino fundamental', 'fonte' => 'filtro_api'],
            'tipo' => ['status' => 'nao_avaliado', 'valor' => null, 'esperado' => [], 'fonte' => 'filtro_api'],
        ];

        return [
            'recommended' => 'both',
            'explicacao' => RuleClassifier::explicacao($criterios, 'both', 'Por política do SisREAd...'),
        ];
    }

    public function test_corrigir_nivel_recalcula_status_e_faixa_e_guarda_o_original(): void
    {
        $rea = $this->rea();
        $this->assertSame('interest', $rea['recommended']);

        $corrigido = UserCorrections::corrigirNivel($rea, 'ensino fundamental');
        $nivel = $corrigido['explicacao']['criterios']['nivel'];

        $this->assertSame(['ok', 'ensino fundamental', 'usuario'], [$nivel['status'], $nivel['valor'], $nivel['fonte']]);
        $this->assertSame($rea['explicacao']['criterios']['nivel'], $nivel['original']);
        $this->assertSame('profile', $corrigido['recommended']);
        $this->assertSame('profile', $corrigido['explicacao']['faixa']);
        $this->assertSame('interest', $corrigido['explicacao']['faixa_original']);
    }

    public function test_corrigir_nivel_para_etapa_diferente_do_perfil_fica_falhou(): void
    {
        $corrigido = UserCorrections::corrigirNivel($this->rea(), 'ensino medio');

        $this->assertSame('falhou', $corrigido['explicacao']['criterios']['nivel']['status']);
        $this->assertSame('interest', $corrigido['recommended']);
        // Há correção, então a faixa do job continua registrada mesmo sem mudança.
        $this->assertSame('interest', $corrigido['explicacao']['faixa_original']);
    }

    public function test_segunda_correcao_preserva_o_original_do_job(): void
    {
        $rea = $this->rea();

        $duas = UserCorrections::corrigirNivel(UserCorrections::corrigirNivel($rea, 'ensino medio'), 'ensino fundamental');

        $this->assertSame($rea['explicacao']['criterios']['nivel'], $duas['explicacao']['criterios']['nivel']['original']);
        $this->assertSame('interest', $duas['explicacao']['faixa_original']);
        $this->assertSame('profile', $duas['recommended']);
    }

    public function test_desfazer_restaura_o_job(): void
    {
        $rea = $this->rea();

        $desfeito = UserCorrections::desfazer(UserCorrections::corrigirNivel($rea, 'ensino fundamental'), 'nivel');

        $this->assertSame($rea, $desfeito);
    }

    public function test_corrigir_para_o_valor_estimado_equivale_a_desfazer(): void
    {
        $rea = $this->rea();

        $this->assertSame($rea, UserCorrections::corrigirNivel(UserCorrections::corrigirNivel($rea, 'ensino medio'), 'ensino superior'));
        $this->assertSame($rea, UserCorrections::corrigirNivel($rea, 'ensino superior'));
    }

    public function test_desfazer_sem_correcao_nao_muda_nada(): void
    {
        $rea = $this->rea();

        $this->assertSame($rea, UserCorrections::desfazer($rea, 'nivel'));
        $this->assertSame($rea, UserCorrections::desfazer($rea, 'tipo'));
    }

    public function test_corrigir_meta_nao_avaliada_traz_o_rea_para_a_faixa_de_meta(): void
    {
        $rea = $this->rea('ma', null);
        $this->assertSame('nao_avaliado', $rea['explicacao']['criterios']['meta']['status']);
        $this->assertSame('interest', $rea['recommended']);

        $corrigido = UserCorrections::corrigirMeta($rea, 'ma');
        $meta = $corrigido['explicacao']['criterios']['meta'];

        $this->assertSame(['ok', 'Aprendizagem', 'ma', 'usuario'], [$meta['status'], $meta['valor'], $meta['esperado'], $meta['fonte']]);
        $this->assertSame('meta', $corrigido['recommended']);
        $this->assertSame('interest', $corrigido['explicacao']['faixa_original']);
    }

    public function test_corrigir_meta_para_outra_tira_o_rea_da_faixa_de_meta(): void
    {
        $rea = $this->rea('ma', 'Aprendizagem');
        $this->assertSame('meta', $rea['recommended']);

        $corrigido = UserCorrections::corrigirMeta($rea, 'mpe');

        $this->assertSame('falhou', $corrigido['explicacao']['criterios']['meta']['status']);
        $this->assertSame('Performance-evitação', $corrigido['explicacao']['criterios']['meta']['valor']);
        $this->assertSame('interest', $corrigido['recommended']);
    }

    public function test_confirmar_a_meta_da_ia_equivale_a_desfazer(): void
    {
        $rea = $this->rea('mpa', 'Performance Aproximação');

        $this->assertSame($rea, UserCorrections::corrigirMeta($rea, 'mpa'));
        $this->assertSame($rea, UserCorrections::corrigirMeta(UserCorrections::corrigirMeta($rea, 'ma'), 'mpa'));
    }

    public function test_redefinir_tipos_recalcula_o_criterio_de_tipo(): void
    {
        $rea = UserCorrections::corrigirNivel($this->rea(), 'ensino fundamental');
        $this->assertSame('profile', $rea['recommended']);

        $corrigido = UserCorrections::redefinirTipos($rea, ['Jogo', 'Vídeo']);
        $tipo = $corrigido['explicacao']['criterios']['tipo'];

        $this->assertSame(['ok', ['jogo', 'video'], 'usuario', ['video']], [$tipo['status'], $tipo['esperado'], $tipo['origem_esperado'], $tipo['esperado_original']]);
        $this->assertSame('colaboradores', $tipo['fonte']);
        $this->assertSame('both', $corrigido['recommended']);
        // A correção de nível continua valendo.
        $this->assertSame('usuario', $corrigido['explicacao']['criterios']['nivel']['fonte']);
        $this->assertSame('interest', $corrigido['explicacao']['faixa_original']);
    }

    public function test_redefinir_tipos_de_volta_para_a_lista_original_desfaz(): void
    {
        $rea = $this->rea();

        $this->assertSame($rea, UserCorrections::redefinirTipos(UserCorrections::redefinirTipos($rea, ['jogo']), ['Vídeo']));
        $this->assertSame($rea, UserCorrections::desfazer(UserCorrections::redefinirTipos($rea, ['jogo']), 'tipo'));
    }

    public function test_redefinir_tipos_nao_mexe_em_item_de_politica(): void
    {
        $rea = $this->reaDePolitica();

        $this->assertSame($rea, UserCorrections::redefinirTipos($rea, ['video']));
    }

    public static function correcoesInvalidas(): array
    {
        return [
            'nivel fora da lista' => ['nivel', 'mestrado'],
            'meta fora da lista' => ['meta', 'xyz'],
            'meta sem criterio de meta' => ['meta', 'ma'],
        ];
    }

    #[DataProvider('correcoesInvalidas')]
    public function test_rejeita_correcao_invalida(string $criterio, string $valor): void
    {
        $this->expectException(InvalidArgumentException::class);

        $criterio === 'nivel'
            ? UserCorrections::corrigirNivel($this->rea(), $valor)
            : UserCorrections::corrigirMeta($this->rea(), $valor);
    }

    public function test_rejeita_corrigir_item_de_politica(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UserCorrections::corrigirNivel($this->reaDePolitica(), 'ensino fundamental');
    }

    public function test_corrigido(): void
    {
        $this->assertFalse(UserCorrections::corrigido($this->rea()));
        $this->assertTrue(UserCorrections::corrigido(UserCorrections::corrigirNivel($this->rea(), 'ensino medio')));
        $this->assertTrue(UserCorrections::corrigido(UserCorrections::redefinirTipos($this->rea(), ['jogo'])));
    }
}
