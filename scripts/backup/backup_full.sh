#!/usr/bin/env bash
# ============================================================
# backup_full.sh — Full DB dump + push ke GitHub
# Jadwalkan via cron setiap 30 menit
#
# Setup cron (jalankan: crontab -e):
#   */30 * * * * /path/to/finance/scripts/backup/backup_full.sh >> /path/to/finance/backup/logs/cron.log 2>&1
# ============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FINANCE_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# ── Load .env ──────────────────────────────────────────────
ENV_FILE="${SCRIPT_DIR}/.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "[ERROR] File .env tidak ditemukan di ${SCRIPT_DIR}"
  echo "        Salin .env.example ke .env dan isi konfigurasi."
  exit 1
fi
# shellcheck source=/dev/null
source "$ENV_FILE"

# ── Defaults ───────────────────────────────────────────────
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-db_finance}"
BACKUP_DIR="${FINANCE_ROOT}/${BACKUP_DIR:-backup/dumps}"
LOG_DIR="${FINANCE_ROOT}/${LOG_DIR:-backup/logs}"
RETENTION_DAYS="${RETENTION_DAYS:-3}"
BACKUP_REPO_REMOTE="${BACKUP_REPO_REMOTE:-origin}"
BACKUP_REPO_BRANCH="${BACKUP_REPO_BRANCH:-main}"
BACKUP_REPO_PATH="${BACKUP_REPO_PATH:-$FINANCE_ROOT}"
EXCLUDE_TABLES="${EXCLUDE_TABLES:-}"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOGFILE="${LOG_DIR}/backup_${TIMESTAMP}.log"
DUMPFILE="${BACKUP_DIR}/backup_${DB_NAME}_${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR" "$LOG_DIR"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOGFILE"; }

log "====== Backup START: ${TIMESTAMP} ======"
log "Database : ${DB_NAME}@${DB_HOST}:${DB_PORT}"
log "Output   : ${DUMPFILE}"

# ── Build ignore tables args ───────────────────────────────
IGNORE_ARGS=""
if [ -n "$EXCLUDE_TABLES" ]; then
  IFS=',' read -ra TABLES <<< "$EXCLUDE_TABLES"
  for tbl in "${TABLES[@]}"; do
    tbl=$(echo "$tbl" | tr -d ' ')
    IGNORE_ARGS="$IGNORE_ARGS --ignore-table=${DB_NAME}.${tbl}"
  done
  log "Exclude  : ${EXCLUDE_TABLES}"
fi

# ── Dump ───────────────────────────────────────────────────
MYSQL_OPTS=(
  "--host=${DB_HOST}"
  "--port=${DB_PORT}"
  "--user=${DB_USER}"
  "--single-transaction"
  "--routines"
  "--events"
  "--no-tablespaces"
  "--set-gtid-purged=OFF"
  "--skip-lock-tables"
)
[ -n "$DB_PASS" ] && MYSQL_OPTS+=("--password=${DB_PASS}")
[ -n "$IGNORE_ARGS" ] && MYSQL_OPTS+=($IGNORE_ARGS)

if mysqldump "${MYSQL_OPTS[@]}" "$DB_NAME" | gzip -9 > "$DUMPFILE"; then
  SIZE=$(du -sh "$DUMPFILE" | cut -f1)
  log "Dump OK  : ${SIZE}"
else
  log "[ERROR] mysqldump gagal!"
  exit 1
fi

# ── Cleanup file lama ──────────────────────────────────────
DELETED=$(find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime +"${RETENTION_DAYS}" -print -delete | wc -l)
log "Cleanup  : ${DELETED} file lama dihapus (> ${RETENTION_DAYS} hari)"

# ── Git commit & push ──────────────────────────────────────
REPO_PATH="${BACKUP_REPO_PATH}"
cd "$REPO_PATH"

# Tambah hanya folder backup
git add backup/dumps/ backup/logs/ 2>/dev/null || true

CHANGED=$(git status --porcelain backup/ 2>/dev/null | wc -l)
if [ "$CHANGED" -gt 0 ]; then
  git commit -m "backup: ${DB_NAME} ${TIMESTAMP}" --quiet
  if git push "${BACKUP_REPO_REMOTE}" "${BACKUP_REPO_BRANCH}" --quiet 2>/dev/null; then
    log "Git push : OK → ${BACKUP_REPO_REMOTE}/${BACKUP_REPO_BRANCH}"
  else
    log "[WARN] Git push gagal. Backup lokal tetap tersimpan."
  fi
else
  log "Git push : Tidak ada perubahan, skip."
fi

log "====== Backup SELESAI ======"
log ""
