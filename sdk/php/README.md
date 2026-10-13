# Sanctum Temp — PHP SDK

Full-parity client with the Python `sanctum_tmp_sdk` package (upload + QR encode).

Public API — **no API key**. Default base: `https://tmp.sanctumos.org`.

## Install / load

**Composer (this repo):**

```php
require __DIR__ . '/../../vendor/autoload.php';

use SanctumTmp\Sdk\SanctumTmpClient;

$client = new SanctumTmpClient(); // or SANCTUM_TMP_BASE_URL
```

**Standalone require:**

```php
require __DIR__ . '/src/SanctumTmp/Sdk/SanctumTmpException.php';
require __DIR__ . '/src/SanctumTmp/Sdk/ApiException.php';
require __DIR__ . '/src/SanctumTmp/Sdk/ValidationException.php';
require __DIR__ . '/src/SanctumTmp/Sdk/QrCapacityException.php';
require __DIR__ . '/src/SanctumTmp/Sdk/SanctumTmpClient.php';

use SanctumTmp\Sdk\SanctumTmpClient;
```

## Usage

```php
$c = new SanctumTmpClient(getenv('SANCTUM_TMP_BASE_URL') ?: null);

// Agents / Broca / Venice — host bytes
$up = $c->uploadFile('/path/to/photo.png');
$direct = $up['data']['direct_url'];

// Humans / short text — QR-first with file fallback
$out = $c->encodeOrUpload('example.com');
// $out['sanctum_tmp_mode'] === 'qr' | 'file'

$qr = $c->encode('hello', ['format' => 'svg', 'persist' => false]);
// $qr['data']['data_uri']

// On QrCapacityException, call uploadText() or use encodeOrUpload()
```

| Method | Maps to |
|--------|---------|
| `health()` | `GET /AGENTS.md` |
| `uploadFile` / `uploadBytes` / `uploadFiles` / `uploadText` | `POST /api/v1/upload.php` |
| `encode(...)` | `POST /api/v1/encode.php` — throws `QrCapacityException` |
| `encodeOrUpload(...)` | encode → on capacity, `uploadText` |
| `qrHtmlFragment(array)` | `POST /api/v1/qr.php` |
| `download($directUrl)` | `GET` bytes |

Env: `SANCTUM_TMP_BASE_URL`, optional `SANCTUM_TMP_USER_AGENT`.

HTTP contract: [`docs/API.md`](../../docs/API.md) · overview: [`docs/SDK.md`](../../docs/SDK.md).
