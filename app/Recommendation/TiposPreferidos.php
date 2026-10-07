<?php

namespace App\Recommendation;

use App\Models\Correction;
use App\Models\User;

/**
 * De onde vem a lista de tipos preferidos de uma busca (docs/plano-escrutabilidade.md §15).
 *
 * - Sem preferência na conta (visitante ou usuário que nunca salvou): tipos dos colaboradores.
 * - Com preferência na conta: ela **substitui** os tipos dos colaboradores.
 * - Com preferência e "incluir também os tipos dos colaboradores": a união das duas listas.
 */
class TiposPreferidos
{
    public const COLABORADORES = 'colaboradores';

    public const CONTA = 'conta';

    public const CONTA_E_COLABORADORES = 'conta+colaboradores';

    public const ORIGENS = [self::COLABORADORES, self::CONTA, self::CONTA_E_COLABORADORES];

    /**
     * @return array{tipos: array<int, string>, origem: string}
     */
    public static function resolver(?array $conta, bool $incluirColaboradores, array $tiposColaboradores): array
    {
        $conta = RuleClassifier::normalizarTipos($conta ?? []);
        $tiposColaboradores = RuleClassifier::normalizarTipos($tiposColaboradores);

        if (! $conta) {
            return ['tipos' => $tiposColaboradores, 'origem' => self::COLABORADORES];
        }

        if ($incluirColaboradores) {
            return [
                'tipos' => array_values(array_unique(array_merge($conta, $tiposColaboradores))),
                'origem' => self::CONTA_E_COLABORADORES,
            ];
        }

        return ['tipos' => $conta, 'origem' => self::CONTA];
    }

    /**
     * Tipos salvos na conta (normalizados); lista vazia quando o usuário não salvou preferência.
     */
    public static function daConta(?User $user): array
    {
        return RuleClassifier::normalizarTipos($user?->tipos_preferidos ?? []);
    }

    /**
     * Grava a preferência na conta e registra a mudança em `corrections` (alvo `tipos`, ação
     * `salvar_preferencia`). Lista vazia remove a preferência: as buscas voltam aos tipos dos colaboradores.
     */
    public static function salvar(User $user, array $tipos, bool $incluirColaboradores, $searchedAt = null): void
    {
        $tipos = RuleClassifier::normalizarTipos($tipos);
        $anterior = self::daConta($user);

        $user->forceFill([
            'tipos_preferidos' => $tipos ?: null,
            'incluir_tipos_colaboradores' => $tipos && $incluirColaboradores,
        ])->save();

        Correction::create([
            'searched_at' => $searchedAt,
            'user_id' => $user->id,
            'acao' => 'salvar_preferencia',
            'alvo' => 'tipos',
            'valor_anterior' => $anterior,
            'valor_novo' => $tipos,
        ]);
    }
}
