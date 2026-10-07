<?php

namespace App\Recommendation;

/**
 * Ordenação da lista pelo grau de recomendação (RuleClassifier::grau). Os rótulos são faixas do grau;
 * mantenha em sincronia com RuleClassifier::rotular e ExplanationRenderer::FAIXAS.
 */
class Ranking
{
    /**
     * Com meta, as três últimas faixas reúnem os REAs cuja meta não atende: aparecem abaixo dos
     * compatíveis, primeiro os de meta não conferida e depois os de meta diferente da do usuário
     * (ver grupoMeta()). Nenhum REA sai da lista por causa da meta.
     */
    public const ORDEM_COM_META = ['meta_both', 'meta_one', 'meta', 'both', 'profile', 'interest'];

    public const ORDEM_SEM_META = ['both', 'profile', 'interest'];

    /**
     * Grau usado para REAs gravados antes do grau (sem critérios): o menor grau da faixa.
     */
    private const GRAU_LEGADO = ['meta_both' => 7, 'meta_one' => 5, 'meta' => 4, 'both' => 3, 'profile' => 2, 'interest' => 0];

    public static function ordem(bool $comMeta): array
    {
        return $comMeta ? self::ORDEM_COM_META : self::ORDEM_SEM_META;
    }

    /**
     * Grupos da lista numa busca com meta, nesta ordem (ver grupoMeta()).
     */
    public const GRUPO_COMPATIVEL = 0;

    public const GRUPO_META_NAO_CONFERIDA = 1;

    public const GRUPO_META_INCOMPATIVEL = 2;

    /**
     * Ordena os REAs visíveis por: (1) grupo da meta (compatível, não conferida, diferente); (2) grau,
     * decrescente; (3) posição no próprio repositório, crescente, o que intercala os repositórios;
     * (4) nome do repositório; (5) chave. A ordem de chegada dos jobs não influi. Itens ocultos
     * (ver visivel()) ficam de fora.
     */
    public static function ordenar(iterable $reas, bool $comMeta): array
    {
        $itens = [];

        foreach (self::comPosicao($reas) as [$rea, $dados, $posicao]) {
            if (! self::visivel($dados, $comMeta)) {
                continue;
            }

            $itens[] = [
                'rea' => $rea,
                'chave' => [self::grupoMeta($dados, $comMeta), -self::grauDe($dados), $posicao, (string) ($dados['repositorio'] ?? ''), (string) ($dados['chave'] ?? '')],
            ];
        }

        usort($itens, fn ($a, $b) => $a['chave'] <=> $b['chave']);

        return array_column($itens, 'rea');
    }

    /**
     * Um REA aparece quando a faixa dele está na ordem do contexto. A meta não esconde ninguém: o
     * classificador de meta tem viés (problema #18) e, escondendo, deixava a lista vazia para metas
     * diferentes de Aprendizagem. Ela só define o grupo (grupoMeta()).
     */
    public static function visivel($rea, bool $comMeta): bool
    {
        return in_array(self::paraArray($rea)['recommended'] ?? null, self::ordem($comMeta), true);
    }

    /**
     * Grupo do REA numa busca com meta: compatível (faixas meta*), meta não conferida (a IA não
     * classificou) ou meta diferente da do usuário (classificada como incompatível, pela IA ou pelo
     * usuário). Sem meta, todos ficam no mesmo grupo.
     */
    public static function grupoMeta($rea, bool $comMeta): int
    {
        $dados = self::paraArray($rea);

        if (! $comMeta || in_array($dados['recommended'] ?? null, ['meta_both', 'meta_one', 'meta'], true)) {
            return self::GRUPO_COMPATIVEL;
        }

        return self::statusMeta($dados) === 'falhou' ? self::GRUPO_META_INCOMPATIVEL : self::GRUPO_META_NAO_CONFERIDA;
    }

    /**
     * Grau calculado dos critérios (a mesma regra do job e das correções); REAs antigos usam a faixa.
     */
    public static function grauDe($rea): int
    {
        $dados = self::paraArray($rea);
        $criterios = $dados['explicacao']['criterios'] ?? [];

        if (! empty($criterios)) {
            return RuleClassifier::grau($criterios)['total'];
        }

        return self::GRAU_LEGADO[$dados['recommended'] ?? ''] ?? 0;
    }

    /**
     * Motivos dos ocultos: sem_meta_usuario (item de meta numa busca sem meta) e outros. Os exibidos abaixo
     * dos compatíveis com a meta são contados em `meta_nao_conferida` (a meta não pôde ser conferida),
     * `meta_incompativel` (a IA classificou como diferente) e `meta_corrigida_incompativel` (o usuário
     * informou uma meta diferente). `corrigidos` e `mudaram_faixa` contam as correções.
     *
     * @return array{faixas: array<string, int>, ocultos: int, motivos_ocultos: array<string, int>, meta_nao_conferida: int, meta_incompativel: int, meta_corrigida_incompativel: int, corrigidos: int, mudaram_faixa: int}
     */
    public static function contar(iterable $reas, bool $comMeta): array
    {
        $faixas = array_fill_keys(self::ordem($comMeta), 0);
        $ocultos = 0;
        $motivos = ['sem_meta_usuario' => 0, 'outros' => 0];
        $abaixo = ['meta_nao_conferida' => 0, 'meta_incompativel' => 0, 'meta_corrigida_incompativel' => 0];
        $corrigidos = 0;
        $mudaramFaixa = 0;

        foreach ($reas as $rea) {
            $dados = self::paraArray($rea);
            $rotulo = $dados['recommended'] ?? null;
            $faixaOriginal = $dados['explicacao']['faixa_original'] ?? null;

            if ($faixaOriginal !== null) {
                $corrigidos++;
                $mudaramFaixa += $faixaOriginal !== $rotulo ? 1 : 0;
            }

            if (self::visivel($dados, $comMeta)) {
                $faixas[$rotulo]++;

                $grupo = self::grupoMeta($dados, $comMeta);
                if ($grupo === self::GRUPO_META_NAO_CONFERIDA) {
                    $abaixo['meta_nao_conferida']++;
                } elseif ($grupo === self::GRUPO_META_INCOMPATIVEL) {
                    $abaixo[($dados['explicacao']['criterios']['meta']['fonte'] ?? null) === 'usuario' ? 'meta_corrigida_incompativel' : 'meta_incompativel']++;
                }

                continue;
            }

            $ocultos++;
            $motivos[self::motivoOculto($dados, $comMeta)]++;
        }

        return [
            'faixas' => $faixas,
            'ocultos' => $ocultos,
            'motivos_ocultos' => $motivos,
        ] + $abaixo + [
            'corrigidos' => $corrigidos,
            'mudaram_faixa' => $mudaramFaixa,
        ];
    }

    /**
     * Motivo de um REA não ser exibido (ver contar()).
     */
    public static function motivo($rea, bool $comMeta): string
    {
        return self::motivoOculto(self::paraArray($rea), $comMeta);
    }

    private static function motivoOculto(array $dados, bool $comMeta): string
    {
        $rotulo = $dados['recommended'] ?? null;

        if (! $comMeta && in_array($rotulo, ['meta_both', 'meta_one', 'meta'], true)) {
            return 'sem_meta_usuario';
        }

        return 'outros';
    }

    /**
     * Acrescenta a posição de cada REA no próprio repositório: a gravada pelo job ou, em REAs antigos,
     * a ordem em que aparecem em `Data.data` (cada job grava os seus itens de uma vez, na ordem da API).
     *
     * @return \Generator<int, array{0: mixed, 1: array, 2: int}>
     */
    private static function comPosicao(iterable $reas): \Generator
    {
        $contagem = [];

        foreach ($reas as $rea) {
            $dados = self::paraArray($rea);
            $repositorio = (string) ($dados['repositorio'] ?? '');
            $contagem[$repositorio] = ($contagem[$repositorio] ?? 0) + 1;

            yield [$rea, $dados, (int) ($dados['explicacao']['grau']['posicao'] ?? $contagem[$repositorio])];
        }
    }

    private static function statusMeta(array $dados): ?string
    {
        return $dados['explicacao']['criterios']['meta']['status'] ?? null;
    }

    private static function paraArray($rea): array
    {
        return is_array($rea) ? $rea : (json_decode(json_encode($rea), true) ?? []);
    }
}
