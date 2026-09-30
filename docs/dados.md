# Modelo de dados (SQLite)

| Tabela | Uso |
|---|---|
| `users` | contas (login/cadastro) |
| `questionnaires` | resultado do EMAPRE-U: médias `ma`, `mpa`, `mpe`, `dominant` e `user_id` (uma linha por usuário) |
| `data` | uma linha por busca: `searched_at` (chave), `data` (JSON com os REAs), `finished`, `time` (soma dos segundos dos jobs) e `stars` |
| `feedback_reasons` | motivos fixos de insatisfação (seeder `FeedbackReasonSeeder`: 6 originais + 2 sobre explicações) |
| `data_reasons` | pivot data ↔ feedback_reasons, com o comentário (`feedback`) |
| `searches` | log de perfil/interesse buscados (sidebar "Pesquisas mais acessadas") |
| `collaborators` | REAs cadastrados por colaboradores. `item` vira tipo preferido |
| `feedbacks` | feedback livre sobre o sistema |
| `search_metrics` | uma linha por job (repositório × busca), ver abaixo |
| `explanation_events` | uso das explicações ([transparencia.md](transparencia.md#9-registro-de-uso-explanation_events)) |
| `jobs`, `failed_jobs`, `job_batches` | fila do Laravel |
| `sessions`, `cache`, `cache_locks` | infraestrutura do Laravel |

## Campos de cada item em `data.data`

Os três jobs convertem a resposta de cada API para a mesma estrutura:

| Campo | Conteúdo |
|---|---|
| `title`, `link`, `type`, `repositorio` | exibição na tabela |
| `titulo`, `descricao`, `tipoConteudo`, `dtype` | metadados de origem (completos só no Aquarela) |
| `recommended` | faixa (`both`, `profile`, `interest`, `meta_both`, `meta_one`, `meta`) |
| `explicacao` | critérios avaliados ([transparencia.md](transparencia.md#4-estrutura-explicacao-gravada-em-cada-rea-de-datadata)) |
| `interatividade`, `nivel_interatividade`, `estilo_aprendizagem`, `estrategia`, `fonte_interatividade` | características pedagógicas ([recomendacao.md](recomendacao.md#características-pedagógicas)) |

## Métricas (`search_metrics`)

São as métricas da seção 4.13 da monografia, usadas no experimento de desempenho (seção 5.4).

| Coluna | Métrica |
|---|---|
| `searched_at`, `repository`, `profile`, `interest`, `meta` | identificação do cenário |
| `total_time` | tempo total do job (s) |
| `api_time`, `api_calls` | tempo e número de chamadas à API do repositório |
| `ollama_time`, `ollama_calls`, `ollama_errors` | tempo, chamadas e falhas do LLM (só Aquarela com meta) |
| `items_returned` | REAs brutos devolvidos pela API |
| `items_filtered` | REAs aproveitados: com meta, os das faixas `meta*`; sem meta, todos |
| `timeouts_errors` | falhas ou timeouts nas chamadas à API |
| `breakdown` | JSON com a contagem por faixa |

A razão `items_filtered / items_returned` por repositório é o gráfico "itens retornados vs.
recomendados" da monografia. `ollama_time` × `api_time` é a composição do tempo de resposta.
