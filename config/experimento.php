<?php

/*
 * Experimento de explicabilidade (docs/feature-flags.md).
 *
 * Cada participante pertence a um grupo, e o grupo define quais funcionalidades de explicabilidade
 * aparecem. A recomendação e a ordenação são as mesmas em todos os grupos: muda só o que é mostrado.
 */

return [

    /*
     * Funcionalidades ligadas em cada grupo. As flags possíveis estão em App\Experimento\Experimento::FLAGS.
     */
    'grupos' => [
        'controle' => [],
        'transparencia' => [
            'explicacao-rea',
            'painel-ordenacao',
            'reas-ocultos',
            'painel-contexto',
            'progresso-busca',
        ],
        'escrutabilidade' => [
            'explicacao-rea',
            'painel-ordenacao',
            'reas-ocultos',
            'painel-contexto',
            'progresso-busca',
            'escrutabilidade',
        ],
    ],

    /*
     * Força um grupo para todo mundo, ignorando links e sorteio (nada é gravado).
     * `controle` desliga tudo; `escrutabilidade` liga tudo. Vazio: experimento normal.
     */
    'forcar_grupo' => env('EXPERIMENTO_FORCAR_GRUPO'),

    /*
     * Liga ou desliga flags isoladas por cima do grupo, para ablações.
     * Formato: "progresso-busca=0,reas-ocultos=1".
     */
    'forcar_flags' => env('EXPERIMENTO_FORCAR_FLAGS', ''),

    /*
     * Parâmetro de URL que define o grupo: /?grupo=<código>. O pesquisador distribui um link por grupo.
     */
    'parametro_url' => env('EXPERIMENTO_PARAMETRO', 'grupo'),

    /*
     * Código aceito no link de cada grupo. Troque por códigos opacos para o participante não ver o
     * nome do grupo (ex.: EXPERIMENTO_CODIGO_CONTROLE=k7q2).
     */
    'codigos' => [
        'controle' => env('EXPERIMENTO_CODIGO_CONTROLE', 'controle'),
        'transparencia' => env('EXPERIMENTO_CODIGO_TRANSPARENCIA', 'transparencia'),
        'escrutabilidade' => env('EXPERIMENTO_CODIGO_ESCRUTABILIDADE', 'escrutabilidade'),
    ],

    /*
     * Grupo de quem chega sem link e ainda não tem grupo: `sorteio` (uniforme entre os grupos,
     * gravado e estável) ou o nome de um grupo.
     */
    'sem_link' => env('EXPERIMENTO_SEM_LINK', 'sorteio'),

];
