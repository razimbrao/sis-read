# Continuidade: transparência (para retomar em outra máquina)

> Estado em 2026-10-02. Resume o que está pronto, o que falta e o que precisa ser decidido.
> Especificação técnica: [transparencia.md](transparencia.md). Plano original e base no MSL:
> [plano-transparencia.md](plano-transparencia.md). Bugs: [problemas-conhecidos.md](problemas-conhecidos.md).

## 1. Onde o código está

| Branch | Commit | Situação |
|---|---|---|
| `main` | `81d159e` | fase 1 da transparência, já mergeada (PR #2) |
| `feat/transparencia-avisos` | `2a43741` | avisos, progresso e REAs excluídos — **PR #3 aberto** |

Tudo está no remoto (`github.com/razimbrao/sis-read`). Nada ficou só local.

## 2. Subir o projeto na outra máquina

```bash
git clone https://github.com/razimbrao/sis-read.git
cd sis-read
git checkout feat/transparencia-avisos
powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
```

Depois, em dois terminais:

```bash
php artisan serve
php artisan queue:work --timeout=600
```

**Sem o worker as buscas nunca terminam.** Abra http://127.0.0.1:8000.

### O banco não vem do git

`database/database.sqlite` saiu do versionamento (contém e-mails e hashes reais). O `setup.ps1`
cria um banco **vazio**, com migrations e seeders. Consequências num banco novo:

- A tabela `collaborators` fica vazia, então **não há "tipos preferidos"**: nenhum REA cai na faixa
  "Nível e tipo", e todos ficam em "Nível" ou "Só tema". A explicação diz isso corretamente
  ("nenhum tipo preferido foi encontrado para esta busca"), mas é bom saber antes de estranhar.
- Os interesses buscáveis são só os 4 fixos: `algoritmos`, `decomposição`, `reconhecimento de
  padrões`, `abstração`.
- Não há usuários, então não dá para testar meta sem criar conta e responder `/emapre`.

Para reproduzir o comportamento real, copie o `database.sqlite` da máquina antiga (≈50 MB) por
fora do git e rode `php artisan migrate` depois.

### Meta de aprendizagem (opcional)

Só funciona com o Ollama rodando:

```bash
winget install --id Ollama.Ollama -e
ollama pull gemma3:4b
```

No Windows ele sobe como serviço na porta 11434. Sem ele, o critério de meta fica "não avaliado" e
os REAs do Aquarela são ocultados — o painel explica o motivo.

## 3. O que já está pronto

**Fase 1 (na `main`)** — três níveis de explicação:
- por item: selo de faixa e "Por que este REA?", com critério, status, fonte e evidência;
- pela lista: "Como ordenamos", com faixas, ocultos por motivo e situação de cada repositório;
- sobre o usuário: "O que usamos sobre você", com perfil, termo, tipos preferidos por origem e
  médias do EMAPRE;
- registro de uso em `explanation_events` e motivos de feedback sobre explicações.

**Fase 1b (PR #3)** — quatro falhas silenciosas:
- interesse não reconhecido passa a avisar, com a lista de interesses válidos;
- "Consultando repositórios… N de 3 responderam" durante a busca;
- a classificação por IA declara modelo e tempo;
- "Ver os REAs que não aparecem", com motivo e resumo por item.

67 testes passando (`php artisan test`), em SQLite em memória.

## 4. Próximos passos sugeridos

1. **Mergear o PR #3.**
2. **Decidir o problema #15 (MEC RED).** É o único ponto em que a ordenação ainda contradiz a
   explicação: a API ignora os filtros enviados, mas a política mantém esses itens na faixa mais
   alta. Opções: usar o parâmetro `filters`, buscar a etapa item a item em `/public/resource/{id}`,
   ou tirar o MEC RED do topo. **Decisão de produto, não técnica.**
3. **Confirmar a decisão D2** com o orientador: itens `profile` passaram a aparecer para quem não
   tem meta (antes eram descartados).
4. **Fase 2 — contrafactual.** Os critérios que falharam já estão salvos, então é derivação direta:
   "se o tipo fosse vídeo, este REA subiria para a faixa Nível e tipo". Mexe em
   `ExplanationRenderer` e na partial `explicacao-rea.blade.php`.
5. ~~**Fase 2 — escrutabilidade.**~~ **Feita** (2026-10-03, PR #4): o usuário corrige o nível e a
   meta estimados e os tipos preferidos. Ver [plano-escrutabilidade.md](plano-escrutabilidade.md)
   e [transparencia.md §13](transparencia.md#13-escrutabilidade). Os registros de `corrections`
   também viram dado para a Etapa 3.
6. **Etapa 3 — avaliação com usuários.** Questionário de clareza percebida da explicação, nos
   moldes do artigo *Personalized AI based Learning Path Generator* (o único do corpus que mede a
   percepção da explicação em si). Os dados de `explanation_events` já começam a ser coletados.

## 5. Decisões em aberto

| # | Assunto | Quem decide |
|---|---|---|
| D2 | itens `profile` aparecendo sem meta | orientador |
| #15 | ordenação do MEC RED sem base real | produto/orientador |
| #11 | qualquer tipo de qualquer colaborador conta como preferido | produto |
| #18 | viés da IA: testar prompt melhor ou modelo maior | técnica |

## 6. Armadilhas que já me pegaram

- **`php artisan queue:restart` depois de mexer nos jobs.** O worker guarda o código antigo em
  memória e continua gravando explicações desatualizadas.
- **O banco trava o git.** Com o servidor ou o worker rodando, operações como `git checkout` falham
  com "unable to unlink old 'database/database.sqlite'". Pare os processos antes.
- **`vendor/bin/pint` sem argumento reformata o projeto inteiro.** Rode só nos arquivos alterados.
- **APIs externas caem.** O Aquarela ficou fora do ar durante um teste; o painel "Repositórios
  consultados" mostra isso.
- **Buscar com meta é lento**, porque cada REA do Aquarela vira uma chamada ao LLM.
