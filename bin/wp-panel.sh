#!/bin/bash
# ============================================================================
# wp-panel.sh - root CLI helpers for the panel itself
#   password [username]   reset / create admin password (new one is printed)
# ============================================================================
set -u
PANEL_DIR="${PANEL_DIR:-/usr/local/webpanel/panel}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"

case "${1:-}" in
    password)
        exec "$PHP_BIN" "$PANEL_DIR/tools/admin.php" password "${2:-admin}"
        ;;
    *)
        echo "usage: wp-panel.sh password [username]" >&2
        exit 64
        ;;
esac
