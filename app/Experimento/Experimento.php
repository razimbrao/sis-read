<?php

namespace App\Experimento;

use App\Features\GrupoExperimento;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Pennant\Feature;

/**
 * Grupo do participante e flags de explicabilidade (docs/feature-flags.md).
 *
 * Precedência: EXPERIMENTO_FORCAR_GRUPO > grupo gravado (link, comando artisan ou sorteio).
 * EXPERIMENTO_FORCAR_FLAGS vale por cima do grupo, flag a flag.
 */
class Experimento
{
    public const GRUPOS = ['controle', 'transparencia', 'escrutabilidade'];

    /**
     * Funcionalidades que podem ser ligadas ou desligadas.
     */
    public const FLAGS = [
        'explicacao-rea' => 'Por que este REA? (selo de faixa, critérios, coluna Meta do cartão)',
        'painel-ordenacao' => 'Painel "Como ordenamos estes resultados" (faixas, repositórios)',
        'reas-ocultos' => 'Lista dos REAs que não aparecem, com o motivo',
        'painel-contexto' => 'Painel "O que usamos sobre você"',
        'progresso-busca' => '"Consultando repositórios… N de 3 responderam"',
        'escrutabilidade' => 'Corrigir nível e meta, editar tipos preferidos, desfazer',
    ];

    /**
     * Flag que precisa estar ligada para cada ação registrada em explanation_events.
     */
    public const FLAG_DA_ACAO = [
        'abriu_explicacao' => 'explicacao-rea',
        'abriu_ordenacao' => 'painel-ordenacao',
        'abriu_ocultos' => 'reas-ocultos',
        'abriu_contexto' => 'painel-contexto',
        'abriu_correcao' => 'escrutabilidade',
    ];

    private const SESSAO_VISITANTE = 'experimento.visitante';

    private const SESSAO_GRUPO = 'experimento.grupo';

    /** @var array<string, string> grupo por escopo, nesta requisição */
    private array $memoria = [];

    public static function ativa(string $flag): bool
    {
        return app(self::class)->ativo($flag);
    }

    public function grupo(?User $usuario = null): string
    {
        $forcado = config('experimento.forcar_grupo');

        if (in_array($forcado, self::GRUPOS, true)) {
            return $forcado;
        }

        $escopo = $this->escopo($usuario);
        $chave = is_string($escopo) ? $escopo : 'usuario:'.$escopo->getKey();

        if (! isset($this->memoria[$chave])) {
            $grupo = Feature::for($escopo)->value(GrupoExperimento::class);

            // Valor gravado que não é mais um grupo (configuração mudou): sorteia de novo.
            if (! in_array($grupo, self::GRUPOS, true)) {
                $grupo = $this->grupoInicial();
                Feature::for($escopo)->activate(GrupoExperimento::class, $grupo);
            }

            $this->memoria[$chave] = $grupo;

            if ($usuario === null && $this->temSessao()) {
                session()->put(self::SESSAO_GRUPO, $grupo);
            }
        }

        return $this->memoria[$chave];
    }

    public function ativo(string $flag): bool
    {
        $forcadas = $this->flagsForcadas();

        if (array_key_exists($flag, $forcadas)) {
            return $forcadas[$flag];
        }

        return in_array($flag, config('experimento.grupos.'.$this->grupo(), []), true);
    }

    /**
     * Flags ligadas agora, em ordem fixa (gravadas em cada busca).
     *
     * @return array<int, string>
     */
    public function flagsAtivas(): array
    {
        return array_values(array_filter(array_keys(self::FLAGS), fn ($flag) => $this->ativo($flag)));
    }

    /**
     * Os motivos de feedback sobre explicações só fazem sentido para quem viu alguma explicação.
     */
    public function mostraExplicacoes(): bool
    {
        return $this->ativo('explicacao-rea') || $this->ativo('painel-ordenacao') || $this->ativo('painel-contexto');
    }

    /**
     * Identificador do participante nas métricas: `u:<id>` ou `v:<token da sessão>`.
     */
    public function participante(): string
    {
        $escopo = $this->escopo();

        return is_string($escopo) ? 'v:'.Str::after($escopo, 'visitante:') : 'u:'.$escopo->getKey();
    }

    /**
     * Grava o grupo vindo do link (/?grupo=<código>). Código desconhecido é ignorado.
     */
    public function definirPorCodigo(string $codigo): ?string
    {
        $grupo = array_search($codigo, config('experimento.codigos', []), true);

        if (! is_string($grupo) || ! in_array($grupo, self::GRUPOS, true)) {
            return null;
        }

        $this->fixar($grupo);

        return $grupo;
    }

    /**
     * Fixa o grupo do usuário (ou do visitante atual, sem usuário).
     */
    public function fixar(string $grupo, ?User $usuario = null): void
    {
        if (! in_array($grupo, self::GRUPOS, true)) {
            throw new InvalidArgumentException('Grupo desconhecido: '.$grupo);
        }

        $escopo = $this->escopo($usuario);
        Feature::for($escopo)->activate(GrupoExperimento::class, $grupo);
        $this->memoria = [];

        // O visitante que fizer login herda este grupo (se a conta ainda não tiver um).
        if ($usuario === null && $this->temSessao()) {
            session()->put(self::SESSAO_GRUPO, $grupo);
        }
    }

    /**
     * Grupo de quem ainda não tem: o da sessão de visitante (login depois do link), ou o padrão.
     */
    public function grupoInicial(): string
    {
        $daSessao = $this->temSessao() ? session(self::SESSAO_GRUPO) : null;

        if (in_array($daSessao, self::GRUPOS, true)) {
            return $daSessao;
        }

        $padrao = config('experimento.sem_link', 'sorteio');

        return in_array($padrao, self::GRUPOS, true) ? $padrao : self::GRUPOS[random_int(0, count(self::GRUPOS) - 1)];
    }

    /**
     * @return array<string, bool>
     */
    public function flagsForcadas(): array
    {
        $forcadas = [];

        foreach (explode(',', (string) config('experimento.forcar_flags', '')) as $par) {
            [$flag, $valor] = array_pad(array_map('trim', explode('=', $par, 2)), 2, null);

            if (isset(self::FLAGS[$flag]) && $valor !== null) {
                $forcadas[$flag] = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $forcadas;
    }

    private function escopo(?User $usuario = null): User|string
    {
        $usuario ??= auth()->user();

        if ($usuario) {
            return $usuario;
        }

        // Token próprio, e não o id da sessão: o Laravel troca o id no login.
        if (! $this->temSessao()) {
            return 'visitante:sem-sessao';
        }

        if (! session()->has(self::SESSAO_VISITANTE)) {
            session()->put(self::SESSAO_VISITANTE, (string) Str::uuid());
        }

        return 'visitante:'.session(self::SESSAO_VISITANTE);
    }

    private function temSessao(): bool
    {
        return app()->bound('session');
    }
}
