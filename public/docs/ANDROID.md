# Sanctum Share — Android app

Share-sheet client for this Sanctum Temp API. Lives in **`android/`** in the same repo as the PHP host so one clone can run the server *and* build the APK.

**PRD (DSC Tasks):** [Doc #1514](https://tasks.decisionsciencecorp.com/admin/doc.php?id=1514)  
**Branding swap:** [`BRANDING.md`](BRANDING.md)

---

## What it does

- Appears in Android **Share** (`ACTION_SEND` / `ACTION_SEND_MULTIPLE`) for `*/*` and text
- **Text / small text-like shares (default):** QR-first via `POST {base}/api/v1/encode.php` (same soft ≤3500‑byte rule as the website). Shows a scannable QR + **Host as file instead** override. Too large for a QR → auto-falls back to upload.
- **Files:** Uploads with `POST {base}/api/v1/upload.php`
  - one file → multipart field `file`
  - many files → multipart `files[]` (max 20, same as server)
- Shows **view URL** + **direct_url**; copy / open
- **Settings:** Sanctum Temp base URL (default `https://tmp.sanctumos.org`) + **Prefer QR for shared text** (default on)
- Home: pick files + recent share history

Package ID: `org.sanctumos.share`  
UA: `Sanctum-Share/{versionName} (Android)`

---

## Layout in this repo

```
android/
  app/src/main/…          Kotlin + resources
  tools/assemble-debug-on-termux.sh
  tools/bootstrap-termux-android-sdk.sh
  README.md
branding/tokens.json      Shared SanctumOS color/name tokens
docs/ANDROID.md           This file
docs/BRANDING.md          Web + app rebrand guide
```

---

## Build

### Requirements

- JDK **17+**
- Android SDK platform **34** + build-tools **34.0.0**
- On **aarch64 Termux/Proot:** use the shipped assemble script (patches aapt2)

### Termux / Proot aarch64 (phone)

```bash
export JAVA_HOME=/opt/jdk17-temurin   # or your JDK 17+
export PATH="$JAVA_HOME/bin:$PATH"
cd android
./tools/assemble-debug-on-termux.sh
# → app/build/outputs/apk/debug/app-debug.apk
```

Install: copy APK to Downloads and open, or `termux-open app/build/outputs/apk/debug/app-debug.apk`.

### Desktop / Android Studio

```bash
cd android
cp local.properties.example local.properties
# set sdk.dir=
./gradlew :app:assembleDebug
```

---

## Configure endpoint

In-app **Settings** → Sanctum Temp base URL.

Examples:

- `https://tmp.sanctumos.org` (default)
- `https://tmp.example.com` (self-hosted)

Trailing slashes are stripped. Upload path is always `{base}/api/v1/upload.php`.

---

## API surface used

| Call | Path |
|------|------|
| Upload | `POST /api/v1/upload.php` |
| QR encode (text path) | `POST /api/v1/encode.php` |
| Open result | `data.url` / `data.direct_url` |

Full HTTP reference: [`API.md`](API.md). Text shares use **encode.php** (QR-first); agents and binary shares keep using **upload.php**.

---

## Smoke test

1. Install debug APK.
2. Gallery → Share → **Sanctum Share**.
3. Confirm result shows `direct_url`.
4. `curl -fsSI "$direct_url"` → `200` and a sensible `Content-Type`.
5. Settings → change base to another Temp host → share again → host in URLs matches.

---

## Public download page (tmp.sanctumos.org)

Live install instructions for end users: **`/app/`** (also `/download/`).

Footer link on every HTML page: **Get the app**.

Hosted artifacts (deployed with `public/`):

| Path | Role |
|------|------|
| `public/downloads/sanctum-share-latest.apk` | Stable download URL |
| `public/downloads/sanctum-share-<version>.apk` | Versioned copy |
| `public/downloads/SHA256SUMS` | SHA-256 list (also the integrity source of truth in git) |
| `public/downloads/release.json` | Version metadata for the `/app/` page |

### Verify a download (security-conscious)

1. Download the APK from `/downloads/sanctum-share-latest.apk`.
2. `sha256sum sanctum-share-latest.apk` (or `sha256sum -c SHA256SUMS`).
3. Compare to **`public/downloads/SHA256SUMS` in this GitHub repo** — not only the copy on the website. If site and git disagree, do not install.
4. Rebuilding from source yourself is encouraged for audit; the hash of a local rebuild may differ from the published APK (timestamps / signing). The published SHA-256 authenticates the **published** binary.

### Publish a new APK (maintainers & forks)

```bash
cd android
./tools/assemble-debug-on-termux.sh   # or ./gradlew :app:assembleDebug / assembleRelease
./tools/publish-apk-to-public.sh      # copies into public/downloads/, rewrites SHA256SUMS + release.json
# commit + push public/downloads/  → multihost sync serves /app/ and the new file
```

Optional env for forks: `SANCTUM_SHARE_SOURCE_REPO=https://github.com/you/your-tmp-fork`.

Also update `Prefs.DEFAULT_BASE` (and branding — see [`BRANDING.md`](BRANDING.md)) if your fork should default to *your* Temp host.

---

## Customize for a fork (checklist)

1. **Brand** — [`BRANDING.md`](BRANDING.md): `site.json`, `brand.css`, Android `colors.xml` / `strings.xml` / mark.
2. **Default host** — `android/.../Prefs.kt` → `DEFAULT_BASE`.
3. **Package id** (optional) — `applicationId` in `android/app/build.gradle.kts` (new id = clean install, not an upgrade).
4. **Build + publish** — `publish-apk-to-public.sh`, commit `public/downloads/`.
5. **Ship site** — deploy `public/` so `/app/` and `/downloads/*.apk` are live on your domain.

---

## License

Same as the host project (see repo root). App sources are part of `sanctumos/tmp-sanctum`.
