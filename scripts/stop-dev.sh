#!/usr/bin/env bash
# Stop the Laravel dev server, scheduler and MariaDB started by start-dev.sh.
pkill -f "artisan serve --host=0.0.0.0" || true
pkill -f "artisan schedule:work" || true
"$HOME/tools/mariadb/bin/mariadb-admin" --defaults-file="$HOME/tools/my.cnf" -uroot shutdown 2>/dev/null || true
echo "Stopped."
