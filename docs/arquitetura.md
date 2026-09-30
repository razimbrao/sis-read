# Arquitetura

## Modelo conceitual

A monografia (Figura 4.1) descreve o SisREAd como uma arquitetura MVC com cinco partes. No código,
elas ficam assim:

| Parte | Responsabilidade | Onde está |
|---|---|---|
| Entrada | Coleta perfil, interesse e (se houver conta) meta EMAPRE-U | `FindREA::search()`, `app/Livewire/Emapre.php` |
| Recomendação | Busca nas fontes externas, filtragem e ordenação | `app/Jobs/Process*.php`, `app/Recommendation/Ranking.php`, `FindREA::paginate()` |
| Classificação | Rótulo de cada REA por perfil, tipo e meta. O LLM só atua no Aquarela | `app/Recommendation/RuleClassifier.php`, `ProcessAquarela::classificarMetaComLLM` |
| Persistência | Buscas, resultados, métricas, questionário e avaliações | SQLite ([dados.md](dados.md)) |
| Apresentação | Resultados paginados, destaque, explicações e avaliação | `resources/views/livewire/find-r-e-a.blade.php`, `app/Recommendation/ExplanationRenderer.php` |

## Stack
- **Laravel 11** (PHP 8.2+) e **Livewire 3** (componentes reativos em PHP, renderizados no servidor),
  com Tailwind via CDN
- **SQLite** (`database/database.sqlite`) para dados, sessão, cache e fila (`QUEUE_CONNECTION=database`)
- **Ollama** local para a classificação por meta no Aquarela (modelo em
  [integracoes.md](integracoes.md); a monografia usou Llama 3.1)
- **Vite**, praticamente sem uso (o JS do app é só o bootstrap do axios)
- Dependências presentes mas sem uso em runtime local: Laravel Horizon/predis (Redis) e
  `revolution/laravel-google-sheets` (o código que a usa está comentado)

## Mapa de arquivos
```
routes/web.php            / (welcome), /login, /register, /emapre (auth), POST /logout
routes/console.php        schedule: queue:work a cada minuto (produção via cron)
app/Livewire/FindREA.php  componente principal: busca, cadastro de colaborador, feedback, avaliação
app/Livewire/Emapre.php   questionário EMAPRE-U (28 itens) → meta dominante do usuário
app/Livewire/Auth/*       login e cadastro simples
app/Jobs/Process*.php     um job por fonte externa (Aquarela, MecRed, Eduplay)
app/Recommendation/*      regras (RuleClassifier), ordenação (Ranking), textos (ExplanationRenderer)
app/Helpers/helpers.php   getMecRedURL(), mecRedEtapas(), mecRedTiposObjeto()
app/Console/Commands/RunRecommendationSimulation.php  simulation:run-all (experimento da monografia)
resources/views/welcome.blade.php              layout da home (sidebar "Pesquisas mais acessadas")
resources/views/livewire/find-r-e-a.blade.php  toda a UI de busca/resultados/feedback
start-queue.php           loop infinito de queue:work (alternativa ao cron em produção)
```

## Fluxo de uma busca
```
Usuário (perfil, interesse) ──► FindREA::search()
   ├─ grava Searches (estatística da sidebar)
   ├─ filtra Collaborators com mesmo interesse+perfil → tipos preferidos (+ tipos de todos os colaboradores)
   ├─ findAdequateTerm(): mapeia o interesse para um termo conhecido (interestApiSearch)
   ├─ monta $contexto (painel "O que usamos sobre você")
   ├─ cria Data{searched_at = timestamp}   ← chave que liga os jobs à busca
   └─ dispatch ProcessAquarela, ProcessMecRed, ProcessEduplay (fila database), com a meta dominante se houver
            │
queue:work ─┴─► cada job chama sua API, converte os itens para uma estrutura comum, classifica
               cada REA (RuleClassifier → `recommended` + `explicacao`; no Aquarela com meta, chama o
               Ollama por item), faz merge do JSON em Data.data, marca finished e grava search_metrics
            │
Blade com wire:poll ──► relê Data a cada ~2s; FindREA::paginate() ordena (Ranking) e pagina (10/pág)
               ──► usuário avalia (estrelas → Data.stars; motivos e comentário → data_reasons)
```

Os três jobs rodam em paralelo se houver mais de um worker. Com um só, rodam em sequência. O tempo
total é dominado pela classificação por LLM no Aquarela quando o usuário tem meta (monografia,
seção 5.4.2).

Observação: `finished` vira `true` quando o **primeiro** job termina, não quando os três terminam
(problema conhecido #2).

## Adicionar uma nova fonte de REA

Cada API tem formato e metadados próprios, então uma fonte nova precisa de:
1. Um job `app/Jobs/Process<Fonte>.php` que busque, converta os itens para os campos comuns de
   `data.data` (ver [dados.md](dados.md)), grave `recommended` e `explicacao` a partir dos critérios
   de `RuleClassifier` e registre `search_metrics`.
2. O dispatch em `FindREA::findInApi()` e em `RunRecommendationSimulation`.
3. Se a fonte tiver regra fixa (como MEC RED e Eduplay), declarar isso na `observacao` da explicação
   ([transparencia.md](transparencia.md#5-regras-por-repositório)).

## Tipos de usuário na home
- **Usuário**: faz buscas. Sem conta, a ordenação usa só perfil e tipos preferidos.
- **Usuário cadastrado**: pode responder ao EMAPRE-U (`/emapre`), o que muda a ordenação para as faixas de meta.
- **Colaborador**: cadastra um REA. Os tipos cadastrados viram "tipos preferidos".
- **Feedback**: texto livre salvo em `feedbacks`.

Telas em detalhe: [uso.md](uso.md).
