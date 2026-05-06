#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$PROJECT_DIR/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing .env file at $ENV_FILE"
  exit 1
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "lftp is required. Install with: brew install lftp"
  exit 1
fi

set -a
# shellcheck source=/dev/null
source "$ENV_FILE"
set +a

: "${FTP_HOST:?FTP_HOST is required in .env}"
: "${FTP_PORT:?FTP_PORT is required in .env}"
: "${FTP_USER:?FTP_USER is required in .env}"
: "${FTP_PASS:?FTP_PASS is required in .env}"
: "${FTP_PATH:?FTP_PATH is required in .env}"

LFTP_DELETE_FLAG=""
if [[ "${FTP_DELETE_REMOTE:-false}" == "true" ]]; then
  LFTP_DELETE_FLAG="--delete"
fi

UPLOAD_ENV_FILE="${FTP_UPLOAD_ENV:-false}"
SERVER_ENV_FILE="$ENV_FILE"

lftp -u "$FTP_USER","$FTP_PASS" -p "$FTP_PORT" "$FTP_HOST" <<EOF
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate no
set xfer:clobber on

mirror -R \
  --verbose=2 \
  $LFTP_DELETE_FLAG \
  --exclude-glob .git* \
  --exclude .DS_Store \
  "$PROJECT_DIR/src" "$FTP_PATH/src"

mirror -R \
  --verbose=2 \
  $LFTP_DELETE_FLAG \
  --exclude-glob .git* \
  --exclude .DS_Store \
  "$PROJECT_DIR/config" "$FTP_PATH/config"

put "$PROJECT_DIR/public/index.php" -o "$FTP_PATH/index.php"
put "$PROJECT_DIR/public/api.php" -o "$FTP_PATH/api.php"
put "$PROJECT_DIR/public/live.php" -o "$FTP_PATH/live.php"

mirror -R \
  --verbose=2 \
  $LFTP_DELETE_FLAG \
  --exclude-glob .git* \
  --exclude .env \
  --exclude .DS_Store \
  --exclude .ftpquota \
  --exclude vendor \
  "$PROJECT_DIR/public" "$FTP_PATH/public"

$(if [[ "$UPLOAD_ENV_FILE" == "true" ]]; then echo "put \"$SERVER_ENV_FILE\" -o \"$FTP_PATH/.env\""; fi)

bye
EOF

echo "Deploy completed to $FTP_HOST:$FTP_PATH"
