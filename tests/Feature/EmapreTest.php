<?php

namespace Tests\Feature;

use App\Livewire\Emapre;
use App\Livewire\Preferencias;
use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmapreTest extends TestCase
{
    use RefreshDatabase;

    /** Respostas com o valor `$alto` nos itens do fator e 1 nos demais. */
    private function respostas(array $itensAltos, int $alto = 5): array
    {
        $respostas = [];
        for ($i = 1; $i <= 28; $i++) {
            $respostas[$i] = in_array($i, $itensAltos, true) ? $alto : 1;
        }

        return $respostas;
    }

    public function test_refazer_o_questionario_substitui_a_resposta_anterior(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Emapre::class)
            ->set('responses', $this->respostas(range(1, 12)))
            ->call('submit');

        $this->assertSame('ma', $user->fresh()->questionnaire->dominant);

        Livewire::actingAs($user)->test(Emapre::class)
            ->set('responses', $this->respostas(range(22, 28)))
            ->call('submit');

        $this->assertSame(1, Questionnaire::where('user_id', $user->id)->count());
        $this->assertSame('mpe', $user->fresh()->questionnaire->dominant);
    }

    public function test_preferencias_oferece_refazer_quando_ja_respondeu(): void
    {
        $user = User::factory()->create();
        $user->questionnaire()->create(['ma' => 4, 'mpa' => 2, 'mpe' => 1, 'dominant' => 'ma']);

        Livewire::actingAs($user)->test(Preferencias::class)
            ->assertSee('Refazer o questionário');
    }
}
