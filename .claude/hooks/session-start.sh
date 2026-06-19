#!/bin/bash
# AfrikaLink — démo/staging : provisionne une base MariaDB locale + 50 boutiques
# de démonstration + un serveur de prévisualisation, à chaque session web.
# Idempotent : ré-exécutable sans effet de bord. Web uniquement.
set -uo pipefail

# N'agir qu'en environnement distant (Claude Code on the web / staging).
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

DIR="${CLAUDE_PROJECT_DIR:-$(pwd)}"
cd "$DIR" || exit 0

SOCK=/tmp/mysql.sock
DATADIR=/tmp/mdb-data
LOG=/tmp/afk-demo-hook.log
# Journalise tout dans un fichier (garde la sortie de démarrage propre).
exec >>"$LOG" 2>&1
echo "=== session-start $(date -u) ==="

# 1) .env local (gitignoré) si absent.
if [ ! -f .env ]; then
  KEY=$(php -r 'echo bin2hex(random_bytes(32));' 2>/dev/null || echo devkey)
  cat > .env <<ENV
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8080
DEFAULT_LOCALE=fr
DEFAULT_CURRENCY=EUR
SESSION_DRIVER=file
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=afriklink
DB_USER=root
DB_PASS=
DB_SSL=false
ENV
  echo ".env créé"
fi

# 2) MariaDB : initialise (si besoin) puis démarre (si pas déjà en cours).
if ! mysqladmin --socket="$SOCK" ping >/dev/null 2>&1; then
  if [ ! -d "$DATADIR/mysql" ]; then
    mariadb-install-db --datadir="$DATADIR" --auth-root-authentication-method=normal --skip-test-db >/dev/null 2>&1 || true
  fi
  nohup /usr/sbin/mariadbd --user=root --datadir="$DATADIR" --socket="$SOCK" \
        --port=3306 --bind-address=127.0.0.1 --pid-file=/tmp/mdb.pid >/tmp/mdb.log 2>&1 &
  disown || true
  for i in $(seq 1 30); do mysqladmin --socket="$SOCK" ping >/dev/null 2>&1 && break; sleep 1; done
fi
mysql --socket="$SOCK" -e "CREATE DATABASE IF NOT EXISTS afriklink CHARACTER SET utf8mb4;" 2>/dev/null || true

# 3) Schéma cœur (SQL) + migrations, seulement si la table users manque.
HAS_USERS=$(mysql --socket="$SOCK" afriklink -N -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='afriklink' AND table_name='users';" 2>/dev/null || echo 0)
if [ "${HAS_USERS:-0}" = "0" ]; then
  mysql --socket="$SOCK" afriklink < database/install_tables_only.sql 2>/dev/null || true
  for m in database/migrations/*.sql; do mysql --socket="$SOCK" afriklink < "$m" 2>/dev/null || true; done
  echo "schéma cœur chargé"
fi

# 4) Schéma des verticales (boutiques, produits…) — idempotent.
php database/demo_schema.php >/dev/null 2>&1 || true

# 5) Seed des 50 boutiques de démo si pas encore peuplé.
N=$(mysql --socket="$SOCK" afriklink -N -e "SELECT COUNT(*) FROM boutiques WHERE status='published';" 2>/dev/null || echo 0)
if [ "${N:-0}" -lt 50 ]; then
  php database/seed_demo.php --force >/dev/null 2>&1 || true
fi

# 6) Serveur de prévisualisation (arrière-plan persistant) si pas déjà actif.
if ! curl -s -o /dev/null http://127.0.0.1:8080/ 2>/dev/null; then
  nohup php -S 127.0.0.1:8080 -t public >/tmp/php-server.log 2>&1 &
  disown || true
  for i in $(seq 1 15); do curl -s -o /dev/null http://127.0.0.1:8080/ 2>/dev/null && break; sleep 0.5; done
fi

FINAL=$(mysql --socket="$SOCK" afriklink -N -e "SELECT COUNT(*) FROM boutiques WHERE status='published';" 2>/dev/null || echo 0)
echo "démo prête : ${FINAL} boutiques publiées · serveur http://127.0.0.1:8080"
exit 0
