<?php

namespace Tests\Feature;

use App\Livewire\FindREA;
use App\Models\Collaborator;
use App\Models\Data;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A busca volta pela URL (?busca=<id>) depois de um F5, e só para quem a fez.
 */
class RestaurarBuscaTest extends TestCase
{
    use RefreshDatabase;

    private function colaborador(): void
    {
        Collaborator::create([
            'name' => 'C', 'role' => 'Professor', 'institution' => 'UF', 'reference' => 'ref-1',
            'rea_title' => 'Título do colaborador', 'interest' => 'algoritmos', 'profile' => 'ensino fundamental', 'item' => 'Vídeo',
        ]);
    }

    /**
     * Faz uma busca como o participante e devolve o id da linha `data` que ela criou.
     */
    private function buscar(User $user): int
    {
        $componente = Livewire::actingAs($user)
            ->test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino fundamental')
            ->set('interest', 'Algoritmos')
            ->call('search');

        return $componente->get('busca');
    }

    public function test_busca_grava_o_contexto_e_o_id_na_linha(): void
    {
        Queue::fake();
        $this->colaborador();
        $user = User::factory()->create();

        $id = $this->buscar($user);

        $data = Data::find($id);
        $this->assertSame('algoritmos', $data->contexto['termo_api']);
        $this->assertSame('Ensino fundamental', $data->contexto['perfil']);
        $this->assertSame('u:'.$user->id, $data->participante);
    }

    public function test_mesmo_participante_restaura_a_busca_pela_url(): void
    {
        Queue::fake();
        $this->colaborador();
        $user = User::factory()->create();
        $id = $this->buscar($user);
        $searchedAt = Data::find($id)->getRawOriginal('searched_at');

        // Nova requisição com a URL ?busca=<id>, como depois de um F5.
        $componente = Livewire::withQueryParams(['busca' => $id])
            ->actingAs($user)
            ->test(FindREA::class);

        $componente->assertSet('busca', $id)
            ->assertSet('interestApiSearch', 'algoritmos')
            ->assertSet('timestampSession', $searchedAt)
            ->assertSet('contexto.perfil', 'Ensino fundamental');

        $this->assertSame($id, $componente->instance()->data->id);
        $this->assertCount(1, $componente->get('sheet'));
    }

    public function test_outro_participante_nao_restaura_a_busca(): void
    {
        Queue::fake();
        $this->colaborador();
        $id = $this->buscar(User::factory()->create());

        $componente = Livewire::withQueryParams(['busca' => $id])
            ->actingAs(User::factory()->create())
            ->test(FindREA::class);

        $componente->assertSet('busca', null)
            ->assertSet('interestApiSearch', '')
            ->assertSet('timestampSession', null)
            ->assertSet('contexto', [])
            ->assertSet('sheet', []);
    }

    public function test_visitante_nao_restaura_busca_de_quem_tem_conta(): void
    {
        // Linha de uma conta; a requisição não tem login (o visitante tem outro participante).
        $dono = User::factory()->create();
        $id = Data::create([
            'searched_at' => '2026-11-15 12:00:00', 'participante' => 'u:'.$dono->id, 'data' => '[]',
            'contexto' => ['perfil' => 'Ensino fundamental', 'interesse' => 'Algoritmos', 'termo_api' => 'algoritmos'],
        ])->id;

        $componente = Livewire::withQueryParams(['busca' => $id])->test(FindREA::class);

        $componente->assertSet('busca', null)->assertSet('contexto', []);
    }

    public function test_id_inexistente_deixa_a_tela_vazia(): void
    {
        $componente = Livewire::withQueryParams(['busca' => 999])
            ->actingAs(User::factory()->create())
            ->test(FindREA::class);

        $componente->assertSet('busca', null)->assertSet('contexto', []);
    }
}
