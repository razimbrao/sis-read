#!/bin/sh
# Deploy na VM, chamado pelo CI (docs/deploy.md, seção Deploy automático).
# A chave do CI só consegue rodar este script: o authorized_keys da VM o fixa com command=, e o
# comando enviado pelo SSH é ignorado. Fica instalado fora do repositório, em
# /usr/local/bin/sisread-deploy, para um git pull não trocar o script que está rodando.
# O log do Actions é público: a saída detalhada vai para ~/deploy.log e o CI só vê o resultado.
set -eu

PASTA="$HOME/sis-read"
COMPOSE="docker compose -f docker-compose.prod.yml"
LOG="$HOME/deploy.log"
ESPERA_SAUDE=180

exec 3>&1
umask 077
exec >>"$LOG" 2>&1

falhou() {
    echo "$(date -u +%FT%TZ) falhou: $1"
    echo "Deploy falhou em: $1. Detalhes em ~/deploy.log na VM." >&3
    exit 1
}

# Só um deploy por vez.
exec 9>"$HOME/.deploy.lock"
flock -n 9 || falhou "outro deploy em andamento"

echo "=== $(date -u +%FT%TZ) início"
cd "$PASTA" || falhou "pasta do projeto"
git fetch --quiet origin main || falhou "git fetch"
git merge --ff-only origin/main || falhou "git merge"
$COMPOSE up -d --build || falhou "build ou subida do container"

CONTAINER=$($COMPOSE ps -q app)
ESPEROU=0
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$CONTAINER")" = "healthy" ]; do
    [ "$ESPEROU" -lt "$ESPERA_SAUDE" ] || falhou "container não ficou saudável"
    sleep 5
    ESPEROU=$((ESPEROU + 5))
done

docker image prune -f
echo "=== $(date -u +%FT%TZ) ok"
echo "Deploy ok: $(git rev-parse --short HEAD)" >&3
