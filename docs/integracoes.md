# Integrações externas

| Serviço | Endpoint | Onde |
|---|---|---|
| Aquarela | `AQUARELA_API_URL` (padrão `https://aquarelaapi.dev.br/api/reas`, params `string`, `page`) | `ProcessAquarela` |
| MEC RED | `https://api.mecred.c3sl.ufpr.br/public/elastic/search?...` | `helpers.php`, `ProcessMecRed` |
| Eduplay (RNP) | `https://eduplay.rnp.br/api/v1/search?term=&page=&quantity=10` | `ProcessEduplay` |
| Ollama | `OLLAMA_URL` (padrão `http://127.0.0.1:11434`) + `/api/generate`, modelo `OLLAMA_MODELO` (padrão `gemma3:4b`) | `MetaClassifier` + `Llm\OllamaProvedor`, nos três jobs |
| Google Sheets | `config/google.php` + `storage/credentials.json` | código comentado em `FindREA::insert` |

Todas as chamadas HTTP aos repositórios usam `verify => false` (TLS não verificado). O timeout é de
15s nos repositórios e `LLM_TIMEOUT` (padrão 10s) na LLM. As falhas são contadas em
`search_metrics.timeouts_errors` / `ollama_errors` e não quebram o job.

## LLM (classificação de meta)

`App\Recommendation\MetaClassifier` classifica a meta de cada REA quando o usuário tem meta, nos
três jobs. O provedor fica atrás da interface `App\Recommendation\Llm\ProvedorLlm` (`modelo()`,
`preparar()`, `gerarJson()`), registrada no `AppServiceProvider`. Para usar uma LLM hospedada, crie a
implementação e acrescente o caso de `config('llm.provedor')` no `AppServiceProvider`.

Configuração em `config/llm.php` (variáveis de ambiente):

| Variável | Padrão | Uso |
|---|---|---|
| `LLM_PROVEDOR` | `ollama` | `ollama` ou `nenhum` (desliga: meta "não avaliada", com o motivo) |
| `OLLAMA_URL` / `OLLAMA_MODELO` / `OLLAMA_KEEP_ALIVE` | `http://127.0.0.1:11434` / `gemma3:4b` / `10m` | servidor, modelo e tempo que o modelo fica carregado |
| `LLM_TIMEOUT` | `10` | segundos por chamada |
| `LLM_AQUECER` / `LLM_TIMEOUT_AQUECIMENTO` | `true` / `60` | carrega o modelo antes da primeira chamada de cada job |
| `LLM_CONCORRENCIA` | `4` | chamadas simultâneas (`Http::pool`) |
| `LLM_ORCAMENTO_SEGUNDOS` | `240` | tempo máximo de LLM por job; o resto fica não avaliado |
| `LLM_FALHAS_SEGUIDAS_MAX` / `LLM_PAUSA_APOS_FALHA_SEGUNDOS` | `3` / `60` | desiste depois de N falhas seguidas e avisa os outros jobs pelo cache |
| `LLM_CACHE_DIAS` | `30` | reaproveita a classificação por `chave` do REA (0 desliga) |

### Desempenho

Com meta, uma busca típica ("algoritmos", perfil Ensino médio, meta `ma`) traz 157 REAs: 57 do
Aquarela, 10 do MEC RED e 90 do Eduplay. Medição local em 2026-10-06 (Ollama `gemma3:4b`,
`OLLAMA_NUM_PARALLEL` padrão, um worker com `queue:work --timeout=600`):

| Cenário | Chamadas à LLM | Tempo de LLM | Tempo total dos jobs |
|---|---|---|---|
| Sem aquecimento, modelo frio | 4 (todas estouraram 10s) | 10s | 16s, **todos os 157 não avaliados** |
| Com aquecimento, modelo frio, cache vazio | 152 (5 repetidos vieram do cache) | 57,5s (Aquarela 34,6s, Eduplay 20,9s, MEC RED 2,0s) | 64s |
| Mesma busca de novo (cache quente) | 0 | 0s | 6s |

Mitigações:

- **Aquecimento**: a primeira chamada a um `gemma3:4b` descarregado leva ~30s. Sem aquecer, as
  primeiras chamadas estouravam o timeout e o job desistia da LLM. O `OllamaProvedor::preparar`
  manda uma requisição sem prompt (só carrega o modelo) com timeout de 60s, uma vez por job e só se
  houver algo fora do cache. Todas as chamadas mandam `keep_alive`.
- **Cache por `RuleClassifier::chave`** (repositório + link + título), com o modelo e
  `MetaClassifier::VERSAO_PROMPT` na chave. Guarda a classe, não o status, então vale para qualquer
  meta do usuário. Falhas e respostas inválidas não entram no cache. Usa o cache padrão do Laravel
  (`CACHE_STORE=database`).
- **Lotes simultâneos**: cada página vai para a LLM em lotes de `LLM_CONCORRENCIA`. No Ollama local o
  ganho depende de `OLLAMA_NUM_PARALLEL` (com 1, o servidor enfileira). Num provedor hospedado, o
  ganho é direto.
- **Orçamento por job** (240s): somado às chamadas aos repositórios (até 9 × 15s no Eduplay), cabe
  no `--timeout=600`.
- **Parada após falhas**: com a LLM fora do ar, cada job tenta no máximo um lote e grava
  `meta_llm:pausa` no cache por 60s, para que os outros jobs da mesma busca nem tentem.

Métricas em `search_metrics` (uma linha por repositório): `ollama_time` (aquecimento incluído),
`ollama_calls`, `ollama_errors` (respostas inválidas incluídas), `llm_cache_hits` e
`llm_nao_avaliados`. Os nomes `ollama_*` valem para qualquer provedor.
