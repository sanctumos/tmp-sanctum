# tmp.sanctumos.org — agent notes

Sanctum ephemeral file exchange. Hostname: **`tmp.sanctumos.org`**.

## When to use this host

- Short-lived uploads for **Broca → Athena** image attachments (Venice / `image_interpret` need a **GET**-able URL).
- Fallback when **tmpfiles.org** direct URLs fail (HTML interstitial instead of raw bytes).
- Human **desktop → phone** handoff: browser `/` defaults to Files; small text-like picks open the QR tab (ephemeral encode; optional persist / host-as-file).
- Full branded QR studio remains at **qr.decisionsciencecorp.com** (not replaced).

## What agents should call

| Task | Method | Path |
|------|--------|------|
| Upload bytes | `POST` | `/api/v1/upload.php` |
| Download bytes | `GET` | value of `data.direct_url` from upload JSON |
| QR encode (humans/studio) | `POST` | `/api/v1/encode.php` (optional; not for Broca file drops) |

Do **not** scrape the HTML home page or view URLs for automation. Use the API path and **`direct_url`**.

## Upload contract

- Multipart field name: **`file`**
- Response: JSON (`status`, `data.url`, `data.direct_url`, `data.id`, `data.filename`)
- Prefer User-Agent: `Sanctum-Broca/1.0 (ephemeral upload)` (matches workspace `ephemeral_upload.py`)

## Broca workspace default

`projects/broca/common/ephemeral_upload.py` — primary upload URL:

`https://tmp.sanctumos.org/api/v1/upload.php` (`SANCTUM_TMP_UPLOAD_URL` optional override).

## Plain discovery (no HTML UI)

Automated clients hitting **`GET /`** receive **plain text** pointers, not the branded web UI.

- API reference (markdown): **`GET /docs/API.md`**
- This file over HTTP: **`GET /AGENTS.md`**

Full API detail: [`docs/API.md`](docs/API.md) in the repo.

## Android share client

**Sanctum Share** lives in [`android/`](android/) — buildable from this clone. Docs: [`docs/ANDROID.md`](docs/ANDROID.md). Rebrand web + app: [`docs/BRANDING.md`](docs/BRANDING.md).

## Repo layout

| Path | Role |
|------|------|
| `public/api/v1/upload.php` | Upload endpoint |
| `public/index.php` | Browser router + view pages |
| `src/UploadService.php` | Single-copy storage under uploads/ + SQLite metadata |
| `tools/purge-expired.php` | Cron TTL purge |
| `tools/orphan-sweep.php` | Cron orphan dir cleanup |
| `tools/usage-report.php` | usage_events rollup CLI |

Deploy: multihost git-sync; **`composer install --no-dev`** at repo root (QR on browser uploads). See `docs/DEPLOY-multihost.md`.

## Limits

Default max upload **100 MiB**; nginx must set `client_max_body_size 100M;` and PHP `upload_max_filesize` accordingly (see `public/.user.ini`).
