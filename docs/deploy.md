# Deploy

O SisREAd roda em produção num **único container Docker** numa VM, com o SQLite num diretório da VM.
O caminho escolhido (2026-11) é gratuito: **VM do Azure for Students** (via GitHub Student Pack), na
região North Central US, com **Groq** como LLM.

Produção: <https://sisread.northcentralus.cloudapp.azure.com>.

## Por que uma VM, e não um PaaS gratuito

O app precisa de duas coisas que os planos gratuitos de PaaS (Render, Koyeb, Fly.io, Laravel Cloud)
não dão juntas:

- **worker sempre ligado**: sem o `queue:work`, as buscas nunca terminam;
- **disco persistente** para o SQLite (≈ 55 MB, com dados reais).

Opções de VM gratuita, em ordem de preferência:

| Opção | O que é grátis | Observações |
|---|---|---|
| **Azure for Students** | 12 meses de VM B1s / B2ats v2 / B2pts v2 (750 h/mês) e US$ 100 de crédito/ano, sem cartão | 1 GB de RAM (o swap do `provisionar-vm.sh` resolve). A B1s está sumindo de várias regiões: use a **B2ats v2** (x86). Renovável enquanto houver vínculo de estudante |
| Google Cloud e2-micro | 1 VM e2-micro, 30 GB de disco, sem prazo, só em `us-west1`, `us-central1`, `us-east1` | Plano B para depois dos 12 meses do Azure. 1 GB de RAM; exige conta de faturamento |
| Oracle Always Free | 2 OCPU ARM e 12 GB de RAM no total (corte de 2026), 200 GB | Mais folga, mas exige cartão, falta capacidade com frequência, e VMs ociosas podem ser recuperadas. A imagem precisaria ser compilada para ARM |

Os mesmos arquivos servem para qualquer uma delas.

## O que roda no container

`Dockerfile` (FrankenPHP + PHP 8.3) sobe, sob o `supervisord` (`docker/supervisord.conf`):

- **web**: FrankenPHP/Caddy, que emite o certificado HTTPS (Let's Encrypt) para o domínio em
  `SERVER_NAME`;
- **worker**: **um** `queue:work --timeout=600` (dois workers causam a corrida do item 3 de
  [problemas-conhecidos.md](problemas-conhecidos.md));
- **scheduler**: `schedule:work`, que faz o backup diário do SQLite às 06:00 (`docker/backup-sqlite.sh`,
  guarda 7 cópias em `data/backups/`).

`QUEUE_WORKER_SUPERVISIONADO=true` (padrão da imagem) desliga o agendamento de `queue:work` de
`routes/console.php`, que só serve para hospedagens que têm apenas cron.

Na partida, `docker/entrypoint.sh` cria o banco se não existir, liga o modo WAL, roda `migrate --force`,
o seeder (idempotente) e os caches do Laravel.

Arquivos:

| Arquivo | Papel |
|---|---|
| `Dockerfile`, `.dockerignore`, `docker/` | Imagem. O `.dockerignore` impede que o banco e o `.env` entrem nela |
| `docker-compose.prod.yml` | Sobe o container na VM, com `./data` montado em `/data` |
| `.env.production.example` | Modelo do `.env.production` (que fica só na VM) |
| `scripts/provisionar-vm.sh` | Prepara a VM Ubuntu: swap de 2 GB, Docker, atualizações automáticas |
| `.github/workflows/ci.yml` | Testes, Pint e build da imagem a cada PR (não faz deploy) |

## Primeiro deploy

### 1. No seu computador

1. **Chave da Groq**: em <https://console.groq.com/keys>, crie uma chave (plano gratuito, sem cartão).
2. **APP_KEY**: na pasta do projeto, rode `php artisan key:generate --show` e guarde o valor
   (`base64:...`). Ele **não** vai para o `.env` local.
3. **Chave SSH** (se ainda não tiver): `ssh-keygen -t ed25519`. A pública fica em
   `C:\Users\<você>\.ssh\id_ed25519.pub`.

### 2. No Azure

1. Ative o **Azure for Students** pelo GitHub Student Pack (<https://education.github.com/pack>) ou em
   <https://azure.microsoft.com/free/students>, com o e-mail da universidade.
2. No portal, **Virtual machines → Create → Azure virtual machine**:
   - *Subscription*: Azure for Students. *Resource group*: novo, `sisread`.
   - *Virtual machine name*: `sisread`. *Region*: **(US) North Central US**. A
     política da assinatura só permite `northcentralus`, `brazilsouth`, `francecentral`, `spaincentral`
     e `southafricanorth`; East US é recusada. Confira com `az policy assignment list`.
   - *Image*: **Ubuntu Server 24.04 LTS – x64 Gen2**.
   - *Size*: **Standard_B2ats_v2** (o portal marca os tamanhos *free services eligible*; se não
     houver, tente B1s ou outra região).
   - *Authentication*: SSH public key, *username* `azureuser`, cole o conteúdo do `id_ed25519.pub`.
   - *Inbound ports*: **SSH (22), HTTP (80), HTTPS (443)**.
   - Aba *Disks*: *OS disk type* **Premium SSD**, tamanho **64 GiB** (P6, o que entra na gratuidade).
   - Aba *Management*: deixe **auto-shutdown desligado** (o worker precisa ficar ligado).
3. Depois de criada: **VM → Overview → DNS name → Configure**, rótulo `sisread` (ou outro livre).
   O domínio fica `sisread.northcentralus.cloudapp.azure.com`. Na mesma tela do IP, mude *Assignment* para
   **Static**.
4. **Cost Management → Budgets**: crie um orçamento de US$ 5/mês com alerta por e-mail, para saber
   se algo saiu da gratuidade.

### 3. Na VM (por SSH)

```bash
ssh azureuser@sisread.northcentralus.cloudapp.azure.com
git clone https://github.com/razimbrao/sis-read.git
cd sis-read
sudo sh scripts/provisionar-vm.sh
exit   # saia e entre de novo, para usar docker sem sudo
```

Entre de novo e configure:

```bash
cd sis-read
cp .env.production.example .env.production
nano .env.production   # APP_KEY, APP_URL, SERVER_NAME e GROQ_API_KEY
chmod 600 .env.production
mkdir -p data
```

### 4. Copiar o banco (no seu computador)

Pare o `php artisan serve` e o worker locais (para o arquivo não mudar no meio da cópia) e, no
PowerShell, na pasta do projeto:

```powershell
scp database\database.sqlite azureuser@sisread.northcentralus.cloudapp.azure.com:~/sis-read/data/database.sqlite
```

Se pular este passo, o container cria um banco vazio.

### 5. Subir (na VM)

```bash
cd ~/sis-read
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml logs -f   # Ctrl+C para sair
```

O primeiro build leva alguns minutos (o swap evita falta de memória). Quando o log mostrar o Caddy
com o certificado obtido, abra `https://sisread.northcentralus.cloudapp.azure.com`, faça login e rode uma
busca com meta para conferir worker e Groq.

## Operação

| Tarefa | Comando (na VM, em `~/sis-read`) |
|---|---|
| Atualizar o código | `git pull && docker compose -f docker-compose.prod.yml up -d --build` |
| Ver logs | `docker compose -f docker-compose.prod.yml logs -f --tail 200` |
| Rodar um artisan | `docker compose -f docker-compose.prod.yml exec app php artisan <comando>` |
| Reiniciar | `docker compose -f docker-compose.prod.yml restart` |
| Aplicar mudança no `.env.production` | `docker compose -f docker-compose.prod.yml up -d --force-recreate` (o `restart` não relê o arquivo) |
| Backup na hora | `docker compose -f docker-compose.prod.yml exec app sh docker/backup-sqlite.sh` |

Evite atualizar durante sessões do estudo: o container espera até 11 min o job em andamento, e um job
interrompido volta para a fila só depois de `retry_after` (660 s).

### Backups

O backup diário fica no **mesmo disco** da VM (`data/backups/`): protege de erro humano e migration
ruim, não de perder a VM. De tempos em tempos (e antes de cada atualização), baixe uma cópia. Os
arquivos são criados pelo root do container com permissão 600, por isso o `chown` antes:

```powershell
ssh azureuser@sisread.northcentralus.cloudapp.azure.com "sudo chown -R azureuser ~/sis-read/data/backups"
scp "azureuser@sisread.northcentralus.cloudapp.azure.com:~/sis-read/data/backups/*.gz" .\backups-producao\
```

Os arquivos têm dados pessoais (e-mails, hashes de senha): não os coloque no repositório nem em pasta
pública. Restaurar: pare o container, descompacte (`gunzip`) por cima de `data/database.sqlite` e
suba de novo.

### Depois dos 12 meses

A VM do Azure passa a ser cobrada do crédito (e, sem crédito, desligada). Renove o Azure for Students
ou repita os passos 3 a 5 numa e2-micro do Google Cloud, copiando `data/` da VM antiga.
