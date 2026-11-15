<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\Data;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Aquecimento do cache da IA: as linhas da busca de aquecimento são apagadas no fim de cada combinação.
 */
class ExperimentoAquecerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem REAs: os jobs saem sem chamar a IA, e o teste não depende das APIs externas.
        Http::fake();

        Collaborator::create([
            'name' => 'C', 'role' => 'Professor', 'institution' => 'UF', 'reference' => 'ref-1',
            'rea_title' => 'T', 'interest' => 'algoritmos', 'profile' => 'ensino fundamental', 'item' => 'Vídeo',
        ]);
    }

    public function test_roda_cada_etapa_e_apaga_as_linhas_no_fim(): void
    {
        $this->artisan('experimento:aquecer', ['--temas' => 'Algoritmos'])
            ->expectsOutputToContain('Aquecendo: Algoritmos | Ensino fundamental')
            ->expectsOutputToContain('Aquecendo: Algoritmos | Ensino superior')
            ->assertSuccessful();

        $this->assertSame(0, Data::count());
        $this->assertSame(0, DB::table('search_metrics')->count());
    }

    public function test_tema_sem_termo_conhecido_e_pulado(): void
    {
        $this->artisan('experimento:aquecer', ['--temas' => 'Tema inventado'])
            ->expectsOutputToContain('Tema sem termo de API conhecido, pulado: Tema inventado')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, Data::count());
    }
}
