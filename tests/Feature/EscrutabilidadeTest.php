<?php

namespace Tests\Feature;

use App\Livewire\FindREA;
use App\Models\Correction;
use App\Models\Data;
use App\Models\ExplanationEvent;
use App\Models\Questionnaire;
use App\Models\User;
use App\Recommendation\RuleClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class EscrutabilidadeTest extends TestCase
{
    use RefreshDatabase;

    private string $searchedAt = '2026-10-03 12:00:00';

    // Colunas da tabela de resultados que os jobs sempre gravam.
    private const COLUNAS = [
        'fonte_interatividade' => 'indisponivel', 'interatividade' => '', 'nivel_interatividade' => '',
        'estilo_aprendizagem' => '', 'estrategia' => '',
    ];

    /**
     * REA do Aquarela como o job grava (perfil Ensino fundamental, tipos preferidos: video).
     */
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
            'title' => $titulo,
            'type' => $tipo,
            'link' => 'http://aquarela/'.$titulo,
            'repositorio' => 'Aquarela',
            'recommended' => $rotulo,
            'explicacao' => RuleClassifier::explicacao($criterios, $rotulo),
        ] + self::COLUNAS;
    }

    private function mecRed(string $titulo): array
    {
        $criterios = [
            'tema' => RuleClassifier::criterioTema('algoritmos', 'MEC RED'),
            'nivel' => ['status' => 'nao_avaliado', 'valor' => null, 'esperado' => 'ensino fundamental', 'fonte' => 'filtro_api'],
            'tipo' => ['status' => 'nao_avaliado', 'valor' => null, 'esperado' => [], 'fonte' => 'filtro_api'],
        ];

        return [
            'chave' => RuleClassifier::chave('MECRED', '1', $titulo),
            'title' => $titulo,
            'type' => '',
            'link' => '',
            'repositorio' => 'MECRED',
            'recommended' => 'both',
            'explicacao' => RuleClassifier::explicacao($criterios, 'both', 'Política.'),
        ] + self::COLUNAS;
    }

    private function busca(array $reas, array $repositorios = ['Aquarela', 'MecRed', 'Eduplay']): void
    {
        Data::create(['searched_at' => $this->searchedAt, 'data' => json_encode($reas), 'finished' => true, 'time' => 2]);

        foreach ($repositorios as $repositorio) {
            DB::table('search_metrics')->insert([
                'searched_at' => $this->searchedAt, 'repository' => $repositorio, 'profile' => 'p', 'interest' => 'i',
                'total_time' => 1, 'api_time' => 1, 'api_calls' => 1, 'items_returned' => 1, 'items_filtered' => 1,
                'timeouts_errors' => 0, 'breakdown' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function componente(?User $user = null): Testable
    {
        $teste = $user ? Livewire::actingAs($user)->test(FindREA::class) : Livewire::test(FindREA::class);

        return $teste->set('timestampSession', $this->searchedAt);
    }

    private function gravados(): array
    {
        return json_decode(Data::where('searched_at', $this->searchedAt)->first()->data, true);
    }

    private function titulosExibidos(Testable $componente): array
    {
        $data = Data::where('searched_at', $this->searchedAt)->first();

        return collect($componente->instance()->paginate($data)->items())->pluck('title')->all();
    }

    private function usuarioComMeta(string $meta): User
    {
        $user = User::factory()->create();
        $questionnaire = new Questionnaire(['ma' => 4, 'mpa' => 3, 'mpe' => 2, 'dominant' => $meta]);
        $questionnaire->user_id = $user->id;
        $questionnaire->save();

        return $user;
    }

    public function test_corrigir_nivel_reordena_a_lista_e_registra(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $this->busca([$this->aquarela('Algoritmos no médio', 'Jogo'), $grafos]);
        $componente = $this->componente();

        $this->assertSame(['Algoritmos no médio', 'Grafos'], $this->titulosExibidos($componente));

        $componente->call('corrigirNivel', $grafos['chave'], 'ensino fundamental')->assertHasNoErrors();

        $this->assertSame(['Grafos', 'Algoritmos no médio'], $this->titulosExibidos($componente));

        $gravado = $this->gravados()[1];
        $this->assertSame('profile', $gravado['recommended']);
        $this->assertSame('usuario', $gravado['explicacao']['criterios']['nivel']['fonte']);

        $correcao = Correction::sole();
        $this->assertSame(['corrigir', 'nivel', $grafos['chave'], 'Aquarela', 'Grafos'], [$correcao->acao, $correcao->alvo, $correcao->chave_rea, $correcao->repositorio, $correcao->titulo]);
        $this->assertSame('regex', $correcao->valor_anterior['fonte']);
        $this->assertSame('ensino fundamental', $correcao->valor_novo['valor']);
        $this->assertSame(['interest', 'profile', 1], [$correcao->faixa_anterior, $correcao->faixa_nova, $correcao->itens_afetados]);
        $this->assertNull($correcao->user_id);
    }

    public function test_corrigir_meta_traz_de_volta_um_rea_oculto(): void
    {
        $oculto = $this->aquarela('Grafos', 'Vídeo', 'ma', null);
        $this->busca([$oculto]);
        $user = $this->usuarioComMeta('ma');
        $componente = $this->componente($user);

        $this->assertSame([], $this->titulosExibidos($componente));
        $this->assertSame($oculto['chave'], $componente->instance()->ocultos(Data::first())[0]['chave']);

        $componente->call('corrigirMeta', $oculto['chave'], 'ma')->assertHasNoErrors();

        $this->assertSame(['Grafos'], $this->titulosExibidos($componente));
        $this->assertSame($user->id, Correction::sole()->user_id);
    }

    public function test_desfazer_restaura_e_registra(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $this->busca([$grafos]);

        $this->componente()
            ->call('corrigirNivel', $grafos['chave'], 'ensino fundamental')
            ->call('desfazerCorrecao', $grafos['chave'], 'nivel');

        $this->assertSame([$grafos], $this->gravados());
        $this->assertSame(['corrigir', 'desfazer'], Correction::orderBy('id')->pluck('acao')->all());
    }

    public function test_mesma_chave_corrige_todas_as_ocorrencias(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $this->busca([$grafos, $grafos]);

        $this->componente()->call('corrigirNivel', $grafos['chave'], 'ensino fundamental');

        $this->assertSame(['profile', 'profile'], array_column($this->gravados(), 'recommended'));
        $this->assertSame(1, Correction::count());
    }

    public function test_nao_corrige_enquanto_algum_repositorio_nao_respondeu(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $this->busca([$grafos], ['Aquarela', 'MecRed']);
        $componente = $this->componente();

        $this->assertFalse($componente->instance()->podeCorrigir(Data::first()));

        $componente->call('corrigirNivel', $grafos['chave'], 'ensino fundamental')->assertHasErrors('correcao');

        $this->assertSame([$grafos], $this->gravados());
        $this->assertSame(0, Correction::count());
    }

    public function test_rejeita_correcoes_invalidas(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $politica = $this->mecRed('Recurso');
        $this->busca([$grafos, $politica]);

        $this->componente()->call('corrigirNivel', $grafos['chave'], 'mestrado')->assertHasErrors('correcao');
        $this->componente()->call('corrigirNivel', $politica['chave'], 'ensino fundamental')->assertHasErrors('correcao');
        $this->componente()->call('corrigirMeta', $grafos['chave'], 'ma')->assertHasErrors('correcao');
        $this->componente()->call('corrigirNivel', 'inexistente', 'ensino fundamental')->assertHasNoErrors();
        $this->componente()->call('desfazerCorrecao', $grafos['chave'], 'tema')->assertHasNoErrors();

        $this->assertSame([$grafos, $politica], $this->gravados());
        $this->assertSame(0, Correction::count());
    }

    public function test_tipos_preferidos_oferece_os_tipos_da_busca(): void
    {
        $this->busca([$this->aquarela('Grafos', 'Jogo'), $this->aquarela('Árvores', 'Vídeo'), $this->mecRed('Recurso')]);

        $tipos = $this->componente()->instance()->tiposPreferidos(Data::first());

        $this->assertSame(['jogo', 'video'], $tipos['opcoes']);
        $this->assertSame(['video'], $tipos['atuais']);
        $this->assertSame(['video'], $tipos['originais']);
        $this->assertFalse($tipos['editados']);
    }

    public function test_redefinir_tipos_recalcula_a_busca_toda(): void
    {
        $this->busca([
            $this->aquarela('Jogo no sexto ano', 'Jogo'),
            $this->aquarela('Vídeo no sexto ano', 'Vídeo'),
            $politica = $this->mecRed('Recurso'),
        ]);
        $componente = $this->componente()->set('contexto', ['tipos_busca' => ['video']]);

        $componente->call('redefinirTipos', ['jogo'])->assertHasNoErrors();

        $gravados = $this->gravados();
        $this->assertSame(['both', 'profile'], [$gravados[0]['recommended'], $gravados[1]['recommended']]);
        $this->assertSame($politica, $gravados[2]);
        $this->assertSame(['jogo'], $componente->get('contexto')['tipos_usuario']);

        $correcao = Correction::sole();
        $this->assertSame(['corrigir', 'tipos', null, 2], [$correcao->acao, $correcao->alvo, $correcao->chave_rea, $correcao->itens_afetados]);
        $this->assertSame([['video'], ['jogo']], [$correcao->valor_anterior, $correcao->valor_novo]);

        $tipos = $componente->instance()->tiposPreferidos(Data::first());
        $this->assertSame([['jogo'], ['video'], true], [$tipos['atuais'], $tipos['originais'], $tipos['editados']]);

        // Voltar para a lista original é registrado como desfazer.
        $componente->call('redefinirTipos', ['Vídeo']);
        $this->assertSame('desfazer', Correction::latest('id')->first()->acao);
        $this->assertNull($componente->get('contexto')['tipos_usuario']);
        $this->assertFalse($componente->instance()->tiposPreferidos(Data::first())['editados']);
    }

    public function test_redefinir_tipos_rejeita_tipo_fora_das_opcoes(): void
    {
        $this->busca([$this->aquarela('Grafos', 'Jogo')]);

        $this->componente()->call('redefinirTipos', ['programacao'])->assertHasErrors('correcao');

        $this->assertSame(0, Correction::count());
    }

    public function test_desfazer_todas(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $arvores = $this->aquarela('Árvores', 'Vídeo');
        $this->busca([$grafos, $arvores]);

        $componente = $this->componente()
            ->call('corrigirNivel', $grafos['chave'], 'ensino fundamental')
            ->call('redefinirTipos', ['jogo', 'video'])
            ->call('desfazerTodas');

        $this->assertSame([$grafos, $arvores], $this->gravados());
        $ultima = Correction::latest('id')->first();
        $this->assertSame(['desfazer', 'todas', 1], [$ultima->acao, $ultima->alvo, $ultima->itens_afetados]);

        // Sem nada para desfazer, não registra.
        $componente->call('desfazerTodas');
        $this->assertSame(3, Correction::count());
    }

    public function test_registra_abertura_do_formulario_de_correcao(): void
    {
        $this->componente()->call('registrarExplicacao', 'abriu_correcao', 'Aquarela', 'Grafos', 'interest');

        $this->assertSame('abriu_correcao', ExplanationEvent::sole()->acao);
    }

    public function test_tela_oferece_correcao_so_quando_pode(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $this->busca([$grafos, $this->mecRed('Recurso do MEC')], ['Aquarela', 'MecRed']);
        $componente = $this->componente()->set('userType', 'usuario')->set('interestApiSearch', 'algoritmos');

        $componente->assertSee('Você poderá corrigir quando todos os repositórios responderem.')
            ->assertDontSee('corrigirNivel', false)
            ->assertSee('não podem ser corrigidos: a posição dele é definida por política do repositório.');

        DB::table('search_metrics')->insert([
            'searched_at' => $this->searchedAt, 'repository' => 'Eduplay', 'profile' => 'p', 'interest' => 'i',
            'total_time' => 1, 'api_time' => 1, 'api_calls' => 1, 'items_returned' => 0, 'items_filtered' => 0,
            'timeouts_errors' => 0, 'breakdown' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $componente->call('$refresh')
            ->assertSee('Se estiver errado, corrija')
            ->assertSee('corrigirNivel', false)
            ->assertSee('Qual é a etapa deste REA?')
            ->assertSee('wire:key="rea-'.$grafos['chave'].'-1"', false)
            ->assertSee('✎ corrigido por você');
    }

    public function test_tela_mostra_a_correcao_feita(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $this->busca([$grafos]);

        $this->componente()->set('userType', 'usuario')->set('interestApiSearch', 'algoritmos')
            ->call('corrigirNivel', $grafos['chave'], 'ensino fundamental')
            ->assertSee('informado por você (o sistema tinha assumido ensino superior)')
            ->assertSee('Faixa alterada pela sua correção: antes Só tema, agora Nível.')
            ->assertSee('Você corrigiu 1 REA;')
            ->assertSee('desfazerCorrecao', false)
            ->assertSee('Corrigir de novo');
    }

    public function test_tela_oferece_corrigir_a_meta_dos_ocultos(): void
    {
        $oculto = $this->aquarela('Grafos', 'Vídeo', 'ma', null);
        $this->busca([$oculto]);

        $this->componente($this->usuarioComMeta('ma'))->set('userType', 'usuario')->set('interestApiSearch', 'algoritmos')
            ->assertSee('A meta deste REA está errada?')
            ->assertSee('Qual é a meta deste REA?')
            ->assertSee('wire:key="oculto-'.$oculto['chave'].'-1-meta-estimado"', false);
    }

    public function test_erro_de_correcao_aparece_na_tela(): void
    {
        $grafos = $this->aquarela('Grafos', 'Jogo');
        $this->busca([$grafos]);

        $this->componente()->set('userType', 'usuario')->set('interestApiSearch', 'algoritmos')
            ->call('corrigirNivel', $grafos['chave'], 'mestrado')
            ->assertSee('Esta correção não é válida para este REA.');
    }
}
