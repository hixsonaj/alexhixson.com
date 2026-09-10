#!/usr/bin/env bash
# Deploy one site.
#
#   ./scripts/deploy-site.sh alex            # front end only
#   ./scripts/deploy-site.sh leah --php      # front end + web PHP
#   ./scripts/deploy-site.sh leah --mail     # mail pipe only
#   ./scripts/deploy-site.sh leah --all      # everything
#
# Fixes file permissions after every upload. SCP does not preserve them, and the
# two failures that causes are silent:
#   - message.php without +x  -> the mail pipe never runs, mail vanishes
#   - images without 644      -> the webserver returns 403, photos don't load

set -euo pipefail

SITE="${1:-}"
MODE="${2:---web}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/sites/$SITE.env"

if [[ -z "$SITE" || ! -f "$ENV_FILE" ]]; then
  echo "usage: $0 <site> [--web|--php|--mail|--all]" >&2
  exit 1
fi

set -a; source "$ENV_FILE"; set +a

BUILD="$ROOT/builds/$SITE"
SRV="$ROOT/Server Side"

do_web=0; do_php=0; do_mail=0
case "$MODE" in
  --web)  do_web=1 ;;
  --php)  do_web=1; do_php=1 ;;
  --mail) do_mail=1 ;;
  --all)  do_web=1; do_php=1; do_mail=1 ;;
  *) echo "unknown mode: $MODE" >&2; exit 1 ;;
esac

echo "Deploying $SITE -> $SITE_DOMAIN"

if [[ $do_web -eq 1 ]]; then
  [[ -d "$BUILD" ]] || { echo "no build at $BUILD — run ./scripts/build-site.sh $SITE" >&2; exit 1; }

  # Guard against deploying one site's bundle to the other's server.
  if ! grep -rq "$REACT_APP_API_BASE" "$BUILD/static/js/" 2>/dev/null; then
    echo "REFUSING: build in $BUILD does not reference $REACT_APP_API_BASE." >&2
    echo "Rebuild with ./scripts/build-site.sh $SITE" >&2
    exit 1
  fi

  echo "  front end"
  scp -q -r "$BUILD"/* "$SSH_TARGET:$REMOTE_WEB/"
fi

if [[ $do_php -eq 1 ]]; then
  echo "  web php"
  scp -q "$SRV/retrieve_messages.php" "$SRV/submit_vote.php" \
         "$SRV/gallery_images.php" "$SRV/profile_images.php" \
         "$SSH_TARGET:$REMOTE_WEB/"
  echo "  shared site_config.php -> account root"
  scp -q "$SRV/site_config.php" "$SSH_TARGET:site_config.php"
fi

if [[ $do_mail -eq 1 ]]; then
  echo "  mail pipe"
  ssh "$SSH_TARGET" "mkdir -p $REMOTE_MAIL"
  scp -q "$SRV/message.php" "$SSH_TARGET:$REMOTE_MAIL/message.php"
  # Without this the pipe silently never executes.
  ssh "$SSH_TARGET" "chmod 755 $REMOTE_MAIL/message.php"
  echo "    chmod 755 message.php"
fi

# Image folders: make sure anything uploaded since last time is world-readable.
echo "  fixing image permissions"
ssh "$SSH_TARGET" "
  for d in gallery profile-images post-images; do
    if [ -d $REMOTE_WEB/\$d ]; then
      chmod 755 $REMOTE_WEB/\$d
      find $REMOTE_WEB/\$d -type f -exec chmod 644 {} \; 2>/dev/null || true
    fi
  done"

echo "Done. https://$SITE_DOMAIN"
