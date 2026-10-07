<?php

namespace App\Recommendation;

/**
 * Converte a estrutura `explicacao` gravada pelos jobs em textos (template-based).
 */
class ExplanationRenderer
{
    public const ICONES = [
        'ok' => '✓',
        'falhou' => '✗',
        'nao_avaliado' => '?',
        'filtro_api' => '▽',
    ];

    /**
     * Marca de critério corrigido pelo usuário, exibida ao lado do ícone do status recalculado.
     */
    public const MARCA_CORRECAO = '✎';

    public const NOMES = [
        'tema' => 'tema',
        'nivel' => 'nível',
        'tipo' => 'tipo',
        'meta' => 'meta',
    ];

    public const NIVEIS_LEGIVEIS = [
        'educacao infantil' => 'educação infantil',
        'ensino fundamental' => 'ensino fundamental',
        'ensino medio' => 'ensino médio',
        'ensino superior' => 'ensino superior',
    ];

    public const FAIXAS = [
        'meta_both' => ['titulo' => 'Meta + nível e tipo', 'descricao' => 'compatível com a sua meta, com nível do seu perfil e tipo preferido'],
        'meta_one' => ['titulo' => 'Meta + nível ou tipo', 'descricao' => 'compatível com a sua meta e com o nível ou o tipo'],
        'meta' => ['titulo' => 'Meta', 'descricao' => 'compatível com a sua meta, mas não com nível nem tipo'],
        'both' => ['titulo' => 'Nível e tipo', 'descricao' => 'nível do seu perfil e tipo entre os preferidos'],
        'profile' => ['titulo' => 'Nível', 'descricao' => 'o nível bate com o seu perfil, mas o tipo não está entre os preferidos'],
        'interest' => ['titulo' => 'Só tema', 'descricao' => 'resultado da busca, sem compatibilidade de nível e tipo'],
    ];

    /**
     * Título e descrição da faixa. Quando a posição vem de política do repositório (há `observacao`),
     * a descrição genérica da faixa não é afirmada, pois os critérios não foram conferidos.
     */
    public static function faixa(?string $rotulo, $explicacao = null): array
    {
        $faixa = self::FAIXAS[$rotulo] ?? ['titulo' => 'Sem faixa', 'descricao' => 'rótulo desconhecido'];

        if (! empty(self::paraArray($explicacao)['observacao'])) {
            return [
                'titulo' => $faixa['titulo'].' (política)',
                'descricao' => 'posição definida por política do repositório, não pelos critérios conferidos',
            ];
        }

        return $faixa;
    }

    /**
     * `corrigido`: o usuário informou o valor (ou a lista de tipos). `corrigivel`: o critério foi estimado
     * pelo sistema e pode receber uma correção.
     *
     * @return array<int, array{criterio: string, status: string, icone: string, texto: string, corrigido: bool, corrigivel: bool}>
     */
    public static function linhas($explicacao): array
    {
        $explicacao = self::paraArray($explicacao);

        if (empty($explicacao['criterios'])) {
            return [[
                'criterio' => '',
                'status' => 'nao_avaliado',
                'icone' => self::ICONES['nao_avaliado'],
                'texto' => 'Explicação indisponível para esta busca.',
                'corrigido' => false,
                'corrigivel' => false,
            ]];
        }

        $politica = ! empty($explicacao['observacao']);
        $linhas = [];

        foreach ($explicacao['criterios'] as $nome => $c) {
            $status = $c['status'] ?? 'nao_avaliado';

            $linhas[] = [
                'criterio' => $nome,
                'status' => $status,
                'icone' => self::ICONES[$status] ?? '?',
                'texto' => self::texto($nome, $c),
                'corrigido' => self::corrigido($c),
                'corrigivel' => ! $politica && in_array($nome, ['nivel', 'meta'], true) && RuleClassifier::corrigivel($c),
            ];
        }

        return $linhas;
    }

    public static function resumo($explicacao): string
    {
        $explicacao = self::paraArray($explicacao);

        if (empty($explicacao['criterios'])) {
            return 'Explicação indisponível para esta busca.';
        }

        $grupos = ['Atende' => [], 'Não atende' => [], 'Não verificado' => []];

        foreach ($explicacao['criterios'] as $nome => $c) {
            $grupo = match ($c['status'] ?? null) {
                'ok', 'filtro_api' => 'Atende',
                'falhou' => 'Não atende',
                default => 'Não verificado',
            };
            $grupos[$grupo][] = (self::NOMES[$nome] ?? $nome).(self::corrigido($c) ? ' (corrigido por você)' : '');
        }

        $partes = [];
        foreach ($grupos as $rotulo => $nomes) {
            if ($nomes) {
                $partes[] = $rotulo.': '.implode(', ', $nomes).'.';
            }
        }

        if (! empty($explicacao['observacao'])) {
            $partes[] = $explicacao['observacao'];
        }

        return implode(' ', $partes);
    }

    /**
     * Efeito das correções do usuário sobre a faixa do REA; null quando não há correção.
     */
    public static function mudancaFaixa($explicacao): ?string
    {
        $explicacao = self::paraArray($explicacao);
        $original = $explicacao['faixa_original'] ?? null;

        if ($original === null) {
            return null;
        }

        if ($original === ($explicacao['faixa'] ?? null)) {
            return 'Sua correção não mudou a faixa deste REA.';
        }

        return 'Faixa alterada pela sua correção: antes '.self::faixa($original)['titulo']
            .', agora '.self::faixa($explicacao['faixa'] ?? null)['titulo'].'.';
    }

    /**
     * Só critérios ainda estimados (regex/IA) geram aviso; um critério corrigido tem fonte `usuario`.
     */
    public static function avisos($explicacao): array
    {
        $explicacao = self::paraArray($explicacao);

        foreach ($explicacao['criterios'] ?? [] as $c) {
            if (in_array($c['fonte'] ?? null, ['regex', 'llm'], true)
                && in_array($c['status'] ?? null, ['ok', 'falhou'], true)) {
                return ['Estimativa automática (regex/IA): pode conter erros. A classificação por IA roda num modelo local e tende a favorecer “Aprendizagem”.'];
            }
        }

        return [];
    }

    private static function texto(string $nome, array $c): string
    {
        $status = $c['status'] ?? 'nao_avaliado';

        return match ($nome) {
            'tema' => self::textoTema($c),
            'nivel' => self::textoNivel($status, $c),
            'tipo' => self::textoTipo($status, $c),
            'meta' => self::textoMeta($status, $c),
            default => $c['evidencia'] ?? '',
        };
    }

    private static function textoTema(array $c): string
    {
        return "Resultado da busca por “{$c['valor']}” no ".($c['repositorio'] ?? 'repositório').'.';
    }

    private static function textoNivel(string $status, array $c): string
    {
        $valor = self::nivel($c['valor'] ?? null);
        $esperado = self::nivel($c['esperado'] ?? null);
        $assumido = $c['assumido'] ?? false;

        if (($c['fonte'] ?? null) === 'usuario') {
            $original = $c['original'] ?? [];
            $antes = ($original['assumido'] ?? false) ? 'assumido' : 'estimado';

            return "Nível {$valor}, informado por você (o sistema tinha {$antes} ".self::nivel($original['valor'] ?? null).'). '
                .($status === 'ok' ? 'Igual ao seu perfil.' : "Diferente do seu perfil ({$esperado}).");
        }

        return match (true) {
            $status === 'filtro_api' => "O MEC RED filtrou a busca pela etapa do seu perfil ({$esperado}).",
            $status === 'nao_avaliado' => 'Nível não verificado: '.($c['evidencia'] ?? 'informação indisponível').'.',
            $status === 'ok' && $assumido => "Nível {$valor}, igual ao seu perfil, mas assumido: o texto não menciona nenhuma etapa.",
            $status === 'ok' => "Nível {$valor}, igual ao seu perfil: identificado pelo trecho “{$c['evidencia']}” no título ou na descrição.",
            $assumido => "O texto não menciona nenhuma etapa; o sistema assume {$valor}, diferente do seu perfil ({$esperado}).",
            default => "Nível estimado {$valor} (trecho “{$c['evidencia']}”), diferente do seu perfil ({$esperado}).",
        };
    }

    private static function textoTipo(string $status, array $c): string
    {
        $valor = ($c['valor'] ?? '') !== '' ? $c['valor'] : 'não informado';
        $lista = implode(', ', $c['esperado'] ?? []);

        if (($c['origem_esperado'] ?? null) === 'usuario') {
            return match (true) {
                $lista === '' => "Tipo {$valor}: você não definiu nenhum tipo preferido.",
                $status === 'ok' => "Tipo {$valor}, entre os tipos preferidos que você definiu ({$lista}).",
                default => "Tipo {$valor} não está entre os tipos preferidos que você definiu ({$lista}).",
            };
        }

        return match (true) {
            $status === 'nao_avaliado' => 'Tipo não comparado com os preferidos: '.($c['evidencia'] ?? 'informação indisponível').'.',
            $status === 'ok' => "Tipo {$valor}, entre os tipos preferidos ({$lista}).",
            $lista === '' => "Tipo {$valor}: nenhum tipo preferido foi encontrado para esta busca.",
            default => "Tipo {$valor} não está entre os tipos preferidos ({$lista}).",
        };
    }

    private static function textoMeta(string $status, array $c): string
    {
        $esperado = RuleClassifier::METAS[$c['esperado'] ?? ''] ?? ($c['esperado'] ?? '');
        $fonte = $c['fonte'] ?? null;

        if ($fonte === 'usuario') {
            $original = $c['original'] ?? [];
            $antes = ($original['status'] ?? null) === 'nao_avaliado' || empty($original['valor'])
                ? 'a IA não tinha conseguido classificar'
                : "a IA tinha classificado como {$original['valor']}";

            return "Meta {$c['valor']}, informada por você ({$antes}). "
                .($status === 'ok' ? "Compatível com a sua meta ({$esperado})." : "Sua meta é {$esperado}.");
        }

        if ($status === 'nao_avaliado' && ! empty($c['evidencia'])) {
            return "Meta não verificada: {$c['evidencia']}.";
        }

        if ($fonte === 'filtro_api') {
            return "O MEC RED filtrou por tipos de objeto associados à sua meta ({$esperado}).";
        }

        if ($fonte === 'padrao_repositorio') {
            return ($c['evidencia'] ?? '').($status === 'ok' ? " Compatível com a sua meta ({$esperado})." : " Sua meta é {$esperado}.");
        }

        $modelo = empty($c['modelo']) ? '' : " Modelo: {$c['modelo']}".(isset($c['duracao']) ? ", em {$c['duracao']}s." : '.');
        // Ex.: classificação reaproveitada do cache, ou feita só pelo título.
        $modelo .= empty($c['evidencia']) ? '' : ' '.ucfirst($c['evidencia']).'.';

        return match ($status) {
            'ok' => "Classificado por IA como {$c['valor']}, compatível com a sua meta ({$esperado}).".$modelo,
            'falhou' => "Classificado por IA como {$c['valor']}; sua meta é {$esperado}.".$modelo,
            default => 'Não foi possível classificar a meta deste recurso (IA indisponível ou resposta inválida).'.$modelo,
        };
    }

    private static function corrigido(array $c): bool
    {
        return isset($c['original']) || isset($c['esperado_original']);
    }

    private static function nivel(?string $nivel): string
    {
        return self::NIVEIS_LEGIVEIS[$nivel] ?? (string) $nivel;
    }

    private static function paraArray($valor): array
    {
        if ($valor === null) {
            return [];
        }

        return is_array($valor) ? $valor : json_decode(json_encode($valor), true);
    }
}
