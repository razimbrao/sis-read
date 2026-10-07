#!/bin/sh
# Prepara o volume e o banco antes de subir web, worker e agendador.
# As migrations rodam aqui (e não num release_command do Fly) porque o SQLite mora no volume,
# que só está montado na própria máquina.
set -e

cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY não definida. Gere com 'php artisan key:generate --show' e grave como segredo." >&2
    exit 1
fi

mkdir -p /data/backups "$XDG_DATA_HOME" "$XDG_CONFIG_HOME"

if [ ! -f "$DB_DATABASE" ]; then
    echo "Criando banco vazio em $DB_DATABASE"
    touch "$DB_DATABASE"
fi

# WAL deixa o web ler enquanto o worker escreve (o modo fica gravado no arquivo do banco).
# A espera por lock já é de 60s por padrão no PDO do SQLite.
sqlite3 "$DB_DATABASE" "PRAGMA journal_mode=WAL;" >/dev/null

php artisan migrate --force
php artisan db:seed --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reinicia workers de um deploy anterior que ainda estejam lendo o código antigo.
php artisan queue:restart

exec supervisord -c /etc/supervisor/sisread.conf
