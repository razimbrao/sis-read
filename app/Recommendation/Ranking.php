<?php

namespace App\Recommendation;

/**
 * Ordem das faixas de recomendação. Mantenha em sincronia com os rótulos de RuleClassifier.
 */
class Ranking
{
    public const ORDEM_COM_META = ['meta_both', 'meta_one', 'meta'];

    public const ORDEM_SEM_META = ['both', 'profile', 'interest'];

    public static function ordem(bool $comMeta): array
    {
        return $comMeta ? self::ORDEM_COM_META : self::ORDEM_SEM_META;
    }

    /**
     * Ordena por faixa mantendo a ordem de chegada dentro de cada faixa; itens fora da ordem são ocultados.
     */
    public static function ordenar(iterable $reas, bool $comMeta): array
    {
        $faixas = array_fill_keys(self::ordem($comMeta), []);

        foreach ($reas as $rea) {
            $rotulo = self::rotulo($rea);

            if (array_key_exists($rotulo, $faixas)) {
                $faixas[$rotulo][] = $rea;
            }
        }

        return array_merge(...array_values($faixas));
    }

    /**
     * Motivos dos ocultos: meta_nao_avaliada (IA não classificou), meta_incompativel,
     * meta_corrigida_incompativel (o usuário informou outra meta), sem_meta_usuario
     * (item de meta numa busca sem meta) e outros. `corrigidos` e `mudaram_faixa` contam as correções do usuário.
     *
     * @return array{faixas: array<string, int>, ocultos: int, motivos_ocultos: array<string, int>, corrigidos: int, mudaram_faixa: int}
     */
    public static function contar(iterable $reas, bool $comMeta): array
    {
        $faixas = array_fill_keys(self::ordem($comMeta), 0);
        $ocultos = 0;
        $motivos = ['meta_nao_avaliada' => 0, 'meta_incompativel' => 0, 'meta_corrigida_incompativel' => 0, 'sem_meta_usuario' => 0, 'outros' => 0];
        $corrigidos = 0;
        $mudaramFaixa = 0;

        foreach ($reas as $rea) {
            $rotulo = self::rotulo($rea);
            $faixaOriginal = self::paraArray($rea)['explicacao']['faixa_original'] ?? null;

            if ($faixaOriginal !== null) {
                $corrigidos++;
                $mudaramFaixa += $faixaOriginal !== $rotulo ? 1 : 0;
            }

            if (array_key_exists($rotulo, $faixas)) {
                $faixas[$rotulo]++;

                continue;
            }

            $ocultos++;
            $motivos[self::motivoOculto($rea, $rotulo, $comMeta)]++;
        }

        return [
            'faixas' => $faixas,
            'ocultos' => $ocultos,
            'motivos_ocultos' => $motivos,
            'corrigidos' => $corrigidos,
            'mudaram_faixa' => $mudaramFaixa,
        ];
    }

    /**
     * Motivo de um REA não ser exibido (ver contar()).
     */
    public static function motivo($rea, bool $comMeta): string
    {
        return self::motivoOculto($rea, self::rotulo($rea), $comMeta);
    }

    private static function motivoOculto($rea, ?string $rotulo, bool $comMeta): string
    {
        if (! $comMeta) {
            return in_array($rotulo, self::ORDEM_COM_META, true) ? 'sem_meta_usuario' : 'outros';
        }

        $meta = self::paraArray($rea)['explicacao']['criterios']['meta'] ?? [];

        return match ($meta['status'] ?? null) {
            'nao_avaliado' => 'meta_nao_avaliada',
            'falhou' => ($meta['fonte'] ?? null) === 'usuario' ? 'meta_corrigida_incompativel' : 'meta_incompativel',
            default => 'outros',
        };
    }

    private static function paraArray($rea): array
    {
        return is_array($rea) ? $rea : json_decode(json_encode($rea), true);
    }

    private static function rotulo($rea): ?string
    {
        return is_array($rea) ? ($rea['recommended'] ?? null) : ($rea->recommended ?? null);
    }
}
