<?php

namespace Tests\Feature;

use App\Experimento\Experimento;
use App\Livewire\FindREA;
use App\Models\Data;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Números decimais para o usuário usam vírgula (padrão pt-BR), como no painel de médias do EMAPRE.
 */
class MediasComVirgulaTest extends TestCase
{
    use RefreshDatabase;

    public function test_painel_mostra_as_medias_com_virgula(): void
    {
        Data::create([
            'searched_at' => '2026-11-15 12:00:00', 'data' => json_encode([]), 'finished' => true, 'time' => 2, 'grupo' => 'transparencia',
        ]);

        $user = User::factory()->create();
        app(Experimento::class)->fixar('transparencia', $user);

        Livewire::actingAs($user)->test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('interestApiSearch', 'algoritmos')
            ->set('timestampSession', '2026-11-15 12:00:00')
            ->set('contexto', [
                'perfil' => 'Ensino fundamental', 'interesse' => 'Algoritmos', 'termo_api' => 'algoritmos',
                'tipos_busca' => [], 'tipos_gerais' => [], 'tipos_conta' => [], 'origem_tipos' => 'colaboradores',
                'meta' => ['dominante' => 'ma', 'ma' => 4.67, 'mpa' => 3.0, 'mpe' => 2.5],
            ])
            ->assertSee('>4,7<', false)
            ->assertSee('>3,0<', false)
            ->assertSee('>2,5<', false)
            ->assertDontSee('>4.67<', false);
    }
}
