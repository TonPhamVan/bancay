#!/bin/sh
# Install as the owning DirectAdmin account, with private configuration supplied by that owner.
set -eu
: "${BANCAY_PHP_BIN:?Set the verified absolute PHP 8.3 CLI path}"
: "${BANCAY_RELEASE_PATH:?Set the verified absolute application directory}"
case "$BANCAY_PHP_BIN" in /*) ;; *) echo 'PHP path must be absolute' >&2; exit 2;; esac
case "$BANCAY_RELEASE_PATH" in /*) ;; *) echo 'Release path must be absolute' >&2; exit 2;; esac
test -x "$BANCAY_PHP_BIN"
test -f "$BANCAY_RELEASE_PATH/artisan"
cd "$BANCAY_RELEASE_PATH"
exec "$BANCAY_PHP_BIN" artisan schedule:run --no-interaction
