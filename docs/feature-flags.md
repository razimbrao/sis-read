# Feature flags de explicabilidade e experimento

> As funcionalidades de explicabilidade ([transparencia.md](transparencia.md) e
> [plano-escrutabilidade.md](plano-escrutabilidade.md)) ficam atrás de flags, para comparar a
> experiência **com e sem** elas num estudo com usuários. A recomendação e a ordenação são
> **as mesmas em todos os grupos**: as flags mudam só o que é mostrado e quais ações são aceitas.

## 1. Grupos

| Grupo | O que o participante vê |
|---|---|
| `controle` | A lista de REAs como antes da transparência: cartões sem explicação, sem painéis e com "Carregando..." durante a busca |
| `transparencia` | Tudo o que a transparência acrescentou (fases 1 e 1b): explicação por REA, painéis, REAs excluídos e progresso. Nada pode ser corrigido |
| `escrutabilidade` | Transparência + correções (nível, meta, tipos preferidos, desfazer) |

Os grupos estão em `config/experimento.php` (`grupos`), como listas de flags. Um grupo novo é só
uma entrada nova nessa lista e em `App\Experimento\Experimento::GRUPOS`.

## 2. Flags

O inventário veio do histórico do git: `aefbd90` (transparência), `d7ab584` (avisos, progresso e
REAs excluídos), `cbed4dc`/`8e1f1e5` (escrutabilidade na tela) e `2d77d3d`/`bf62156` (cartões e
painéis redesenhados).

| Flag | Funcionalidade | Onde está | Eventos |
|---|---|---|---|
| `explicacao-rea` | Painel azul **Por que este REA?** (selo de faixa, critérios, avisos de estimativa, legenda), legenda "Como ler os cartões", coluna **Meta** do cartão (status do critério de meta) | `find-r-e-a.blade.php`, `partials/explicacao-rea` | `abriu_explicacao` |
| `painel-ordenacao` | Painel **Como ordenamos estes resultados**: faixas, contagem de ocultos por motivo, situação dos repositórios | `partials/transparencia-paineis` (painel 1) | `abriu_ordenacao` |
| `reas-ocultos` | **Ver os REAs que não aparecem** (lista com título, motivo e resumo), dentro do painel de ordenação | `partials/transparencia-paineis` | `abriu_ocultos` |
| `painel-contexto` | Painel **O que usamos sobre você**: perfil, termo, tipos preferidos e origem, meta EMAPRE com médias | `partials/transparencia-paineis` (painel 2) | `abriu_contexto` |
| `progresso-busca` | "Consultando repositórios… N de 3 responderam" (desligada: "Carregando...") | `find-r-e-a.blade.php` | — |
| `escrutabilidade` | Corrigir nível e meta (no REA e nos ocultos), editar tipos preferidos, **Desfazer todas** | `partials/corrigir-criterio`, `partials/transparencia-paineis`, ações do `FindREA` | `abriu_correcao` |

Não são flags:

- **Motivos de feedback sobre explicações** ("As explicações… estavam erradas ou confusas", "…não
  ajudaram a entender a ordem"): aparecem quando alguma explicação está visível (`explicacao-rea`,
  `painel-ordenacao` ou `painel-contexto`). No `controle`, ficam escondidos e são descartados se
  enviados.
- **Aviso de interesse não reconhecido** (`search()`): é validação da busca. Sem ele a busca falharia
  calada (problema #14), o que mudaria o comportamento e não só a explicação.
- **Destaque amarelo do REA da faixa mais alta**: existia antes da transparência.
- **Gravação de `explicacao` pelos jobs, `Ranking` e `RuleClassifier`**: iguais em todos os grupos.
- **Preferência por conta**: não fica atrás de flag.

`escrutabilidade` depende de `explicacao-rea` ou dos painéis para ter onde aparecer. Ligá-la
sozinha não mostra nada.

### Bloqueio no servidor

Desligar uma flag esconde a UI **e** recusa a ação, mesmo que alguém chame o método Livewire direto:

| Ação | Sem a flag |
|---|---|
| `corrigirNivel`, `corrigirMeta`, `desfazerCorrecao`, `redefinirTipos`, `desfazerTodas` | Sem `escrutabilidade`: retornam sem alterar `data.data` nem gravar `corrections` |
| `registrarExplicacao(acao)` | Não grava se a flag da ação (`Experimento::FLAG_DA_ACAO`) estiver desligada |
| `saveSearchFeedback` | Descarta motivos que não foram exibidos ao grupo |

## 3. Como ligar e desligar

Precedência, da mais forte para a mais fraca:

1. `EXPERIMENTO_FORCAR_FLAGS`: liga ou desliga flags isoladas por cima do grupo.
2. `EXPERIMENTO_FORCAR_GRUPO`: todo mundo vê esse grupo, e nada é gravado.
3. Grupo gravado do participante (link, comando artisan ou sorteio).

```dotenv
# Tudo desligado / tudo ligado para todo mundo (demonstração, desenvolvimento):
EXPERIMENTO_FORCAR_GRUPO=controle
EXPERIMENTO_FORCAR_GRUPO=escrutabilidade

# Ablação: grupo do participante, mas sem a lista de ocultos e sem o progresso
EXPERIMENTO_FORCAR_FLAGS="reas-ocultos=0,progresso-busca=0"

# Quem chega sem link: sorteio uniforme (padrão) ou um grupo fixo
EXPERIMENTO_SEM_LINK=sorteio
```

Depois de mudar o `.env`, rode `php artisan config:clear` se a configuração estiver em cache.

### Na view

```blade
@explicabilidade('explicacao-rea')
    ...
@else
    ...
@endexplicabilidade
```

Em PHP: `App\Experimento\Experimento::ativa('flag')` ou `app(Experimento::class)->ativo('flag')`.
Os condicionais ficam colados nos `@include` e blocos existentes, para que o redesenho das views
possa movê-los junto.

## 4. Atribuição do grupo

O pesquisador define quem participa de cada grupo distribuindo **um link por grupo**:

```
https://<servidor>/?grupo=controle
https://<servidor>/?grupo=transparencia
https://<servidor>/?grupo=escrutabilidade
```

Para o participante não ver o nome do grupo, troque os códigos no `.env` e use os links com eles:

```dotenv
EXPERIMENTO_CODIGO_CONTROLE=k7q2
EXPERIMENTO_CODIGO_TRANSPARENCIA=m3x9
EXPERIMENTO_CODIGO_ESCRUTABILIDADE=p8w4
```

Com códigos definidos, o nome do grupo deixa de valer no link. Código desconhecido é ignorado.

Como o grupo é guardado:

- O grupo é uma feature rica do [Laravel Pennant](https://laravel.com/docs/11.x/pennant)
  (`App\Features\GrupoExperimento`, nome `grupo-experimento`), gravada na tabela `features` por
  escopo. Escopo do usuário logado: `App\Models\User|<id>`. Escopo do visitante:
  `visitante:<uuid>`, um token guardado na sessão (o id da sessão muda no login, o token não).
- O link (middleware `App\Http\Middleware\GrupoPorLink`) grava o grupo do escopo atual. Um link novo
  troca o grupo, mesmo que já houvesse outro.
- Quem chega sem link e não tem grupo recebe o de `EXPERIMENTO_SEM_LINK`. Com `sorteio`, a escolha é
  uniforme entre os três grupos e fica gravada, ou seja, é estável.
- Visitante que faz login: se a conta ainda não tem grupo, herda o do visitante. Se já tem, vale o da conta.
- O visitante perde o grupo quando a sessão expira (`SESSION_LIFETIME`). Para estudos com mais de uma
  sessão, peça login ou mande o link de novo.

### Comando para o pesquisador

```bash
php artisan experimento:grupo                              # distribuição: usuários e visitantes por grupo
php artisan experimento:grupo p@ufjf.br                    # grupo de um usuário (id ou e-mail)
php artisan experimento:grupo p@ufjf.br transparencia      # fixa o grupo
php artisan experimento:grupo p@ufjf.br --limpar           # apaga (link ou sorteio no próximo acesso)
```

## 5. Desenho do experimento

- **Entre sujeitos**, três condições: `controle` × `transparencia` × `escrutabilidade`. A diferença
  entre `controle` e `transparencia` mede o efeito das explicações. A diferença entre `transparencia`
  e `escrutabilidade` mede o efeito de poder corrigir.
- **Mantido igual**: repositórios consultados, regras, rótulos, ordenação, itens ocultos, texto da
  busca e avaliação por estrelas. O teste `FeatureFlagsTest::test_ordenacao_e_igual_em_todos_os_grupos`
  garante que a ordem dos REAs não depende do grupo. Correções mudam a ordem, mas só existem no
  grupo `escrutabilidade`, e isso faz parte do tratamento.
- **Atribuição**: links distribuídos pelo pesquisador (balanceamento manual) ou sorteio para quem chega
  sem link. Registre fora do sistema quem recebeu qual link.
- **Ameaças à validade**: o participante pode editar o link e trocar de grupo (use códigos opacos);
  a resposta das APIs externas e do Ollama varia entre buscas (compare as métricas de falha por
  grupo); o mesmo participante pode fazer várias buscas (agregue por `participante`, e não só por busca).

## 6. Métricas

O grupo é gravado no momento do registro:

| Tabela | Colunas novas | Observação |
|---|---|---|
| `data` (uma por busca) | `grupo`, `flags` (JSON com as flags ligadas), `participante` (`u:<id>` ou `v:<token>`) | `stars` (avaliação) fica nesta linha |
| `explanation_events` | `grupo` | Só existem eventos de funcionalidades ligadas |
| `corrections` | `grupo` | Só no grupo `escrutabilidade` |
| `data_reasons` | `grupo` | Motivos da avaliação |
| `feedbacks` | `grupo`, `participante` | Feedback livre |
| `search_metrics` | — | Gravada pelos jobs. O grupo vem de `data` pela chave `searched_at` |

Registros anteriores ao experimento e os da simulação (`simulation:run-all`) ficam com `grupo` nulo
e não entram na exportação.

### Exportar (CSV)

```bash
php artisan experimento:exportar --saida=storage/app/buscas.csv              # uma linha por busca
php artisan experimento:exportar --por-grupo --saida=storage/app/grupos.csv  # uma linha por grupo
php artisan experimento:exportar --por-grupo --desde=2026-10-10 --separador=";"
```

Colunas por busca: `busca_id, searched_at, grupo, flags, participante, estrelas, motivos` (ids
separados por `|`), `motivos_explicacao, comentario, reas, tempo_s, repositorios_com_falha`,
`eventos_<acao>` (uma por ação de `ExplanationEvent::ACOES`), `correcoes, correcoes_desfeitas`.

Colunas por grupo: `buscas, participantes, buscas_avaliadas, media_estrelas, buscas_com_motivo,
buscas_com_motivo_explicacao, media_reas, media_tempo_s`, `taxa_buscas_<acao>` (fração das buscas
em que a ação ocorreu ao menos uma vez), `correcoes, taxa_buscas_com_correcao, feedbacks_livres`.

### Consulta SQL equivalente (SQLite)

```sql
SELECT d.grupo,
       COUNT(*)                                    AS buscas,
       COUNT(DISTINCT d.participante)              AS participantes,
       AVG(NULLIF(d.stars, 0))                     AS media_estrelas,
       SUM(EXISTS (SELECT 1 FROM explanation_events e
                   WHERE e.searched_at = d.searched_at AND e.acao = 'abriu_explicacao')) AS buscas_abriu_explicacao,
       (SELECT COUNT(*) FROM corrections c WHERE c.grupo = d.grupo AND c.acao = 'corrigir') AS correcoes,
       SUM((SELECT COUNT(*) FROM search_metrics m
             WHERE m.searched_at = d.searched_at AND m.timeouts_errors > 0)) AS falhas_repositorio
FROM data d
WHERE d.grupo IS NOT NULL
GROUP BY d.grupo;
```

## 7. Código

| Arquivo | Papel |
|---|---|
| `config/experimento.php` | Grupos, overrides, parâmetro e códigos do link |
| `app/Experimento/Experimento.php` | Grupo do participante, flags ativas, link, sorteio, participante |
| `app/Features/GrupoExperimento.php` | Feature do Pennant (valor gravado por escopo) |
| `app/Http/Middleware/GrupoPorLink.php` | Lê `?grupo=` em qualquer página web |
| `app/Console/Commands/ExperimentoGrupo.php` | `experimento:grupo` |
| `app/Console/Commands/ExperimentoExportar.php` | `experimento:exportar` |
| `app/Providers/AppServiceProvider.php` | Diretiva Blade `@explicabilidade` |
| `tests/Feature/FeatureFlagsTest.php` | UI e ações em cada grupo, atribuição estável, link, comandos, exportação |

Nos testes, `phpunit.xml` fixa `EXPERIMENTO_SEM_LINK=escrutabilidade` (tudo ligado), para os testes
de transparência e escrutabilidade não dependerem do sorteio.
