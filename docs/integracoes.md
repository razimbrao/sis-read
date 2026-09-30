# Integrações externas

| Serviço | Endpoint | Onde |
|---|---|---|
| Aquarela (repositório de REA para a educação básica) | `AQUARELA_API_URL` (padrão `https://aquarelaapi.dev.br/api/reas`, params `string`, `page`) | `ProcessAquarela` |
| MEC RED (Plataforma MEC de Recursos Educacionais Digitais) | `https://api.mecred.c3sl.ufpr.br/public/elastic/search?...` | `helpers.php`, `ProcessMecRed` |
| Eduplay (vídeos educacionais da RNP) | `https://eduplay.rnp.br/api/v1/search?term=&page=&quantity=10` | `ProcessEduplay` |
| Ollama | `http://127.0.0.1:11434/api/generate`, modelo `gemma3:4b` (fixo no código), `format: json` | `ProcessAquarela::classificarMetaComLLM` |
| Google Sheets | `config/google.php` + `storage/credentials.json` | código comentado em `FindREA::insert` e `search` |

Todas as chamadas HTTP aos repositórios usam `verify => false` (TLS não verificado). O timeout é de
15 s (10 s no Ollama). As falhas são contadas em `search_metrics.timeouts_errors` / `ollama_errors` e
não quebram o job.

## Ollama e o modelo de linguagem

- A inferência roda **localmente**, sem API comercial. É o que a monografia chama de alternativa de
  baixo custo.
- A monografia avaliou o **Llama 3.1**. O código usa `gemma3:4b` (antes, `llama3.2`). Para
  reproduzir a avaliação, troque o `model` em `ProcessAquarela` e rode `ollama pull llama3.1`.
  Qualquer troca de modelo invalida os números de acurácia até uma nova avaliação.
- Só o Aquarela passa pelo LLM, e só quando o usuário tem meta. MEC RED e Eduplay usam regras fixas.
- O LLM é o gargalo de latência: há uma chamada por REA, e o Aquarela devolve até 3 páginas.

## Observações por repositório

- **Aquarela**: devolve o maior volume bruto e os metadados mais ricos (`titulo`, `descricao`,
  `tipoConteudo`, `dtype`). É o único com classificação completa por critério.
- **MEC RED**: aceita `educational_stages` e `object_type` na URL, mas em 2026-09-18 devolveu os
  mesmos itens com e sem filtros (problema conhecido #15). Não informa etapa nem tipo por item.
- **Eduplay**: só vídeos, sem etapa. O termo vai na URL sem encoding (problema conhecido #7).
