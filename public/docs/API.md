# tmp.sanctumos.org — HTTP API

Ephemeral file host for Sanctum. **tmpfiles.org-compatible** upload responses; direct downloads are plain `GET` bytes.

Base URL: `https://tmp.sanctumos.org`

## Upload

**`POST /api/v1/upload.php`**

| Part | Name | Required |
|------|------|----------|
| multipart | `file` | yes |

**Success (200)** — `Content-Type: application/json`

```json
{
  "status": "success",
  "data": {
    "url": "https://tmp.sanctumos.org/{id}/{filename}",
    "direct_url": "https://tmp.sanctumos.org/uploads/{id}/{filename}",
    "id": "{id}",
    "filename": "{filename}"
  }
}
```

Use **`direct_url`** for bots, Venice vision, Broca, and other clients that need raw bytes via `GET`.

**Errors** — JSON body, non-2xx status:

```json
{"status":"error","message":"…"}
```

Common cases: missing `file` (400), empty upload (500), over size limit (500), nginx body limit (413).

### Example (curl)

```bash
curl -fsS -F "file=@./photo.png" https://tmp.sanctumos.org/api/v1/upload.php
```

Recommended User-Agent for Sanctum automation: `Sanctum-Broca/1.0 (ephemeral upload)`.

## Direct download

**`GET /uploads/{id}/{filename}`**

Returns file bytes. `Content-Type` is set from upload metadata.

Legacy alias: **`GET /dl/{id}/{filename}`** (same behavior).

## View page (humans)

**`GET /{id}/{filename}`**

HTML metadata and download link. Prefer **`direct_url`** for programmatic access.

## Limits (defaults)

| Setting | Default |
|---------|---------|
| Max file size | 100 MiB |
| TTL | 72 hours (259200 s) |

Override on server via `config.local.php` or env (`SANCTUM_TMP_MAX_BYTES`, `SANCTUM_TMP_TTL_SECONDS`).

## Browser upload

**`POST /`** with multipart `file` returns an HTML result page (links + QR code with the JSON above). API clients should use **`/api/v1/upload.php`** only.

## Related

- Agent / integration notes: [`/AGENTS.md`](https://tmp.sanctumos.org/AGENTS.md)
- Human-readable API page: [`/docs/api`](https://tmp.sanctumos.org/docs/api)
