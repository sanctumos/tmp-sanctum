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
   - `public/dl/` (or `html/dl/` after deploy)
   - `logs/`
6. **Cron purge:** `*/15 * * * * php /var/www/tmp.sanctumos.org/tools/purge-expired.php` (adjust path to deployed tree).
7. **Smoke:**
   - `curl -fsS -F "file=@/etc/hostname" https://tmp.sanctumos.org/api/v1/upload.php` → JSON `"status":"success"`.
   - `curl -fsSI "<direct_url from JSON>"` → `200` and `image/*` or `text/plain`.

## Suggested `sites/tmp.sanctumos.org.env`

| Key | Value |
|-----|--------|
| `REPO` | `actuallyrizzn/tmp-sanctum` or `sanctumos/tmp-sanctum` (after repo created) |
| `BRANCH` | `main` |
| `SRC_DIR` | repo root |
| `WEB_ROOT` | `/var/www/tmp.sanctumos.org/html` |
| `DB_PARENT` | `/var/www/tmp.sanctumos.org` |
| `WWW_USER` | `www-data` |

## Server `config.local.php`

Copy `public/includes/config.example.php` → `public/includes/config.local.php` on server:

```php
<?php
return [
    'base_url' => 'https://tmp.sanctumos.org',
];
```

Repo: https://github.com/sanctumos/tmp-sanctum
