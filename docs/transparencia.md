# Transparência das recomendações (explicabilidade model-intrinsic)

> Especificação técnica da implementação da transparência no SisREAd. O plano e a justificativa
> baseada no mapeamento sistemático (MSL) estão em [plano-transparencia.md](plano-transparencia.md).
> A escrutabilidade (o usuário corrige o que o sistema estimou) está em §13 e no
> [plano-escrutabilidade.md](plano-escrutabilidade.md). Explicações contrafactuais ficam para a próxima fase.
> O caminho dos dados, com diagramas, está em [fluxos-transparencia.md](fluxos-transparencia.md).
> Cada funcionalidade descrita aqui fica atrás de uma flag do experimento ([feature-flags.md](feature-flags.md)).

## 1. Objetivo e princípios

O SisREAd decide por **regras**: nível educacional, tipo de conteúdo, tema buscado e meta de
aprendizagem (EMAPRE). Como as regras são a própria decisão, a explicação é **model-intrinsic**:
ela é montada no mesmo ponto do código em que a regra é avaliada, e não por um modelo separado.

Classificação da abordagem no vocabulário do MSL:

| Dimensão | Valor |
|---|---|
| Abordagem | Model-intrinsic |
| Técnica | Rule-based + Template-based |
| Estilo | Feature-based (atributos do REA) + User-based (perfil, tipos preferidos, meta) |
| Formato | Textual + numérico (médias EMAPRE, contagens por faixa) + marcadores visuais |
| Objetivo | Transparência |

Princípios:

1. **Decisão e explicação vêm da mesma fonte.** `RuleClassifier` devolve os critérios avaliados, e
   o rótulo (`recommended`) e o grau de recomendação são calculados a partir deles. O `Ranking` ordena
   pelo grau recalculado dos mesmos critérios. Não há como a explicação dizer uma coisa e a ordenação
   fazer outra.
2. **Só se afirma o que foi verificado.** Um critério que não foi avaliado aparece como
   *não avaliado* (`?`), nunca como atendido, e vale 0 ponto no grau. O nível *assumido* (o texto não
   menciona etapa) também aparece como `?` e vale 0.
3. **A fonte de cada informação é declarada**: regex, IA, filtro do repositório, colaboradores,
   meta do usuário ou padrão do repositório. Estimativas automáticas vêm com aviso de que podem errar.
4. **Nenhum repositório tem posição reservada.** Até a versão 1 das regras, o MEC RED e o Eduplay
   tinham faixas fixas por política. Desde a versão 2, a regra é a mesma para todos (§5 e §6).

## 2. Três níveis de explicação

| Nível | Pergunta do usuário | Onde aparece |
|---|---|---|
| Item | "Por que este REA está aqui?" | Selo com grau e faixa ("Grau 5 de 7 · Meta e nível ou tipo") + botão **Por que este REA?** com a conta do grau e o desempate |
| Lista | "Como esta lista foi ordenada?" | Painel **Como ordenamos** (fórmula do grau, contagem por faixa, desempate, itens ocultos) |
| Usuário | "O que o sistema usou sobre mim?" | Painel **O que usamos sobre você** (perfil, termo, tipos preferidos com origem, meta EMAPRE com médias) |

## 3. Arquitetura

```
FindREA::search()
  ├─ monta o contexto do usuário (perfil, termo digitado → termo da API, tipos preferidos
  │  por origem, meta EMAPRE com médias) → $contexto (painel "O que usamos sobre você")
  └─ dispatch jobs com tipos preferidos já normalizados
        │
Process{Aquarela,MecRed,Eduplay}
  ├─ RuleClassifier::criterio*()   → critérios {status, valor, esperado, fonte, evidencia}
  ├─ RuleClassifier::rotular()     → recommended (mesma regra nos três repositórios)
  ├─ RuleClassifier::explicacao()  → critérios + grau (pontos e posição no repositório)
  └─ grava no item: recommended, explicacao, fonte_interatividade
        │
FindREA::paginate()  → Ranking::ordenar()   (grau, posição no repositório, repositório, chave)
FindREA::resumoOrdenacao() → Ranking::contar() (painel "Como ordenamos")
Blade → ExplanationRenderer::resumo()/linhas()/faixa()/grau()  (texto por templates)
      → FindREA::registrarExplicacao()  (tabela explanation_events)
```

Arquivos:

| Arquivo | Papel |
|---|---|
| `app/Recommendation/RuleClassifier.php` | Normalização, inferência de nível, critérios, pesos, grau e rótulo |
| `app/Recommendation/Ranking.php` | Visibilidade, ordenação pelo grau com desempate e contagem (inclusive ocultos) |
| `app/Recommendation/ExplanationRenderer.php` | Templates de texto em português |
| `app/Models/ExplanationEvent.php` + migration | Registro de uso das explicações |
| `app/Jobs/Process*.php` | Gravam `explicacao` em cada REA |
| `app/Helpers/helpers.php` | `mecRedEtapas()` e `mecRedTiposObjeto()`, usados na URL **e** na explicação |
| `app/Livewire/FindREA.php` | `$contexto`, `paginate`, `resumoOrdenacao`, `registrarExplicacao` |
| `resources/views/livewire/find-r-e-a.blade.php` | Selos, detalhe por item, painéis |

## 4. Estrutura `explicacao` (gravada em cada REA de `Data.data`)

Não é preciso migration: fica dentro do JSON que já existe.

```json
{
  "recommended": "profile",
  "fonte_interatividade": "dtype",
  "explicacao": {
    "versao_regras": 2,
    "faixa": "profile",
    "grau": {"total": 2, "maximo": 7, "pontos": {"meta": 0, "nivel": 2, "tipo": 0}, "posicao": 3},
    "observacao": null,
    "criterios": {
      "tema":  {"status": "ok", "valor": "algoritmos", "fonte": "busca", "repositorio": "Aquarela"},
      "nivel": {"status": "ok", "valor": "ensino fundamental", "esperado": "ensino fundamental",
                "fonte": "regex", "evidencia": "6º", "assumido": false},
      "tipo":  {"status": "falhou", "valor": "jogo", "esperado": ["video", "livro digital"],
                "fonte": "colaboradores"},
      "meta":  {"status": "nao_avaliado", "valor": null, "esperado": "ma", "fonte": "llm"}
    }
  }
}
```

### Campos de um critério

| Campo | Significado |
|---|---|
| `status` | `ok` (atende), `falhou` (não atende), `nao_avaliado` (não foi possível ou não se aplica verificar), `filtro_api` (o repositório já filtrou por isso) |
| `valor` | O que foi observado no REA |
| `esperado` | O que o usuário pediu: perfil normalizado, lista de tipos ou código da meta |
| `fonte` | `busca`, `regex`, `colaboradores`, `llm`, `filtro_api`, `padrao_repositorio`, `usuario` (corrigido pelo usuário, §13) |
| `evidencia` | Prova concreta: trecho casado pelo regex, etapas e tipos do filtro, justificativa da regra fixa |
| `assumido` | (nível) `true` quando o regex não achou nada e o sistema assumiu *ensino superior* |
| `original` | (nível, meta corrigidos) o critério como o job gravou |
| `esperado_original`, `origem_esperado` | (tipo) lista de tipos do sistema e `usuario` quando o usuário redefiniu os tipos preferidos |

Cada REA também tem `chave` (hash de repositório, link e título), usada para apontar correções, e a
`explicacao` ganha `faixa_original` (faixa do job) enquanto houver correção.

`grau` é calculado por `RuleClassifier::grau` a partir dos critérios: `pontos` traz os pontos de cada
critério (`PESOS`: meta 4, nível 2, tipo 1), `maximo` soma os pesos dos critérios presentes, e
`posicao` é a ordem do REA na resposta do repositório (1, 2, …), usada no desempate. As correções do
usuário recalculam o grau e mantêm a posição.

`versao_regras` (constante `RuleClassifier::VERSAO_REGRAS`) permite separar, na avaliação,
explicações geradas por versões diferentes das regras. A versão 2 trouxe o grau, a regra única para
os três repositórios e o fim da `observacao` de política (que só aparece em REAs da versão 1).

REAs gravados antes desta funcionalidade não têm `explicacao`. O renderer mostra
"Explicação indisponível para esta busca" e não quebra.

## 5. Regras por repositório

Os três repositórios usam o mesmo `RuleClassifier::rotular` e o mesmo `RuleClassifier::grau`. O que muda
é só o dado que cada um informa para conferir os critérios.

### 5.1 Aquarela

| Critério | Como é avaliado | Fonte |
|---|---|---|
| tema | Todo item é resultado da busca pelo termo | `busca` |
| nível | Regex em título + descrição: infantil → fundamental → médio. Sem casamento, assume superior (`assumido = true`). Compara com o perfil normalizado | `regex` |
| tipo | `tipoConteudo` normalizado ∈ tipos preferidos | `colaboradores` |
| meta | Só se o usuário tem meta. O `MetaClassifier` (LLM, por padrão Ollama `gemma3:4b`) classifica e o resultado é comparado com a meta dominante. Se a resposta for inválida, o serviço falhar ou o tempo reservado acabar, o critério fica `nao_avaliado`, com o motivo em `evidencia` (§5.4) | `llm` |

Rótulo (`RuleClassifier::rotular`), em que "atendido" é `RuleClassifier::atende` (status `ok`, não
assumido):

```
com meta e meta atendida:  nível e tipo → meta_both | nível ou tipo → meta_one | nenhum → meta
caso contrário:            nível e tipo → both      | só nível → profile       | senão → interest
```

`fonte_interatividade = dtype` (`T` = ativo, `D` = expositivo); sem `dtype`, `indisponivel`.

### 5.2 MEC RED

O SisREAd envia filtros de etapa (`educational_stages`) e de tipo por meta (`object_type`), mas a
resposta não traz a etapa nem o tipo de cada item. No teste manual de 2026-09-18, a API devolveu
**os mesmos 10 itens** com e sem filtros (ver `problemas-conhecidos.md` #15). Por isso o filtro não
conta como conferência:

| Critério | Status | Evidência |
|---|---|---|
| tema | `ok` | termo da busca |
| nível | regex sobre `name` e `description`, como no Aquarela (fonte `regex`, corrigível) | trecho casado, ou *assumido* (0 ponto) |
| tipo | `nao_avaliado` | o MEC RED não informa o tipo |
| meta | com meta: classificada por IA (§5.4), só pelo título | "classificado só pelo título, porque o MEC RED não informa descrição nem tipo; a busca já pediu tipos de objeto associados à sua meta (object_type=…)" |

O rótulo e o grau saem da regra comum. Na prática, um item do MEC RED sem etapa no título só pontua
pela meta (classificada por IA) e fica abaixo dos que conferem nível ou tipo, intercalado com os de mesmo
grau. O status `filtro_api` (▽) continua disponível para um repositório que confirme o filtro aplicado,
mas não pontua.
`fonte_interatividade = meta_usuario` quando há meta (interatividade derivada da meta do usuário,
e não do recurso). Sem meta, `indisponivel`.

### 5.3 Eduplay

| Critério | Status |
|---|---|
| tema | `ok` |
| nível | regex sobre `name` e `metatagDescription` (fonte `regex`, corrigível) |
| tipo | `video` comparado com os tipos preferidos (`criterioTipo`, fonte `colaboradores`). O job recebe os tipos da busca |
| meta | com meta: classificada por IA (§5.4) a partir do título e de `metatagDescription` |

Rótulo e grau pela regra comum, sem `observacao`. Até a versão 1 das regras, o rótulo era fixo:
`meta_one` para `ma`/`mpa`, `interest` para `mpe`, sem olhar o recurso. Com a meta não avaliada, o REA
aparece com 0 ponto de meta (não é ocultado). `fonte_interatividade = padrao_repositorio`. A meta do
Eduplay é corrigível pelo usuário (fonte `llm`).

### 5.4 Classificação de meta por IA (`MetaClassifier`)

Os três jobs usam a mesma classe, `App\Recommendation\MetaClassifier`, com o mesmo prompt e a mesma
interpretação. Ela devolve o critério pronto (`RuleClassifier::criterioMeta`) com `modelo`, `duracao`
(do lote de chamadas simultâneas) e `evidencia`. A resposta só vale se `{"meta": …}` casar com
exatamente uma das três metas; senão, `nao_avaliado`. Nunca há meta "atendida" sem classificação.

| Situação | `status` | `evidencia` |
|---|---|---|
| Classificado agora | `ok`/`falhou` | (MEC RED: "classificado só pelo título…") |
| Reaproveitado do cache (mesma `chave`, modelo e versão do prompt) | `ok`/`falhou` | "classificação reaproveitada de uma busca anterior" (`duracao` nula) |
| Erro HTTP, conexão recusada ou tempo esgotado | `nao_avaliado` | "a IA não respondeu (erro ou tempo esgotado)" |
| JSON inválido ou ambíguo | `nao_avaliado` | "a IA respondeu fora do formato esperado" |
| `falhas_seguidas_max` falhas seguidas (neste job ou em outro, nos últimos `pausa_apos_falha_segundos`) | `nao_avaliado` | "a IA está indisponível (falhou várias vezes seguidas) e não foi consultada para este recurso" |
| Orçamento de tempo do job esgotado | `nao_avaliado` | "o tempo reservado à classificação por IA (240s por repositório) acabou antes deste recurso" |
| `LLM_PROVEDOR=nenhum` | `nao_avaliado` | "a classificação por IA está desativada nesta instalação" |

O texto (`ExplanationRenderer::textoMeta`) mostra a evidência: "Meta não verificada: …" ou, quando
classificado, "Classificado por IA como …. Modelo: …. Classificação reaproveitada de uma busca
anterior." Configuração e desempenho em [integracoes.md](integracoes.md#llm-classificação-de-meta).

## 6. Ordenação (`Ranking`)

A lista é ordenada pelo **grau de recomendação** (fórmula em [recomendacao.md](recomendacao.md)), com
desempate estável e documentado:

1. grau, decrescente (`Ranking::grauDe`, recalculado dos critérios);
2. posição do REA no próprio repositório (`explicacao.grau.posicao`), crescente. Isso intercala os
   repositórios;
3. nome do repositório, em ordem alfabética;
4. `chave`.

A ordem de chegada dos jobs não influi (testado com a entrada embaralhada em `RankingTest`).

| Contexto | Faixas exibidas | Ocultos |
|---|---|---|
| Usuário com meta dominante | `meta_both` → `meta_one` → `meta` → `both` → `profile` → `interest` (as três últimas com "meta não conferida") | REAs com meta **conferida** como incompatível (`falhou`) |
| Sem meta | `both` → `profile` → `interest` | `meta*` |

`Ranking::visivel` decide o que aparece. `Ranking::contar` devolve a contagem por faixa, o total de
ocultos, o **motivo** de cada oculto (`meta_incompativel`, `meta_corrigida_incompativel`,
`sem_meta_usuario`, `outros`) e `meta_nao_conferida`: quantos REAs aparecem no fim da lista porque a
meta deles não pôde ser conferida. O motivo `meta_nao_avaliada` deixou de existir, porque esses REAs
não são mais ocultados (problema #16). O painel **Como ordenamos** mostra a fórmula, as faixas com o
intervalo de grau, o desempate e também a situação de cada repositório (`FindREA::statusRepositorios`,
lida de `search_metrics`): itens retornados, falha (tempo esgotado ou erro) ou ainda consultando.
Assim, uma lista sem itens do Aquarela é explicada ("não respondeu"), e não fica só a ausência.

Mudança de comportamento: sem meta, os itens `profile` **passam a aparecer**, entre `both` e
`interest`. Antes eles eram descartados (problema conhecido #1).

## 7. Textos (templates)

`ExplanationRenderer::linhas()` gera uma linha por critério, com ícone e texto:

| Ícone | Status |
|---|---|
| ✓ | ok |
| ✗ | falhou |
| ? | não avaliado |
| ▽ | filtrado pelo repositório |

Exemplos:

| Critério | Situação | Texto |
|---|---|---|
| tema | ok | Resultado da busca por “algoritmos” no Aquarela. |
| nível | ok, regex | Nível ensino fundamental, igual ao seu perfil: identificado pelo trecho “6º” no título ou na descrição. |
| nível | ok, assumido (ícone `?`, 0 ponto) | Nível ensino superior, igual ao seu perfil, mas assumido: o texto não menciona nenhuma etapa. |
| nível | falhou, regex | Nível estimado ensino medio (trecho “médio”), diferente do seu perfil (ensino fundamental). |
| nível | falhou, assumido | O texto não menciona nenhuma etapa; o sistema assume ensino superior, diferente do seu perfil (…). |
| nível | filtro_api | O MEC RED filtrou a busca pela etapa do seu perfil (ensino fundamental). |
| tipo | ok | Tipo video, entre os tipos preferidos (video, livro digital). |
| tipo | falhou | Tipo jogo não está entre os tipos preferidos (video, livro digital). |
| meta | ok, llm | Classificado por IA como Aprendizagem, compatível com a sua meta (Aprendizagem). |
| meta | nao_avaliado | Não foi possível classificar a meta deste recurso (IA indisponível ou resposta inválida). |

- `resumo()` gera uma linha: "Atende: tema, nível. Não atende: tipo. Não verificado: meta." Usa
  `RuleClassifier::atende`, então nível assumido e `filtro_api` ficam em "Não verificado".
- `faixa()` gera título, descrição e intervalo de grau do selo. Por exemplo, `profile` → "Nível", grau 2:
  "o nível bate com o seu perfil, mas o tipo não está entre os preferidos ou não foi conferido". Numa
  busca com meta, `both`/`profile`/`interest` ganham ", meta não conferida".
- `grau()` gera o selo ("Grau 5 de 7"), a conta ("Grau 5 de 7: meta +4, nível 0 (não atende),
  tipo +1.") e o desempate ("Entre REAs de mesmo grau, vale a posição no repositório: este é o 3º
  resultado do Aquarela."). O critério com 0 ponto diz por quê: não atende, não verificado ou assumido.
- `avisos()` devolve "Estimativa automática (regex/IA): pode conter erros." quando algum critério
  atendido ou não atendido veio de `regex` ou `llm`.

## 8. Contexto do usuário (`FindREA::$contexto`)

Montado em `search()`, antes do `reset` dos campos:

```php
[
  'perfil'          => 'Ensino fundamental',
  'interesse'       => 'Algoritmos',
  'termo_api'       => 'algoritmos',          // após findAdequateTerm()
  'tipos_busca'     => ['video'],             // colaboradores com mesmo tema e perfil
  'tipos_gerais'    => ['jogo', 'e-book', 'livro digital'], // demais colaboradores
  'meta'            => ['dominante' => 'ma', 'ma' => 4.2, 'mpa' => 3.1, 'mpe' => 2.0], // ou null
]
```

Os tipos enviados aos jobs são a união normalizada (`RuleClassifier::normalizarTipos`: minúsculas,
sem acento, sem vazios nem duplicados, com `e-book` → também `livro digital`). Isso corrige o bug
em que os itens de todos os colaboradores entravam como arrays e nunca casavam.

## 9. Registro de uso (`explanation_events`)

| Coluna | Conteúdo |
|---|---|
| `searched_at` | chave da busca (`Data.searched_at`) |
| `user_id` | usuário logado ou `null` |
| `acao` | `abriu_explicacao`, `abriu_ordenacao`, `abriu_contexto`, `abriu_ocultos` |
| `repositorio`, `titulo`, `faixa` | preenchidos em `abriu_explicacao` |

`FindREA::registrarExplicacao()` é `#[Renderless]` (não re-renderiza) e só aceita as ações de `ExplanationEvent::ACOES`.
Uma migration acrescenta dois motivos de feedback ligados à explicação, e o
`FeedbackReasonSeeder` também:

- "As explicações das recomendações estavam erradas ou confusas."
- "As explicações não ajudaram a entender a ordem dos resultados."

Esses dados alimentam a Etapa 3 (avaliação com usuários).

## 10. Correções feitas junto (pré-requisito de honestidade)

| # | Correção |
|---|---|
| D1 | `paginate()` classificava `meta_both`/`meta_one` também como `interest` (`if` sem `elseif`). Agora usa `Ranking` |
| D2 | Itens `profile` passam a aparecer sem meta |
| D3 | Prompt do Ollama pede JSON `{"meta": ...}`, e a comparação normaliza acento, espaço e hífen (`performance aproximação` ≡ `performance_aproximacao`) |
| D4 | Tipos preferidos achatados e normalizados |
| D5–D7 | MEC RED e Eduplay explicavam a política fixa, e a fonte da interatividade é declarada. A política fixa acabou na versão 2 das regras (§5) |
| D8 | Typo `obkect_type` corrigido |

## 11. Testes

Os testes rodam em SQLite em memória (`phpunit.xml`), sem tocar em `database/database.sqlite`.

| Arquivo | Cobre |
|---|---|
| `tests/Unit/RuleClassifierTest.php` | normalização, tipos, inferência de nível com evidência, critérios, `casaMeta`, tabela de rótulos, tabela de graus, grau e rótulo concordam, nível assumido não conta |
| `tests/Unit/RankingTest.php` | ordem por grau com e sem meta, desempate por posição e repositório, ordem de chegada não influi, só a meta incompatível oculta, contagem |
| `tests/Unit/ExplanationRendererTest.php` | texto por status, nunca ✓ para não avaliado, resumo, avisos, faixa, item legado sem explicação |
| `tests/Feature/JobsExplicacaoTest.php` | os três jobs com `Http::fake`: todo item tem `explicacao` e grau coerentes com `recommended`, posição gravada, MEC RED e Eduplay sem prioridade fixa, meta por IA nos três, Ollama fora do ar → `nao_avaliado`, métricas da LLM em `search_metrics` |
| `tests/Feature/MetaClassifierTest.php` | sucesso, JSON inválido/ambíguo, timeout, provedor que lança exceção, cache entre buscas (e o que não entra nele), parada após falhas, orçamento, lotes, aquecimento, `LLM_PROVEDOR=nenhum`, configuração por `config/llm.php` |
| `tests/Feature/FindREATransparenciaTest.php` | `search()` monta `$contexto` e tipos normalizados (também para o Eduplay), `paginate` mistura os repositórios pelo grau, `resumoOrdenacao`, selo e painel com o grau, `registrarExplicacao` grava e rejeita ação inválida |
| `tests/Unit/UserCorrectionsTest.php` | correção de nível, meta e tipos: critério, `original`, rótulo, desfazer, item de política, entradas inválidas |
| `tests/Feature/EscrutabilidadeTest.php` | ações de correção do `FindREA`: gravam `data.data` e `corrections`, reordenam, bloqueiam durante a busca; tela de correção e painel de tipos |

Rodar: `php artisan test`.

## 11.1 Avisos e falhas silenciosas (fase 1b)

Quatro pontos em que o sistema falhava calado ou omitia o que estava fazendo:

1. **Interesse não reconhecido**: `search()` resolve o termo **antes** de disparar os jobs. Sem
   correspondência, mostra "Não sabemos buscar por X. Interesses disponíveis: …" e não cria busca
   nenhuma. `opcoesInteresse()` junta os fixos com os dos colaboradores, sem duplicatas.
2. **Progresso da busca**: enquanto algum repositório não respondeu, a tela mostra "Consultando
   repositórios… N de 3 responderam", em vez de só "Carregando". Isso cobre o efeito do `finished`
   prematuro (problema #2), em que a lista parecia pronta e continuava crescendo.
3. **Classificação por IA**: o critério de meta guarda o modelo (`MetaClassifier::modelo()`, de
   `config/llm.php`) e quanto tempo levou, e o texto os declara ("Modelo: gemma3:4b, em 1.23s"). O aviso de estimativa
   automática menciona que o modelo local tende a favorecer "Aprendizagem" (problema #18).
4. **REAs excluídos**: o painel de ordenação ganhou "Ver os REAs que não aparecem", com título,
   repositório, motivo e resumo dos critérios (até 20). A ação vira o evento `abriu_ocultos`.

## 12. Próxima fase (diferenciais)

- **Contrafactual**: derivado dos critérios `falhou` e dos pesos do grau. Exemplo: "se o tipo fosse
  vídeo, este REA subiria do grau 2 para o 3 (faixa Nível e tipo)".
- **Escrutabilidade**: implementada, ver §13. Ficou de fora a edição da meta EMAPRE do usuário.

## 13. Escrutabilidade

O usuário corrige o que o sistema estimou, e a lista se reordena na hora. Plano, decisões e
diagramas: [plano-escrutabilidade.md](plano-escrutabilidade.md). Fluxo:
[fluxos-transparencia.md §10](fluxos-transparencia.md#10-fluxo-9-escrutabilidade).

| O que se corrige | Onde | Efeito |
|---|---|---|
| Nível do REA (fonte `regex`) | **Por que este REA?** → Corrigir | status recalculado contra o perfil |
| Meta do REA (fonte `llm`) | **Por que este REA?** e **Ver os REAs que não aparecem** | status recalculado contra a meta do usuário; um oculto pode voltar |
| Tipos preferidos | **O que usamos sobre você** → Editar tipos preferidos | critério de tipo recalculado em todos os REAs comparáveis |

Regras:

1. O usuário informa **o que o REA é**, e não se ele atende. O status vem da mesma comparação do
   job, o rótulo de `RuleClassifier::rotular` e o grau de `RuleClassifier::grau` (a posição no
   repositório é mantida). Decisão e explicação continuam com a mesma fonte.
2. Só é corrigível critério com fonte `regex` ou `llm` (`RuleClassifier::corrigivel`). Isso inclui o
   nível de MEC RED e Eduplay, que agora vem do regex. O tema e os itens de política da versão 1 das
   regras não são, e a tela diz por quê.
3. O critério do job fica em `original`, e a faixa do job em `faixa_original`. Toda correção pode
   ser desfeita, uma a uma ou todas. Corrigir para o valor estimado equivale a desfazer.
4. A correção vale só para a busca atual (fica em `data.data`).
5. Correções só ficam disponíveis depois que os três repositórios responderam
   (`FindREA::podeCorrigir`), porque os jobs regravam `data.data` sem lock (problema #3).

Código: `App\Recommendation\UserCorrections` (funções puras sobre o item) e as ações
`corrigirNivel`, `corrigirMeta`, `desfazerCorrecao`, `redefinirTipos` e `desfazerTodas` do `FindREA`.

Textos novos (`ExplanationRenderer`):

| Situação | Texto |
|---|---|
| nível corrigido | Nível ensino fundamental, informado por você (o sistema tinha estimado ensino médio). Igual ao seu perfil. |
| meta corrigida | Meta Aprendizagem, informada por você (a IA não tinha conseguido classificar). Compatível com a sua meta (Aprendizagem). |
| tipos editados | Tipo jogo, entre os tipos preferidos que você definiu (jogo, video). |
| faixa mudou | ✎ Faixa alterada pela sua correção: antes Tipo ou só tema, agora Nível. |
| resumo | Atende: tema, nível (corrigido por você). Não atende: tipo. |

O aviso de estimativa automática some para o critério corrigido. O painel **Como ordenamos** mostra
"N REAs mudaram de faixa por correções suas" e **Desfazer todas**. Um oculto cuja meta o usuário
marcou como diferente da dele aparece com o motivo `meta_corrigida_incompativel`.

Registro: cada correção e cada desfazer viram uma linha em `corrections` (ver [dados.md](dados.md)),
e abrir um formulário de correção grava o evento `abriu_correcao` em `explanation_events`. No painel
de tipos o evento vai sem repositório e título.
