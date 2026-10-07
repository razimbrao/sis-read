<?php

namespace Tests\Feature;

use App\Experimento\Experimento;
use App\Livewire\FindREA;
use App\Models\Data;
use App\Models\Searches;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Comportamentos de tela do redesign: busca direta, menu por link, busca travada, privacidade e erros.
 */
class RedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_abre_direto_na_busca_com_as_quatro_etapas(): void
    {
        $tela = $this->get('/')->assertOk();

        foreach (FindREA::ETAPAS as $etapa) {
            $tela->assertSee('value="'.$etapa.'"', false);
        }

        $tela->assertSee('Buscar recursos')->assertDontSee('Você é usuário ou colaborador?');
    }

    public function test_menu_abre_colaborar_e_sugestao_pelo_link(): void
    {
        $this->get('/?secao=colaborar')->assertOk()->assertSee('Contribuir com um REA')->assertSee('Enviar contribuição');
        $this->get('/?secao=sugestao')->assertOk()->assertSee('Deixe seu feedback');
    }

    public function test_busca_frequente_so_com_etapa_e_tema_conhecidos(): void
    {
        Searches::create(['profile' => 'ensino medio', 'interest' => 'algoritmos']);
        Searches::create(['profile' => 'ensino medio', 'interest' => 'algoritmos']);
        Searches::create(['profile' => 'qualquer coisa', 'interest' => 'algoritmos']);

        $frequentes = Livewire::test(FindREA::class)->instance()->buscasFrequentes();

        $this->assertSame([['perfil' => 'Ensino médio', 'interesse' => 'algoritmos', 'total' => 2]], $frequentes);
    }

    public function test_busca_travada_aparece_em_todos_os_grupos_e_pode_ser_refeita(): void
    {
        Queue::fake();
        $inicio = Carbon::parse('2026-10-06 12:00:00', 'UTC');
        Carbon::setTestNow($inicio->copy()->addMinutes(FindREA::MINUTOS_BUSCA_TRAVADA + 1));
        Data::create(['searched_at' => $inicio, 'data' => json_encode([]), 'finished' => false, 'time' => 0]);
        DB::table('search_metrics')->insert([
            'searched_at' => $inicio, 'repository' => 'Aquarela', 'profile' => 'p', 'interest' => 'i',
            'total_time' => 1, 'api_time' => 1, 'api_calls' => 1, 'items_returned' => 0, 'items_filtered' => 0,
            'timeouts_errors' => 0, 'breakdown' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['controle', 'escrutabilidade'] as $grupo) {
            $user = User::factory()->create();
            app(Experimento::class)->fixar($grupo, $user);

            $componente = Livewire::actingAs($user)->test(FindREA::class)
                ->set('interestApiSearch', 'algoritmos')
                ->set('timestampSession', $inicio)
                ->set('contexto', ['perfil' => 'Ensino médio', 'interesse' => 'algoritmos', 'termo_api' => 'algoritmos', 'meta' => null])
                ->assertSee('A busca não terminou')
                ->assertSee('Buscar de novo');
        }

        $componente->call('refazerBusca');
        $this->assertSame(2, Data::count());
        Carbon::setTestNow();
    }

    public function test_busca_recente_nao_aparece_como_travada(): void
    {
        $repos = ['Aquarela' => ['situacao' => 'aguardando', 'itens' => 0]];

        $componente = Livewire::test(FindREA::class)->set('timestampSession', now()->subMinute());

        $this->assertFalse($componente->instance()->buscaTravada($repos));
    }

    public function test_pagina_de_privacidade(): void
    {
        $this->get(route('privacidade'))->assertOk()->assertSee('O que guardamos')->assertSee('Nenhum dado seu é enviado');
    }

    public function test_paginas_de_erro_em_portugues(): void
    {
        $this->get('/nao-existe')->assertNotFound()->assertSee('Página não encontrada')->assertSee('Ir para o início');
    }
}
