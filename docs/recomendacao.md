# Lógica de recomendação

Cada REA retornado recebe um rótulo `recommended`:

| Rótulo | Significado |
|---|---|
| `both` | o nível educacional **e** o tipo de conteúdo batem com o perfil/tipos preferidos |
| `profile` | só o nível educacional bate |
| `interest` | só o tema bate (é resultado da busca) |
| `meta_both` / `meta_one` / `meta` | equivalentes aos rótulos acima para usuários com meta de aprendizagem, quando o REA é compatível com a meta |

## Por repositório
Se o usuário tem meta, **todo REA dos três repositórios** é classificado por LLM
(`App\Recommendation\MetaClassifier`, por padrão Ollama `gemma3:4b`) em Aprendizagem, Performance
Aproximação ou Performance Evitação, e comparado com a meta dominante. Sem classificação (IA fora do
ar, resposta inválida, tempo esgotado), o critério de meta fica não avaliado e o REA não entra nas
faixas `meta*`. Detalhes em [transparencia.md §5.4](transparencia.md#54-classificação-de-meta-por-ia-metaclassifier).

- **Aquarela** (até 3 páginas): o nível é inferido por regex no título + descrição (infantil,
  fundamental, médio; se nada bater, superior). A interatividade vem do `dtype` (`T` = ativo,
  `D` = expositivo). A LLM recebe título, descrição, tipo e `dtype`.
- **MEC RED** (1 chamada, 10 itens): os filtros de nível e `object_type` já vão na URL
  (`getMecRedURL`). Todo item vira `both` (ou `meta_both`, com meta), por política. A LLM só recebe
  o título, porque a busca não devolve descrição nem tipo.
- **Eduplay** (até 9 páginas): tudo é vídeo e não há etapa. O rótulo sai da regra comum: com meta,
  `meta` se a LLM disser que o vídeo é compatível, senão `interest`; sem meta, `interest`. A LLM
  recebe título e descrição.

## Ordenação (`FindREA::paginate`)
- Com meta dominante: `meta_both` → `meta_one` → `meta` (os demais são descartados)
- Sem meta: `both` → `profile` → `interest` (itens `meta*` não aparecem)

A regra está em `App\Recommendation\RuleClassifier` e a ordem em `App\Recommendation\Ranking`.
Cada REA também grava a `explicacao` da decisão; veja [transparencia.md](transparencia.md).

O usuário pode corrigir o nível e a meta estimados de um REA e os tipos preferidos da busca. O
rótulo é recalculado pela mesma regra (`RuleClassifier::rotular`), só para a busca atual; veja
[transparencia.md §13](transparencia.md#13-escrutabilidade).

## EMAPRE (Escala de Metas de Realização)
São 28 afirmações em escala Likert de 1 a 5. O sistema calcula a média de cada fator: **ma** (meta
aprender, itens 1–12), **mpa** (performance-aproximação, itens 13–21) e **mpe**
(performance-evitação, itens 22–28). O fator com a maior média é salvo como `dominant` em
`questionnaires`.
