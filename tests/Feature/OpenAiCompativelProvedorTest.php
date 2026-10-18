<?php

namespace Tests\Feature;

use App\Recommendation\Llm\OpenAiCompativelProvedor;
use App\Recommendation\Llm\ProvedorLlm;
use App\Recommendation\MetaClassifier;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class OpenAiCompativelProvedorTest extends TestCase
{
    private function provedor(): OpenAiCompativelProvedor
    {
        return new OpenAiCompativelProvedor('https://api.groq.com/openai/v1/', 'gsk_teste', 'openai/gpt-oss-20b', ['reasoning_effort' => 'low']);
    }

    private static function resposta(string $conteudo): array
    {
        return ['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $conteudo], 'finish_reason' => 'stop']]];
    }

    public function test_devolve_o_texto_de_cada_prompt_na_ordem(): void
    {
        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push(self::resposta('{"meta": "Aprendizagem"}'))
            ->push(self::resposta('{"meta": "Performance Evitação"}')),
        ]);

        $textos = $this->provedor()->gerarJson(['primeiro', 'segundo'], 10);

        $this->assertSame(['{"meta": "Aprendizagem"}', '{"meta": "Performance Evitação"}'], $textos);
        $this->assertSame('Aprendizagem', MetaClassifier::interpretar($textos[0]));
    }

    public function test_envia_chave_modelo_schema_das_metas_e_extras(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(self::resposta('{"meta": "Aprendizagem"}'))]);

        $this->provedor()->gerarJson(['classifique'], 10, MetaClassifier::schema(1));

        Http::assertSent(function (Request $r) {
            return $r->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $r->hasHeader('Authorization', 'Bearer gsk_teste')
                && $r['model'] === 'openai/gpt-oss-20b'
                && $r['messages'][0]['content'] === 'classifique'
                && $r['response_format']['json_schema']['schema']['properties']['meta']['enum'] === array_values(MetaClassifier::CLASSES)
                && $r['reasoning_effort'] === 'low';
        });
    }

    public function test_sem_schema_pede_so_um_objeto_json(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(self::resposta('{"meta": "Aprendizagem"}'))]);

        $this->provedor()->gerarJson(['classifique'], 10);

        Http::assertSent(fn (Request $r) => $r['response_format'] === ['type' => 'json_object']);
    }

    public function test_erro_e_resposta_vazia_viram_null_sem_derrubar_as_outras(): void
    {
        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'falhou']], 500)
            ->push(['choices' => []])
            ->push(self::resposta('{"meta": "Aprendizagem"}')),
        ]);

        $this->assertSame([null, null, '{"meta": "Aprendizagem"}'], $this->provedor()->gerarJson(['a', 'b', 'c'], 10));
    }

    public function test_limite_estourado_espera_o_retry_after_e_reenvia_so_o_recusado(): void
    {
        Sleep::fake();
        Http::fake(['api.groq.com/*' => function (Request $r) {
            static $recusou = false;
            if ($r['messages'][0]['content'] === 'b' && ! $recusou) {
                $recusou = true;

                return Http::response(['error' => ['message' => 'Rate limit reached']], 429, ['retry-after' => '2']);
            }

            return Http::response(self::resposta('{"meta": "Aprendizagem"}'));
        }]);

        $textos = $this->provedor()->gerarJson(['a', 'b'], 10);

        $this->assertSame(['{"meta": "Aprendizagem"}', '{"meta": "Aprendizagem"}'], $textos);
        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(2)->seconds()]);
    }

    public function test_limite_que_passa_da_espera_maxima_vira_null(): void
    {
        Sleep::fake();
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'Rate limit reached']], 429, ['retry-after' => '30'])]);

        $provedor = new OpenAiCompativelProvedor('https://api.groq.com/openai/v1', 'gsk_teste', 'openai/gpt-oss-20b', [], 45);

        $this->assertSame([null], $provedor->gerarJson(['a'], 10));
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_llm_provedor_groq_liga_o_classificador_ao_groq(): void
    {
        config(['llm.provedor' => 'groq', 'llm.groq.modelo' => 'qwen/qwen3.8-27b']);

        $this->assertInstanceOf(OpenAiCompativelProvedor::class, app(ProvedorLlm::class));
        $this->assertSame('qwen/qwen3.8-27b', app(MetaClassifier::class)->modelo());
    }
}
