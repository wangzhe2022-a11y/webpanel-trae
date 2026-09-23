#!/usr/bin/env bash
# Cloud Agent install script for the webpanel-trae development environment.
#
# The panel is a PHP + SQLite app. Locally we run it in "dry-run" mode
# (PANEL_DRYRUN=1) so every privileged host operation (nginx/mysql/postgres/
# useradd/systemd/acme.sh ...) is mocked and the whole UI is usable without a
# real AlmaLinux host or root. This script is idempotent: it can run repeatedly.
set -euo pipefail

# Resolve the repository root from this script's location so the install works
# regardless of the checkout path.
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_DIR"

# 1. Ensure the PHP CLI and the extensions the panel needs are installed.
#    (pdo_sqlite/sqlite3 for the panel DB, mbstring for text handling, curl for
#    outbound calls.) Skipped entirely when PHP is already present, e.g. when the
#    environment boots from a prebuilt snapshot that already has PHP baked in.
need_php_install=0
if ! command -v php >/dev/null 2>&1; then
  need_php_install=1
else
  for ext in pdo_sqlite sqlite3 mbstring; do
    if ! php -m | grep -qi "^${ext}$"; then
      need_php_install=1
    fi
  done
fi

if [ "$need_php_install" -eq 1 ]; then
  echo "[install] Installing PHP CLI + extensions via apt..."
  sudo apt-get update -qq
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
    php-cli php-sqlite3 php-mbstring php-curl
fi

echo "[install] PHP version: $(php -v | head -n1)"

# 2. Create the panel runtime data directory (SQLite DB, sessions, cache, tmp).
#    This directory is gitignored; bootstrap.php also creates it at runtime.
mkdir -p panel/data

# 3. Create / reset a local development admin account. Uses dry-run mode so no
#    real services are touched. admin.php only reads a password from a *regular
#    file* on stdin, so write it to a temp file and redirect it in.
ADMIN_USER="${PANEL_ADMIN:-admin}"
ADMIN_PASS="${PANEL_ADMIN_PASSWORD:-Admin!Passw0rd}"

pw_file="$(mktemp)"
trap 'rm -f "$pw_file"' EXIT
printf '%s\n' "$ADMIN_PASS" > "$pw_file"
PANEL_DRYRUN=1 php panel/tools/admin.php password "$ADMIN_USER" < "$pw_file" >/dev/null
echo "[install] Panel admin ready -> user: ${ADMIN_USER} (dev password from PANEL_ADMIN_PASSWORD, default 'Admin!Passw0rd')"

echo "[install] Done. The panel dev server is started by the 'panel' terminal on http://localhost:8888/"
