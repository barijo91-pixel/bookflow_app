#!/usr/bin/env bash
# BookSys 운영 배포 스크립트 (충돌 면역판)
# 사용: ./deploy.sh   (운영 서버 ~/bookflow_app 에서 실행)
set -e

echo "=== BookSys 배포 시작 ==="

echo "[1/5] 최신 코드 강제 정렬 (로컬 변경 무시 — 충돌 면역)"
git fetch origin
git reset --hard origin/main
#  ↑ git pull 과 달리 로컬에 뭐가 꼬여 있어도 무조건 GitHub 최신으로 맞춤.
#    .env / vendor 등은 git 추적 대상이 아니라 보존됨. (운영 코드는 직접 수정 금지)

echo "[2/5] 마이그레이션 (있으면 적용)"
sudo docker compose exec -T app php artisan migrate --force

echo "[3/5] 설정 시드 (실패해도 진행)"
sudo docker compose exec -T app php artisan db:seed --class=SiteSettingSeeder --force || echo "  (시드 건너뜀)"

echo "[4/5] 캐시 전체 정리 (route/config/view/cache/event)"
sudo docker compose exec -T app php artisan optimize:clear

echo "[5/5] 컨테이너 재시작 (opcache 리셋)"
sudo docker compose restart app
sleep 3

echo "=== 배포 완료 — 현재 커밋 ==="
git log --oneline -1
sudo docker compose exec -T app php artisan route:list 2>/dev/null | grep -iE "(^| )login|settings" | head -5 || true
