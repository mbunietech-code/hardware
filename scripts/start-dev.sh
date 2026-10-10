#!/usr/bin/env bash
# Start the local development stack: MariaDB (MySQL) + Laravel web/API on port 8765.
# Tools were installed in user space under ~/tools (no sudo needed).
set -euo pipefail
TOOLS="$HOME/tools"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PATH="$TOOLS/bin:$TOOLS/flutter/bin:$PATH"
PORT="${PORT:-8765}"

if [ ! -S "$TOOLS/mysql.sock" ] || ! "$TOOLS/mariadb/bin/mariadb-admin" --defaults-file="$TOOLS/my.cnf" -uroot ping >/dev/null 2>&1; then
  echo "Starting MariaDB..."
  nohup "$TOOLS/mariadb/bin/mariadbd-safe" --defaults-file="$TOOLS/my.cnf" >/dev/null 2>&1 &
  for _ in $(seq 1 30); do
    "$TOOLS/mariadb/bin/mariadb-admin" --defaults-file="$TOOLS/my.cnf" -uroot ping >/dev/null 2>&1 && break
    sleep 1
  done
fi
echo "MariaDB is running."

cd "$ROOT/backend"
php artisan migrate --force
echo "Web admin + API: http://localhost:$PORT  (phones on the same Wi-Fi: http://$(hostname -I | awk '{print $1}'):$PORT)"
php artisan schedule:work >/dev/null 2>&1 &
exec php artisan serve --host=0.0.0.0 --port="$PORT"
