<?php

namespace Tests\Unit;

use App\Recommendation\Ranking;
use App\Recommendation\RuleClassifier;
use App\Recommendation\UserCorrections;
use PHPUnit\Framework\TestCase;

class RankingTest extends TestCase
{
    /**
     * REAs antigos, só com o rótulo (sem critérios): o grau vem da faixa.
     */
    private function reas(string ...$rotulos): array
    {
        return array_map(fn ($r, $i) => (object) ['recommended' => $r, 'id' => $i], $rotulos, array_keys($rotulos));
    }

    private function ids(array $reas): array
    {
        return array_map(fn ($r) => is_array($r) ? $r['id'] : $r->id, $reas);
    }

    /**
     * REA como os jobs gravam: rótulo, grau e posição saem dos critérios.
     */
    private function rea(string $id, string $repositorio, int $posicao, string $nivel, string $tipo, ?string $meta = null): array
    {
        $criterios = [
            'tema' => ['status' => 'ok', 'repositorio' => $repositorio],
            'nivel' => ['status' => $nivel],
            'tipo' => ['status' => $tipo],
        ];
        if ($meta !== null) {
            $criterios['meta'] = ['status' => $meta];
        }
        $rotulo = RuleClassifier::rotular($criterios, $meta !== null);

        return [
            'id' => $id,
            'chave' => $id,
            'repositorio' => $repositorio,
            'recommended' => $rotulo,
            'explicacao' => RuleClassifier::explicacao($criterios, $rotulo, $posicao),
        ];
    }

    public function test_sem_meta_ordena_both_profile_interest_e_mostra_profile(): void
    {
        $reas = $this->reas('interest', 'profile', 'both', 'profile', 'meta_both');

        $this->assertSame([2, 1, 3, 0], $this->ids(Ranking::ordenar($reas, false)));
    }

    public function test_com_meta_mostra_meta_nao_conferida_abaixo_dos_compativeis(): void
    {
        $reas = $this->reas('meta', 'both', 'meta_one', 'meta_both', 'interest');

        $this->assertSame([3, 2, 0, 1, 4], $this->ids(Ranking::ordenar($reas, true)));
    }

    public function test_ordenacao_estavel_dentro_da_faixa(): void
    {
        $reas = $this->reas('both', 'interest', 'both', 'both');

        $this->assertSame([0, 2, 3, 1], $this->ids(Ranking::ordenar($reas, false)));
    }

    public function test_aceita_arrays(): void
    {
        $ordenado = Ranking::ordenar([['recommended' => 'interest'], ['recommended' => 'both']], false);

        $this->assertSame('both', $ordenado[0]['recommended']);
    }

    public function test_ordena_pelo_grau_e_nao_pelo_repositorio(): void
    {
        $reas = [
            $this->rea('mec', 'MECRED', 1, 'nao_avaliado', 'nao_avaliado'),
            $this->rea('edu', 'Eduplay', 1, 'falhou', 'ok'),
            $this->rea('aqu', 'Aquarela', 1, 'ok', 'ok'),
            $this->rea('aqu2', 'Aquarela', 2, 'ok', 'falhou'),
        ];

        $this->assertSame(['aqu', 'aqu2', 'edu', 'mec'], $this->ids(Ranking::ordenar($reas, false)));
    }

    public function test_tipo_atendido_fica_acima_de_so_tema_na_mesma_faixa(): void
    {
        $reas = [$this->rea('tema', 'Aquarela', 1, 'falhou', 'falhou'), $this->rea('tipo', 'Aquarela', 2, 'falhou', 'ok')];

        $this->assertSame('interest', $reas[0]['recommended']);
        $this->assertSame('interest', $reas[1]['recommended']);
        $this->assertSame(['tipo', 'tema'], $this->ids(Ranking::ordenar($reas, false)));
    }

    public function test_desempate_intercala_repositorios_pela_posicao(): void
    {
        $reas = [
            $this->rea('a1', 'Aquarela', 1, 'falhou', 'falhou'),
            $this->rea('a2', 'Aquarela', 2, 'falhou', 'falhou'),
            $this->rea('a3', 'Aquarela', 3, 'falhou', 'falhou'),
            $this->rea('m1', 'MECRED', 1, 'falhou', 'falhou'),
            $this->rea('m2', 'MECRED', 2, 'falhou', 'falhou'),
            $this->rea('e1', 'Eduplay', 1, 'falhou', 'falhou'),
        ];

        $this->assertSame(['a1', 'e1', 'm1', 'a2', 'm2', 'a3'], $this->ids(Ranking::ordenar($reas, false)));
    }

    public function test_ordem_de_chegada_dos_jobs_nao_influi(): void
    {
        $reas = [
            $this->rea('a1', 'Aquarela', 1, 'ok', 'falhou'),
            $this->rea('a2', 'Aquarela', 2, 'falhou', 'ok'),
            $this->rea('m1', 'MECRED', 1, 'falhou', 'nao_avaliado'),
            $this->rea('e1', 'Eduplay', 1, 'ok', 'ok'),
            $this->rea('e2', 'Eduplay', 2, 'falhou', 'ok'),
        ];
        $esperado = $this->ids(Ranking::ordenar($reas, false));

        $this->assertSame(['e1', 'a1', 'a2', 'e2', 'm1'], $esperado);
        $this->assertSame($esperado, $this->ids(Ranking::ordenar(array_reverse($reas), false)));
        $this->assertSame($esperado, $this->ids(Ranking::ordenar([$reas[3], $reas[2], $reas[0], $reas[4], $reas[1]], false)));
    }

    public function test_posicao_de_rea_antigo_vem_da_ordem_no_repositorio(): void
    {
        $reas = [
            (object) ['id' => 'a1', 'repositorio' => 'Aquarela', 'recommended' => 'interest'],
            (object) ['id' => 'a2', 'repositorio' => 'Aquarela', 'recommended' => 'interest'],
            (object) ['id' => 'm1', 'repositorio' => 'MECRED', 'recommended' => 'interest'],
        ];

        $this->assertSame(['a1', 'm1', 'a2'], $this->ids(Ranking::ordenar($reas, false)));
    }

    public function test_com_meta_so_oculta_meta_conferida_como_incompativel(): void
    {
        $reas = [
            $this->rea('incompativel', 'Aquarela', 1, 'ok', 'ok', 'falhou'),
            $this->rea('nao_conferida', 'MECRED', 1, 'falhou', 'nao_avaliado', 'nao_avaliado'),
            $this->rea('compativel', 'Aquarela', 2, 'falhou', 'falhou', 'ok'),
        ];

        $this->assertSame(['compativel', 'nao_conferida'], $this->ids(Ranking::ordenar($reas, true)));

        $contagem = Ranking::contar($reas, true);
        $this->assertSame(1, $contagem['ocultos']);
        $this->assertSame(1, $contagem['motivos_ocultos']['meta_incompativel']);
        $this->assertSame(1, $contagem['meta_nao_conferida']);
        $this->assertSame(1, $contagem['faixas']['meta']);
        $this->assertSame(1, $contagem['faixas']['interest']);
    }

    public function test_grau_de_vem_dos_criterios_e_rea_antigo_usa_a_faixa(): void
    {
        $this->assertSame(6, Ranking::grauDe($this->rea('x', 'Aquarela', 1, 'ok', 'falhou', 'ok')));
        $this->assertSame(3, Ranking::grauDe((object) ['recommended' => 'both']));
        $this->assertSame(0, Ranking::grauDe((object) ['recommended' => 'desconhecido']));
    }

    public function test_contar_faixas_e_ocultos(): void
    {
        $reas = $this->reas('both', 'profile', 'interest', 'interest', 'meta', 'desconhecido');

        $semMeta = Ranking::contar($reas, false);
        $this->assertSame(['both' => 1, 'profile' => 1, 'interest' => 2], $semMeta['faixas']);
        $this->assertSame(2, $semMeta['ocultos']);
        $this->assertSame(1, $semMeta['motivos_ocultos']['sem_meta_usuario']);
        $this->assertSame(1, $semMeta['motivos_ocultos']['outros']);
        $this->assertSame(0, $semMeta['meta_nao_conferida']);

        $comMeta = Ranking::contar($reas, true);
        $this->assertSame(['meta_both' => 0, 'meta_one' => 0, 'meta' => 1, 'both' => 1, 'profile' => 1, 'interest' => 2], $comMeta['faixas']);
        $this->assertSame(1, $comMeta['ocultos']);
        $this->assertSame(4, $comMeta['meta_nao_conferida']);
    }

    public function test_ocultos_com_meta_so_por_incompatibilidade(): void
    {
        $item = fn ($status) => (object) ['recommended' => 'both', 'explicacao' => (object) ['criterios' => (object) ['meta' => (object) ['status' => $status]]]];

        $contagem = Ranking::contar([$item('nao_avaliado'), $item('nao_avaliado'), $item('falhou')], true);

        $this->assertSame(1, $contagem['ocultos']);
        $this->assertSame(1, $contagem['motivos_ocultos']['meta_incompativel']);
        $this->assertSame(2, $contagem['meta_nao_conferida']);
    }

    public function test_lista_vazia(): void
    {
        $this->assertSame([], Ranking::ordenar([], false));
    }

    private function reaComMeta(?string $classificacao): array
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema('grafos', 'Aquarela'),
            'nivel' => RuleClassifier::criterioNivel('Ensino fundamental', RuleClassifier::inferirNivel('Grafos', '')),
            'tipo' => RuleClassifier::criterioTipo('Jogo', ['video']),
            'meta' => RuleClassifier::criterioMeta('ma', $classificacao),
        ];
        $rotulo = RuleClassifier::rotular($criterios, true);

        return ['recommended' => $rotulo, 'explicacao' => RuleClassifier::explicacao($criterios, $rotulo, 1)];
    }

    public function test_oculto_com_meta_corrigida_passa_a_aparecer(): void
    {
        $oculto = $this->reaComMeta('Performance Evitação');
        $this->assertSame([], Ranking::ordenar([$oculto], true));

        $corrigido = UserCorrections::corrigirMeta($oculto, 'ma');

        $this->assertSame([$corrigido], Ranking::ordenar([$corrigido], true));
        $this->assertSame(4, $corrigido['explicacao']['grau']['total']);
        $this->assertSame(1, $corrigido['explicacao']['grau']['posicao']);
    }

    public function test_correcao_de_meta_nao_conferida_sobe_o_rea(): void
    {
        $naoConferido = $this->reaComMeta(null);
        $outro = ['chave' => 'z'] + $this->reaComMeta('Aprendizagem');

        $this->assertSame([$outro, $naoConferido], Ranking::ordenar([$naoConferido, $outro], true));

        $corrigido = UserCorrections::corrigirNivel(UserCorrections::corrigirMeta($naoConferido, 'ma'), 'ensino fundamental');

        $this->assertSame(6, Ranking::grauDe($corrigido));
        $this->assertSame([$corrigido, $outro], Ranking::ordenar([$outro, $corrigido], true));
    }

    public function test_meta_marcada_como_incompativel_pelo_usuario_tem_motivo_proprio(): void
    {
        $corrigido = UserCorrections::corrigirMeta($this->reaComMeta('Aprendizagem'), 'mpe');

        $this->assertSame('meta_corrigida_incompativel', Ranking::motivo($corrigido, true));
        $this->assertSame('meta_corrigida_incompativel', Ranking::motivo(json_decode(json_encode($corrigido)), true));
        $this->assertSame(1, Ranking::contar([$corrigido], true)['motivos_ocultos']['meta_corrigida_incompativel']);
    }

    public function test_contar_correcoes_e_mudancas_de_faixa(): void
    {
        $semCorrecao = $this->reaComMeta('Aprendizagem');
        $subiu = UserCorrections::corrigirMeta($this->reaComMeta(null), 'ma');
        $mesmaFaixa = UserCorrections::corrigirNivel($this->reaComMeta('Aprendizagem'), 'ensino medio');

        $contagem = Ranking::contar(json_decode(json_encode([$semCorrecao, $subiu, $mesmaFaixa])), true);

        $this->assertSame(2, $contagem['corrigidos']);
        $this->assertSame(1, $contagem['mudaram_faixa']);
    }
}
