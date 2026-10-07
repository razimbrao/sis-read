<?php

namespace App\Recommendation;

use Illuminate\Support\Str;

/**
 * Regras de recomendação do SisREAd. Cada regra devolve um critério explicável
 * (status, valor, esperado, fonte, evidência); o rótulo é calculado a partir dos critérios,
 * de modo que decisão e explicação vêm sempre da mesma fonte (docs/transparencia.md).
 */
class RuleClassifier
{
    public const VERSAO_REGRAS = 1;

    public const METAS = [
        'ma' => 'Aprendizagem',
        'mpa' => 'Performance-aproximação',
        'mpe' => 'Performance-evitação',
    ];

    // Mesma ordem e mesmas expressões usadas antes da refatoração.
    private const NIVEIS = [
        'educacao infantil' => '/\b(criança|infantil)\b/',
        'ensino fundamental' => '/\b(fundamental|sexto ano|6º|sétimo ano|7º|oitavo ano|8º|nono ano|9º|ef)\b/',
        'ensino medio' => '/\b(médio)\b/',
    ];

    private const NIVEL_PADRAO = 'ensino superior';

    /**
     * Etapas que o usuário pode informar ao corrigir o nível de um REA.
     */
    public const NIVEIS_VALIDOS = ['educacao infantil', 'ensino fundamental', 'ensino medio', self::NIVEL_PADRAO];

    /**
     * Fontes estimadas pelo sistema, as únicas que o usuário pode corrigir (docs/plano-escrutabilidade.md).
     */
    public const FONTES_CORRIGIVEIS = ['regex', 'llm'];

    /**
     * Identificador estável de um REA dentro da busca, usado para apontar correções.
     */
    public static function chave(string $repositorio, ?string $link, ?string $titulo): string
    {
        return substr(sha1($repositorio.'|'.($link ?? '').'|'.($titulo ?? '')), 0, 12);
    }

    /**
     * Um critério é corrigível quando foi estimado pelo sistema (ou já corrigido a partir de uma estimativa).
     */
    public static function corrigivel(?array $criterio): bool
    {
        return in_array($criterio['original']['fonte'] ?? $criterio['fonte'] ?? null, self::FONTES_CORRIGIVEIS, true);
    }

    public static function normalizar(?string $texto): string
    {
        return trim(Str::lower(Str::ascii($texto ?? '')));
    }

    /**
     * Achata, normaliza e deduplica a lista de tipos preferidos.
     */
    public static function normalizarTipos(array $tipos): array
    {
        $normalizados = [];

        array_walk_recursive($tipos, function ($tipo) use (&$normalizados) {
            $tipo = self::normalizar((string) $tipo);

            if ($tipo === '') {
                return;
            }

            $normalizados[] = $tipo;

            if ($tipo === 'e-book') {
                $normalizados[] = 'livro digital';
            }
        });

        return array_values(array_unique($normalizados));
    }

    /**
     * @return array{valor: string, evidencia: ?string, assumido: bool}
     */
    public static function inferirNivel(?string $titulo, ?string $descricao): array
    {
        $texto = mb_strtolower(($titulo ?? '').' '.($descricao ?? ''), 'UTF-8');

        foreach (self::NIVEIS as $nivel => $regex) {
            if (preg_match($regex, $texto, $m)) {
                return ['valor' => $nivel, 'evidencia' => $m[1], 'assumido' => false];
            }
        }

        return ['valor' => self::NIVEL_PADRAO, 'evidencia' => null, 'assumido' => true];
    }

    public static function criterioTema(string $termo, string $repositorio): array
    {
        return [
            'status' => 'ok',
            'valor' => $termo,
            'fonte' => 'busca',
            'repositorio' => $repositorio,
        ];
    }

    public static function criterioNivel(string $perfil, array $inferido): array
    {
        $esperado = self::normalizar($perfil);

        return [
            'status' => $inferido['valor'] === $esperado ? 'ok' : 'falhou',
            'valor' => $inferido['valor'],
            'esperado' => $esperado,
            'fonte' => 'regex',
            'evidencia' => $inferido['evidencia'],
            'assumido' => $inferido['assumido'],
        ];
    }

    public static function criterioTipo(?string $tipo, array $tiposPreferidos): array
    {
        $valor = self::normalizar($tipo);

        return [
            'status' => $valor !== '' && in_array($valor, $tiposPreferidos, true) ? 'ok' : 'falhou',
            'valor' => $valor,
            'esperado' => array_values($tiposPreferidos),
            'fonte' => 'colaboradores',
        ];
    }

    /**
     * Critério de meta a partir da classificação do LLM ("Não classificado" = não avaliado).
     * `evidencia`: motivo de não ter sido avaliado, ou observação sobre a classificação (ex.: cache).
     */
    public static function criterioMeta(string $metaUsuario, ?string $classificacao, ?string $modelo = null, ?float $segundos = null, ?string $evidencia = null): array
    {
        $base = [
            'esperado' => $metaUsuario,
            'fonte' => 'llm',
            'modelo' => $modelo,
            'duracao' => $segundos === null ? null : round($segundos, 2),
        ];

        if ($evidencia !== null) {
            $base['evidencia'] = $evidencia;
        }

        if ($classificacao === null || $classificacao === '' || $classificacao === 'Não classificado') {
            return $base + ['status' => 'nao_avaliado', 'valor' => null];
        }

        return $base + [
            'status' => self::casaMeta($classificacao, $metaUsuario) ? 'ok' : 'falhou',
            'valor' => $classificacao,
        ];
    }

    /**
     * Compara a classificação textual com o código da meta, tolerando acento, espaço e hífen.
     */
    public static function casaMeta(string $classificacao, ?string $meta): bool
    {
        $c = str_replace([' ', '-'], '_', self::normalizar($classificacao));

        return match ($meta) {
            'ma' => str_contains($c, 'aprendizagem'),
            'mpa' => str_contains($c, 'aproximacao'),
            'mpe' => str_contains($c, 'evitacao'),
            default => false,
        };
    }

    public static function atende(array $criterios, string $nome): bool
    {
        return in_array($criterios[$nome]['status'] ?? null, ['ok', 'filtro_api'], true);
    }

    public static function rotular(array $criterios, bool $comMeta): string
    {
        $nivel = self::atende($criterios, 'nivel');
        $tipo = self::atende($criterios, 'tipo');

        if ($comMeta && self::atende($criterios, 'meta')) {
            if ($nivel && $tipo) {
                return 'meta_both';
            }
            if ($nivel || $tipo) {
                return 'meta_one';
            }

            return 'meta';
        }

        if ($nivel && $tipo) {
            return 'both';
        }
        if ($nivel) {
            return 'profile';
        }

        return 'interest';
    }

    public static function explicacao(array $criterios, string $faixa, ?string $observacao = null): array
    {
        return [
            'versao_regras' => self::VERSAO_REGRAS,
            'faixa' => $faixa,
            'observacao' => $observacao,
            'criterios' => $criterios,
        ];
    }
}
