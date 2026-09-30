<?php

namespace Tests\Unit;

use App\Recommendation\Ranking;
use PHPUnit\Framework\TestCase;

class RankingTest extends TestCase
{
    private function reas(string ...$rotulos): array
    {
        return array_map(fn ($r, $i) => (object) ['recommended' => $r, 'id' => $i], $rotulos, array_keys($rotulos));
    }

    private function ids(array $reas): array
    {
        return array_map(fn ($r) => $r->id, $reas);
    }

    public function test_sem_meta_ordena_both_profile_interest_e_mostra_profile(): void
    {
        $reas = $this->reas('interest', 'profile', 'both', 'profile', 'meta_both');

        $this->assertSame([2, 1, 3, 0], $this->ids(Ranking::ordenar($reas, false)));
    }

    public function test_com_meta_nao_confunde_meta_both_com_interest(): void
    {
        $reas = $this->reas('meta', 'both', 'meta_one', 'meta_both', 'interest');

        $this->assertSame([3, 2, 0], $this->ids(Ranking::ordenar($reas, true)));
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

    public function test_contar_faixas_e_ocultos(): void
    {
        $reas = $this->reas('both', 'profile', 'interest', 'interest', 'meta', 'desconhecido');

        $semMeta = Ranking::contar($reas, false);
        $this->assertSame(['both' => 1, 'profile' => 1, 'interest' => 2], $semMeta['faixas']);
        $this->assertSame(2, $semMeta['ocultos']);
        $this->assertSame(1, $semMeta['motivos_ocultos']['sem_meta_usuario']);
        $this->assertSame(1, $semMeta['motivos_ocultos']['outros']);

        $comMeta = Ranking::contar($reas, true);
        $this->assertSame(['meta_both' => 0, 'meta_one' => 0, 'meta' => 1], $comMeta['faixas']);
        $this->assertSame(5, $comMeta['ocultos']);
    }

    public function test_ocultos_distinguem_meta_nao_avaliada_de_incompativel(): void
    {
        $item = fn ($status) => (object) ['recommended' => 'both', 'explicacao' => (object) ['criterios' => (object) ['meta' => (object) ['status' => $status]]]];

        $motivos = Ranking::contar([$item('nao_avaliado'), $item('nao_avaliado'), $item('falhou')], true)['motivos_ocultos'];

        $this->assertSame(2, $motivos['meta_nao_avaliada']);
        $this->assertSame(1, $motivos['meta_incompativel']);
    }

    public function test_lista_vazia(): void
    {
        $this->assertSame([], Ranking::ordenar([], false));
    }
}
