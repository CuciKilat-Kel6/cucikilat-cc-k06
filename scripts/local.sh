#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
project_dir="$PWD"
echo 'Pastikan MySQL FlyEnv sudah menyala dan konfigurasi .env sesuai.'
echo 'Domain cucikilat.test dilayani langsung oleh FlyEnv; server ini alternatif port 8000.'
cd public
php -S 127.0.0.1:8000 "$project_dir/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
