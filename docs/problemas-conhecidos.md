# Problemas conhecidos (observados no mapeamento, não corrigidos)

1. ~~**Itens `profile` somem quando o usuário não tem meta**: `paginate()` junta só `both` + `interest`.
   Além disso, a checagem de `meta` usa um `if` separado em vez de `elseif`, então itens
   `meta_both`/`meta_one` também caem no `else` e entram como `interest`.~~ **Corrigido** (2026-09-18): a ordenação usa
   `App\Recommendation\Ranking` (`both → profile → interest`). Ver [transparencia.md](transparencia.md).
2. **`finished` prematuro**: o primeiro job que termina já marca a busca como concluída.
3. **Condição de corrida no merge do JSON**: os jobs leem e escrevem `Data.data` sem lock. Com mais de
   um worker, resultados podem ser sobrescritos.
   Observado em 2026-10-03 com dois workers: os 10 itens do MEC RED sumiram de `data.data`, embora
   `search_metrics` registrasse 10 itens retornados. Por isso as correções do usuário (escrutabilidade)
   só são liberadas depois que os três repositórios responderam (`FindREA::podeCorrigir`).
4. ~~**Prompt do Ollama incompatível com o parser**: o prompt pede texto puro, mas o código lê
   `result['meta']` de um JSON. E `analisarMeta` procura `performance_aproximacao` com underscore.
   Na prática, a classificação quase sempre dá "Não classificado".~~ **Corrigido** (2026-09-18): o prompt pede
   `{"meta": ...}` e `RuleClassifier::casaMeta` normaliza acento, espaço e hífen.
5. ~~**Typo `obkect_type`** em `getMecRedURL` (meta mpe).~~ **Corrigido** (2026-09-18): IDs em `mecRedTiposObjeto()`.
6. **`searched_at` usado como chave**: duas buscas no mesmo segundo colidem.
7. ~~`ProcessEduplay` interpola o termo de busca na URL sem encoding.~~ **Corrigido** (2026-10-07): os
   parâmetros vão em array para o `Http::get`, que os codifica.
8. ~~O `database.sqlite` versionado contém dados de usuários (e-mails e hashes de senha).~~
   **Parcialmente corrigido** (2026-09-30): o arquivo saiu do versionamento (`.gitignore` + `git rm --cached`)
   e o `setup.ps1` passa a criá-lo com migrations e seeders. **O histórico antigo ainda contém o banco com
   os dados**: limpar de vez exige reescrever o histórico (`git filter-repo`) e combinar com quem tiver clone.
9. O `down` da migration do questionário faz `dropIfExists('questionnaire')`, mas a tabela se chama
   `questionnaires`.
10. O Horizon exige `ext-pcntl`/`ext-posix`, que não existem no Windows.
11. ~~**Tipos preferidos de todos os colaboradores nunca casavam**: entravam como arrays (`[[item]]`) e sem
    normalização.~~ **Corrigido** (2026-09-18) com `RuleClassifier::normalizarTipos`. Efeito colateral: agora
    qualquer tipo cadastrado por **qualquer** colaborador conta como preferido, o que aumenta os itens `both`.
    O painel "O que usamos sobre você" separa as duas origens. **Mitigado** (2026-10-06): o usuário logado pode
    salvar os seus tipos preferidos na conta, e eles substituem os dos colaboradores
    ([plano-escrutabilidade.md §15](plano-escrutabilidade.md#15-tipos-preferidos-salvos-na-conta)). Visitantes
    continuam com o comportamento acima.
12. Tipos cadastrados por colaboradores incluem valores ruidosos (ex.: `item`, `programacao`) que viram
    "tipos preferidos". Vale revisar a tabela `collaborators`.
13. A linha "Eficiência" da tela de resultados divide por `Data.time`. Se `time` for `null` (nenhum job
    terminou), a view lança divisão por zero.
14. ~~Se o interesse digitado não bate com nenhuma opção conhecida, `findAdequateTerm` não define
    `interestApiSearch`: os jobs buscam termo vazio e a tabela não aparece.~~ **Corrigido** (2026-09-30):
    `search()` valida o termo antes de disparar os jobs e mostra a mensagem com os interesses disponíveis.
15. **O MEC RED ignora os filtros enviados**: em 2026-09-18, `educational_stages` e `object_type` na URL
    devolveram os mesmos 10 itens de uma busca sem filtros (ex.: "Anos Iniciais do Ensino Fundamental" para
    perfil *Ensino médio*). ~~Mesmo assim, a política colocava todo item do MEC RED na faixa mais alta
    (`both`/`meta_both`), e a ordenação favorecia esses itens.~~ **Corrigida a ordenação** (2026-10-06): não
    há mais prioridade fixa por repositório. A lista é ordenada pelo grau de recomendação, calculado só dos
    critérios conferidos (meta 4, nível 2, tipo 1; o que não foi conferido vale 0), com desempate pela posição
    no repositório (`docs/recomendacao.md`). O nível do MEC RED passou a vir do regex sobre título e descrição,
    e a meta, da IA pelo título.
    **Continua aberto**: a API ignora os filtros e não informa etapa nem tipo, então os itens do MEC RED
    raramente pontuam em nível e tipo. Caminhos para dar dados ao grau: usar o parâmetro `filters` (JSON com
    `nivel`) ou buscar a etapa de cada item em `/public/resource/{id}`.
16. ~~Busca com meta e Ollama fora do ar: todos os REAs do Aquarela e do Eduplay ficam ocultos (meta não
    avaliada).~~ **Corrigido** (2026-10-06): a meta não esconde mais nenhum REA (ver #18). Com a meta não
    avaliada, o REA aparece com 0 ponto de meta, abaixo dos compatíveis, e o painel "Como ordenamos" diz
    quantos estão nessa situação; cada critério explica por quê (IA indisponível, tempo esgotado, resposta
    inválida). Também foi **mitigada** a falha em si: o modelo é aquecido antes da primeira chamada (a frio, o
    `gemma3:4b` levava ~30s e todas as primeiras chamadas estouravam o timeout), o job desiste após 3 falhas
    seguidas em vez de esperar o timeout de cada REA, e classificações anteriores vêm do cache. Ver
    [integracoes.md](integracoes.md#llm-classificação-de-meta).
17. ~~**Interesses cadastrados por colaboradores nunca funcionavam na busca**: `interestOptions` é uma
    propriedade privada, que o Livewire não persiste entre requisições. O `mount()` carregava a lista, mas
    no request do `search()` ela já tinha voltado só aos 4 termos fixos.~~ **Corrigido** (2026-09-30):
    `opcoesInteresse()` consulta os colaboradores a cada requisição e remove duplicatas por forma normalizada.
18. A classificação de meta por IA (`gemma3:4b`) tem viés: em teste manual, reconheceu
    "Performance Aproximação" mas classificou como "Aprendizagem" um recurso claramente de
    "Performance Evitação". Numa busca real com meta `ma`, **nenhum** dos 157 REAs foi excluído, ou seja, o
    critério quase não filtra. A explicação declara o modelo usado, mas vale testar prompt ou modelo maior.
    Com a LLM nos três repositórios (2026-10-06), o viés aparece em todos: na busca "algoritmos" com meta
    `ma`, os 157 REAs (Aquarela, MEC RED e Eduplay) saíram "Aprendizagem". O cache guarda a classe por REA:
    ao trocar o prompt, incremente `MetaClassifier::VERSAO_PROMPT`; ao trocar o modelo, o cache já separa
    por `OLLAMA_MODELO`. Efeito na lista: numa busca real com meta `mpa`, os 39 REAs saíram com meta
    `falhou`, e como a meta incompatível ocultava o REA, a lista ficava **vazia**. **Mitigado** (2026-10-06):
    a meta diferente não esconde mais o REA; ele vai para o fim da lista, marcado "meta diferente da sua",
    abaixo dos de meta não conferida (`docs/recomendacao.md`). O viés em si continua aberto.
19. Empate no EMAPRE é resolvido em silêncio: `array_keys($resultados, max(...))[0]` pega o primeiro fator.
    Existe ao menos um caso real no banco (usuário 3, ma=2 e mpa=2).
20. Os 24 `collaborators` vieram de uma planilha de artigos (o "nome" é uma referência bibliográfica), e a
    primeira linha é o **cabeçalho da planilha**, o que criou o interesse "Interesse" e o tipo "Item".
21. 61 `failed_jobs` acumulados, nunca revisados.
22. Os REAs do MEC RED não têm link: `ProcessMecRed` grava `'link' => ''` e não grava o `id`, que a tela
    usava para montar `plataformaintegrada.mec.gov.br/recurso/{id}`. Desde 2026-10-07 o cartão mostra o botão
    "Abrir recurso" desabilitado, com o tooltip "O repositório não informou o link deste recurso." (antes o
    botão sumia). A causa, no job, continua aberta.
