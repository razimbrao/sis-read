#!/bin/sh
# Cópia consistente do SQLite (funciona com o banco em uso, inclusive em modo WAL).
# Guarda as últimas 7 cópias em /data/backups. Isso protege contra erro humano e migration
# ruim, mas fica no MESMO volume: para desastre, use os snapshots do Fly ou copie para fora
# (docs/deploy.md, seção Backups). Os arquivos têm dados pessoais: nunca os publique.
set -e

BANCO="${DB_DATABASE:-/data/database.sqlite}"
DESTINO="${BACKUP_DIR:-/data/backups}"
MANTER="${BACKUP_KEEP:-7}"

mkdir -p "$DESTINO"
ARQUIVO="$DESTINO/database-$(date -u +%Y%m%d-%H%M%S).sqlite"

sqlite3 "$BANCO" ".backup '$ARQUIVO'"
gzip "$ARQUIVO"
chmod 600 "$ARQUIVO.gz"

ls -1t "$DESTINO"/database-*.sqlite.gz | tail -n +"$((MANTER + 1))" | xargs -r rm -f
echo "Backup gravado em $ARQUIVO.gz"
