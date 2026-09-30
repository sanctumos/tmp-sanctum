# Multihost provision: tmp.sanctumos.org

Handoff checklist for Ada (full provisioning per multihost git-sync pattern).

## Outcomes

1. **DNS:** `tmp.sanctumos.org` → multihost (`64.95.10.156`).
2. **HTTPS:** valid certificate; `https://tmp.sanctumos.org/` returns upload form.
3. **Upload size limits (413 fix):** nginx vhost must set `client_max_body_size 100M;` (default 1M causes 413 before PHP). Docroot includes `public/.user.ini` (`upload_max_filesize` / `post_max_size` ≥ 100M) for PHP-FPM.
3b. **Shortlink routing:** `location /` MUST use `try_files $uri $uri/ /index.php?$query_string;` (not `=404`). Stock `=404` returns nginx 404 for `/{id}/{filename}` before PHP can render the HTML view page. Keep `location ^~ /uploads/` static-only (`try_files $uri =404;`, no PHP).
4. **Git sync:** `/root/sites/tmp.sanctumos.org.env` exists; `/root/sync.sh tmp.sanctumos.org` succeeds.
4. **Cron:** standard sync/deploy pair for this vhost.
5. **Writable dirs** (outside docroot or under `html` as needed):
   - `db/` (SQLite)
   - `storage/` — **legacy only**. New uploads no longer write here; keep writable so orphan sweep / purge can remove leftover `{id}/` trees from the dual-write era.
   - `html/uploads/` — **the single copy of bytes** (served statically at `/uploads/{id}/{file}`). This directory MUST persist across deploys; the multihost `deploy.sh` already protects it via `--exclude='uploads/'` + `--ignore-existing`.
   - `logs/`
6. **Cron purge (target the LIVE data root):** the purge script defaults to the repo checkout, so it must be pointed at the deployed data root or it purges the wrong tree:
   ```
   */15 * * * * SANCTUM_TMP_ROOT=/var/www/tmp.sanctumos.org /usr/bin/php /root/repos/tmp.sanctumos.org/tools/purge-expired.php >> /var/www/tmp.sanctumos.org/logs/purge.log 2>&1
   ```
   (`SANCTUM_TMP_ROOT` or an argv path both work; the log line prints the resolved `root=`.)
6b. **Cron orphan sweep (hourly):** scans `html/uploads/` and legacy `storage/` for id directories with no live `uploads` row, logs `usage_events.event=orphan_sweep`, then deletes the tree. Ada/Otto should add (do not invent a custom Ada cron tool — document + add via host crontab):
   ```
   0 * * * * SANCTUM_TMP_ROOT=/var/www/tmp.sanctumos.org /usr/bin/php /root/repos/tmp.sanctumos.org/tools/orphan-sweep.php >> /var/www/tmp.sanctumos.org/logs/orphan-sweep.log 2>&1
   ```
6c. **Usage rollup (on demand):**
   ```
   SANCTUM_TMP_ROOT=/var/www/tmp.sanctumos.org /usr/bin/php /root/repos/tmp.sanctumos.org/tools/usage-report.php --days=7
   ```
7. **Smoke:**
   - `curl -fsS -F "file=@/etc/hostname" https://tmp.sanctumos.org/api/v1/upload.php` → JSON `"status":"success"`, `direct_url` under `/uploads/`.
   - After upload: bytes exist under `html/uploads/{id}/` only — **not** a twin under `storage/{id}/`.
   - Browser upload at `https://tmp.sanctumos.org/` → HTML result page with QR encoding the same JSON.
   - `curl -fsSI "<direct_url from JSON>"` → `200` and `image/*` or `text/plain`.
   - **Persistence:** re-run the `direct_url` fetch after the next deploy tick (>2 min) — it must still return `200` (previously downloads were wiped by `rsync --delete` because they were written under `html/dl/`).

## Migration note (single-copy)

Existing dual-write leftovers under `storage/` can remain until `orphan-sweep.php` (or TTL purge for still-tracked rows) removes them. New uploads set `storage_rel = dl_rel` and write only to `html/uploads/`.
## Suggested `sites/tmp.sanctumos.org.env`

| Key | Value |
|-----|--------|
| `REPO` | `actuallyrizzn/tmp-sanctum` or `sanctumos/tmp-sanctum` (after repo created) |
| `BRANCH` | `main` |
| `SRC_DIR` | repo root |
| `WEB_ROOT` | `/var/www/tmp.sanctumos.org/html` |
| `DB_PARENT` | `/var/www/tmp.sanctumos.org` |
| `WWW_USER` | `www-data` |

## Server `config.local.php` (deploy-safe location)

Place the override **one directory above the docroot** so `rsync --delete` never
removes it: `/var/www/tmp.sanctumos.org/config.local.php`

```php
<?php
return [
    'base_url' => 'https://tmp.sanctumos.org',
    'ttl_seconds' => 3600, // 60 minutes
];
```

Config search order: `<root>/config.local.php` (preferred, survives deploy) →
`html/includes/config.local.php` → `public/includes/config.local.php`. An
in-docroot `config.local.php` still works but can be overwritten by a deploy.

Repo: https://github.com/sanctumos/tmp-sanctum
