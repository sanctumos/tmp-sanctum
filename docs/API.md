# tmp.sanctumos.org — HTTP API

Ephemeral file host for Sanctum. **tmpfiles.org-compatible** upload responses; direct downloads are plain `GET` bytes.

Base URL: `https://tmp.sanctumos.org`

## Upload

**`POST /api/v1/upload.php`**

### Single file (default)

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

### Multi-file batch (opt-in)

Send multiple files in one request using the **`files[]`** multipart field (or **`file[]`** with `?multi=1` / header `X-Sanctum-Tmp-Multi: 1`). Existing single-`file` callers are unchanged.

| Part | Name | Required |
|------|------|----------|
| multipart | `files[]` | yes (one part per file) |

**Success (200)** when all succeed; **207** when some fail; **500** when all fail.

```json
{
  "status": "success",
  "data": {
    "count": 2,
    "succeeded": 2,
    "failed": 0,
    "files": [
      {
        "status": "success",
        "url": "https://tmp.sanctumos.org/{id}/{filename}",
        "direct_url": "https://tmp.sanctumos.org/uploads/{id}/{filename}",
        "id": "{id}",
        "filename": "{filename}"
      }
    ]
  }
}
```

Per-file errors include `"status": "error"`, `"filename"`, and `"message"`. Batch size is capped (default **20** files) via `max_batch_size` in `config.local.php` or `SANCTUM_TMP_MAX_BATCH_SIZE`.

**Errors** — JSON body, non-2xx status:

```json
{"status":"error","message":"…"}
```

Common cases: missing `file` (400), empty upload (500), over size limit (500), nginx body limit (413).

### Example (curl)

```bash
curl -fsS -F "file=@./photo.png" https://tmp.sanctumos.org/api/v1/upload.php
```

Multi-file batch:

```bash
curl -fsS -F "files[]=@./a.png" -F "files[]=@./b.txt" https://tmp.sanctumos.org/api/v1/upload.php
```

Recommended User-Agent for Sanctum automation: `Sanctum-Broca/1.0 (ephemeral upload)`.

## Direct download

**`GET /uploads/{id}/{filename}`**

Returns file bytes. `Content-Type` is set from upload metadata.

Legacy alias: **`GET /dl/{id}/{filename}`** (same behavior).

## View page (humans)

**`GET /{id}/{filename}`**

HTML metadata, download link, and **QR code** (same API JSON as the upload-success page) for desktop→phone handoff. Prefer **`direct_url`** for programmatic access.

## Limits (defaults)

| Setting | Default |
|---------|---------|
| Max file size | 100 MiB |
| TTL | 72 hours (259200 s) |
| Max batch size | 20 files |

Override on server via `config.local.php` or env (`SANCTUM_TMP_MAX_BYTES`, `SANCTUM_TMP_TTL_SECONDS`, `SANCTUM_TMP_MAX_BATCH_SIZE`).

## Browser upload

**`GET /`** — drag-and-drop or multi-select UI uploads files via the single-file API (queued, up to three concurrent). **`POST /`** with multipart `file` or `files[]` returns an HTML result page. API clients should use **`/api/v1/upload.php`**.

## Related

- Agent / integration notes: [`/AGENTS.md`](https://tmp.sanctumos.org/AGENTS.md)
- Human-readable API page: [`/docs/api`](https://tmp.sanctumos.org/docs/api)
