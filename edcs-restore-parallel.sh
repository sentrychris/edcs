#!/usr/bin/env bash
#
# Dispatch a parallel db:restore as a transient systemd unit so it survives
# SSH disconnects. Output streams to the journal under the unit name below.
#
# Usage: sudo ./edcs-restore-parallel.sh <dump-folder-name> <target-schema> [extra db:restore flags...]
# Example: sudo ./edcs-restore-parallel.sh dump-2026-05-03_105317 edcs_clone --reset-progress

set -euo pipefail

UNIT="edcs-restore"
WORKDIR="/var/www/edcts"
RUN_AS_USER="chris"
HOME_DIR="/home/chris"

if [[ $# -lt 2 ]]; then
    echo "Usage: sudo $0 <dump-folder-name> <target-schema> [extra db:restore flags...]" >&2
    exit 64
fi

DUMP="$1"
TARGET="$2"
shift 2
EXTRA_ARGS=("$@")

if [[ $EUID -ne 0 ]]; then
    echo "This script must be run with sudo (systemd-run needs root)." >&2
    exit 77
fi

if systemctl is-active --quiet "$UNIT"; then
    echo "Unit '$UNIT' is already running. Tail it with:" >&2
    echo "  journalctl -u $UNIT -f" >&2
    echo "Or stop it with:" >&2
    echo "  sudo systemctl stop $UNIT" >&2
    exit 75
fi

# Clear any leftover failed unit from a previous run so systemd-run
# doesn't refuse with "unit already exists".
systemctl reset-failed "$UNIT" 2>/dev/null || true

systemd-run \
    --unit="$UNIT" \
    --working-directory="$WORKDIR" \
    --setenv=HOME="$HOME_DIR" \
    --uid="$RUN_AS_USER" \
    /usr/bin/php artisan db:restore "$DUMP" --target="$TARGET" "${EXTRA_ARGS[@]}"

echo
echo "Restore dispatched as systemd unit '$UNIT'."
echo "Follow output:    journalctl -u $UNIT -f"
echo "Check status:     systemctl status $UNIT     (visible while running and after failure)"
echo "Stop it:          sudo systemctl stop $UNIT"
