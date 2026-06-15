#!/usr/bin/env bash
# BookSys 운영 배포 스크립트
# 사용: ./deploy.sh   (운영 서버 ~/bookflow_app 에서 실행)
set -e

echo "=== BookSys 배포 시작 ==="

echo "[1/6] 최신 코드 가져오기"
git pull origin main

echo "[2/6] 마이그레이션 (있으면 적용)"
sudo docker compose exec -T app php artisan migrate --force

echo "[3/6] 설정 시드 (site_settings 추가분)"
sudo docker compose exec -T app php artisan db:seed --class=SiteSettingSeeder --force

echo "[4/6] 캐시 정리"
sudo docker compose exec -T app php artisan view:clear
sudo docker compose exec -T app php artisan route:clear
sudo docker compose exec -T app php artisan config:clear
sudo docker compose exec -T app php artisan cache:clear

echo "[5/6] 컨테이너 재시작 (opcache 리셋 — 코드 변경 반영 필수)"
sudo docker compose restart app

echo "[6/6] 완료 대기"
sleep 3

echo "=== 배포 완료 ==="
