# tmp.sanctumos.org — agent notes

Sanctum ephemeral file exchange. Hostname: **`tmp.sanctumos.org`**.

## When to use this host

- Short-lived uploads for **Broca → Athena** image attachments (Venice / `image_interpret` need a **GET**-able URL).
- Fallback when **tmpfiles.org** direct URLs fail (HTML interstitial instead of raw bytes).
- Human **desktop → phone** handoff: browser `/` defaults to Files; small text-like picks open the QR tab (ephemeral encode; optional persist / host-as-file).
- **Sanctum Share** (Android): text → QR-first; files → upload + results QR of API JSON.
- Full branded QR studio remains at **qr.decisionsciencecorp.com** (not replaced).

## What agents should call

| Task | Method | Path |
|------|--------|------|
| Upload bytes | `POST` | `/api/v1/upload.php` |
| Download bytes | `GET` | value of `data.direct_url` from upload JSON |
| QR encode (humans / text handoff) | `POST` | `/api/v1/encode.php` |
| QR HTML fragment (browser results) | `POST` | `/api/v1/qr.php` (optional) |

Do **not** scrape the HTML home page or view URLs for automation. Use the API path and **`direct_url`**.

Broca / Venice attachment drops: **upload only** — do not use `encode.php`.

## Upload contract

- Multipart field name: **`file`** (batch: **`files[]`**)
- Response: JSON (`status`, `data.url`, `data.direct_url`, `data.id`, `data.filename`)
- Prefer User-Agent: `Sanctum-Broca/1.0 (ephemeral upload)` (matches workspace `ephemeral_upload.py`)

## QR encode contract (summary)

- JSON body: `content`, optional `type` (`auto`|`url`|`email`|`tel`|`text`), `format` (`svg`|`png`), `persist`
- Success: `data.mode=qr`, `data.encoded`, `data.data_uri` / `data_base64`
- Too large: **422** + `"code":"qr_capacity"` → fall back to **`upload.php`** as text/file
- Soft UI candidate size (website + Android): **≤ 3500 bytes** text-like

Full detail: [`docs/API.md`](docs/API.md).

## SDK & SMCP

| Piece | Path |
|-------|------|
| Python SDK | [`sanctum_tmp_sdk/`](sanctum_tmp_sdk/) |
| SMCP plugin | [`smcp_plugin/tmp/`](smcp_plugin/tmp/) |
| Docs | [`docs/SDK.md`](docs/SDK.md) |

```bash
PYTHONPATH=. python3 -c 'from sanctum_tmp_sdk import SanctumTmpClient; print(SanctumTmpClient().health())'
python3 smcp_plugin/tmp/cli.py --describe
python3 smcp_plugin/tmp/cli.py encode-or-upload --content 'hello'
```

Env: `SANCTUM_TMP_BASE_URL` (optional). No API key.

## Broca workspace default

`projects/broca/common/ephemeral_upload.py` — primary upload URL:

`https://tmp.sanctumos.org/api/v1/upload.php` (`SANCTUM_TMP_UPLOAD_URL` optional override).

## Plain discovery (no HTML UI)

Automated clients hitting **`GET /`** receive **plain text** pointers, not the branded web UI.

- API reference (markdown): **`GET /docs/API.md`**
- This file over HTTP: **`GET /AGENTS.md`**
- SDK notes: **`GET /docs/SDK.md`** (when deployed from `docs/` / public docs routes)

## Android share client

**Sanctum Share** lives in [`android/`](android/) — buildable from this clone. Docs: [`docs/ANDROID.md`](docs/ANDROID.md). Rebrand web + app: [`docs/BRANDING.md`](docs/BRANDING.md).

## Repo layout

| Path | Role |
|------|------|
| `public/api/v1/upload.php` | Upload endpoint |
| `public/api/v1/encode.php` | QR encode endpoint |
| `public/api/v1/qr.php` | Results QR HTML fragment |
| `public/index.php` | Browser router + view pages |
| `sanctum_tmp_sdk/` | Python SDK |
| `smcp_plugin/tmp/` | SMCP CLI plugin |
| `src/UploadService.php` | Single-copy storage under uploads/ + SQLite metadata |
| `tools/purge-expired.php` | Cron TTL purge |
| `tools/orphan-sweep.php` | Cron orphan dir cleanup |
| `tools/usage-report.php` | usage_events rollup CLI |

Deploy: multihost git-sync; **`composer install --no-dev`** at repo root (QR on browser uploads). See `docs/DEPLOY-multihost.md`.

## Limits

Default max upload **100 MiB**; nginx must set `client_max_body_size 100M;` and PHP `upload_max_filesize` accordingly (see `public/.user.ini`).
