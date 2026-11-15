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
    public const VERSAO_REGRAS = 2;

    /**
     * Pontos que cada critério atendido soma ao grau de recomendação (docs/recomendacao.md).
     * Potências de 2: meta pesa mais que nível e tipo juntos, e nível mais que tipo. O tema não pontua:
     * ele decide se o REA entra na lista (Ranking::visivel), e um REA que não fala do tema fica oculto.
     */
    public const PESOS = ['meta' => 4, 'nivel' => 2, 'tipo' => 1];

    /**
     * Termos que mostram que um REA fala de cada tema oferecido em FindREA::TEMAS. A chave é o tema
     * normalizado (sem acento, minúsculo). Cada termo é uma expressão regular aplicada ao título e à
     * descrição normalizados (normalizar). Lista editável: um REA sem nenhum termo do tema não entra na
     * lista principal (criterioTema). Tema que não aparece aqui não reprova ninguém.
     *
     * "Reconhecimento" sozinho não entra no tema de padrões: aparece em "reconhecimento da união homoafetiva".
     */
    public const TERMOS_TEMA = [
        'algoritmos' => ['/\balgoritmos?\b/', '/\bpasso a passo\b/', '/\bsequencia de instrucoes\b/'],
        'decomposicao' => ['/\bdecomposicao(oes)?\b/', '/\bdecompor\b/', '/\bdecompoe(m)?\b/', '/\bdividir (o|um|uma) problema\b/', '/\bpartes menores\b/'],
        'reconhecimento de padroes' => ['/\bpadr(ao|oes)\b/', '/\bregularidades?\b/', '/\bsequencias? logicas?\b/'],
        'abstracao' => ['/\babstracao(oes)?\b/', '/\babstrair\b/'],
        'pensamento computacional' => ['/\bpensamento computacional\b/'],
        'programacao' => ['/\bprogramac(ao|oes)\b/', '/\bprogramar\b/', '/\bprogramando\b/'],
        'logica de programacao' => ['/\blogica (de )?programac(ao|oes)\b/'],
        'computacao desplugada' => ['/\bcomputacao desplugada\b/', '/\bdesplugad[oa]s?\b/'],
        'programacao em blocos' => ['/\bprogramac(ao|oes) (em|por) blocos\b/'],
        'scratch' => ['/\bscratch\b/'],
        'estruturas de repeticao' => ['/\bestruturas? de repetic(ao|oes)\b/', '/\blacos? de repetic(ao|oes)\b/', '/\brepeticoes?\b/'],
        'robotica educacional' => ['/\brobot(ica|ico|icas|icos)\b/', '/\brobos?\b/'],
        'inteligencia artificial' => ['/\binteligencias? artificiais?\b/', '/\bia\b/'],
    ];

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

    /**
     * O tema da busca confere quando o título ou a descrição do REA trazem algum termo de TERMOS_TEMA.
     * Tema sem termos cadastrados fica `ok` sem evidência, para nunca esvaziar uma busca por falta de
     * configuração. Tema não é corrigível: a fonte `texto` não entra em RuleClassifier::FONTES_CORRIGIVEIS.
     */
    public static function criterioTema(string $termo, string $repositorio, ?string $titulo, ?string $descricao): array
    {
        $padroes = self::TERMOS_TEMA[self::normalizar($termo)] ?? null;

        if ($padroes === null) {
            return [
                'status' => 'ok',
                'valor' => $termo,
                'evidencia' => null,
                'fonte' => 'texto',
                'repositorio' => $repositorio,
                'motivo' => 'o tema não tem termos cadastrados, então não foi conferido',
            ];
        }

        $texto = self::normalizar(($titulo ?? '').' '.($descricao ?? ''));
        $evidencia = null;

        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $texto, $m)) {
                $evidencia = $m[0];
                break;
            }
        }

        return [
            'status' => $evidencia === null ? 'falhou' : 'ok',
            'valor' => $termo,
            'evidencia' => $evidencia,
            'fonte' => 'texto',
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

    /**
     * `$origem` diz de onde veio a lista (TiposPreferidos::ORIGENS). Com a preferência da conta, a fonte é
     * `usuario`, e `inclui_colaboradores` diz se a lista também tem os tipos dos colaboradores.
     */
    public static function criterioTipo(?string $tipo, array $tiposPreferidos, string $origem = TiposPreferidos::COLABORADORES): array
    {
        $valor = self::normalizar($tipo);

        $criterio = [
            'status' => $valor !== '' && in_array($valor, $tiposPreferidos, true) ? 'ok' : 'falhou',
            'valor' => $valor,
            'esperado' => array_values($tiposPreferidos),
            'fonte' => 'colaboradores',
        ];

        if ($origem !== TiposPreferidos::COLABORADORES) {
            $criterio['fonte'] = 'usuario';
            $criterio['inclui_colaboradores'] = $origem === TiposPreferidos::CONTA_E_COLABORADORES;
        }

        return $criterio;
    }

    /**
     * O tipo do REA é comparado com a lista de tipos preferidos (e a lista pode ser editada na busca).
     * Itens de política (MEC RED, Eduplay) têm outra fonte e ficam de fora.
     */
    public static function tipoComparavel(?array $criterio): bool
    {
        return in_array($criterio['fonte'] ?? null, ['colaboradores', 'usuario'], true);
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

    /**
     * Um critério só é atendido quando foi conferido e bateu. Não avaliado, filtro de API sem retorno e
     * nível assumido (o texto não menciona etapa) não contam: nunca se afirma o que não foi verificado.
     */
    public static function atende(array $criterios, string $nome): bool
    {
        $criterio = $criterios[$nome] ?? null;

        return ($criterio['status'] ?? null) === 'ok' && ! ($criterio['assumido'] ?? false);
    }

    /**
     * Grau de recomendação: soma dos PESOS dos critérios atendidos. `maximo` considera só os critérios
     * presentes (a meta só existe quando o usuário tem meta). `posicao` é a ordem do REA no próprio
     * repositório, usada no desempate (Ranking::ordenar).
     *
     * @return array{total: int, maximo: int, pontos: array<string, int>, posicao: ?int}
     */
    public static function grau(array $criterios, ?int $posicao = null): array
    {
        $pontos = [];

        foreach (self::PESOS as $nome => $peso) {
            if (isset($criterios[$nome])) {
                $pontos[$nome] = self::atende($criterios, $nome) ? $peso : 0;
            }
        }

        return [
            'total' => array_sum($pontos),
            'maximo' => array_sum(array_intersect_key(self::PESOS, $pontos)),
            'pontos' => $pontos,
            'posicao' => $posicao,
        ];
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

    /**
     * O rótulo e o grau saem dos mesmos critérios; `posicao` é a ordem do REA no repositório (1, 2, ...).
     */
    public static function explicacao(array $criterios, string $faixa, ?int $posicao = null): array
    {
        return [
            'versao_regras' => self::VERSAO_REGRAS,
            'faixa' => $faixa,
            'grau' => self::grau($criterios, $posicao),
            'observacao' => null,
            'criterios' => $criterios,
        ];
    }
}
