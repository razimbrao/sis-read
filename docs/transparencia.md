# Transparência das recomendações (explicabilidade model-intrinsic)

> Especificação técnica da implementação da transparência no SisREAd. O plano e a justificativa
> baseada no mapeamento sistemático (MSL) estão em [plano-transparencia.md](plano-transparencia.md).
> Escrutabilidade e explicações contrafactuais ficam para a próxima fase e usam as mesmas estruturas.

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
   o rótulo (`recommended`) é calculado a partir deles. Não há como a explicação dizer uma coisa e a
   ordenação fazer outra.
2. **Só se afirma o que foi verificado.** Um critério que não foi avaliado aparece como
   *não avaliado* (`?`), nunca como atendido.
3. **A fonte de cada informação é declarada**: regex, IA, filtro do repositório, colaboradores,
   meta do usuário ou padrão do repositório. Estimativas automáticas vêm com aviso de que podem errar.
4. **Exceções de política são explicadas como exceções.** O MEC RED e o Eduplay têm faixas fixas
   por repositório. A explicação diz isso, e não finge uma checagem que não houve.

## 2. Três níveis de explicação

| Nível | Pergunta do usuário | Onde aparece |
|---|---|---|
| Item | "Por que este REA está aqui?" | Selo de faixa + botão **Por que este REA?** em cada linha |
| Lista | "Como esta lista foi ordenada?" | Painel **Como ordenamos** (contagem por faixa, itens ocultos) |
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
  ├─ RuleClassifier::rotular()     → recommended (Aquarela)  | faixa fixa + observação (MEC RED, Eduplay)
  └─ grava no item: recommended, explicacao, fonte_interatividade
        │
FindREA::paginate()  → Ranking::ordenar()   (ordem das faixas)
FindREA::resumoOrdenacao() → Ranking::contar() (painel "Como ordenamos")
Blade → ExplanationRenderer::resumo()/linhas()/faixa()  (texto por templates)
      → FindREA::registrarExplicacao()  (tabela explanation_events)
```

Arquivos:

| Arquivo | Papel |
|---|---|
| `app/Recommendation/RuleClassifier.php` | Normalização, inferência de nível, critérios e rótulo |
| `app/Recommendation/Ranking.php` | Ordem das faixas, ordenação e contagem (inclusive ocultos) |
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
    "versao_regras": 1,
    "faixa": "profile",
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
| `fonte` | `busca`, `regex`, `colaboradores`, `llm`, `filtro_api`, `padrao_repositorio` |
| `evidencia` | Prova concreta: trecho casado pelo regex, etapas e tipos do filtro, justificativa da regra fixa |
| `assumido` | (nível) `true` quando o regex não achou nada e o sistema assumiu *ensino superior* |

`versao_regras` (constante `RuleClassifier::VERSAO_REGRAS`) permite separar, na avaliação,
explicações geradas por versões diferentes das regras.

REAs gravados antes desta funcionalidade não têm `explicacao`. O renderer mostra
"Explicação indisponível para esta busca" e não quebra.

## 5. Regras por repositório

### 5.1 Aquarela (regra completa)

| Critério | Como é avaliado | Fonte |
|---|---|---|
| tema | Todo item é resultado da busca pelo termo | `busca` |
| nível | Regex em título + descrição: infantil → fundamental → médio. Sem casamento, assume superior (`assumido = true`). Compara com o perfil normalizado | `regex` |
| tipo | `tipoConteudo` normalizado ∈ tipos preferidos | `colaboradores` |
| meta | Só se o usuário tem meta. O LLM (Ollama `gemma3:4b`) classifica e o resultado é comparado com a meta dominante. Se a resposta for inválida ou o serviço falhar, o critério fica `nao_avaliado` | `llm` |

A confiabilidade desse critério foi medida na monografia com Llama 3.1 (acurácia de 85,48%, F1 macro de 0,85,
erros concentrados entre Performance-Aproximação e Performance-Evitação). Ver [monografia.md](monografia.md#42-classificação-por-meta-com-llm-qs2).
Por isso a explicação traz o aviso de estimativa automática.

Rótulo (`RuleClassifier::rotular`), idêntico à regra anterior:

```
com meta e meta atendida:  nível e tipo → meta_both | nível ou tipo → meta_one | nenhum → meta
caso contrário:            nível e tipo → both      | só nível → profile       | senão → interest
```

`fonte_interatividade = dtype` (`T` = ativo, `D` = expositivo); sem `dtype`, `indisponivel`.

### 5.2 MEC RED (faixa fixa por política)

O SisREAd envia filtros de etapa (`educational_stages`) e de tipo por meta (`object_type`), mas a
resposta não traz a etapa nem o tipo de cada item. No teste manual de 2026-09-18, a API devolveu
**os mesmos 10 itens** com e sem filtros (ver `problemas-conhecidos.md` #15). Por isso nenhum
critério além do tema é marcado como atendido. A evidência registra o que foi pedido:

| Critério | Status | Evidência |
|---|---|---|
| tema | `ok` | termo da busca |
| nível | `nao_avaliado` | "o SisREAd pediu ao MEC RED itens desta etapa (educational_stages=…), mas o repositório não informa a etapa de cada item para conferir". Se o perfil não corresponde a nenhuma etapa, "nenhum filtro de nível foi aplicado" |
| tipo | `nao_avaliado` | o MEC RED não informa o tipo |
| meta | com meta: `nao_avaliado` | pedido de `object_type=…` associado à meta |

Rótulo fixo: `both` (ou `meta_both` com meta), como antes. `observacao`: "Por política do SisREAd,
os itens do MEC RED ficam na faixa mais alta, mas nível, tipo e meta não puderam ser conferidos
neste repositório." O status `filtro_api` (▽) continua disponível para um repositório que confirme
o filtro aplicado.
`fonte_interatividade = meta_usuario` quando há meta (interatividade derivada da meta do usuário,
e não do recurso). Sem meta, `indisponivel`.

### 5.3 Eduplay (faixa fixa por política)

| Critério | Status |
|---|---|
| tema | `ok` |
| nível | `nao_avaliado`: o Eduplay não informa etapa |
| tipo | `nao_avaliado`: é sempre vídeo e não é comparado com os tipos preferidos |
| meta | com meta: `ok` para `ma`/`mpa`, `falhou` para `mpe` (`padrao_repositorio`) |

Rótulo fixo: `meta_one` para metas `ma`/`mpa`. Senão, `interest`. `fonte_interatividade = padrao_repositorio`.

## 6. Ordenação (`Ranking`)

| Contexto | Ordem das faixas | Ocultos |
|---|---|---|
| Usuário com meta dominante | `meta_both` → `meta_one` → `meta` | `both`, `profile`, `interest` (REAs incompatíveis com a meta) |
| Sem meta | `both` → `profile` → `interest` | `meta*` |

Dentro de cada faixa a ordem de chegada é mantida (ordenação estável). `Ranking::contar` devolve
a contagem por faixa, o total de ocultos e o **motivo** de cada oculto (`meta_incompativel`,
`meta_nao_avaliada` quando a IA não classificou, `sem_meta_usuario`, `outros`). O painel **Como
ordenamos** mostra tudo isso e também a situação de cada repositório (`FindREA::statusRepositorios`,
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
| nível | ok, assumido | Nível ensino superior, igual ao seu perfil, mas assumido: o texto não menciona nenhuma etapa. |
| nível | falhou, regex | Nível estimado ensino medio (trecho “médio”), diferente do seu perfil (ensino fundamental). |
| nível | falhou, assumido | O texto não menciona nenhuma etapa; o sistema assume ensino superior, diferente do seu perfil (…). |
| nível | filtro_api | O MEC RED filtrou a busca pela etapa do seu perfil (ensino fundamental). |
| tipo | ok | Tipo video, entre os tipos preferidos (video, livro digital). |
| tipo | falhou | Tipo jogo não está entre os tipos preferidos (video, livro digital). |
| meta | ok, llm | Classificado por IA como Aprendizagem, compatível com a sua meta (Aprendizagem). |
| meta | nao_avaliado | Não foi possível classificar a meta deste recurso (IA indisponível ou resposta inválida). |

- `resumo()` gera uma linha: "Atende: tema, nível. Não atende: tipo. Não verificado: meta."
- `faixa()` gera título e descrição do selo. Por exemplo, `profile` → "Nível": "o nível bate com o seu
  perfil, mas o tipo não está entre os preferidos".
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
| `acao` | `abriu_explicacao`, `abriu_ordenacao`, `abriu_contexto` |
| `repositorio`, `titulo`, `faixa` | preenchidos em `abriu_explicacao` |

`FindREA::registrarExplicacao()` é `#[Renderless]` (não re-renderiza) e só aceita as três ações.
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
| D5–D7 | MEC RED e Eduplay explicam a política fixa, e a fonte da interatividade é declarada |
| D8 | Typo `obkect_type` corrigido |

## 11. Testes

Os testes rodam em SQLite em memória (`phpunit.xml`), sem tocar em `database/database.sqlite`.

| Arquivo | Cobre |
|---|---|
| `tests/Unit/RuleClassifierTest.php` | normalização, tipos, inferência de nível com evidência, critérios, `casaMeta`, tabela de rótulos |
| `tests/Unit/RankingTest.php` | ordem com e sem meta, estabilidade, ocultos, contagem |
| `tests/Unit/ExplanationRendererTest.php` | texto por status, nunca ✓ para não avaliado, resumo, avisos, faixa, item legado sem explicação |
| `tests/Feature/JobsExplicacaoTest.php` | os três jobs com `Http::fake`: todo item tem `explicacao` coerente com `recommended`, Ollama fora do ar → `nao_avaliado` |
| `tests/Feature/FindREATransparenciaTest.php` | `search()` monta `$contexto` e tipos normalizados, `paginate` e `resumoOrdenacao`, `registrarExplicacao` grava e rejeita ação inválida |

Rodar: `php artisan test`.

## 12. Próxima fase (diferenciais)

- **Contrafactual**: derivado dos critérios `falhou`. Exemplo: "se o tipo fosse vídeo, este REA
  subiria para a faixa Nível e tipo".
- **Escrutabilidade**: cada critério de fonte `regex` ou `llm` ganha "corrigir". O painel do usuário
  permite editar os tipos preferidos e a meta. As correções entram no `RuleClassifier` como
  sobrescritas e ficam registradas.
