<?php

namespace App\Recommendation\Llm;

/**
 * Provedor de LLM usado na classificação de meta. Trocar de provedor (Ollama local, LLM hospedada)
 * é implementar esta interface e registrá-la no AppServiceProvider.
 */
interface ProvedorLlm
{
    /**
     * Nome do modelo, gravado na explicação de cada REA e na chave do cache.
     */
    public function modelo(): string;

    /**
     * Chamado uma vez por job, antes da primeira classificação (ex.: carregar o modelo local na memória,
     * que no Ollama leva ~30s a frio). Provedores hospedados podem não fazer nada.
     */
    public function preparar(int $timeout): void;

    /**
     * Envia os prompts (de preferência em paralelo) pedindo resposta em JSON.
     *
     * @param  array<int, string>  $prompts
     * @return array<int, ?string> texto devolvido pela LLM, na mesma ordem; null quando a chamada falhou
     *                             (erro HTTP, conexão recusada ou tempo esgotado)
     */
    public function gerarJson(array $prompts, int $timeout): array;
}
