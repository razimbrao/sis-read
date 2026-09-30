# Modelo de dados (SQLite)

| Tabela | Uso |
|---|---|
| `users` | contas (login/cadastro) |
| `questionnaires` | resultado do EMAPRE: `ma`, `mpa`, `mpe`, `dominant`, `user_id` |
| `data` | uma linha por busca: `searched_at` (chave), `data` (JSON com os REAs), `finished`, `time` (soma dos segundos dos jobs), `stars` |
| `feedback_reasons` | 6 motivos fixos (seeder `FeedbackReasonSeeder`) |
| `data_reasons` | pivot data ↔ feedback_reasons, com o comentário (`feedback`) |
| `searches` | log de perfil/interesse buscados (sidebar "mais acessadas") |
| `collaborators` | REAs cadastrados por colaboradores |
| `feedbacks` | feedback livre sobre o sistema |
| `search_metrics` | métricas por job: tempos de API/Ollama, chamadas, itens, erros e `breakdown` (JSON) |
| `jobs`, `failed_jobs`, `job_batches` | fila do Laravel |
| `sessions`, `cache`, `cache_locks` | infraestrutura do Laravel |

Campos de cada item em `data.data`:
`title, link, type, repositorio, recommended, titulo, descricao, tipoConteudo, dtype,
interatividade, nivel_interatividade, estilo_aprendizagem, estrategia`.
