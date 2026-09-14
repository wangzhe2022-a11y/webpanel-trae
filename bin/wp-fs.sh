#!/bin/bash
# Thin sudo-friendly dispatcher: all logic lives in fs-worker.php so we get
# robust JSON encoding and realpath() jailing. Runs as root.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec /usr/bin/php "$SCRIPT_DIR/fs-worker.php" "$@"
