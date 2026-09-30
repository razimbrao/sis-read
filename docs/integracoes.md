# Integrações externas

| Serviço | Endpoint | Onde |
|---|---|---|
| Aquarela | `AQUARELA_API_URL` (padrão `https://aquarelaapi.dev.br/api/reas`, params `string`, `page`) | `ProcessAquarela` |
| MEC RED | `https://api.mecred.c3sl.ufpr.br/public/elastic/search?...` | `helpers.php`, `ProcessMecRed` |
| Eduplay (RNP) | `https://eduplay.rnp.br/api/v1/search?term=&page=&quantity=10` | `ProcessEduplay` |
| Ollama | `http://127.0.0.1:11434/api/generate`, modelo `gemma3:4b` (fixo no código) | `ProcessAquarela::classificarMetaComLLM` |
| Google Sheets | `config/google.php` + `storage/credentials.json` | código comentado em `FindREA::insert` |

Todas as chamadas HTTP usam `verify => false` (TLS não verificado). O timeout é de 15s (10s no
Ollama). As falhas são contadas em `search_metrics.timeouts_errors` / `ollama_errors` e não quebram
o job.
