# Lógica de recomendação

A recomendação é a mesma para todos os repositórios: cada REA é avaliado em quatro critérios (tema,
nível, tipo e meta), e o **grau de recomendação** e o rótulo saem só desses critérios. Nenhum
repositório tem posição reservada.

## Grau de recomendação (`RuleClassifier::grau`)

| Critério | Pontos se atendido | Observação |
|---|---|---|
| meta | 4 | só existe quando o usuário tem meta (EMAPRE) |
| nível | 2 | |
| tipo | 1 | |
| tema | 0 | todo REA listado é resultado da busca, então o tema não diferencia ninguém |

**Grau = soma dos pontos dos critérios atendidos**: de 0 a 7 com meta e de 0 a 3 sem meta. Os pesos
são potências de 2, então a meta vale mais que nível e tipo juntos, e o nível vale mais que o tipo.

Um critério só é **atendido** (`RuleClassifier::atende`) quando foi conferido e bateu: `status = ok`
e não `assumido`. Valem 0 ponto, como o não atendido:
- `nao_avaliado`: o repositório não informa o dado, ou a IA não classificou;
- `filtro_api`: o filtro foi pedido ao repositório, mas não houve como conferir o resultado;
- nível `assumido`: o regex não achou nenhuma etapa no texto e o sistema assumiu *ensino superior*.

A explicação continua distinguindo "? não verificado" de "✗ não atende".

## Rótulos (faixas do grau)

| Rótulo | Grau | Significado |
|---|---|---|
| `meta_both` | 7 | meta, nível e tipo |
| `meta_one` | 5 a 6 | meta e nível (6) ou meta e tipo (5) |
| `meta` | 4 | só a meta |
| `both` | 3 | nível e tipo |
| `profile` | 2 | só o nível |
| `interest` | 0 a 1 | tipo (1) ou só o tema (0) |

`RuleClassifier::rotular` aplica a mesma regra de `atende`, então o rótulo é sempre um intervalo do
grau: nenhum REA de faixa mais baixa tem grau maior que um de faixa mais alta (testado em
`RuleClassifierTest::test_grau_e_rotulo_concordam`). Rótulos, `Ranking` e `ExplanationRenderer::FAIXAS`
estão acoplados: mude os três juntos.

## Por repositório
Todos usam `RuleClassifier::criterioTema`, `criterioNivel` (regex) e, quando há o dado, `criterioTipo`.
- **Aquarela** (até 3 páginas): o nível vem do regex no título e na descrição (infantil, fundamental,
  médio; se nada bater, superior *assumido*). O tipo vem de `tipoConteudo`. A interatividade vem do
  `dtype` (`T` = ativo, `D` = expositivo). Se o usuário tem meta, cada REA é classificado por LLM
  (Ollama `gemma3:4b`) em Aprendizagem, Performance Aproximação ou Performance Evitação.
- **MEC RED** (1 chamada, 10 itens): os filtros de nível e `object_type` vão na URL (`getMecRedURL`),
  mas a API não devolve a etapa nem o tipo de cada item e, em 2026-09-18, ignorava os filtros
  (problema #15). O nível vem do regex sobre o título e a descrição. Tipo e meta ficam como
  `nao_avaliado`, com o pedido feito como evidência.
- **Eduplay** (até 9 páginas): tudo é vídeo. O nível vem do regex sobre o título e a descrição, e o
  tipo `video` é comparado com os tipos preferidos. A meta fica `nao_avaliado`, porque o repositório não
  informa nada que permita conferi-la.

## Ordenação (`Ranking::ordenar`, chamada em `FindREA::paginate`)
1. **Grau**, do maior para o menor.
2. **Posição do REA no próprio repositório**, da menor para a maior. É a ordem de relevância da API,
   gravada pelo job em `explicacao.grau.posicao`. Isso intercala os repositórios: o 1º de cada um, depois
   o 2º, e assim por diante.
3. **Nome do repositório**, em ordem alfabética.
4. **`chave`** do REA.

A ordem de chegada dos jobs não influi. REAs antigos, sem critérios, usam o menor grau da faixa e, como
posição, a ordem em que aparecem em `Data.data`.

Visibilidade:
- **Com meta**: só fica oculto o REA cuja meta foi **conferida como incompatível** (`falhou`). O REA
  com meta não conferida aparece com 0 ponto de meta, abaixo de todos os compatíveis, nas faixas
  `both` → `profile` → `interest` ("meta não conferida").
- **Sem meta**: `both` → `profile` → `interest`. Os itens `meta*` (de uma busca feita com meta) não aparecem.

Cada REA grava a `explicacao` da decisão, com o grau e a conta; veja [transparencia.md](transparencia.md).

O usuário pode corrigir o nível e a meta estimados de um REA e os tipos preferidos da busca. O rótulo
e o grau são recalculados pela mesma regra, só para a busca atual; veja
[transparencia.md §13](transparencia.md#13-escrutabilidade).


## EMAPRE (Escala de Metas de Realização)
São 28 afirmações em escala Likert de 1 a 5. O sistema calcula a média de cada fator: **ma** (meta
aprender, itens 1–12), **mpa** (performance-aproximação, itens 13–21) e **mpe**
(performance-evitação, itens 22–28). O fator com a maior média é salvo como `dominant` em
`questionnaires`.
