# tmp-sanctum

Temporary file host for Sanctum (`tmp.sanctumos.org`) — **tmpfiles.org-compatible** upload API and **GET** direct downloads at `/dl/{id}/{filename}`.

## Purpose

- Public ephemeral uploads (Telegram → Broca → Athena image URLs)
- Fallback when `tmpfiles.org` is unavailable
- Venice / `image_interpret` need **GET**-able raw file URLs (not POST-only hosts)

## API (tmpfiles-compatible)

**Upload:** `POST /api/v1/upload.php` — multipart field `file` → JSON.

**Browser:** `/` Files tab by default (short-lived upload). A single small text-like file is offered the QR / paste tab instead. QR path = try-encode → ephemeral (optional persist). **API upload** unchanged for agents.

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
| `public/uploads/` (or `html/uploads/` on multihost) | **Single copy** of uploaded bytes (`/uploads/{id}/{file}`) |
| `db/` | SQLite metadata + durable `usage_events` |
| `storage/` | Legacy only — no longer written on upload; orphan sweeper cleans leftovers |
| `tools/purge-expired.php` | Delete expired uploads (cron) |
| `tools/orphan-sweep.php` | Delete id dirs with no live DB row; log `orphan_sweep` |
| `tools/usage-report.php` | Rollup from `usage_events` (`--days=7`) |

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

Ada provisions `tmp.sanctumos.org` with standard LEMP sync (`sites/tmp.sanctumos.org.env`, `sync.sh`, cron). Docroot = `public/` (deployed as `html/`). Run `composer install --no-dev` at the **repo root** (not only `public/`) so QR generation works. Ensure `html/uploads/`, `db/`, `logs/` are writable by PHP-FPM user. `storage/` may remain for sweeping legacy orphans.

Cron (example, root crontab — always set `SANCTUM_TMP_ROOT` to the live data root):

```cron
*/15 * * * * SANCTUM_TMP_ROOT=/var/www/tmp.sanctumos.org /usr/bin/php /root/repos/tmp.sanctumos.org/tools/purge-expired.php >> /var/www/tmp.sanctumos.org/logs/purge.log 2>&1
0 * * * * SANCTUM_TMP_ROOT=/var/www/tmp.sanctumos.org /usr/bin/php /root/repos/tmp.sanctumos.org/tools/orphan-sweep.php >> /var/www/tmp.sanctumos.org/logs/orphan-sweep.log 2>&1
```

Usage rollup:

```bash
SANCTUM_TMP_ROOT=/var/www/tmp.sanctumos.org php tools/usage-report.php --days=7
```
## Broca

Set `SANCTUM_TMP_UPLOAD_URL=https://tmp.sanctumos.org/api/v1/upload.php` (optional; default). Upload helper tries tmpfiles.org first, then this host.
