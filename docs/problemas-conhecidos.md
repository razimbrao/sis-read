# Problemas conhecidos (observados no mapeamento, não corrigidos)

1. ~~**Itens `profile` somem quando o usuário não tem meta**: `paginate()` junta só `both` + `interest`.
   Além disso, a checagem de `meta` usa um `if` separado em vez de `elseif`, então itens
   `meta_both`/`meta_one` também caem no `else` e entram como `interest`.~~ **Corrigido** (2026-09-18): a ordenação usa
   `App\Recommendation\Ranking` (`both → profile → interest`). Ver [transparencia.md](transparencia.md).
2. **`finished` prematuro**: o primeiro job que termina já marca a busca como concluída.
3. **Condição de corrida no merge do JSON**: os jobs leem e escrevem `Data.data` sem lock. Com mais de
   um worker, resultados podem ser sobrescritos.
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
14. Se o interesse digitado não bate com nenhuma opção conhecida, `findAdequateTerm` não define
    `interestApiSearch`: os jobs buscam termo vazio e a tabela não aparece.
15. **O MEC RED ignora os filtros enviados**: em 2026-09-18, `educational_stages` e `object_type` na URL
    devolveram os mesmos 10 itens de uma busca sem filtros (ex.: "Anos Iniciais do Ensino Fundamental" para
    perfil *Ensino médio*). Mesmo assim, a política coloca todo item do MEC RED na faixa mais alta
    (`both`/`meta_both`). A explicação já declara que nível, tipo e meta não foram conferidos, mas a
    **ordenação** continua favorecendo esses itens. Caminhos possíveis: usar o parâmetro `filters` (JSON com
    `nivel`), buscar a etapa de cada item em `/public/resource/{id}`, ou tirar o MEC RED da faixa mais alta.
16. Busca com meta e Ollama fora do ar: todos os REAs do Aquarela ficam ocultos (meta não avaliada). O painel
    "Como ordenamos" mostra esse motivo separadamente.
