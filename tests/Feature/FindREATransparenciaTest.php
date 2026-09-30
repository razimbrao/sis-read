<?php

namespace Tests\Feature;

use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessMecRed;
use App\Livewire\FindREA;
use App\Models\Collaborator;
use App\Models\Data;
use App\Models\ExplanationEvent;
use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class FindREATransparenciaTest extends TestCase
{
    use RefreshDatabase;

    private function colaborador(string $interest, string $profile, string $item): void
    {
        Collaborator::create([
            'name' => 'C', 'role' => 'Professor', 'institution' => 'UF', 'reference' => 'r',
            'rea_title' => 'T', 'interest' => $interest, 'profile' => $profile, 'item' => $item,
        ]);
    }

    public function test_busca_monta_contexto_e_envia_tipos_normalizados(): void
    {
        Queue::fake();
        $this->colaborador('algoritmos', 'ensino fundamental', 'Vídeo');
        $this->colaborador('abstracao', 'ensino medio', 'E-book');

        $componente = Livewire::test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino fundamental')
            ->set('interest', 'Algoritmos')
            ->call('search');

        $contexto = $componente->get('contexto');
        $this->assertSame('Ensino fundamental', $contexto['perfil']);
        $this->assertSame('algoritmos', $contexto['termo_api']);
        $this->assertSame(['video'], $contexto['tipos_busca']);
        $this->assertSame(['e-book', 'livro digital'], $contexto['tipos_gerais']);
        $this->assertNull($contexto['meta']);

        Queue::assertPushed(ProcessAquarela::class, fn ($job) => $job->types === ['video', 'e-book', 'livro digital']);
        Queue::assertPushed(ProcessMecRed::class);
    }

    public function test_contexto_inclui_medias_emapre(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $questionnaire = new Questionnaire(['ma' => 4.25, 'mpa' => 3, 'mpe' => 2.123, 'dominant' => 'ma']);
        $questionnaire->user_id = $user->id;
        $questionnaire->save();

        $contexto = Livewire::actingAs($user)->test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino superior')
            ->set('interest', 'abstração')
            ->call('search')
            ->get('contexto');

        $this->assertSame(['dominante' => 'ma', 'ma' => 4.25, 'mpa' => 3.0, 'mpe' => 2.12], $contexto['meta']);
    }

    public function test_paginate_e_resumo_de_ordenacao_sem_meta(): void
    {
        $data = Data::create([
            'searched_at' => '2026-09-18 12:00:00',
            'data' => json_encode([
                ['title' => 'A', 'recommended' => 'interest'],
                ['title' => 'B', 'recommended' => 'profile'],
                ['title' => 'C', 'recommended' => 'both'],
                ['title' => 'D', 'recommended' => 'meta_both'],
            ]),
        ]);

        $componente = Livewire::test(FindREA::class)->instance();

        $titulos = collect($componente->paginate($data)->items())->pluck('title')->all();
        $this->assertSame(['C', 'B', 'A'], $titulos);

        $resumo = $componente->resumoOrdenacao($data);
        $this->assertSame(['both' => 1, 'profile' => 1, 'interest' => 1], $resumo['faixas']);
        $this->assertSame(1, $resumo['ocultos']);
        $this->assertFalse($resumo['com_meta']);
    }

    public function test_status_dos_repositorios_mostra_falhas(): void
    {
        $data = Data::create(['searched_at' => '2026-09-18 12:00:00']);
        $metrica = fn ($repo, $itens, $erros) => DB::table('search_metrics')->insert([
            'searched_at' => '2026-09-18 12:00:00', 'repository' => $repo, 'profile' => 'p', 'interest' => 'i',
            'total_time' => 1, 'api_time' => 1, 'api_calls' => 1, 'items_returned' => $itens, 'items_filtered' => $itens,
            'timeouts_errors' => $erros, 'breakdown' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $metrica('Aquarela', 0, 1);
        $metrica('MecRed', 10, 0);

        $status = Livewire::test(FindREA::class)->instance()->statusRepositorios($data->fresh());

        $this->assertSame('falhou', $status['Aquarela']['situacao']);
        $this->assertSame(['situacao' => 'ok', 'itens' => 10], $status['MEC RED']);
        $this->assertSame('aguardando', $status['Eduplay']['situacao']);
    }

    public function test_registrar_explicacao_grava_evento(): void
    {
        Livewire::test(FindREA::class)
            ->call('registrarExplicacao', 'abriu_explicacao', 'Aquarela', 'Algoritmos no sexto ano', 'both')
            ->call('registrarExplicacao', 'abriu_ordenacao');

        $this->assertDatabaseHas('explanation_events', [
            'acao' => 'abriu_explicacao', 'repositorio' => 'Aquarela', 'titulo' => 'Algoritmos no sexto ano', 'faixa' => 'both',
        ]);
        $this->assertDatabaseHas('explanation_events', ['acao' => 'abriu_ordenacao', 'repositorio' => null]);
    }

    public function test_registrar_explicacao_rejeita_acao_desconhecida(): void
    {
        Livewire::test(FindREA::class)->call('registrarExplicacao', 'qualquer_coisa');

        $this->assertSame(0, ExplanationEvent::count());
    }

    public function test_motivos_de_feedback_sobre_explicacao_existem(): void
    {
        $this->assertDatabaseHas('feedback_reasons', ['phrase' => 'As explicações das recomendações estavam erradas ou confusas.']);
    }

    public function test_tela_mostra_explicacoes_e_paineis(): void
    {
        Queue::fake();

        $componente = Livewire::test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino fundamental')
            ->set('interest', 'Algoritmos')
            ->call('search');

        $data = Data::first();
        $data->update(['finished' => true, 'time' => 2, 'data' => json_encode([[
            'title' => 'Algoritmos no sexto ano', 'type' => 'Vídeo', 'repositorio' => 'Aquarela', 'recommended' => 'both',
            'interatividade' => 'Ativo', 'nivel_interatividade' => 'Alto', 'estilo_aprendizagem' => 'x', 'estrategia' => 'y',
            'fonte_interatividade' => 'dtype', 'link' => 'http://a',
            'explicacao' => [
                'versao_regras' => 1, 'faixa' => 'both', 'observacao' => null,
                'criterios' => [
                    'tema' => ['status' => 'ok', 'valor' => 'algoritmos', 'fonte' => 'busca', 'repositorio' => 'Aquarela'],
                    'nivel' => ['status' => 'ok', 'valor' => 'ensino fundamental', 'esperado' => 'ensino fundamental', 'fonte' => 'regex', 'evidencia' => 'sexto ano', 'assumido' => false],
                ],
            ],
        ], [
            'title' => 'Item antigo', 'type' => '', 'repositorio' => 'Aquarela', 'recommended' => 'interest',
            'interatividade' => '', 'nivel_interatividade' => '', 'estilo_aprendizagem' => '', 'estrategia' => '',
        ]])]);

        $componente->call('$refresh')
            ->assertSee('Como ordenamos estes resultados')
            ->assertSee('O que usamos sobre você')
            ->assertSee('Por que este REA?')
            ->assertSee('identificado pelo trecho “sexto ano”', false)
            ->assertSee('Explicação indisponível para esta busca.')
            ->assertSee('Repositórios consultados')
            ->assertSee('Derivado do tipo de interatividade (dtype) informado pelo repositório.');
    }
}
