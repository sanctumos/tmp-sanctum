#!/usr/bin/env bash
# Copy a built Sanctum Share APK into public/downloads/ and refresh checksums + release.json.
# For SanctumOS maintainers and forks that host their own Temp + download page.
#
# Usage:
#   ./tools/publish-apk-to-public.sh [path/to/app.apk] [version_name]
# Defaults: app/build/outputs/apk/debug/app-debug.apk and version from build.gradle.kts.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
ANDROID_DIR="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="$ROOT/public/downloads"
mkdir -p "$OUT_DIR"

APK_SRC="${1:-$ANDROID_DIR/app/build/outputs/apk/debug/app-debug.apk}"
VERSION="${2:-}"
if [[ -z "$VERSION" ]]; then
  VERSION="$(grep -oE 'versionName[[:space:]]*=[[:space:]]*"[^"]+"' "$ANDROID_DIR/app/build.gradle.kts" | head -1 | cut -d'"' -f2 || true)"
  VERSION="${VERSION:-0.0.0}"
fi
BUILD_TYPE="debug"
if [[ "$APK_SRC" == *"/release/"* ]]; then
  BUILD_TYPE="release"
fi

if [[ ! -f "$APK_SRC" ]]; then
  echo "APK not found: $APK_SRC" >&2
  echo "Build first: ./tools/assemble-debug-on-termux.sh  (or gradlew :app:assembleDebug)" >&2
  exit 1
fi

VERSIONED="sanctum-share-${VERSION}.apk"
LATEST="sanctum-share-latest.apk"
cp -f "$APK_SRC" "$OUT_DIR/$VERSIONED"
cp -f "$APK_SRC" "$OUT_DIR/$LATEST"
chmod 644 "$OUT_DIR/$VERSIONED" "$OUT_DIR/$LATEST"

HASH="$(sha256sum "$OUT_DIR/$VERSIONED" | awk '{print $1}')"
cat > "$OUT_DIR/SHA256SUMS" <<EOF
# Sanctum Share published APKs — verify with: sha256sum -c SHA256SUMS
# Also published in git: public/downloads/SHA256SUMS
${HASH}  ${VERSIONED}
${HASH}  ${LATEST}
EOF

REPO_URL="${SANCTUM_SHARE_SOURCE_REPO:-https://github.com/sanctumos/tmp-sanctum}"
PACKAGE_ID="$(grep -oE 'applicationId[[:space:]]*=[[:space:]]*"[^"]+"' "$ANDROID_DIR/app/build.gradle.kts" | head -1 | cut -d'"' -f2 || echo org.sanctumos.share)"
VERSION_CODE="$(grep -oE 'versionCode[[:space:]]*=[[:space:]]*[0-9]+' "$ANDROID_DIR/app/build.gradle.kts" | head -1 | awk '{print $NF}' || echo 1)"
PUBLISHED_AT="$(date -u +%Y-%m-%d)"

python3 -c "
import json
from pathlib import Path
path = Path(r'''$OUT_DIR''') / 'release.json'
data = {
  'product': 'Sanctum Share',
  'package_id': r'''$PACKAGE_ID''',
  'version_name': r'''$VERSION''',
  'version_code': int('''$VERSION_CODE''' or 1),
  'build_type': r'''$BUILD_TYPE''',
  'apk': r'''$LATEST''',
  'apk_versioned': r'''$VERSIONED''',
  'sha256_file': 'SHA256SUMS',
  'sha256': r'''$HASH''',
  'source_repo': r'''$REPO_URL''',
  'source_path': 'android/',
  'published_at': r'''$PUBLISHED_AT''',
}
path.write_text(json.dumps(data, indent=2) + '\n')
print('Wrote', path)
print('sha256', r'''$HASH''')
print('files', r'''$VERSIONED''', r'''$LATEST''')
"

echo "Next: commit public/downloads/ and push so the site sync picks up /app/ metadata + APK."
