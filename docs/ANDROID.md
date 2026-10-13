# Sanctum Share — Android app

Share-sheet client for this Sanctum Temp API. Lives in **`android/`** in the same repo as the PHP host so one clone can run the server *and* build the APK.

**PRD (DSC Tasks):** [Doc #1514](https://tasks.decisionsciencecorp.com/admin/doc.php?id=1514)  
**Branding swap:** [`BRANDING.md`](BRANDING.md)

---

## What it does

- Appears in Android **Share** (`ACTION_SEND` / `ACTION_SEND_MULTIPLE`) for `*/*` and text
- Uploads with `POST {base}/api/v1/upload.php`
  - one file → multipart field `file`
  - many files → multipart `files[]` (max 20, same as server)
- Shows **view URL** + **direct_url**; copy / open
- **Settings:** configurable Sanctum Temp base URL (default `https://tmp.sanctumos.org`)
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
| Open result | `data.url` / `data.direct_url` |

Full HTTP reference: [`API.md`](API.md). Optional QR studio (`POST /api/v1/qr.php`) is **not** required for MVP share uploads.

---

## Smoke test

1. Install debug APK.
2. Gallery → Share → **Sanctum Share**.
3. Confirm result shows `direct_url`.
4. `curl -fsSI "$direct_url"` → `200` and a sensible `Content-Type`.
5. Settings → change base to another Temp host → share again → host in URLs matches.

---

## License

Same as the host project (see repo root). App sources are part of `sanctumos/tmp-sanctum`.
