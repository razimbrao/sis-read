<?php

namespace Tests\Feature;

use App\Jobs\ProcessAquarela;
use App\Jobs\ProcessEduplay;
use App\Livewire\FindREA;
use App\Livewire\Preferencias;
use App\Models\Collaborator;
use App\Models\Correction;
use App\Models\Data;
use App\Models\User;
use App\Recommendation\RuleClassifier;
use App\Recommendation\TiposPreferidos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tipos preferidos salvos na conta (docs/plano-escrutabilidade.md §15).
 */
class PreferenciasPorContaTest extends TestCase
{
    use RefreshDatabase;

    private string $searchedAt = '2026-10-06 12:00:00';

    private function colaborador(string $item): void
    {
        Collaborator::create([
            'name' => 'C', 'role' => 'Professor', 'institution' => 'UF', 'reference' => 'r',
            'rea_title' => 'T', 'interest' => 'algoritmos', 'profile' => 'ensino fundamental', 'item' => $item,
        ]);
    }

    private function usuario(?array $tipos = null, bool $incluir = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['tipos_preferidos' => $tipos, 'incluir_tipos_colaboradores' => $incluir])->save();

        return $user;
    }

    private function buscar(?User $user = null)
    {
        $teste = $user ? Livewire::actingAs($user)->test(FindREA::class) : Livewire::test(FindREA::class);

        return $teste->set('userType', 'usuario')
            ->set('profile', 'Ensino fundamental')
            ->set('interest', 'Algoritmos')
            ->call('search');
    }

    /**
     * Busca concluída com dois REAs do Aquarela, como o job grava com a lista e a origem dadas.
     */
    private function buscaConcluida(array $tipos, string $origem): void
    {
        $reas = [];

        foreach (['Jogo no sexto ano' => 'Jogo', 'Vídeo no sexto ano' => 'Vídeo'] as $titulo => $tipo) {
            $criterios = [
                'tema' => RuleClassifier::criterioTema('algoritmos', 'Aquarela'),
                'nivel' => RuleClassifier::criterioNivel('Ensino fundamental', RuleClassifier::inferirNivel($titulo, '')),
                'tipo' => RuleClassifier::criterioTipo($tipo, $tipos, $origem),
            ];
            $rotulo = RuleClassifier::rotular($criterios, false);

            $reas[] = [
                'chave' => RuleClassifier::chave('Aquarela', null, $titulo), 'title' => $titulo, 'type' => $tipo,
                'link' => 'http://aquarela/'.$titulo, 'repositorio' => 'Aquarela', 'recommended' => $rotulo,
                'explicacao' => RuleClassifier::explicacao($criterios, $rotulo),
                'fonte_interatividade' => 'indisponivel', 'interatividade' => '', 'nivel_interatividade' => '',
                'estilo_aprendizagem' => '', 'estrategia' => '',
            ];
        }

        Data::updateOrCreate(['searched_at' => $this->searchedAt], ['data' => json_encode($reas), 'finished' => true, 'time' => 2]);

        foreach (['Aquarela', 'MecRed', 'Eduplay'] as $repositorio) {
            DB::table('search_metrics')->insert([
                'searched_at' => $this->searchedAt, 'repository' => $repositorio, 'profile' => 'p', 'interest' => 'i',
                'total_time' => 1, 'api_time' => 1, 'api_calls' => 1, 'items_returned' => 1, 'items_filtered' => 1,
                'timeouts_errors' => 0, 'breakdown' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_tela_de_preferencias_exige_login(): void
    {
        $this->get('/conta/preferencias')->assertRedirect(route('login'));

        $this->actingAs($this->usuario())->get('/conta/preferencias')
            ->assertOk()
            ->assertSee('Minhas preferências')
            ->assertSee('Hoje você não tem preferência salva');
    }

    public function test_salvar_preferencia_na_tela_grava_na_conta_e_registra(): void
    {
        $this->colaborador('Vídeo');
        $this->colaborador('Jogo');
        $user = $this->usuario();

        Livewire::actingAs($user)->test(Preferencias::class)
            ->assertSee('sugerido por colaboradores')
            ->set('tipos', ['Jogo'])
            ->set('incluirColaboradores', true)
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSee('Preferências salvas');

        $user->refresh();
        $this->assertSame(['jogo'], $user->tipos_preferidos);
        $this->assertTrue($user->incluir_tipos_colaboradores);

        $registro = Correction::sole();
        $this->assertSame(['salvar_preferencia', 'tipos', $user->id, null], [$registro->acao, $registro->alvo, $registro->user_id, $registro->searched_at]);
        $this->assertSame([[], ['jogo']], [$registro->valor_anterior, $registro->valor_novo]);
    }

    public function test_tela_rejeita_tipo_fora_das_opcoes_e_lista_vazia(): void
    {
        $this->colaborador('Vídeo');
        $user = $this->usuario();

        Livewire::actingAs($user)->test(Preferencias::class)
            ->set('tipos', ['programacao'])
            ->call('salvar')
            ->assertHasErrors('tipos')
            ->set('tipos', [])
            ->call('salvar')
            ->assertHasErrors('tipos');

        $this->assertNull($user->refresh()->tipos_preferidos);
        $this->assertSame(0, Correction::count());
    }

    public function test_remover_preferencia_volta_aos_colaboradores(): void
    {
        $user = $this->usuario(['jogo'], true);

        Livewire::actingAs($user)->test(Preferencias::class)
            ->assertSet('tipos', ['jogo'])
            ->call('remover');

        $user->refresh();
        $this->assertNull($user->tipos_preferidos);
        $this->assertFalse($user->incluir_tipos_colaboradores);
    }

    public function test_busca_seguinte_usa_os_tipos_da_conta_no_lugar_dos_colaboradores(): void
    {
        Queue::fake();
        $this->colaborador('Vídeo');

        $contexto = $this->buscar($this->usuario(['Jogo']))->get('contexto');

        Queue::assertPushed(ProcessAquarela::class, fn ($job) => $job->types === ['jogo'] && $job->origemTipos === TiposPreferidos::CONTA);
        Queue::assertPushed(ProcessEduplay::class, fn ($job) => $job->types === ['jogo'] && $job->origemTipos === TiposPreferidos::CONTA);
        $this->assertSame(['jogo'], $contexto['tipos_conta']);
        $this->assertSame(TiposPreferidos::CONTA, $contexto['origem_tipos']);
        $this->assertSame(['video'], $contexto['tipos_busca']);
    }

    public function test_busca_com_opcao_de_unir_inclui_os_colaboradores(): void
    {
        Queue::fake();
        $this->colaborador('Vídeo');

        $this->buscar($this->usuario(['jogo'], true));

        Queue::assertPushed(ProcessAquarela::class, fn ($job) => $job->types === ['jogo', 'video']
            && $job->origemTipos === TiposPreferidos::CONTA_E_COLABORADORES);
    }

    public function test_visitante_anonimo_continua_com_os_tipos_dos_colaboradores(): void
    {
        Queue::fake();
        $this->colaborador('Vídeo');

        $contexto = $this->buscar()->get('contexto');

        Queue::assertPushed(ProcessAquarela::class, fn ($job) => $job->types === ['video'] && $job->origemTipos === TiposPreferidos::COLABORADORES);
        $this->assertSame([], $contexto['tipos_conta']);
    }

    public function test_preferencia_de_um_usuario_nao_afeta_outro(): void
    {
        Queue::fake();
        $this->colaborador('Vídeo');
        $this->usuario(['jogo']);

        $this->buscar($this->usuario());

        Queue::assertPushed(ProcessAquarela::class, fn ($job) => $job->types === ['video'] && $job->origemTipos === TiposPreferidos::COLABORADORES);
        Queue::assertNotPushed(ProcessAquarela::class, fn ($job) => in_array('jogo', $job->types, true));
    }

    public function test_salvar_na_conta_pela_busca_vale_na_busca_seguinte(): void
    {
        Queue::fake();
        $this->colaborador('Vídeo');
        $user = $this->usuario();
        $this->buscaConcluida(['video'], TiposPreferidos::COLABORADORES);

        $componente = Livewire::actingAs($user)->test(FindREA::class)->set('timestampSession', $this->searchedAt);
        $componente->call('redefinirTipos', ['jogo'], true)
            ->assertHasNoErrors()
            ->assertSet('avisoPreferencia', 'Tipos preferidos salvos na sua conta. Valem a partir da próxima busca.');

        $this->assertSame(['jogo'], $user->refresh()->tipos_preferidos);
        $this->assertFalse($user->incluir_tipos_colaboradores);
        $this->assertSame(['corrigir', 'salvar_preferencia'], Correction::orderBy('id')->pluck('acao')->all());

        // Desfazer na busca restaura a busca, mas não mexe na conta.
        $componente->call('redefinirTipos', ['video']);
        $this->assertSame(['jogo'], $user->refresh()->tipos_preferidos);

        $componente->set('userType', 'usuario')->set('profile', 'Ensino fundamental')->set('interest', 'Algoritmos')->call('search');
        Queue::assertPushed(ProcessAquarela::class, fn ($job) => $job->types === ['jogo'] && $job->origemTipos === TiposPreferidos::CONTA);
    }

    public function test_salvar_na_conta_exige_ao_menos_um_tipo(): void
    {
        $user = $this->usuario();
        $this->buscaConcluida(['video'], TiposPreferidos::COLABORADORES);

        Livewire::actingAs($user)->test(FindREA::class)->set('timestampSession', $this->searchedAt)
            ->call('redefinirTipos', [], true)
            ->assertHasErrors('correcao');

        $this->assertNull($user->refresh()->tipos_preferidos);
        $this->assertSame(0, Correction::count());
    }

    public function test_visitante_nao_salva_na_conta_mesmo_pedindo(): void
    {
        $this->buscaConcluida(['video'], TiposPreferidos::COLABORADORES);

        Livewire::test(FindREA::class)->set('timestampSession', $this->searchedAt)
            ->call('redefinirTipos', ['jogo'], true)
            ->assertHasNoErrors()
            ->assertSet('avisoPreferencia', null);

        $this->assertSame(['corrigir'], Correction::pluck('acao')->all());
    }

    public function test_busca_com_tipos_da_conta_pode_ser_editada_e_desfeita(): void
    {
        $user = $this->usuario(['jogo']);
        $this->buscaConcluida(['jogo'], TiposPreferidos::CONTA);

        $componente = Livewire::actingAs($user)->test(FindREA::class)->set('timestampSession', $this->searchedAt);
        $tipos = $componente->instance()->tiposPreferidos(Data::first());
        $this->assertSame(['usuario', ['jogo'], false], [$tipos['fonte'], $tipos['originais'], $tipos['editados']]);

        $componente->call('redefinirTipos', ['video'])->assertHasNoErrors();
        $tipos = $componente->instance()->tiposPreferidos(Data::first());
        $this->assertSame([['video'], ['jogo'], true], [$tipos['atuais'], $tipos['originais'], $tipos['editados']]);

        // Desfazer volta aos tipos com que a busca começou: os da conta.
        $componente->call('desfazerTodas');
        $tipo = json_decode(Data::first()->data, true)[0]['explicacao']['criterios']['tipo'];
        $this->assertSame(['jogo'], $tipo['esperado']);
        $this->assertSame('usuario', $tipo['fonte']);
        $this->assertArrayNotHasKey('esperado_original', $tipo);
        $this->assertSame(['jogo'], $user->refresh()->tipos_preferidos);
    }

    public function test_painel_mostra_que_os_tipos_vieram_da_conta_com_link(): void
    {
        Queue::fake();
        $user = $this->usuario(['jogo']);

        $componente = $this->buscar($user);
        $this->searchedAt = Data::sole()->getRawOriginal('searched_at');
        $this->buscaConcluida(['jogo'], TiposPreferidos::CONTA);

        $componente->call('$refresh')
            ->assertSee('Da sua conta:')
            ->assertSee('Substituem os tipos cadastrados pelos colaboradores.')
            ->assertSee('Editar os tipos da sua conta')
            ->assertSee(route('preferencias'))
            ->assertSee('Tipo jogo, entre os tipos preferidos da sua conta (jogo).');
    }

    public function test_link_de_preferencias_aparece_para_quem_esta_logado(): void
    {
        Livewire::test(FindREA::class)->assertDontSee('Minhas preferências');
        Livewire::actingAs($this->usuario())->test(FindREA::class)->assertSee('Minhas preferências');
    }
}
