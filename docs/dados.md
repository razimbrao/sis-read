# Modelo de dados (SQLite)

| Tabela | Uso |
|---|---|
| `users` | contas (login/cadastro). `tipos_preferidos` (JSON, `null` = sem preferência salva) e `incluir_tipos_colaboradores` (bool): tipos preferidos da conta ([plano-escrutabilidade.md §15](plano-escrutabilidade.md#15-tipos-preferidos-salvos-na-conta)) |
| `questionnaires` | resultado do EMAPRE: `ma`, `mpa`, `mpe`, `dominant`, `user_id` |
| `data` | uma linha por busca: `searched_at` (chave), `data` (JSON com os REAs), `finished`, `time` (soma dos segundos dos jobs), `stars` |
| `feedback_reasons` | 6 motivos fixos (seeder `FeedbackReasonSeeder`) |
| `data_reasons` | pivot data ↔ feedback_reasons, com o comentário (`feedback`) |
| `searches` | log de perfil/interesse buscados (sidebar "mais acessadas") |
| `collaborators` | REAs cadastrados por colaboradores |
| `feedbacks` | feedback livre sobre o sistema |
| `search_metrics` | métricas por job: tempos de API/Ollama, chamadas, itens, erros e `breakdown` (JSON) |
| `explanation_events` | uso das explicações: `searched_at`, `user_id`, `acao` (`abriu_explicacao`, `abriu_ordenacao`, `abriu_contexto`, `abriu_ocultos`, `abriu_correcao`), repositório, título, faixa |
| `corrections` | correções do usuário (escrutabilidade): `searched_at`, `user_id`, `acao` (`corrigir`/`desfazer`/`salvar_preferencia`), `alvo` (`nivel`/`meta`/`tipos`/`todas`), `chave_rea`, repositório, título, `valor_anterior`/`valor_novo` (JSON), faixa antes e depois, `itens_afetados`. `salvar_preferencia` registra a mudança dos tipos da conta; `searched_at` fica `null` quando ela é feita na tela Minhas preferências |
| `jobs`, `failed_jobs`, `job_batches` | fila do Laravel |
| `sessions`, `cache`, `cache_locks` | infraestrutura do Laravel |

Campos de cada item em `data.data`:
`chave, title, link, type, repositorio, recommended, explicacao, titulo, descricao, tipoConteudo, dtype,
fonte_interatividade, interatividade, nivel_interatividade, estilo_aprendizagem, estrategia`.

`explicacao` é a estrutura da transparência ([transparencia.md §4](transparencia.md#4-estrutura-explicacao-gravada-em-cada-rea-de-datadata)).
As correções do usuário reescrevem o item em `data.data` e guardam o critério do job em `original`
([transparencia.md §13](transparencia.md#13-escrutabilidade)).
