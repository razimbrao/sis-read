<?php

namespace App\Recommendation\Llm;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OllamaProvedor implements ProvedorLlm
{
    public function __construct(private string $url, private string $modelo, private string $keepAlive = '10m')
    {
    }

    public function modelo(): string
    {
        return $this->modelo;
    }

    /**
     * Uma requisição sem prompt só carrega o modelo. Falhas são ignoradas: as chamadas seguintes as revelam.
     */
    public function preparar(int $timeout): void
    {
        try {
            Http::timeout($timeout)->post($this->endpoint(), ['model' => $this->modelo, 'keep_alive' => $this->keepAlive]);
        } catch (\Throwable $e) {
            // Segue sem aquecer.
        }
    }

    private function endpoint(): string
    {
        return rtrim($this->url, '/').'/api/generate';
    }

    public function gerarJson(array $prompts, int $timeout): array
    {
        $prompts = array_values($prompts);
        $endpoint = $this->endpoint();

        // Falhas de conexão voltam como exceção dentro do array, sem interromper as outras chamadas.
        $respostas = Http::pool(fn (Pool $pool) => array_map(
            fn (int $i) => $pool->as("p{$i}")->timeout($timeout)->post($endpoint, [
                'model' => $this->modelo,
                'prompt' => $prompts[$i],
                'format' => 'json',
                'stream' => false,
                'keep_alive' => $this->keepAlive,
            ]),
            array_keys($prompts)
        ));

        $textos = [];
        foreach (array_keys($prompts) as $i) {
            $resposta = $respostas["p{$i}"] ?? null;
            $textos[$i] = $resposta instanceof Response && $resposta->successful()
                ? (string) $resposta->json('response')
                : null;
        }

        return $textos;
    }
}
