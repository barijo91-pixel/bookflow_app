#!/usr/bin/env bash
# BookSys 운영 배포 스크립트
# 사용: ./deploy.sh   (운영 서버 ~/bookflow_app 에서 실행)
set -e

echo "=== BookSys 배포 시작 ==="

echo "[1/5] 최신 코드 가져오기"
git pull origin main

echo "[2/5] 마이그레이션 (있으면 적용)"
sudo docker compose exec -T app php artisan migrate --force

echo "[3/5] 설정 시드 (site_settings 추가분 — 실패해도 진행)"
sudo docker compose exec -T app php artisan db:seed --class=SiteSettingSeeder --force || echo "  (시드 건너뜀)"

echo "[4/5] 캐시 전체 정리 (route/config/view/cache/event 한 번에)"
sudo docker compose exec -T app php artisan optimize:clear

echo "[5/5] 컨테이너 재시작 (opcache 리셋 — 코드 변경 반영 필수)"
sudo docker compose restart app
sleep 3

echo "=== 배포 완료 — 로그인 라우트 확인 ==="
sudo docker compose exec -T app php artisan route:list 2>/dev/null | grep -iE "(^| )login|admin/login" || echo "  (route:list 확인 생략)"
