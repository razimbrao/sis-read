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
7. `ProcessEduplay` interpola o termo de busca na URL sem encoding.
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
    O painel "O que usamos sobre você" separa as duas origens.
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
    no repositório (`docs/recomendacao.md`). O nível do MEC RED passou a vir do regex sobre título e descrição.
    **Continua aberto**: a API ignora os filtros e não informa etapa nem tipo, então os itens do MEC RED
    costumam ficar com grau 0. Caminhos para dar dados ao grau: usar o parâmetro `filters` (JSON com `nivel`)
    ou buscar a etapa de cada item em `/public/resource/{id}`.
16. ~~Busca com meta e Ollama fora do ar: todos os REAs do Aquarela ficam ocultos (meta não avaliada).~~
    **Corrigido** (2026-10-06): só fica oculto o REA com meta conferida como incompatível. Com a meta não
    avaliada, o REA aparece com 0 ponto de meta, abaixo dos compatíveis, e o painel "Como ordenamos" diz
    quantos estão nessa situação.
17. ~~**Interesses cadastrados por colaboradores nunca funcionavam na busca**: `interestOptions` é uma
    propriedade privada, que o Livewire não persiste entre requisições. O `mount()` carregava a lista, mas
    no request do `search()` ela já tinha voltado só aos 4 termos fixos.~~ **Corrigido** (2026-09-30):
    `opcoesInteresse()` consulta os colaboradores a cada requisição e remove duplicatas por forma normalizada.
18. A classificação de meta por IA (`gemma3:4b`) tem viés: em teste manual, reconheceu
    "Performance Aproximação" mas classificou como "Aprendizagem" um recurso claramente de
    "Performance Evitação". Numa busca real com meta `ma`, **nenhum** dos 157 REAs foi excluído, ou seja, o
    critério quase não filtra. A explicação declara o modelo usado, mas vale testar prompt ou modelo maior.
19. Empate no EMAPRE é resolvido em silêncio: `array_keys($resultados, max(...))[0]` pega o primeiro fator.
    Existe ao menos um caso real no banco (usuário 3, ma=2 e mpa=2).
20. Os 24 `collaborators` vieram de uma planilha de artigos (o "nome" é uma referência bibliográfica), e a
    primeira linha é o **cabeçalho da planilha**, o que criou o interesse "Interesse" e o tipo "Item".
21. 61 `failed_jobs` acumulados, nunca revisados.
