# Lógica de recomendação

Cada REA retornado recebe um rótulo `recommended`:

| Rótulo | Significado |
|---|---|
| `both` | o nível educacional **e** o tipo de conteúdo batem com o perfil/tipos preferidos |
| `profile` | só o nível educacional bate |
| `interest` | só o tema bate (é resultado da busca) |
| `meta_both` / `meta_one` / `meta` | equivalentes aos rótulos acima para usuários com meta de aprendizagem, quando o REA é compatível com a meta |

## Por repositório
- **Aquarela** (até 3 páginas): o nível é inferido por regex no título + descrição (infantil,
  fundamental, médio; se nada bater, superior). A interatividade vem do `dtype` (`T` = ativo,
  `D` = expositivo). Se o usuário tem meta, cada REA é classificado por LLM (Ollama `gemma3:4b`) em
  Aprendizagem, Performance Aproximação ou Performance Evitação.
- **MEC RED** (1 chamada, 10 itens): os filtros de nível e `object_type` já vão na URL
  (`getMecRedURL`). Todo item vira `both` (ou `meta_both`, com meta).
- **Eduplay** (até 9 páginas): tudo é vídeo. O rótulo é `meta_one` se a meta for `ma` ou `mpa`;
  caso contrário, `interest`.

## Ordenação (`FindREA::paginate`)
- Com meta dominante: `meta_both` → `meta_one` → `meta` (os demais são descartados)
- Sem meta: `both` → `profile` → `interest` (itens `meta*` não aparecem)

A regra está em `App\Recommendation\RuleClassifier` e a ordem em `App\Recommendation\Ranking`.
Cada REA também grava a `explicacao` da decisão; veja [transparencia.md](transparencia.md).

## Tipos preferidos
A lista com que o tipo do REA é comparado vem, por padrão, dos colaboradores: os tipos cadastrados
com o mesmo interesse e perfil, mais os de todos os outros colaboradores. O usuário logado pode
salvar os seus tipos preferidos na conta (tela **Minhas preferências**, `/conta/preferencias`). Com
preferência salva, ela **substitui** os tipos dos colaboradores em todas as buscas; com a opção
"incluir também os tipos sugeridos pelos colaboradores", as duas listas são unidas. O critério de
tipo grava a fonte `usuario` nesse caso (`App\Recommendation\TiposPreferidos`,
[plano-escrutabilidade.md §15](plano-escrutabilidade.md#15-tipos-preferidos-salvos-na-conta)).
Visitantes continuam com os tipos dos colaboradores.

O usuário pode corrigir o nível e a meta estimados de um REA e os tipos preferidos da busca. O
rótulo é recalculado pela mesma regra (`RuleClassifier::rotular`), só para a busca atual; veja
[transparencia.md §13](transparencia.md#13-escrutabilidade).

## EMAPRE (Escala de Metas de Realização)
São 28 afirmações em escala Likert de 1 a 5. O sistema calcula a média de cada fator: **ma** (meta
aprender, itens 1–12), **mpa** (performance-aproximação, itens 13–21) e **mpe**
(performance-evitação, itens 22–28). O fator com a maior média é salvo como `dominant` em
`questionnaires`.
