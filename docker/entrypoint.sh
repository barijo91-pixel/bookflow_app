#!/bin/sh
set -e

cd /var/www/html

# K8s 환경에서 첫 부팅 시 권장되는 동작:
# 1. APP_KEY 미설정이면 생성 (운영에선 보통 Secret으로 주입하므로 스킵)
if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] APP_KEY 미설정 — generate"
    php artisan key:generate --force --no-interaction || true
fi

# 2. storage:link (이미 있으면 무시)
php artisan storage:link --force >/dev/null 2>&1 || true

# 2-1. 엑셀 업로드용 디렉토리 보장 (Flysystem이 못 만드는 케이스 대응)
mkdir -p /var/www/html/storage/app/private/imports
mkdir -p /var/www/html/storage/app/public
chown -R www-data:www-data /var/www/html/storage/app 2>/dev/null || true
chmod -R 775 /var/www/html/storage/app 2>/dev/null || true

# 2-2. nginx client_body 임시 디렉토리 — 파일 업로드 받을 때 nginx가 임시 저장
#      Permission denied로 업로드 실패하는 케이스 영구 차단
mkdir -p /var/lib/nginx/tmp/client_body
mkdir -p /var/lib/nginx/tmp/proxy
mkdir -p /var/lib/nginx/tmp/fastcgi
mkdir -p /var/lib/nginx/tmp/uwsgi
mkdir -p /var/lib/nginx/tmp/scgi
chmod -R 777 /var/lib/nginx/tmp 2>/dev/null || true

# 3. 캐시 워밍 (route, config, view) — 첫 부팅 시
if [ "${RUN_CACHE_OPTIMIZE:-1}" = "1" ]; then
    echo "[entrypoint] optimize caches"
    php artisan config:cache --no-interaction || true
    php artisan route:cache  --no-interaction || true
    php artisan view:cache   --no-interaction || true
fi

# 4. (선택) 마이그레이션 자동 실행
#    K8s에서는 Job으로 분리하는 게 표준. 환경변수로 ON/OFF
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "[entrypoint] artisan migrate --force"
    php artisan migrate --force --no-interaction
fi

# 5. (선택) 시드 (운영 첫 배포 시만)
if [ "${RUN_SEEDS:-0}" = "1" ]; then
    echo "[entrypoint] artisan db:seed --class=ProductionSeeder"
    php artisan db:seed --class=ProductionSeeder --force --no-interaction || true
fi

echo "[entrypoint] starting supervisord"
exec "$@"
