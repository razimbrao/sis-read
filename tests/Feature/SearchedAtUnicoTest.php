<?php

namespace Tests\Feature;

use App\Jobs\ProcessAquarela;
use App\Livewire\FindREA;
use App\Models\Collaborator;
use App\Models\Data;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Duas buscas no mesmo segundo não se misturam: `searched_at` é a chave que liga a busca aos jobs.
 */
class SearchedAtUnicoTest extends TestCase
{
    use RefreshDatabase;

    private function buscar(User $user): Testable
    {
        return Livewire::actingAs($user)
            ->test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino fundamental')
            ->set('interest', 'Algoritmos')
            ->call('search');
    }

    /**
     * Horário que a busca vai gravar (em UTC), com o relógio do teste congelado.
     */
    private function congelarRelogio(): string
    {
        Carbon::setTestNow('2026-11-15 12:00:00');

        return now()->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Collaborator::create([
            'name' => 'C', 'role' => 'Professor', 'institution' => 'UF', 'reference' => 'ref-1',
            'rea_title' => 'T', 'interest' => 'algoritmos', 'profile' => 'ensino fundamental', 'item' => 'Vídeo',
        ]);
    }

    public function test_busca_no_mesmo_segundo_ganha_mais_um_segundo(): void
    {
        Queue::fake();
        $agora = $this->congelarRelogio();

        // Outra busca (de outro participante) já ocupa o segundo.
        Data::create(['searched_at' => $agora, 'data' => '[]', 'participante' => 'u:999']);

        $componente = $this->buscar(User::factory()->create());

        $mais1 = Carbon::parse($agora)->addSecond()->format('Y-m-d H:i:s');
        $this->assertSame($mais1, Data::find($componente->get('busca'))->getRawOriginal('searched_at'));
        $this->assertSame(2, Data::count());

        // Os jobs saem com o horário final, o que liga os resultados a esta busca.
        Queue::assertPushed(ProcessAquarela::class, fn ($job) => (string) $job->time === $mais1);
    }

    public function test_desiste_depois_de_cinco_tentativas(): void
    {
        $agora = $this->congelarRelogio();

        foreach (range(0, 4) as $segundo) {
            Data::create(['searched_at' => Carbon::parse($agora)->addSeconds($segundo), 'data' => '[]']);
        }

        $this->expectException(UniqueConstraintViolationException::class);

        $this->buscar(User::factory()->create());
    }
}
