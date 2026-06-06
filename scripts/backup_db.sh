#!/bin/bash
#
# BookSys DB 백업 스크립트
# - 운영 서버에서 docker compose 환경 기준
# - cron으로 매일 새벽 3시 실행 권장:
#     0 3 * * * /home/USER/bookflow_app/scripts/backup_db.sh >> /var/log/booksys_backup.log 2>&1
#
# 보관 정책: 최근 14일치만 유지 (오래된 백업 자동 삭제)
#

set -e

# 설정 ===
PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
BACKUP_DIR="${PROJECT_DIR}/backups"
RETAIN_DAYS=14
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/booksys_${TIMESTAMP}.sql.gz"

# .env에서 DB 정보 읽기
ENV_FILE="${PROJECT_DIR}/.env"
if [ ! -f "$ENV_FILE" ]; then
    echo "[ERROR] .env file not found at $ENV_FILE"
    exit 1
fi

DB_DATABASE=$(grep -E "^DB_DATABASE=" "$ENV_FILE" | cut -d= -f2 | tr -d '"' | tr -d "'")
DB_USERNAME=$(grep -E "^DB_USERNAME=" "$ENV_FILE" | cut -d= -f2 | tr -d '"' | tr -d "'")
DB_PASSWORD=$(grep -E "^DB_PASSWORD=" "$ENV_FILE" | cut -d= -f2 | tr -d '"' | tr -d "'")

# 디렉토리 생성
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"  # 본인만 읽기 가능

# 백업 실행 (docker compose 안의 db 컨테이너에서 mysqldump 실행)
echo "[$(date)] Starting backup..."
cd "$PROJECT_DIR"

# docker compose v2 가정. 컨테이너 이름은 'db' 또는 'mariadb'
DB_CONTAINER=$(docker compose ps --services 2>/dev/null | grep -E "^(db|mariadb|mysql)$" | head -1)
if [ -z "$DB_CONTAINER" ]; then
    echo "[ERROR] DB container not found (looked for db/mariadb/mysql)"
    exit 1
fi

# 백업
docker compose exec -T "$DB_CONTAINER" \
    mysqldump --single-transaction --quick --lock-tables=false \
    -u"$DB_USERNAME" -p"$DB_PASSWORD" \
    "$DB_DATABASE" | gzip > "$BACKUP_FILE"

# 체크 — 백업 파일이 의미 있는 크기인지
BACKUP_SIZE=$(stat -c %s "$BACKUP_FILE" 2>/dev/null || stat -f %z "$BACKUP_FILE" 2>/dev/null)
if [ -z "$BACKUP_SIZE" ] || [ "$BACKUP_SIZE" -lt 1024 ]; then
    echo "[ERROR] Backup file too small ($BACKUP_SIZE bytes) — possible failure"
    rm -f "$BACKUP_FILE"
    exit 1
fi

chmod 600 "$BACKUP_FILE"
echo "[$(date)] Backup saved: $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1))"

# 오래된 백업 정리
echo "[$(date)] Cleaning backups older than $RETAIN_DAYS days..."
find "$BACKUP_DIR" -name "booksys_*.sql.gz" -mtime +$RETAIN_DAYS -delete

# 남은 백업 갯수 표시
COUNT=$(find "$BACKUP_DIR" -name "booksys_*.sql.gz" | wc -l)
echo "[$(date)] Done. $COUNT backups in $BACKUP_DIR"
