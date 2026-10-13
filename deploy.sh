#!/bin/bash
# Multihost deploy: mirror public/ to vhost docroot (same pattern as sibling Sanctum sites).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
WEB_ROOT="${WEB_ROOT:-/var/www/tmp.sanctumos.org/html}"
DEPLOY_ROOT="${DEPLOY_ROOT:-$(dirname "$WEB_ROOT")}"
mkdir -p "$WEB_ROOT" "$DEPLOY_ROOT/docs"
rsync -a --delete \
  --exclude 'includes/config.local.php' \
  "$ROOT/public/" "$WEB_ROOT/"
cp -f "$ROOT/AGENTS.md" "$DEPLOY_ROOT/AGENTS.md" 2>/dev/null || cp -f "$ROOT/public/AGENTS.md" "$DEPLOY_ROOT/AGENTS.md"
for doc in API.md ANDROID.md BRANDING.md; do
  if [ -f "$ROOT/docs/$doc" ]; then
    cp -f "$ROOT/docs/$doc" "$DEPLOY_ROOT/docs/$doc"
  elif [ -f "$ROOT/public/docs/$doc" ]; then
    cp -f "$ROOT/public/docs/$doc" "$DEPLOY_ROOT/docs/$doc"
  fi
done

mkdir -p "$ROOT/db" "$ROOT/storage" "$ROOT/logs" "$WEB_ROOT/dl"
if [ ! -e "$DEPLOY_ROOT/vendor" ] && [ -d "$ROOT/vendor" ]; then
  ln -sfn "$ROOT/vendor" "$DEPLOY_ROOT/vendor" 2>/dev/null || true
fi
chmod 755 "$ROOT/db" "$ROOT/storage" "$ROOT/logs" "$ROOT/public/dl" 2>/dev/null || true
echo "Deployed public/ -> $WEB_ROOT (config/db/storage remain outside docroot on server)"
