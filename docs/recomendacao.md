# Lógica de recomendação

O SisREAd é um recomendador **baseado em regras** (conhecimento + conteúdo). Ele usa três entradas:

| Entrada | Obrigatória | Origem |
|---|---|---|
| **Perfil** (nível educacional) | sim | campo de busca |
| **Interesse** (tema) | sim | campo de busca, mapeado para um termo conhecido (`findAdequateTerm`) |
| **Meta de aprendizagem** (`ma`, `mpa`, `mpe`) | não | questionário EMAPRE-U, só para usuários cadastrados |

Além disso, os **tipos preferidos** vêm da tabela `collaborators`: primeiro os tipos cadastrados para o
mesmo interesse e perfil, depois os de todos os colaboradores (ver [uso.md](uso.md#5-colaborador)).

## EMAPRE-U (meta de aprendizagem do usuário)

Escala de Avaliação da Motivação para a Aprendizagem de Universitários (Santos, Alcará e Zenorini,
2013; distribuição dos itens segundo Pereira et al., 2022), no modelo **tricotômico** de metas de
realização. São 28 afirmações em escala Likert de 1 a 5 (`app/Livewire/Emapre.php`, todas
obrigatórias).

| Fator | Itens | Exemplo |
|---|---|---|
| **ma** — Meta Aprender | 1–12 | "Gosto de tarefas difíceis e desafiadoras." |
| **mpa** — Performance-Aproximação | 13–21 | "Na minha turma, eu quero me sair melhor que os demais." |
| **mpe** — Performance-Evitação | 22–28 | "Não participo dos debates em sala de aula porque não quero que os colegas riam de mim." |

Escore de cada fator = média aritmética das respostas dos seus itens, M_m = (Σ rᵢ) / n (equação 4.1
da monografia), arredondada a 2 casas. A meta **dominante** é o fator com a maior média. Em caso de
empate, vence o primeiro na ordem `ma` → `mpa` → `mpe`. Médias e dominante ficam em `questionnaires`.
O questionário só pode ser respondido uma vez.

## Rótulos (faixas)

Cada REA recebe um rótulo `recommended` (`RuleClassifier::rotular`):

| Rótulo | Significado | Quando |
|---|---|---|
| `both` | nível **e** tipo de conteúdo batem com o perfil e os tipos preferidos | sem meta |
| `profile` | só o nível bate | sem meta |
| `interest` | só o tema bate (é resultado da busca) | sem meta |
| `meta_both` | atende à meta **e** a nível e tipo | com meta |
| `meta_one` | atende à meta e a nível **ou** tipo | com meta |
| `meta` | atende à meta, mas não a nível nem tipo | com meta |

Sem meta, os três níveis correspondem ao que a monografia chama de prioridade para usuários não
cadastrados: (1) perfil + interesse + tipo, (2) perfil + interesse, (3) só interesse.

## Por repositório

As três fontes são consultadas em paralelo, e cada uma tem um tratamento próprio de metadados
(monografia, seção 4.4).

### Aquarela (regra completa, com LLM)
- Até 3 páginas por busca (`string`, `page`).
- **Nível**: regex em título + descrição (infantil → fundamental → médio). Se nada casar, assume
  superior.
- **Tipo**: `tipoConteudo` normalizado comparado com os tipos preferidos.
- **Meta** (só se o usuário tem meta): um LLM local (Ollama) recebe título, descrição, `tipoConteudo`
  e `dtype`. O prompt descreve cada meta e pede JSON `{"meta": "Aprendizagem" | "Performance
  Aproximação" | "Performance Evitação"}`. O resultado é comparado com a meta dominante
  (`RuleClassifier::casaMeta`). Se a resposta for inválida ou houver falha ou timeout (10 s), a meta
  fica *não avaliada* e o item cai fora das faixas de meta.
- A monografia avaliou essa classificação com Llama 3.1 contra um padrão-ouro de dois avaliadores:
  acurácia de 85,48% e F1 macro de 0,85 ([monografia.md](monografia.md#42-classificação-por-meta-com-llm-qs2)).
  O código atual usa `gemma3:4b`.

### MEC RED (filtro na requisição, faixa fixa)
- 1 chamada, 10 itens. `getMecRedURL` monta a URL com:
  - `educational_stages` a partir do perfil (`mecRedEtapas`: infantil 1, fundamental 2 e 3, médio 4,
    superior 5). A comparação é exata com o texto do perfil ("Ensino médio"), sem normalização.
  - `object_type` a partir da meta (`mecRedTiposObjeto`), que associa formatos às metas:
    - **ma**: exploração conceitual e aprofundamento (e-books, videoaulas, objetos interativos,
      simuladores, cursos);
    - **mpa**: prática e desempenho (exercícios, desafios, avaliações, atividades práticas);
    - **mpe**: estudo autônomo e revisão gradual (materiais explicativos, objetos de apoio, revisão).
- Todo item vira `both` (ou `meta_both`, com meta), por política. Como a API **ignora esses filtros**
  (problema conhecido #15) e não informa etapa nem tipo de cada item, a explicação marca nível, tipo e
  meta como *não avaliados*.

### Eduplay (vídeos, faixa fixa)
- Até 9 páginas de 10 vídeos (`term`, `page`, `quantity=10`).
- Tudo é vídeo, e não há etapa. O rótulo é `meta_one` se a meta for `ma` ou `mpa`. Caso contrário,
  `interest`. Para usuários `mpe`, portanto, os vídeos do Eduplay ficam **ocultos**. A monografia
  descreve o Eduplay como recomendado "prioritariamente para Meta Aprender".

## Características pedagógicas

Cada REA também recebe `interatividade`, `nivel_interatividade`, `estilo_aprendizagem` e
`estrategia` (e `fonte_interatividade`, que diz de onde vieram):

| Fonte | Regra |
|---|---|
| Aquarela (`dtype`) | `T` → Ativo / Alto / Intuitivo-Ativo / estratégia ativa. `D` → Expositivo / Baixo / Sensorial-Reflexivo / estratégia passiva |
| MEC RED (`meta_usuario`) | Derivado da **meta do usuário**, e não do recurso: `ma`/`mpa` → ativo, `mpe` → expositivo, sem meta → não especificado |
| Eduplay (`padrao_repositorio`) | Sempre ativo |

## Ordenação (`FindREA::paginate` → `Ranking`)
- Com meta dominante: `meta_both` → `meta_one` → `meta`. Os demais ficam ocultos.
- Sem meta: `both` → `profile` → `interest`. Itens `meta*` não aparecem.
- Dentro da faixa, a ordem de chegada é mantida. Paginação de 10 itens.

O painel **Como ordenamos** mostra a contagem por faixa e o motivo dos ocultos
([transparencia.md](transparencia.md#6-ordenação-ranking)).

## Destaque (highlight)

As linhas da faixa mais alta aparecem com fundo amarelo (`bg-yellow-50` na Blade): `meta_both` para
usuário logado e `both` para visitante. As outras faixas continuam na lista, sem destaque, para não
descartar recursos parcialmente compatíveis. Limitação: a condição olha se há login, e não se há meta.
Um usuário logado que não respondeu ao EMAPRE-U nunca vê destaque (problema conhecido #18).

## Onde está no código
- Regras e critérios: `App\Recommendation\RuleClassifier`
- Ordem das faixas: `App\Recommendation\Ranking`
- Textos das faixas e das explicações: `App\Recommendation\ExplanationRenderer::FAIXAS`
- Cada REA grava a `explicacao` da decisão. Ver [transparencia.md](transparencia.md).

Rótulos, `Ranking` e `ExplanationRenderer::FAIXAS` estão acoplados: mude os três juntos.
