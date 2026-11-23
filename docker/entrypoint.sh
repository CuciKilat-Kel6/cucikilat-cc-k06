#!/bin/sh
set -e

cd /var/www/html

echo "[entrypoint] Menjalankan migrasi database..."
tries=0
until runuser -u www-data -- php artisan migrate --force; do
  tries=$((tries + 1))
  if [ "$tries" -ge 20 ]; then
    echo "[entrypoint] Migrasi gagal setelah $tries percobaan" >&2
    exit 1
  fi
  echo "[entrypoint] Database belum siap, mencoba lagi ($tries)..." >&2
  sleep 3
done

echo "[entrypoint] Menjalankan seeder..."
runuser -u www-data -- php artisan db:seed --force

echo "[entrypoint] Menyalakan aplikasi"
exec "$@"
