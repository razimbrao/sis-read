<?php

namespace Tests\Feature;

use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessEduplay;
use App\Jobs\ProcessMecRed;
use App\Livewire\FindREA;
use App\Models\Collaborator;
use App\Models\Data;
use App\Models\ExplanationEvent;
use App\Models\Questionnaire;
use App\Models\User;
use App\Recommendation\RuleClassifier;
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
        Queue::assertPushed(ProcessEduplay::class, fn ($job) => $job->types === ['video', 'e-book', 'livro digital']);
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

    /**
     * REA como os jobs gravam, com os status de nível, tipo e meta dados.
     */
    private function reaComGrau(string $titulo, string $repositorio, int $posicao, string $nivel, string $tipo, ?string $meta): array
    {
        $criterios = [
            'tema' => ['status' => 'ok', 'valor' => 'algoritmos', 'fonte' => 'busca', 'repositorio' => $repositorio],
            'nivel' => ['status' => $nivel, 'valor' => 'ensino fundamental', 'esperado' => 'ensino fundamental', 'fonte' => 'regex', 'evidencia' => '6º', 'assumido' => false],
            'tipo' => ['status' => $tipo, 'valor' => 'video', 'esperado' => ['video'], 'fonte' => 'colaboradores'],
        ];
        if ($meta !== null) {
            $criterios['meta'] = ['status' => $meta, 'valor' => $meta === 'ok' ? 'Aprendizagem' : null, 'esperado' => 'ma', 'fonte' => 'llm'];
        }
        $rotulo = RuleClassifier::rotular($criterios, $meta !== null);

        return [
            'chave' => RuleClassifier::chave($repositorio, null, $titulo), 'title' => $titulo, 'type' => 'Vídeo', 'link' => 'http://'.$titulo,
            'repositorio' => $repositorio, 'recommended' => $rotulo, 'explicacao' => RuleClassifier::explicacao($criterios, $rotulo, $posicao),
            'fonte_interatividade' => 'indisponivel', 'interatividade' => '', 'nivel_interatividade' => '', 'estilo_aprendizagem' => '', 'estrategia' => '',
        ];
    }

    public function test_lista_com_meta_ordena_pelo_grau_e_mistura_os_repositorios(): void
    {
        $user = User::factory()->create();
        $questionnaire = new Questionnaire(['ma' => 4, 'mpa' => 3, 'mpe' => 2, 'dominant' => 'ma']);
        $questionnaire->user_id = $user->id;
        $questionnaire->save();

        // Ordem de chegada: MEC RED primeiro, como quando ele responde antes dos outros.
        $data = Data::create(['searched_at' => '2026-10-06 12:00:00', 'finished' => true, 'data' => json_encode([
            $this->reaComGrau('mec', 'MECRED', 1, 'nao_avaliado', 'nao_avaliado', 'nao_avaliado'),
            $this->reaComGrau('edu', 'Eduplay', 1, 'ok', 'ok', 'nao_avaliado'),
            $this->reaComGrau('aqu-tipo', 'Aquarela', 1, 'falhou', 'ok', 'ok'),
            $this->reaComGrau('aqu-tudo', 'Aquarela', 2, 'ok', 'ok', 'ok'),
            $this->reaComGrau('aqu-incompativel', 'Aquarela', 3, 'ok', 'ok', 'falhou'),
        ])]);

        $componente = Livewire::actingAs($user)->test(FindREA::class);
        $instancia = $componente->instance();

        $titulos = collect($instancia->paginate($data)->items())->pluck('title')->all();
        // Compatíveis, depois meta não conferida, e no fim a meta diferente (mesmo com grau 3).
        $this->assertSame(['aqu-tudo', 'aqu-tipo', 'edu', 'mec', 'aqu-incompativel'], $titulos);

        $resumo = $instancia->resumoOrdenacao($data);
        $this->assertSame(0, $resumo['ocultos']);
        $this->assertSame(1, $resumo['meta_incompativel']);
        $this->assertSame(2, $resumo['meta_nao_conferida']);
        $this->assertSame([], $instancia->ocultos($data));

        $componente->set('timestampSession', '2026-10-06 12:00:00')->set('userType', 'usuario')->set('interestApiSearch', 'algoritmos')
            ->assertSee('grau de recomendação')
            ->assertSee('meta +4, nível +2, tipo +1, de 0 a 7')
            ->assertSee('Grau 7 de 7 · Meta, nível e tipo')
            ->assertSee('Grau 3 de 7 · Nível e tipo, meta não conferida')
            ->assertSee('Grau 3 de 7 · Nível e tipo, meta diferente da sua')
            ->assertSee('2 REAs estão com a meta não conferida.')
            ->assertSee('1 REA está com a meta diferente da sua.')
            ->assertSee('este é o 2º resultado do Aquarela')
            ->assertDontSee('Por política do SisREAd');
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

    public function test_interesse_desconhecido_avisa_em_vez_de_buscar(): void
    {
        Queue::fake();

        $componente = Livewire::test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino médio')
            ->set('interest', 'fotossíntese')
            ->call('search');

        $componente->assertHasErrors('interest');
        $this->assertStringContainsString('algoritmos', $componente->errors()->first('interest'));
        Queue::assertNothingPushed();
        $this->assertSame(0, Data::count());
    }

    public function test_temas_novos_de_pensamento_computacional_sao_aceitos(): void
    {
        Queue::fake();

        Livewire::test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino médio')
            ->set('interest', 'inteligência artificial')
            ->call('search')
            ->assertHasNoErrors()
            ->assertSet('interestApiSearch', 'inteligência artificial');

        Queue::assertPushed(\App\Jobs\ProcessEduplay::class);
    }

    public function test_dropdown_agrupa_temas_fixos_e_de_colaboradores(): void
    {
        $this->colaborador('jogos educativos', 'ensino médio', 'video');

        $grupos = Livewire::test(FindREA::class)->instance()->temasAgrupados();

        $this->assertArrayHasKey('scratch', $grupos['Outros temas']);
        $this->assertSame(['jogos educativos' => 'Jogos educativos'], $grupos['Cadastrados por colaboradores']);
        // Tema de colaborador igual a um fixo não se repete.
        $this->colaborador('Algoritmos', 'ensino médio', 'video');
        $grupos = Livewire::test(FindREA::class)->instance()->temasAgrupados();
        $this->assertArrayNotHasKey('Algoritmos', $grupos['Cadastrados por colaboradores']);
    }

    public function test_interesse_de_colaborador_e_aceito(): void
    {
        Queue::fake();
        $this->colaborador('seguranca da informacao', 'ensino medio', 'Vídeo');

        Livewire::test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino médio')
            ->set('interest', 'Segurança da informação')
            ->call('search')
            ->assertHasNoErrors();

        Queue::assertPushed(ProcessAquarela::class);
    }

    public function test_ocultos_listam_titulo_e_motivo(): void
    {
        $data = Data::create([
            'searched_at' => '2026-09-30 12:00:00',
            'data' => json_encode([
                ['title' => 'Visível', 'recommended' => 'both', 'repositorio' => 'Aquarela'],
                ['title' => 'Escondido', 'recommended' => 'meta_both', 'repositorio' => 'MECRED'],
            ]),
        ]);

        $ocultos = Livewire::test(FindREA::class)->instance()->ocultos($data);

        $this->assertCount(1, $ocultos);
        $this->assertSame('Escondido', $ocultos[0]['titulo']);
        $this->assertSame('sem_meta_usuario', $ocultos[0]['motivo']);
    }

    public function test_ocultos_respeitam_o_limite(): void
    {
        $data = Data::create([
            'searched_at' => '2026-09-30 12:00:00',
            'data' => json_encode(array_fill(0, 30, ['title' => 'X', 'recommended' => 'meta_both'])),
        ]);

        $this->assertCount(3, Livewire::test(FindREA::class)->instance()->ocultos($data, 3));
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
            // "Item antigo" não tem link: o botão aparece desabilitado, com a explicação no tooltip.
            ->assertSee('role="tooltip"', false)
            ->assertSee('O repositório não informou o link deste recurso.')
            ->assertDontSee('Derivado do tipo de interatividade (dtype) informado pelo repositório.');
    }

    public function test_paginacao_numerada_e_nova_busca_volta_a_primeira_pagina(): void
    {
        Queue::fake();

        $componente = Livewire::test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino fundamental')
            ->set('interest', 'Algoritmos')
            ->call('search');

        $itens = array_map(fn ($i) => [
            'title' => "REA $i", 'type' => '', 'repositorio' => 'Aquarela', 'recommended' => 'interest',
            'interatividade' => '', 'nivel_interatividade' => '', 'estilo_aprendizagem' => '', 'estrategia' => '',
        ], range(1, 75));
        Data::first()->update(['finished' => true, 'time' => 2, 'data' => json_encode($itens)]);

        $componente->call('irParaPagina', 5)
            ->assertSet('page', 5)
            ->assertSee('41–50', false)
            ->assertSee('irParaPagina(8)', false)
            ->call('irParaPagina', 0)
            ->assertSet('page', 1)
            ->call('irParaPagina', 4)
            ->set('profile', 'Ensino fundamental')
            ->set('interest', 'Algoritmos')
            ->call('search')
            ->assertSet('page', 1);
    }
}
