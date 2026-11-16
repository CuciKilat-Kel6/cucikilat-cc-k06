#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
project_dir="$PWD"
mysql_dir="$project_dir/storage/app/private/mysql"
mysql_socket="$project_dir/storage/app/private/mysql.sock"
if [[ -d "$mysql_dir/mysql" ]]; then
    mysql_bin="${MYSQL_BIN:-$HOME/Library/FlyEnv/app/mysql-8.4.7/bin}"
    if [[ ! -x "$mysql_bin/mysqld" ]]; then
        echo 'MySQL lokal tidak ditemukan. Isi MYSQL_BIN dengan lokasi folder bin MySQL.' >&2
        exit 1
    fi
    if ! "$mysql_bin/mysqladmin" --no-defaults --socket="$mysql_socket" ping --silent >/dev/null 2>&1; then
        "$mysql_bin/mysqld" --no-defaults --datadir="$mysql_dir" --socket="$mysql_socket" --bind-address=127.0.0.1 --port=13316 --mysqlx=OFF --pid-file="$project_dir/storage/app/private/mysql.pid" >storage/logs/mysql-local.log 2>&1 &
        mysql_local_pid=$!
        trap 'kill "$mysql_local_pid" 2>/dev/null || true' EXIT
        for attempt in {1..30}; do
            if "$mysql_bin/mysqladmin" --no-defaults --socket="$mysql_socket" ping --silent >/dev/null 2>&1; then
                break
            fi
            sleep 1
        done
        if ! "$mysql_bin/mysqladmin" --no-defaults --socket="$mysql_socket" ping --silent >/dev/null 2>&1; then
            echo 'MySQL belum siap. Lihat storage/logs/mysql-local.log.' >&2
            exit 1
        fi
    fi
fi
cd public
php -S 127.0.0.1:8000 "$project_dir/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
