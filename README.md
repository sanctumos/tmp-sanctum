# tmp-sanctum

Temporary file host for Sanctum (`tmp.sanctumos.org`) — **tmpfiles.org-compatible** upload API and **GET** direct downloads at `/dl/{id}/{filename}`.

## Purpose

- Public ephemeral uploads (Telegram → Broca → Athena image URLs)
- Fallback when `tmpfiles.org` is unavailable
- Venice / `image_interpret` need **GET**-able raw file URLs (not POST-only hosts)

## API (tmpfiles-compatible)

**Upload:** `POST /api/v1/upload.php` — multipart field `file` → JSON.

**Browser upload:** `POST /` via the home form → HTML result page (tmpfiles-style) with links and a **QR code** encoding the same JSON (scan on phone to avoid OCR).

```json
{"status":"success","data":{"url":"https://tmp.sanctumos.org/{id}/{filename}","direct_url":"https://tmp.sanctumos.org/uploads/{id}/{filename}","id":"…","filename":"…"}}
```

**Direct download:** `GET /dl/{id}/{filename}` — raw bytes, `Content-Type` from upload.

**View page:** `GET /{id}/{filename}` — HTML metadata + download link.

## Layout

| Path | Role |
|------|------|
| `public/` | Web docroot |
| `AGENTS.md` | Agent integration notes (also at `/AGENTS.md`) |
| `docs/API.md` | HTTP API reference (browser: `/docs/api/`, plain: `/docs/API.md`) |
| `public/index.php` | Home + router (view URLs) |
| `public/api/v1/upload.php` | Upload API |
| `public/dl/` | Published files (GET direct) |
| `db/` | SQLite metadata |
| `storage/` | Canonical file storage (outside direct URL if duplicated) |
| `tools/purge-expired.php` | Delete expired uploads (cron) |

## Configuration

Copy `public/includes/config.example.php` → `config.local.php` on the server (gitignored) or set env:

| Variable | Default |
|----------|---------|
| `SANCTUM_TMP_TTL_SECONDS` | `259200` (3 days) |
| `SANCTUM_TMP_MAX_BYTES` | `104857600` (100 MiB) |
| `SANCTUM_TMP_BASE_URL` | `https://tmp.sanctumos.org` |

## Tests

```bash
composer install
./vendor/bin/phpunit
```

## Deploy (multihost)

Ada provisions `tmp.sanctumos.org` with standard LEMP sync (`sites/tmp.sanctumos.org.env`, `sync.sh`, cron). Docroot = `public/`. Run `composer install --no-dev` at the **repo root** (not only `public/`) so QR generation works. Ensure `public/dl/`, `storage/`, `db/`, `logs/` are writable by PHP-FPM user.

Cron (example, root crontab):

```cron
*/15 * * * * cd /var/www/tmp.sanctumos.org && /usr/bin/php tools/purge-expired.php >> logs/purge.log 2>&1
```

## Broca

Set `SANCTUM_TMP_UPLOAD_URL=https://tmp.sanctumos.org/api/v1/upload.php` (optional; default). Upload helper tries tmpfiles.org first, then this host.
