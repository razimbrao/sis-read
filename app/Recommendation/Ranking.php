<?php

namespace App\Recommendation;

/**
 * Ordenação da lista pelo grau de recomendação (RuleClassifier::grau). Os rótulos são faixas do grau;
 * mantenha em sincronia com RuleClassifier::rotular e ExplanationRenderer::FAIXAS.
 */
class Ranking
{
    /**
     * Com meta, as três últimas faixas reúnem os REAs cuja meta não pôde ser conferida: aparecem
     * abaixo dos compatíveis. Só a meta conferida como incompatível tira o REA da lista.
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
     * Ordena os REAs visíveis por: (1) grau, decrescente; (2) posição no próprio repositório, crescente,
     * o que intercala os repositórios; (3) nome do repositório; (4) chave. A ordem de chegada dos jobs
     * não influi. Itens ocultos (ver visivel()) ficam de fora.
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
                'chave' => [-self::grauDe($dados), $posicao, (string) ($dados['repositorio'] ?? ''), (string) ($dados['chave'] ?? '')],
            ];
        }

        usort($itens, fn ($a, $b) => $a['chave'] <=> $b['chave']);

        return array_column($itens, 'rea');
    }

    /**
     * Um REA aparece quando a faixa dele está na ordem do contexto e, com meta, quando a meta dele não
     * foi conferida como incompatível.
     */
    public static function visivel($rea, bool $comMeta): bool
    {
        $dados = self::paraArray($rea);

        if (! in_array($dados['recommended'] ?? null, self::ordem($comMeta), true)) {
            return false;
        }

        return ! $comMeta || self::statusMeta($dados) !== 'falhou';
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
     * Motivos dos ocultos: meta_incompativel, meta_corrigida_incompativel (o usuário informou outra meta),
     * sem_meta_usuario (item de meta numa busca sem meta) e outros. `meta_nao_conferida` conta os exibidos
     * no fim da lista porque a meta não pôde ser conferida. `corrigidos` e `mudaram_faixa` contam as correções.
     *
     * @return array{faixas: array<string, int>, ocultos: int, motivos_ocultos: array<string, int>, meta_nao_conferida: int, corrigidos: int, mudaram_faixa: int}
     */
    public static function contar(iterable $reas, bool $comMeta): array
    {
        $faixas = array_fill_keys(self::ordem($comMeta), 0);
        $ocultos = 0;
        $motivos = ['meta_incompativel' => 0, 'meta_corrigida_incompativel' => 0, 'sem_meta_usuario' => 0, 'outros' => 0];
        $metaNaoConferida = 0;
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
                $metaNaoConferida += $comMeta && in_array($rotulo, self::ORDEM_SEM_META, true) ? 1 : 0;

                continue;
            }

            $ocultos++;
            $motivos[self::motivoOculto($dados, $comMeta)]++;
        }

        return [
            'faixas' => $faixas,
            'ocultos' => $ocultos,
            'motivos_ocultos' => $motivos,
            'meta_nao_conferida' => $metaNaoConferida,
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

        if (! $comMeta) {
            return in_array($rotulo, ['meta_both', 'meta_one', 'meta'], true) ? 'sem_meta_usuario' : 'outros';
        }

        if (self::statusMeta($dados) === 'falhou' && in_array($rotulo, self::ORDEM_COM_META, true)) {
            return ($dados['explicacao']['criterios']['meta']['fonte'] ?? null) === 'usuario'
                ? 'meta_corrigida_incompativel'
                : 'meta_incompativel';
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
