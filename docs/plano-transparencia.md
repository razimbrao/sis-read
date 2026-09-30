# Plano: transparência (model-intrinsic) no SisREAd

> Etapa 2 do TCC, parte 1. Base teórica: MSL em `Documents/mapeamento/extracao_dados/docs`
> (`07_etapa_sisread_explicabilidade.md`, `12_descobertas_por_rq.md`). Escrutabilidade e
> contrafactual ficam para a parte 2 e reaproveitam as estruturas criadas aqui.

## 1. Enquadramento

O SisREAd é um recomendador **baseado em regras** (conhecimento + conteúdo). As regras são a
própria decisão, então a explicação é **model-intrinsic** por construção: basta registrar e expor
quais regras dispararam. Alinhado ao padrão modal do corpus:

| Dimensão (vocabulário do MSL) | Escolha para o SisREAd |
|---|---|
| Abordagem | Model-intrinsic |
| Técnica | Rule-based + Template-based (texto gerado de templates, não de LLM) |
| Estilo | Feature-based (nível, tipo, tema, meta) + User-based (perfil e meta do usuário) |
| Formato | Textual + numérico (+ visual leve: ícones ✓/✗/?) |
| Objetivo | Transparência (agora); confiança medida na Etapa 3 |

Princípio de honestidade (lição dos 6 artigos excluídos por explicabilidade retórica): **a
explicação só afirma o que o código realmente verificou**. Critério não avaliado aparece como
"não avaliado", nunca como ✓.

## 2. O que precisa ser explicado (três níveis)

1. **Por item** — "Por que este REA?": quais critérios bateram, com qual evidência.
2. **Pela lista** — "Como esta lista foi ordenada?": a regra de ordenação e quantos itens caíram
   em cada faixa (inclusive os descartados).
3. **Pelo usuário** — "O que o SisREAd sabe sobre você": perfil informado, tema mapeado para o
   termo da API, tipos preferidos e de onde vieram, meta EMAPRE com as três médias.

## 3. Diagnóstico: o que hoje impediria uma explicação honesta

Corrigir antes (ou junto) — senão a explicação expõe regras erradas ou afirma coisas falsas.

| # | Problema | Onde | Efeito na explicação | Ação |
|---|---|---|---|---|
| D1 | `paginate()` usa `if` separado para `meta`; `meta_both`/`meta_one` caem no `else` | `FindREA.php:138` | Itens aparecem em faixa errada | Trocar por `match`/`elseif` (problema #1) |
| D2 | Sem meta, itens `profile` são descartados | `FindREA::paginate` | Usuário não vê itens que batem com o nível | Ordem `both → profile → interest` (**decisão do orientador**: é mudança de comportamento) |
| D3 | Prompt Ollama pede texto, parser lê `result['meta']`; `analisarMeta` busca `performance_aproximacao` | `ProcessAquarela.php:214-270` | Meta quase sempre "Não classificado" | Pedir `format: json` e normalizar rótulos (problema #4) |
| D4 | `types` mistura strings com arrays (`$collaboratorsTypes->all()` retorna `[[item]]`) e não é sanitizado | `FindREA::findInApi` | "Tipo preferido" só bate via colaboradores do mesmo tema+perfil | Achatar com `pluck('item')`, sanitizar, deduplicar e **guardar a origem** de cada tipo |
| D5 | MEC RED: todo item vira `both`; nível/tipo vêm do filtro da URL, não do item | `ProcessMecRed.php:96` | "Bateu com seu perfil" seria meia-verdade | Explicar como "filtrado pelo repositório (etapa X, tipos Y)" — fonte `filtro_api` |
| D6 | Colunas de interatividade/estilo/estratégia do MEC RED são derivadas da **meta do usuário**, e do Eduplay são fixas | `ProcessMecRed.php:73-83`, `ProcessEduplay.php:76` | A tabela apresenta como atributo do REA algo que não é | Marcar a fonte (`dtype`, `meta do usuário`, `padrão do repositório`) |
| D7 | Eduplay: rótulo não depende do item (só da meta) | `ProcessEduplay.php:86` | Explicação seria genérica | Explicar exatamente isso: "vídeo; vídeos são indicados para metas ma/mpa" |
| D8 | Typo `obkect_type` (mpe) | `helpers.php` | Filtro mpe incompleto, explicação citaria tipo não filtrado | Corrigir (problema #5) |

## 4. Modelo de dados da explicação

Cada job passa a gravar, ao lado de `recommended`, um campo `explicacao` no JSON de `Data.data`
(sem migration: é um campo dentro do JSON já existente).

```json
"explicacao": {
  "criterios": {
    "tema":  { "status": "ok", "valor": "matemática", "fonte": "busca", "evidencia": "termo 'Matemática' enviado à API do Aquarela" },
    "nivel": { "status": "ok", "valor": "ensino fundamental", "esperado": "ensino fundamental", "fonte": "regex", "evidencia": "6º ano" },
    "tipo":  { "status": "falhou", "valor": "jogo", "esperado": ["video", "e-book"], "fonte": "colaboradores" },
    "meta":  { "status": "nao_avaliado", "valor": null, "esperado": "ma", "fonte": "llm", "evidencia": "classificação indisponível" }
  },
  "faixa": "profile",
  "versao_regras": 1
}
```

- `status ∈ {ok, falhou, nao_avaliado, filtro_api}`.
- `fonte ∈ {busca, regex, dtype, filtro_api, colaboradores, llm, padrao_repositorio, meta_usuario}`.
- `evidencia` = o trecho/valor concreto (termo casado pelo regex, `dtype`, rótulo do LLM).
- `versao_regras` permite comparar explicações antigas e novas na avaliação.

Para isso:
- `identificarNivelEducacional` passa a retornar `[nivel, trecho_casado]` (usar `preg_match` com `$m`).
- `definirRecomendacao` passa a retornar `[rotulo, criterios]`, montados no mesmo ponto em que a
  decisão é tomada — explicação e decisão nunca divergem.
- Extrair a regra para um classe única `App\Recommendation\RuleClassifier` usada pelos três jobs
  (hoje cada job tem sua regra). Ela devolve rótulo + critérios; os jobs só adaptam os campos da API.

## 5. Textos (template-based)

`App\Recommendation\ExplanationRenderer` transforma `criterios` em frases, em português. Exemplos:

| Critério / status | Texto |
|---|---|
| nível ok (regex) | ✓ Nível **ensino fundamental**, como o seu perfil — identificado pelo trecho “6º ano” na descrição |
| nível falhou (regex) | ✗ Nível estimado **ensino médio** (trecho “médio”); você buscou **ensino fundamental** |
| nível regex sem casamento | ? Não encontramos o nível no texto; o sistema assume **ensino superior** |
| nível filtro_api | ⧉ O MEC RED filtrou por **ensino fundamental** (etapas 2 e 3) |
| tipo ok | ✓ Tipo **vídeo**, cadastrado por colaboradores para Matemática / fundamental |
| tipo falhou | ✗ Tipo **jogo** não está entre os tipos preferidos (vídeo, e-book) |
| meta ok (llm) | ✓ Classificado como **Aprendizagem** por IA, compatível com sua meta |
| meta não avaliado | ? Não foi possível classificar a meta deste recurso |
| tema | ✓ Resultado da busca por **matemática** no Aquarela |

O resumo de uma linha vem da faixa: `both` → "Combina com seu nível e tipo preferido";
`profile` → "Combina com seu nível"; `interest` → "Combina apenas com o tema"; `meta_*` → mesmas
frases + "e com sua meta".

Nota honesta obrigatória quando a fonte é heurística (regex, LLM): "estimativa automática, pode
errar". Isso também prepara a escrutabilidade ("está errado? corrija").

## 6. Interface (`find-r-e-a.blade.php`)

1. **Selo de faixa** em cada linha (substitui o destaque amarelo sem legenda): "Nível + tipo",
   "Nível", "Tema", com legenda no topo da tabela.
2. **"Por que este REA?"** — botão por linha que expande (Alpine `x-show`, sem ida ao servidor)
   a lista de critérios da seção 5.
3. **Painel "Como ordenamos"** acima da tabela: regra em uma frase + contagem por faixa, ex.:
   "12 com nível e tipo · 8 só com nível · 30 só com tema · 5 ocultos (sem compatibilidade com a meta)".
   Dados vêm do próprio `paginate()`.
4. **Painel "O que usamos sobre você"**: perfil, termo pesquisado → termo usado na API
   (`findAdequateTerm`), tipos preferidos com origem, meta EMAPRE com as três médias
   (`questionnaires.ma/mpa/mpe`) — numérico, como pede o MSL para futura escrutabilidade.
5. Colunas interatividade/estilo/estratégia ganham tooltip com a fonte (D6).
6. Coluna "Meta" deixa de mostrar texto só para `meta` e passa a mostrar o status do critério meta.

## 7. Registro para a avaliação (Etapa 3)

- Nova tabela `explanation_events` (`searched_at`, `user_id`, `rea_key`, `acao` =
  `abriu_explicacao`/`abriu_painel`, `created_at`) via `wire:click` leve ou `$wire.call` no
  toggle. Mede se as explicações são usadas.
- Acrescentar ao feedback existente (`data_reasons`) motivos ligados à explicação: "A explicação
  me ajudou a decidir", "A explicação estava errada".
- Instrumento sugerido pelo MSL: questionário de clareza percebida da explicação (modelo do
  *Personalized AI based Learning Path Generator*), com itens de transparência e confiança de
  Tintarev & Masthoff.

## 8. Testes

- Unit `RuleClassifier`: tabela de casos (nível ok/falhou/não casado × tipo ok/falhou × meta
  ok/falhou/não avaliado) → rótulo **e** critérios esperados. Garante que texto e decisão coincidem.
- Unit `ExplanationRenderer`: cada status gera a frase certa; nunca gera ✓ para `nao_avaliado`.
- Unit `paginate()`: ordem das faixas com e sem meta (cobre D1/D2).
- Feature: jobs com `Http::fake()` produzem `explicacao` em todos os itens dos três repositórios.

## 9. Sequência de entrega

| Passo | Conteúdo | Depende de |
|---|---|---|
| 1 | Correções D1, D3, D4, D8 + testes de `paginate` | — |
| 2 | `RuleClassifier` com critérios; jobs gravam `explicacao` (incl. D5–D7) | 1 |
| 3 | `ExplanationRenderer` + "Por que este REA?" + selos/legenda | 2 |
| 4 | Painéis "Como ordenamos" e "O que usamos sobre você" | 2 |
| 5 | `explanation_events` + motivos de feedback | 3 |
| 6 | Atualizar `docs/recomendacao.md` e `docs/problemas-conhecidos.md` | 1–4 |

Decisão pendente: **D2** (mostrar itens `profile` sem meta) muda o que o usuário vê; confirmar
com o orientador antes do passo 1.

## 10. Ponte para a parte 2 (diferenciais)

- **Contrafactual**: com `criterios` salvos, "o que faltou" é derivado direto dos `falhou`
  ("se fosse vídeo, subiria para Nível + tipo").
- **Escrutabilidade**: cada critério de fonte heurística (regex, LLM) ganha "corrigir"; o painel
  "O que usamos sobre você" ganha edição de tipos preferidos e da meta. As correções reentram no
  `RuleClassifier` como sobrescritas e ficam registradas.
