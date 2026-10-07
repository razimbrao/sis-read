<?php

namespace Tests\Feature;

use App\Experimento\Experimento;
use App\Livewire\FindREA;
use App\Models\Correction;
use App\Models\Data;
use App\Models\ExplanationEvent;
use App\Models\Feedback;
use App\Models\FeedbackReason;
use App\Models\Questionnaire;
use App\Models\User;
use App\Recommendation\RuleClassifier;
use Database\Seeders\FeedbackReasonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Flags de explicabilidade e grupos do experimento (docs/feature-flags.md).
 */
class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    private string $searchedAt = '2026-10-06 12:00:00';

    private function aquarela(string $titulo, string $tipo, ?string $meta = null, ?string $classificacao = null): array
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema('algoritmos', 'Aquarela'),
            'nivel' => RuleClassifier::criterioNivel('Ensino fundamental', RuleClassifier::inferirNivel($titulo, '')),
            'tipo' => RuleClassifier::criterioTipo($tipo, ['video']),
        ];

        if ($meta) {
            $criterios['meta'] = RuleClassifier::criterioMeta($meta, $classificacao);
        }

        $rotulo = RuleClassifier::rotular($criterios, (bool) $meta);

        return [
            'chave' => RuleClassifier::chave('Aquarela', null, $titulo),
            'title' => $titulo, 'type' => $tipo, 'link' => 'http://aquarela/'.$titulo, 'repositorio' => 'Aquarela',
            'recommended' => $rotulo, 'explicacao' => RuleClassifier::explicacao($criterios, $rotulo),
            'fonte_interatividade' => 'indisponivel', 'interatividade' => '', 'nivel_interatividade' => '',
            'estilo_aprendizagem' => '', 'estrategia' => '',
        ];
    }

    private function busca(array $reas, array $repositorios = ['Aquarela', 'MecRed', 'Eduplay'], string $grupo = 'escrutabilidade'): Data
    {
        $data = Data::create(['searched_at' => $this->searchedAt, 'data' => json_encode($reas), 'finished' => true, 'time' => 2, 'grupo' => $grupo]);

        foreach ($repositorios as $repositorio) {
            DB::table('search_metrics')->insert([
                'searched_at' => $this->searchedAt, 'repository' => $repositorio, 'profile' => 'p', 'interest' => 'i',
                'total_time' => 1, 'api_time' => 1, 'api_calls' => 1, 'items_returned' => 1, 'items_filtered' => 1,
                'timeouts_errors' => 0, 'breakdown' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $data;
    }

    /**
     * Uma busca com um REA de nível corrigível, um oculto por meta e tipos comparáveis.
     */
    private function buscaCompleta(array $repositorios = ['Aquarela', 'MecRed', 'Eduplay']): array
    {
        $grafos = $this->aquarela('Grafos', 'Jogo', 'ma', 'Aprendizagem');
        $this->busca([$grafos, $this->aquarela('Árvores', 'Vídeo', 'ma', 'Performance-evitação')], $repositorios);

        return $grafos;
    }

    private function usuarioComMeta(): User
    {
        $user = User::factory()->create();
        $questionnaire = new Questionnaire(['ma' => 4, 'mpa' => 3, 'mpe' => 2, 'dominant' => 'ma']);
        $questionnaire->user_id = $user->id;
        $questionnaire->save();

        return $user;
    }

    private function componente(string $grupo, ?User $user = null): Testable
    {
        $user ??= $this->usuarioComMeta();
        app(Experimento::class)->fixar($grupo, $user);

        return Livewire::actingAs($user)->test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('interestApiSearch', 'algoritmos')
            ->set('timestampSession', $this->searchedAt)
            ->set('contexto', [
                'perfil' => 'Ensino fundamental', 'interesse' => 'Algoritmos', 'termo_api' => 'algoritmos',
                'tipos_busca' => ['video'], 'tipos_gerais' => [], 'meta' => ['dominante' => 'ma', 'ma' => 4, 'mpa' => 3, 'mpe' => 2],
            ]);
    }

    private function titulosExibidos(Testable $componente): array
    {
        return collect($componente->instance()->paginate(Data::sole())->items())->pluck('title')->all();
    }

    // --- Grupos e flags ----------------------------------------------------------------------

    public function test_cada_grupo_liga_as_flags_configuradas(): void
    {
        $experimento = app(Experimento::class);
        $user = User::factory()->create();

        $experimento->fixar('controle', $user);
        $this->actingAs($user);
        $this->assertSame([], $experimento->flagsAtivas());
        $this->assertFalse($experimento->mostraExplicacoes());

        $experimento->fixar('transparencia', $user);
        $this->assertSame(['explicacao-rea', 'painel-ordenacao', 'reas-ocultos', 'painel-contexto', 'progresso-busca'], $experimento->flagsAtivas());

        $experimento->fixar('escrutabilidade', $user);
        $this->assertSame(array_keys(Experimento::FLAGS), $experimento->flagsAtivas());
    }

    public function test_forcar_grupo_pela_configuracao_ignora_o_grupo_gravado(): void
    {
        $user = User::factory()->create();
        app(Experimento::class)->fixar('escrutabilidade', $user);
        $this->actingAs($user);

        config(['experimento.forcar_grupo' => 'controle']);

        $this->assertSame('controle', app(Experimento::class)->grupo());
        $this->assertFalse(Experimento::ativa('explicacao-rea'));
    }

    public function test_forcar_flags_isoladas_por_cima_do_grupo(): void
    {
        config(['experimento.forcar_grupo' => 'escrutabilidade', 'experimento.forcar_flags' => 'progresso-busca=0, reas-ocultos=false,inexistente=1']);

        $this->assertFalse(Experimento::ativa('progresso-busca'));
        $this->assertFalse(Experimento::ativa('reas-ocultos'));
        $this->assertTrue(Experimento::ativa('explicacao-rea'));

        config(['experimento.forcar_grupo' => 'controle', 'experimento.forcar_flags' => 'progresso-busca=1']);
        $this->assertSame(['progresso-busca'], app(Experimento::class)->flagsAtivas());
    }

    // --- Atribuição ---------------------------------------------------------------------------

    public function test_sorteio_e_estavel_e_gravado_por_usuario(): void
    {
        config(['experimento.sem_link' => 'sorteio']);
        $grupos = [];

        foreach (User::factory()->count(30)->create() as $user) {
            $grupo = app(Experimento::class)->grupo($user);

            // Nova instância (outra requisição): lê o valor gravado, não sorteia de novo.
            $this->app->forgetScopedInstances();
            $this->assertSame($grupo, app(Experimento::class)->grupo($user));
            $this->assertDatabaseHas('features', ['name' => 'grupo-experimento', 'value' => json_encode($grupo)]);

            $grupos[] = $grupo;
        }

        // 30 sorteios uniformes entre 3 grupos: a chance de faltar algum é ~1e-5.
        $this->assertEqualsCanonicalizing(Experimento::GRUPOS, array_values(array_unique($grupos)));
    }

    public function test_sem_link_pode_apontar_um_grupo_fixo(): void
    {
        config(['experimento.sem_link' => 'transparencia']);

        $this->assertSame('transparencia', app(Experimento::class)->grupo(User::factory()->create()));
    }

    public function test_link_define_o_grupo_do_visitante_e_o_login_herda(): void
    {
        $this->get('/?grupo=transparencia')->assertOk();

        $this->assertSame('transparencia', session('experimento.grupo'));
        $this->assertSame('transparencia', app(Experimento::class)->grupo());
        $this->assertStringStartsWith('v:', app(Experimento::class)->participante());

        // Conta sem grupo, na mesma sessão: herda o grupo do link.
        $this->app->forgetScopedInstances();
        $this->assertSame('transparencia', app(Experimento::class)->grupo(User::factory()->create()));
    }

    public function test_link_do_usuario_logado_troca_o_grupo_dele(): void
    {
        $user = User::factory()->create();
        app(Experimento::class)->fixar('controle', $user);

        $this->actingAs($user)->get('/?grupo=escrutabilidade')->assertOk();

        $this->app->forgetScopedInstances();
        $this->assertSame('escrutabilidade', app(Experimento::class)->grupo($user));
    }

    public function test_link_aceita_codigos_opacos_e_ignora_desconhecidos(): void
    {
        config(['experimento.codigos.controle' => 'k7q2', 'experimento.sem_link' => 'escrutabilidade']);

        $this->get('/?grupo=controle')->assertOk();
        $this->assertSame('escrutabilidade', app(Experimento::class)->grupo(), 'nome do grupo não vale quando há código');

        $this->app->forgetScopedInstances();
        $this->get('/?grupo=k7q2')->assertOk();
        $this->assertSame('controle', app(Experimento::class)->grupo());
    }

    public function test_comando_fixa_e_consulta_o_grupo_de_um_usuario(): void
    {
        $user = User::factory()->create(['email' => 'p@ufjf.br']);

        $this->artisan('experimento:grupo', ['usuario' => 'p@ufjf.br'])
            ->expectsOutputToContain('sem grupo')
            ->assertSuccessful();

        $this->artisan('experimento:grupo', ['usuario' => (string) $user->id, 'grupo' => 'controle'])->assertSuccessful();
        $this->assertSame('controle', app(Experimento::class)->grupo($user));

        $this->artisan('experimento:grupo', ['usuario' => 'p@ufjf.br'])->expectsOutputToContain('controle')->assertSuccessful();
        $this->artisan('experimento:grupo', ['usuario' => 'p@ufjf.br', 'grupo' => 'outro'])->assertFailed();
        $this->artisan('experimento:grupo', ['usuario' => 'ninguem@x'])->assertFailed();
        $this->artisan('experimento:grupo')->expectsTable(['Grupo', 'Usuários', 'Visitantes'], [['controle', 1, 0]])->assertSuccessful();
    }

    // --- Interface por grupo ------------------------------------------------------------------

    public function test_controle_esconde_toda_a_explicabilidade(): void
    {
        $this->buscaCompleta();

        $this->componente('controle')
            ->assertSee('Grafos')
            ->assertDontSee('Por que este REA?')
            ->assertDontSee('Como ler os cartões')
            ->assertDontSee('Como ordenamos estes resultados')
            ->assertDontSee('O que usamos sobre você')
            ->assertDontSee('Qual é a etapa deste REA?')
            ->assertDontSee('Compatível');
    }

    public function test_transparencia_mostra_explicacoes_sem_correcoes(): void
    {
        $this->buscaCompleta();

        $this->componente('transparencia')
            ->assertSee('Por que este REA?')
            ->assertSee('Como ler os cartões')
            ->assertSee('Como ordenamos estes resultados')
            ->assertSee('Ver os REAs que não aparecem')
            ->assertSee('O que usamos sobre você')
            ->assertDontSee('Qual é a etapa deste REA?')
            ->assertDontSee('Qual é a meta deste REA?')
            ->assertDontSee('Editar tipos preferidos')
            ->assertDontSee('Você poderá corrigir')
            ->assertDontSee('Você poderá editar os tipos preferidos');
    }

    public function test_escrutabilidade_mostra_explicacoes_e_correcoes(): void
    {
        $this->buscaCompleta();

        $this->componente('escrutabilidade')
            ->assertSee('Por que este REA?')
            ->assertSee('Qual é a etapa deste REA?')
            ->assertSee('Qual é a meta deste REA?')
            ->assertSee('Editar tipos preferidos');
    }

    public function test_flag_de_ocultos_desligada_mantem_o_painel_de_ordenacao(): void
    {
        $this->buscaCompleta();
        config(['experimento.forcar_flags' => 'reas-ocultos=0']);

        $this->componente('escrutabilidade')
            ->assertSee('Como ordenamos estes resultados')
            ->assertSee('REA encontrado não é exibido')
            ->assertDontSee('Ver os REAs que não aparecem');
    }

    public function test_progresso_so_no_grupo_com_a_flag(): void
    {
        $this->buscaCompleta(['Aquarela']);

        $this->componente('transparencia')->assertSee('Consultando repositórios… 1 de 3 responderam.');
        $this->componente('controle')->assertSee('Carregando...')->assertDontSee('Consultando repositórios');
    }

    public function test_motivos_sobre_explicacoes_so_para_quem_viu_explicacoes(): void
    {
        $this->seed(FeedbackReasonSeeder::class);
        $this->buscaCompleta();
        $frase = FeedbackReason::FRASES_EXPLICACAO[0];

        $this->componente('controle')->set('rating', 2)->assertDontSee($frase)->assertSee('Vieram conteúdos repetitivos.');
        $this->componente('transparencia')->set('rating', 2)->assertSee($frase);
    }

    // --- Ações bloqueadas no servidor -------------------------------------------------------

    public function test_correcoes_bloqueadas_sem_escrutabilidade(): void
    {
        foreach (['controle', 'transparencia'] as $grupo) {
            $grafos = $this->buscaCompleta();
            $antes = Data::sole()->data;

            $this->componente($grupo)
                ->call('corrigirNivel', $grafos['chave'], 'ensino medio')
                ->call('corrigirMeta', $grafos['chave'], 'mpe')
                ->call('desfazerCorrecao', $grafos['chave'], 'nivel')
                ->call('redefinirTipos', ['jogo'])
                ->call('desfazerTodas')
                ->assertHasNoErrors();

            $this->assertSame($antes, Data::sole()->data, $grupo);
            $this->assertSame(0, Correction::count(), $grupo);

            Data::query()->delete();
            DB::table('search_metrics')->delete();
        }
    }

    public function test_correcao_funciona_e_grava_o_grupo_com_escrutabilidade(): void
    {
        $grafos = $this->buscaCompleta();

        $this->componente('escrutabilidade')->call('corrigirNivel', $grafos['chave'], 'ensino medio')->assertHasNoErrors();

        $this->assertSame('escrutabilidade', Correction::sole()->grupo);
    }

    public function test_eventos_so_das_funcionalidades_ligadas_e_com_grupo(): void
    {
        $this->buscaCompleta();

        $this->componente('controle')
            ->call('registrarExplicacao', 'abriu_explicacao')
            ->call('registrarExplicacao', 'abriu_ordenacao');
        $this->assertSame(0, ExplanationEvent::count());

        $this->componente('transparencia')
            ->call('registrarExplicacao', 'abriu_explicacao', 'Aquarela', 'Grafos', 'meta_both')
            ->call('registrarExplicacao', 'abriu_correcao');
        $this->assertSame(['abriu_explicacao'], ExplanationEvent::pluck('acao')->all());
        $this->assertSame('transparencia', ExplanationEvent::sole()->grupo);

        config(['experimento.forcar_flags' => 'reas-ocultos=0']);
        $this->componente('escrutabilidade')->call('registrarExplicacao', 'abriu_ocultos')->call('registrarExplicacao', 'abriu_correcao');
        $this->assertSame(['abriu_explicacao', 'abriu_correcao'], ExplanationEvent::pluck('acao')->all());
    }

    public function test_motivo_sobre_explicacao_rejeitado_no_controle_e_grupo_gravado(): void
    {
        $this->seed(FeedbackReasonSeeder::class);
        $this->buscaCompleta();
        $explicacao = FeedbackReason::where('phrase', FeedbackReason::FRASES_EXPLICACAO[0])->value('id');
        $repetitivo = FeedbackReason::where('phrase', 'Vieram conteúdos repetitivos.')->value('id');

        $this->componente('controle')
            ->set('data', Data::sole())
            ->set('selectedReasons', [(string) $explicacao, (string) $repetitivo])
            ->set('comment', 'ok')
            ->call('saveSearchFeedback');

        $this->assertSame([[$repetitivo, 'controle']], DB::table('data_reasons')->get()->map(fn ($r) => [(int) $r->feedback_reason_id, $r->grupo])->all());
    }

    // --- Recomendação igual em todos os grupos ----------------------------------------------

    public function test_ordenacao_e_igual_em_todos_os_grupos(): void
    {
        $user = $this->usuarioComMeta();
        $this->busca([
            $this->aquarela('Grafos', 'Jogo', 'ma', 'Aprendizagem'),
            $this->aquarela('Algoritmos no sexto ano', 'Vídeo', 'ma', 'Aprendizagem'),
            $this->aquarela('Árvores', 'Vídeo', 'ma', 'Performance-evitação'),
        ]);

        $ordens = array_map(fn ($grupo) => $this->titulosExibidos($this->componente($grupo, $user)), Experimento::GRUPOS);

        $this->assertSame(['Algoritmos no sexto ano', 'Grafos'], $ordens[0]);
        $this->assertSame([$ordens[0], $ordens[0], $ordens[0]], $ordens);
    }

    public function test_busca_grava_grupo_flags_e_participante(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        app(Experimento::class)->fixar('transparencia', $user);

        Livewire::actingAs($user)->test(FindREA::class)
            ->set('userType', 'usuario')
            ->set('profile', 'Ensino fundamental')
            ->set('interest', 'Algoritmos')
            ->call('search');

        $data = Data::sole();
        $this->assertSame('transparencia', $data->grupo);
        $this->assertSame(config('experimento.grupos.transparencia'), $data->flags);
        $this->assertSame('u:'.$user->id, $data->participante);
    }

    public function test_feedback_livre_grava_o_grupo(): void
    {
        $user = User::factory()->create();
        app(Experimento::class)->fixar('controle', $user);

        Livewire::actingAs($user)->test(FindREA::class)->set('message', 'Gostei')->call('sendFeedback');

        $this->assertSame(['controle', 'u:'.$user->id], [Feedback::sole()->grupo, Feedback::sole()->participante]);
    }

    // --- Exportação ---------------------------------------------------------------------------

    public function test_exporta_metricas_por_busca_e_por_grupo(): void
    {
        $this->busca([$this->aquarela('Grafos', 'Jogo')], grupo: 'transparencia');
        Data::sole()->update(['stars' => 4, 'participante' => 'u:1', 'flags' => ['explicacao-rea']]);
        ExplanationEvent::create(['searched_at' => $this->searchedAt, 'acao' => 'abriu_explicacao', 'grupo' => 'transparencia']);
        Data::create(['searched_at' => '2026-10-06 13:00:00', 'data' => '[]', 'grupo' => 'controle', 'stars' => 2, 'participante' => 'v:abc']);
        Data::create(['searched_at' => '2026-10-06 14:00:00', 'data' => '[]']); // sem grupo: fica de fora

        $porBusca = $this->saida(['--saida' => $arquivo = tempnam(sys_get_temp_dir(), 'exp')]);
        $this->assertCount(3, $porBusca);
        $this->assertSame(['transparencia', '4', '1', '1', 'explicacao-rea'], [
            $porBusca[1]['grupo'], $porBusca[1]['estrelas'], $porBusca[1]['reas'], $porBusca[1]['eventos_abriu_explicacao'], $porBusca[1]['flags'],
        ]);

        $porGrupo = collect($this->saida(['--saida' => $arquivo, '--por-grupo' => true]))->keyBy('grupo');
        $this->assertSame(['1', '4', '1'], [$porGrupo['transparencia']['buscas'], $porGrupo['transparencia']['media_estrelas'], $porGrupo['transparencia']['taxa_buscas_abriu_explicacao']]);
        $this->assertSame(['1', '2', '0'], [$porGrupo['controle']['buscas'], $porGrupo['controle']['media_estrelas'], $porGrupo['controle']['taxa_buscas_abriu_explicacao']]);

        unlink($arquivo);
    }

    /**
     * Roda a exportação e devolve as linhas do CSV (a primeira é o cabeçalho, como linha 0 associativa).
     */
    private function saida(array $opcoes): array
    {
        $this->artisan('experimento:exportar', $opcoes)->assertSuccessful();

        $linhas = array_map('str_getcsv', file($opcoes['--saida'], FILE_IGNORE_NEW_LINES));
        $cabecalho = $linhas[0];

        return array_map(fn ($l) => array_combine($cabecalho, $l), $linhas);
    }
}
