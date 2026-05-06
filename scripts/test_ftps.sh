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

lftp -u "$FTP_USER","$FTP_PASS" -p "$FTP_PORT" "$FTP_HOST" <<EOF
set ftp:ssl-force true
set ftp:ssl-protect-data true
set ssl:verify-certificate no
cd "$FTP_PATH"
pwd
cls -1
bye
EOF
