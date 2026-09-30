# Arquitetura

## Stack
- **Laravel 11** (PHP 8.2+) e **Livewire 3** (UI reativa renderizada no servidor), com Tailwind via CDN
- **SQLite** (`database/database.sqlite`) para dados, sessão, cache e fila (`QUEUE_CONNECTION=database`)
- **Vite**, praticamente sem uso (o JS do app é só o bootstrap do axios)
- Dependências presentes mas sem uso em runtime local: Laravel Horizon/predis (Redis) e
  `revolution/laravel-google-sheets` (o código que a usa está comentado)

## Mapa de arquivos
```
routes/web.php            / (welcome), /login, /register, /emapre (auth), POST /logout
routes/console.php        schedule: queue:work a cada minuto (produção via cron)
app/Livewire/FindREA.php  componente principal: busca, cadastro de colaborador, feedback, avaliação
app/Livewire/Emapre.php   questionário EMAPRE (28 itens) → meta dominante do usuário
app/Livewire/Auth/*       login e cadastro simples
app/Jobs/Process*.php     um job por repositório externo (Aquarela, MecRed, Eduplay)
app/Helpers/helpers.php   getMecRedURL(): monta a URL da API MEC RED com filtros
app/Console/Commands/RunRecommendationSimulation.php  simulation:run-all
resources/views/welcome.blade.php              layout da home (sidebar "pesquisas mais acessadas")
resources/views/livewire/find-r-e-a.blade.php  toda a UI de busca/resultados/feedback
start-queue.php           loop infinito de queue:work (alternativa ao cron em produção)
```

## Fluxo de uma busca
```
Usuário (perfil, interesse) ──► FindREA::search()
   ├─ grava Searches (estatística da sidebar)
   ├─ filtra Collaborators com mesmo interesse+perfil → tipos de item preferidos
   ├─ findAdequateTerm(): mapeia o interesse para um termo conhecido (interestApiSearch)
   ├─ cria Data{searched_at = timestamp}   ← chave que liga os jobs à busca
   └─ dispatch ProcessAquarela, ProcessMecRed, ProcessEduplay (fila database)
            │
queue:work ─┴─► cada job chama sua API, classifica cada REA (campo `recommended`),
               faz merge do JSON em Data.data, marca finished e grava search_metrics
            │
Blade com wire:poll ──► relê Data a cada ~2s; FindREA::paginate() ordena e pagina (10/pág)
               ──► usuário avalia (estrelas → Data.stars; motivos → data_reasons)
```

Observação: `finished` vira `true` quando o **primeiro** job termina, não quando os três terminam.

## Tipos de usuário na home
- **Usuário**: faz buscas.
- **Colaborador**: cadastra um REA (nome, função, instituição, título, referência, perfil, interesse
  e item). Os interesses cadastrados viram opções de busca e os itens viram "tipos preferidos".
- **Feedback**: texto livre salvo em `feedbacks`.
- Usuário logado: pode responder o questionário EMAPRE (`/emapre`), o que muda a ordenação para metas.
