<?php

/*
 * Classificação de meta dos REAs por LLM (docs/integracoes.md, seção LLM).
 * O provedor é substituível: implemente App\Recommendation\Llm\ProvedorLlm e registre-o no AppServiceProvider.
 */
return [

    // ollama | groq | nenhum (desliga a classificação: o critério de meta fica "não avaliado", com o motivo)
    'provedor' => env('LLM_PROVEDOR', 'ollama'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'modelo' => env('OLLAMA_MODELO', 'gemma3:4b'),
        // Quanto tempo o Ollama mantém o modelo carregado depois da última chamada.
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '10m'),
        // REAs por chamada. Modelos locais pequenos se perdem com vários; fica 1 (um prompt por REA).
        'itens_por_chamada' => (int) env('OLLAMA_ITENS_POR_CHAMADA', 1),
    ],

    // Groq (formato da OpenAI). Plano gratuito sem cartão, com limites por minuto e por dia em
    // console.groq.com/settings/limits. Modelos disponíveis em console.groq.com/docs/models.
    'groq' => [
        'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
        'chave' => env('GROQ_API_KEY'),
        'modelo' => env('GROQ_MODELO', 'openai/gpt-oss-20b'),
        // Quanto um lote pode esperar, somando as pausas pedidas pelo Groq quando o limite por minuto estoura.
        'espera_max_segundos' => (int) env('GROQ_ESPERA_MAX_SEGUNDOS', 60),
        // REAs por chamada. Os critérios vão uma vez por chamada, o que cabe mais REAs no limite de tokens por minuto.
        'itens_por_chamada' => (int) env('GROQ_ITENS_POR_CHAMADA', 10),
        // Parâmetros próprios do Groq: raciocínio curto e sem devolver o raciocínio, que não é usado.
        'extras' => [
            'reasoning_effort' => env('GROQ_ESFORCO', 'low'),
            'include_reasoning' => false,
        ],
    ],

    // Tempo máximo de cada chamada, em segundos.
    'timeout' => (int) env('LLM_TIMEOUT', 10),

    // Antes da primeira chamada de cada job, carrega o modelo (no Ollama, ~30s a frio) com este timeout.
    // Sem isso, as primeiras chamadas estouram o timeout e o job desiste da LLM.
    'aquecer' => (bool) env('LLM_AQUECER', true),
    'timeout_aquecimento' => (int) env('LLM_TIMEOUT_AQUECIMENTO', 60),

    // Chamadas simultâneas. No Ollama, só ajuda se OLLAMA_NUM_PARALLEL permitir.
    'concorrencia' => (int) env('LLM_CONCORRENCIA', 4),

    // Tempo total de classificação por job. Precisa caber, com as chamadas aos repositórios,
    // no `queue:work --timeout=600`. Itens que sobrarem ficam com a meta "não avaliada".
    'orcamento_segundos' => (int) env('LLM_ORCAMENTO_SEGUNDOS', 240),

    // Falhas seguidas que fazem o job parar de consultar a LLM.
    'falhas_seguidas_max' => (int) env('LLM_FALHAS_SEGUIDAS_MAX', 3),

    // Depois de desistir, os outros jobs também não consultam a LLM por este tempo (segundos).
    'pausa_apos_falha_segundos' => (int) env('LLM_PAUSA_APOS_FALHA_SEGUNDOS', 60),

    // Por quantos dias a classificação de um REA (por chave) é reaproveitada entre buscas. 0 desliga o cache.
    'cache_dias' => (int) env('LLM_CACHE_DIAS', 30),
];
