# Sanctum Temp — SDK & SMCP

Python + PHP clients and SMCP plugin for [tmp.sanctumos.org](https://tmp.sanctumos.org) (or self-hosted Temp). Covers **upload**, **QR encode**, **encode-or-upload fallback**, and **download**.

HTTP contract: [`API.md`](API.md).

---

## Python SDK (`sanctum_tmp_sdk/`)

Stdlib only (`urllib`) — no `pip install` required beyond the repo checkout.

```bash
cd /path/to/tmp-sanctum
PYTHONPATH=. python3 - <<'PY'
from sanctum_tmp_sdk import SanctumTmpClient

c = SanctumTmpClient()  # or SANCTUM_TMP_BASE_URL
print(c.health())

# File host (agents / Broca / Venice)
up = c.upload_file("photo.png")
print(up["data"]["direct_url"])

# QR-first text (humans / Sanctum Share parity)
out = c.encode_or_upload("example.com")
print(out["sanctum_tmp_mode"], out.get("data", {}))
PY
```

### Methods

| Method | Maps to |
|--------|---------|
| `health()` | `GET /AGENTS.md` |
| `upload_file` / `upload_bytes` / `upload_files` / `upload_text` | `POST /api/v1/upload.php` |
| `encode(...)` | `POST /api/v1/encode.php` — raises `QrCapacityError` on 422 `qr_capacity` |
| `encode_or_upload(content)` | encode → on capacity, `upload_text` |
| `qr_html_fragment(dict)` | `POST /api/v1/qr.php` (HTML; rarely needed) |
| `download(direct_url)` | `GET` bytes |

Env: `SANCTUM_TMP_BASE_URL` (default `https://tmp.sanctumos.org`), optional `SANCTUM_TMP_USER_AGENT`.

### Exceptions

- `QrCapacityError` — fall back to file host
- `ValidationError` — 400 / 413 / other 422
- `APIError` — transport / 5xx / unexpected

---

## PHP SDK (`sdk/php/`)

Composer-autoloaded (`SanctumTmp\Sdk\`) — mirrors the Python method surface (camelCase). Requires PHP 8.1+ and **curl**.

```php
require __DIR__ . '/vendor/autoload.php';

use SanctumTmp\Sdk\SanctumTmpClient;

$c = new SanctumTmpClient(); // or SANCTUM_TMP_BASE_URL
print_r($c->health());

$up = $c->uploadFile('photo.png');
echo $up['data']['direct_url'], "\n";

$out = $c->encodeOrUpload('example.com');
echo $out['sanctum_tmp_mode'], "\n";
```

| Method | Maps to |
|--------|---------|
| `health()` | `GET /AGENTS.md` |
| `uploadFile` / `uploadBytes` / `uploadFiles` / `uploadText` | `POST /api/v1/upload.php` |
| `encode(...)` | `POST /api/v1/encode.php` — throws `QrCapacityException` |
| `encodeOrUpload($content)` | encode → on capacity, `uploadText` |
| `qrHtmlFragment(array)` | `POST /api/v1/qr.php` |
| `download($directUrl)` | `GET` bytes |

Standalone require path: [`sdk/php/README.md`](../sdk/php/README.md).

Exceptions: `QrCapacityException`, `ValidationException`, `ApiException` (under `SanctumTmp\Sdk`).

---

## SMCP plugin (`smcp_plugin/tmp/`)

CLI for agent hosts (same pattern as Tasks / Reps / Invoicing). **No API key** — Temp is a public upload/encode API.

```bash
export SANCTUM_TMP_BASE_URL=https://tmp.sanctumos.org   # optional
python3 smcp_plugin/tmp/cli.py --describe
python3 smcp_plugin/tmp/cli.py health
python3 smcp_plugin/tmp/cli.py upload-file --path ./note.txt
python3 smcp_plugin/tmp/cli.py encode --content 'hello'
python3 smcp_plugin/tmp/cli.py encode-or-upload --content 'https://sanctumos.org'
python3 smcp_plugin/tmp/cli.py tool-help
```

| Command | Purpose |
|---------|---------|
| `health` | Liveness |
| `upload-file` | Multipart file host |
| `upload-text` | Host UTF-8 text as `.txt` |
| `encode` | QR encode JSON API |
| `encode-or-upload` | QR-first with file fallback |
| `download` | GET `direct_url` to a local path |
| `tool-help` | Routing cheat sheet |
| `--describe` | SMCP tool schema JSON |

Wire into an SMCP host by pointing a plugin symlink/entry at `smcp_plugin/tmp/cli.py` (same as other Sanctum products).

---

## Product alignment

| Client | Text / short payload | Files / agents |
|--------|----------------------|----------------|
| Website `/` | QR tab / soft push ≤3500 B | Files tab → `upload.php` |
| Sanctum Share (Android) | Local QR + refine `encode.php`; **Do file instead** | `upload.php` + results QR of API JSON |
| Python SDK / SMCP | `encode` / `encode_or_upload` | `upload_*` + `download` |
| PHP SDK | `encode` / `encodeOrUpload` | `upload*` + `download` |
| Broca | — | `upload.php` only (`ephemeral_upload.py`) |

---

## Tests

```bash
# PHP (server + SDK behavioral stubs)
composer install && ./vendor/bin/phpunit

# Python SDK (mocked HTTP)
python3 -m unittest discover -s tests/python -v
```
