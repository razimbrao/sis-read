# Setup local (Windows, sem Docker)

Docker não estava instalado nesta máquina. Como o app usa SQLite e fila em banco, rodar PHP nativo é
o caminho mais simples (não precisa de MySQL nem Redis).

## Requisitos
- PHP 8.3 (instalado via `winget install PHP.PHP.8.3`) com as extensões curl, fileinfo, mbstring,
  openssl, pdo_sqlite, sqlite3, zip, intl e sodium
- Composer (o `composer.phar` fica na pasta do PHP, junto com um `composer.bat`)
- Node 20+ / npm (só para `vite build`)
- Opcional: [Ollama](https://ollama.com) com o modelo `gemma3:4b` em `127.0.0.1:11434` (configurável em
  `.env`: `OLLAMA_URL`, `OLLAMA_MODELO`). Ele é usado apenas para usuários que responderam ao
  questionário (classificação de meta nos três repositórios). Sem ele, a meta de cada REA fica "não
  avaliada", com o motivo na explicação. Ver [integracoes.md](integracoes.md#llm-classificação-de-meta).

## Instalação automática
```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
```
O script instala o PHP (se faltar), configura o `php.ini`, baixa o Composer e roda `composer install`.
Depois cria o `.env`, roda `key:generate` e `migrate`, e por fim `npm install` e `npm run build`.

> O `composer install` usa `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix`. Essas
> extensões não existem no Windows e só o Laravel Horizon as exige. O Horizon não é usado localmente
> (a fila é `database`).

## Rodar
```powershell
powershell -ExecutionPolicy Bypass -File scripts\start.ps1
```
Ou manualmente, em dois terminais:
```
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work --timeout=600 --tries=3
```
Acesse http://127.0.0.1:8000. **O worker de fila é obrigatório.** Sem ele as buscas ficam carregando
para sempre, porque os resultados são produzidos por jobs.

## Banco
O `database/database.sqlite` **não** é versionado: ele contém dados reais de usuários (e-mails e
hashes de senha). O `scripts/setup.ps1` cria o arquivo, aplica as migrações e roda os seeders
(motivos de feedback), deixando um banco vazio e utilizável.

Quem já tem o banco com os dados históricos (~50 MB, ~1000 buscas) deve guardá-lo fora do git e
copiá-lo para `database/database.sqlite`. Para zerar, `php artisan migrate:fresh --seed` (apaga tudo).

## Simulação em massa
`php artisan simulation:run-all` enfileira 3 jobs para cada combinação perfil × interesse × meta
(26 × 4 = 104 combinações, ou seja, 312 jobs). As métricas vão para `search_metrics`.
