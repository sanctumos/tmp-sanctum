# Multihost provision: tmp.sanctumos.org

Handoff checklist for Ada (full provisioning per multihost git-sync pattern).

## Outcomes

1. **DNS:** `tmp.sanctumos.org` → multihost (`64.95.10.156`).
2. **HTTPS:** valid certificate; `https://tmp.sanctumos.org/` returns upload form.
3. **Git sync:** `/root/sites/tmp.sanctumos.org.env` exists; `/root/sync.sh tmp.sanctumos.org` succeeds.
4. **Cron:** standard sync/deploy pair for this vhost.
5. **Writable dirs** (outside docroot or under `html` as needed):
   - `db/` (SQLite)
   - `storage/`
   - `html/uploads/` — **published downloads live here** (served statically at `/uploads/{id}/{file}`). This directory MUST persist across deploys; the multihost `deploy.sh` already protects it via `--exclude='uploads/'` + `--ignore-existing`.
   - `logs/`
6. **Cron purge (target the LIVE data root):** the purge script defaults to the repo checkout, so it must be pointed at the deployed data root or it purges the wrong tree:
   ```
   */15 * * * * SANCTUM_TMP_ROOT=/var/www/tmp.sanctumos.org /usr/bin/php /root/repos/tmp.sanctumos.org/tools/purge-expired.php >> /var/www/tmp.sanctumos.org/logs/purge.log 2>&1
   ```
   (`SANCTUM_TMP_ROOT` or an argv path both work; the log line prints the resolved `root=`.)
7. **Smoke:**
   - `curl -fsS -F "file=@/etc/hostname" https://tmp.sanctumos.org/api/v1/upload.php` → JSON `"status":"success"`, `direct_url` under `/uploads/`.
   - `curl -fsSI "<direct_url from JSON>"` → `200` and `image/*` or `text/plain`.
   - **Persistence:** re-run the `direct_url` fetch after the next deploy tick (>2 min) — it must still return `200` (previously downloads were wiped by `rsync --delete` because they were written under `html/dl/`).

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
