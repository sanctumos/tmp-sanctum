# tmp.sanctumos.org — HTTP API

Ephemeral file host for Sanctum. **tmpfiles.org-compatible** upload responses; direct downloads are plain `GET` bytes. Humans get a Files + QR browser studio; agents and SDKs use the JSON endpoints below.

Base URL: `https://tmp.sanctumos.org`

| Surface | Path | Auth |
|---------|------|------|
| Upload bytes | `POST /api/v1/upload.php` | none (public) |
| QR encode | `POST /api/v1/encode.php` | none (public) |
| Results QR HTML fragment | `POST /api/v1/qr.php` | none (public) |
| Direct download | `GET /uploads/{id}/{filename}` | none |
| View page (HTML + results QR) | `GET /{id}/{filename}` | none |
| Agent discovery (plain) | `GET /` · `GET /AGENTS.md` · `GET /docs/API.md` | none |

**SDKs / SMCP:** Python client in [`sanctum_tmp_sdk/`](../sanctum_tmp_sdk/) · SMCP plugin in [`smcp_plugin/tmp/`](../smcp_plugin/tmp/) · notes in [`docs/SDK.md`](SDK.md).

---

## Which endpoint should I use?

| Goal | Endpoint | Notes |
|------|----------|--------|
| Host a file / image for Broca, Venice, bots | **`upload.php`** | Always use `data.direct_url` for raw `GET` |
| Short text / URL / email / tel as a scannable QR | **`encode.php`** | Default ephemeral; `persist: true` also stores bytes |
| Text too large for a QR | **`upload.php`** as `.txt` | `encode.php` returns `422` + `"code":"qr_capacity"` |
| HTML QR block for an upload JSON blob | **`qr.php`** | Browser results page helper; SDKs usually render QR locally from JSON |
| Android Sanctum Share text | Local QR first, refine via **`encode.php`**, override → **`upload.php`** | Same soft ≤3500‑byte candidate rule as the website |

Agents uploading attachments should **not** call `encode.php` — keep using **`upload.php`**.

---

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
`url` is the human view page (HTML + results QR of this same JSON).

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
curl -fsS -F "file=@./photo.png" \
  -A "Sanctum-Broca/1.0 (ephemeral upload)" \
  https://tmp.sanctumos.org/api/v1/upload.php
```

Multi-file batch:

```bash
curl -fsS -F "files[]=@./a.png" -F "files[]=@./b.txt" \
  https://tmp.sanctumos.org/api/v1/upload.php
```

Recommended User-Agent for Sanctum automation: `Sanctum-Broca/1.0 (ephemeral upload)`.  
Sanctum Share Android uses: `Sanctum-Share/{version} (Android)`.

---

## QR encode

**`POST /api/v1/encode.php`** — JSON body.

Normalizes content (`auto` → url / email / tel / text), then tries to render a QR. On capacity failure returns **422** with `"code":"qr_capacity"` so clients can fall back to **`upload.php`**.

### Request

```json
{
  "content": "example.com",
  "type": "auto",
  "format": "svg",
  "ecl": "M",
  "scale": 5,
  "persist": false,
  "logo_base64": null,
  "logo_size_pct": 22,
  "filename": "payload.txt"
}
```

| Field | Default | Notes |
|-------|---------|--------|
| `content` | (required) | Raw text / URL / email / phone |
| `type` | `auto` | `auto` \| `url` \| `email` \| `tel` \| `text` |
| `format` | `svg` | `svg` or `png` (`png` needs PHP GD on the host; otherwise may fall back to SVG) |
| `ecl` | `M` | QR error correction: `L` `M` `Q` `H` |
| `scale` | `5` | Module scale for raster |
| `persist` | `false` | If `true`, also store encoded payload via upload storage |
| `logo_base64` | `null` | Optional center logo (png/jpeg/…) |
| `logo_size_pct` | `22` | Logo size percent when logo set |
| `filename` | `payload.txt` | Used when `persist` is true |

Soft request ceiling: JSON body **≤ 16 KiB**. QR capacity is roughly **2–3 KiB** of payload after normalize.

Website / Android soft candidate before offering QR: **≤ 3500 bytes** text-like content (server still enforces true capacity).

### Success (200)

```json
{
  "status": "success",
  "data": {
    "mode": "qr",
    "encoded": "https://example.com",
    "type": "url",
    "format": "svg",
    "mime_type": "image/svg+xml",
    "data_base64": "…",
    "data_uri": "data:image/svg+xml;base64,…",
    "width": 29,
    "height": 29,
    "ecl_effective": "M",
    "suggestion": null,
    "suggestion_reason": null,
    "persisted": false
  }
}
```

With `"persist": true`, `data` also includes `url`, `direct_url`, `id`, `filename` (same shape as upload).

### Errors

| HTTP | `code` | Meaning |
|------|--------|---------|
| 400 | — | Missing / invalid JSON |
| 413 | `too_large` | Request body too large to attempt |
| 422 | `empty` | Empty content |
| 422 | `validation` | Normalize failed |
| 422 | `qr_capacity` | Will not fit in a QR — **fall back to upload** |
| 405 | — | Not POST |
| 500 | — | Persist failed after successful encode |

```json
{
  "status": "error",
  "message": "Content will not fit in a QR code; use file hosting instead",
  "code": "qr_capacity",
  "encoded": "…",
  "type": "text"
}
```

### Example (curl)

```bash
curl -fsS -X POST https://tmp.sanctumos.org/api/v1/encode.php \
  -H 'Content-Type: application/json' \
  -d '{"content":"hello from sanctum","type":"auto","format":"svg","persist":false}'
```

QR-first with file fallback (shell sketch):

```bash
resp=$(curl -sS -w '\n%{http_code}' -X POST https://tmp.sanctumos.org/api/v1/encode.php \
  -H 'Content-Type: application/json' \
  -d "{\"content\":$(jq -Rs . <<<"$TEXT")}")
# on 422 qr_capacity → curl -F "file=@note.txt" …/upload.php
```

This endpoint does **not** replace upload for agents. Broca / Venice keep using **`POST /api/v1/upload.php`**.

---

## Results QR fragment (browser helper)

**`POST /api/v1/qr.php`** — JSON body = any JSON object (typically an upload success payload). Returns an **HTML fragment** (`text/html`) with a QR of the canonical encoded payload (same as the upload-success / view pages).

Used by the website results page. App / SDK clients usually render a QR of `upload` JSON locally instead of calling this.

Request body max **8 KiB**.

---

## Direct download

**`GET /uploads/{id}/{filename}`**

Returns file bytes. `Content-Type` is set from upload metadata.

Legacy alias: **`GET /dl/{id}/{filename}`** (same behavior).

---

## View page (humans)

**`GET /{id}/{filename}`**

HTML metadata, download link, and **QR code** (same API JSON as the upload-success page) for desktop→phone handoff. Prefer **`direct_url`** for programmatic access.

---

## Limits (defaults)

| Setting | Default |
|---------|---------|
| Max file size | 100 MiB |
| TTL | 72 hours (259200 s) |
| Max batch size | 20 files |
| QR soft candidate (UI/app) | 3500 bytes text-like |
| Encode JSON body | 16 KiB |
| `qr.php` JSON body | 8 KiB |

Override on server via `config.local.php` or env (`SANCTUM_TMP_MAX_BYTES`, `SANCTUM_TMP_TTL_SECONDS`, `SANCTUM_TMP_MAX_BATCH_SIZE`).

---

## Browser upload

**`GET /`** — Files tab by default; QR / paste tab for encode (ephemeral by default). A single small text-like file chosen on Files is pushed to the QR tab. **`POST /`** with multipart `file` still returns an HTML result page. API clients should use **`/api/v1/upload.php`**.

Automated clients hitting **`GET /`** receive **plain text** pointers (not the branded UI).

---

## Related

- Agent notes: [`/AGENTS.md`](https://tmp.sanctumos.org/AGENTS.md) · repo [`AGENTS.md`](../AGENTS.md)
- SDK + SMCP: [`docs/SDK.md`](SDK.md)
- Android Sanctum Share: [`docs/ANDROID.md`](ANDROID.md)
- Human-readable API page: [`/docs/api`](https://tmp.sanctumos.org/docs/api)
- Full QR studio (unchanged hostname): [qr.decisionsciencecorp.com](https://qr.decisionsciencecorp.com/)
