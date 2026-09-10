#!/usr/bin/env bash
# Build one site.
#
#   ./scripts/build-site.sh alex
#   ./scripts/build-site.sh leah
#
# Output goes to builds/<site>/ rather than build/, so the two sites' artifacts
# can never be confused with each other on disk. Deploy from there.

set -euo pipefail

SITE="${1:-}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/sites/$SITE.env"

if [[ -z "$SITE" || ! -f "$ENV_FILE" ]]; then
  echo "usage: $0 <site>" >&2
  echo "available:" >&2
  for f in "$ROOT"/sites/*.env; do echo "  $(basename "$f" .env)" >&2; done
  exit 1
fi

# Export every assignment in the env file. `set -a` makes them environment
# variables so react-scripts picks up the REACT_APP_* ones.
set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

echo "Building $SITE"
echo "  domain   $SITE_DOMAIN"
echo "  api base $REACT_APP_API_BASE"
echo "  title    $REACT_APP_SITE_NAME"
echo

cd "$ROOT"
rm -rf build
npx --no-install react-scripts build

OUT="$ROOT/builds/$SITE"
rm -rf "$OUT"
mkdir -p "$(dirname "$OUT")"
mv build "$OUT"

# Fail loudly rather than shipping a build that talks to the wrong site.
if grep -rq "$REACT_APP_API_BASE" "$OUT/static/js/" 2>/dev/null; then
  echo
  echo "OK  $OUT  (verified it points at $REACT_APP_API_BASE)"
else
  echo
  echo "WARNING: could not find $REACT_APP_API_BASE in the bundle." >&2
  echo "Check sites/$SITE.env before deploying." >&2
  exit 1
fi
