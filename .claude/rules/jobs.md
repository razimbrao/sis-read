---
paths:
  - "app/Jobs/**"
  - "app/Helpers/**"
---

# Regras para os jobs de busca

- Todo job deve: encontrar `Data` por `searched_at`, fazer merge (não sobrescrever) do JSON em `data`, marcar `finished`, somar `time` e inserir uma linha em `search_metrics`.
- Cada item de REA precisa dos campos `title, link, type, repositorio, recommended, titulo, descricao, tipoConteudo, dtype, interatividade, nivel_interatividade, estilo_aprendizagem, estrategia`. A view depende deles.
- Chamadas HTTP externas devem ter timeout e capturar exceções, para que uma API fora do ar não derrube a busca.
- Ao adicionar um repositório novo, dispare o job em `FindREA::findInApi()` e em `RunRecommendationSimulation`.
