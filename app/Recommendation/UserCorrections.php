<?php

namespace App\Recommendation;

use InvalidArgumentException;

/**
 * Correções do usuário sobre o que o sistema estimou (escrutabilidade, docs/plano-escrutabilidade.md).
 *
 * Funções puras: recebem um REA de `Data.data` (array) e devolvem o REA corrigido. O critério corrigido
 * guarda o original do job em `original`, e o rótulo e o grau são recalculados por RuleClassifier,
 * de modo que decisão e explicação continuam vindo da mesma fonte.
 */
class UserCorrections
{
    /**
     * O usuário informa a etapa do REA; o status é recalculado contra o perfil, como no job.
     */
    public static function corrigirNivel(array $rea, string $nivel): array
    {
        if (! in_array($nivel, RuleClassifier::NIVEIS_VALIDOS, true)) {
            throw new InvalidArgumentException("Nível inválido: {$nivel}");
        }

        $original = self::originalCorrigivel($rea, 'nivel');

        if ($original['valor'] === $nivel) {
            return self::desfazer($rea, 'nivel');
        }

        return self::aplicar($rea, 'nivel', [
            'status' => $nivel === $original['esperado'] ? 'ok' : 'falhou',
            'valor' => $nivel,
            'esperado' => $original['esperado'],
            'fonte' => 'usuario',
            'original' => $original,
        ]);
    }

    /**
     * O usuário informa a meta do REA (código ma, mpa ou mpe); o status é recalculado contra a meta dele.
     */
    public static function corrigirMeta(array $rea, string $meta): array
    {
        if (! array_key_exists($meta, RuleClassifier::METAS)) {
            throw new InvalidArgumentException("Meta inválida: {$meta}");
        }

        $original = self::originalCorrigivel($rea, 'meta');

        // Escolher o mesmo que a IA classificou equivale a desfazer.
        if (($original['status'] ?? null) !== 'nao_avaliado'
            && RuleClassifier::casaMeta((string) ($original['valor'] ?? ''), $meta)) {
            return self::desfazer($rea, 'meta');
        }

        $valor = RuleClassifier::METAS[$meta];

        return self::aplicar($rea, 'meta', [
            'status' => RuleClassifier::casaMeta($valor, $original['esperado'] ?? null) ? 'ok' : 'falhou',
            'valor' => $valor,
            'esperado' => $original['esperado'] ?? null,
            'fonte' => 'usuario',
            'original' => $original,
        ]);
    }

    /**
     * Troca a lista de tipos preferidos e recalcula o critério de tipo. Itens cujo tipo não é comparado
     * com os preferidos (política do repositório) voltam inalterados.
     */
    public static function redefinirTipos(array $rea, array $tipos): array
    {
        $criterio = $rea['explicacao']['criterios']['tipo'] ?? null;

        if (($criterio['fonte'] ?? null) !== 'colaboradores') {
            return $rea;
        }

        $tipos = RuleClassifier::normalizarTipos($tipos);
        $originais = $criterio['esperado_original'] ?? $criterio['esperado'] ?? [];

        unset($criterio['esperado_original'], $criterio['origem_esperado']);

        $criterio['esperado'] = $tipos;
        $criterio['status'] = ($criterio['valor'] ?? '') !== '' && in_array($criterio['valor'], $tipos, true) ? 'ok' : 'falhou';

        if (self::mesmaLista($tipos, $originais)) {
            $criterio['esperado'] = $originais;
        } else {
            $criterio['origem_esperado'] = 'usuario';
            $criterio['esperado_original'] = $originais;
        }

        return self::aplicar($rea, 'tipo', $criterio);
    }

    /**
     * Restaura o que o sistema tinha calculado para o critério.
     */
    public static function desfazer(array $rea, string $nome): array
    {
        $criterio = $rea['explicacao']['criterios'][$nome] ?? null;

        if ($nome === 'tipo') {
            return isset($criterio['esperado_original'])
                ? self::redefinirTipos($rea, $criterio['esperado_original'])
                : $rea;
        }

        if (! isset($criterio['original'])) {
            return $rea;
        }

        return self::aplicar($rea, $nome, $criterio['original']);
    }

    public static function corrigido(array $rea): bool
    {
        foreach ($rea['explicacao']['criterios'] ?? [] as $criterio) {
            if (isset($criterio['original']) || isset($criterio['esperado_original'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Critério do job (sem correção) para um nome; falha se não houver critério estimado para corrigir.
     */
    private static function originalCorrigivel(array $rea, string $nome): array
    {
        $criterio = $rea['explicacao']['criterios'][$nome] ?? null;

        if (! empty($rea['explicacao']['observacao']) || ! RuleClassifier::corrigivel($criterio)) {
            throw new InvalidArgumentException("O critério {$nome} deste REA não pode ser corrigido.");
        }

        return $criterio['original'] ?? $criterio;
    }

    /**
     * Grava o critério e recalcula o rótulo e o grau. `faixa_original` guarda o rótulo do job enquanto houver correção.
     */
    private static function aplicar(array $rea, string $nome, array $criterio): array
    {
        $faixaOriginal = $rea['explicacao']['faixa_original'] ?? $rea['recommended'] ?? null;

        $rea['explicacao']['criterios'][$nome] = $criterio;

        // O job só cria o critério de meta quando a busca tem meta; é o mesmo `comMeta` que ele usou.
        $criterios = $rea['explicacao']['criterios'];
        $rea['recommended'] = RuleClassifier::rotular($criterios, isset($criterios['meta']));
        $rea['explicacao']['faixa'] = $rea['recommended'];
        $rea['explicacao']['grau'] = RuleClassifier::grau($criterios, $rea['explicacao']['grau']['posicao'] ?? null);

        if (self::corrigido($rea)) {
            $rea['explicacao']['faixa_original'] = $faixaOriginal;
        } else {
            unset($rea['explicacao']['faixa_original']);
        }

        return $rea;
    }

    private static function mesmaLista(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }
}
