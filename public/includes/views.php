<?php

declare(strict_types=1);

use SanctumTmp\ClientMode;
use SanctumTmp\QrSvg;
use SanctumTmp\UploadApi;

require_once __DIR__ . '/markdown.php';

function sanctum_tmp_brand_css(): string
{
    return <<<'CSS'
:root{
  --bg:#070b12;--bg2:#0e1520;--card:#121c2b;--border:#243044;
  --text:#e8eef7;--muted:#8fa3bf;--accent:#6b8cff;--accent2:#9b7bff;
  --ok:#3dd68c;--err:#f07178;--radius:12px;
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
  background:radial-gradient(1200px 600px at 10% -10%,#1a2450 0%,transparent 55%),
  radial-gradient(900px 500px at 100% 0%,#231636 0%,transparent 50%),var(--bg);
  color:var(--text);min-height:100vh}
.wrap{max-width:760px;margin:0 auto;padding:2rem 1.25rem 3rem}
.brand{display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem}
.logo{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;color:#fff;
  box-shadow:0 8px 24px rgba(107,140,255,.25)}
.brand-text h1{margin:0;font-size:1.15rem;font-weight:600;letter-spacing:.02em}
.brand-text p{margin:.15rem 0 0;font-size:.85rem;color:var(--muted)}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);
  padding:1.5rem;box-shadow:0 12px 40px rgba(0,0,0,.25)}
.card h2{margin:0 0 .75rem;font-size:1.35rem;font-weight:600}
.lead{color:var(--muted);line-height:1.55;margin:.5rem 0 1.25rem}
.meta{color:var(--muted);font-size:.92rem;line-height:1.5}
.upload-box{border:1px dashed #3a4f6e;border-radius:10px;padding:1.25rem;background:var(--bg2);margin:1rem 0}
.upload-box input[type=file]{max-width:100%;margin-bottom:.75rem;color:var(--text)}
.btn{display:inline-block;padding:.65rem 1.15rem;border:none;border-radius:8px;cursor:pointer;
  background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;text-decoration:none;
  font-weight:600;font-size:.95rem}
.btn.secondary{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn:hover{filter:brightness(1.06)}
.links a{color:var(--accent);word-break:break-all}
.links p{margin:.75rem 0}
.doc-links{display:flex;flex-wrap:wrap;gap:.75rem;margin:1.25rem 0 0}
.doc-links a{font-size:.9rem;color:var(--muted);text-decoration:none;border-bottom:1px dotted #4a607a}
.doc-links a:hover{color:var(--accent)}
.qr-wrap{display:flex;flex-direction:column;align-items:center;gap:.75rem;margin:1.25rem 0;padding:1rem;
  background:var(--bg2);border:1px dashed #3a4f6e;border-radius:10px}
.qr-wrap img,.qr-wrap svg{max-width:min(300px,100%);height:auto;background:#fff;padding:.5rem;border-radius:8px}
pre.json{background:#05080f;color:#cbd5e1;padding:1rem;border-radius:8px;overflow:auto;font-size:.78rem;line-height:1.45;
  border:1px solid var(--border)}
table{width:100%;border-collapse:collapse;margin:1rem 0;font-size:.92rem}
th,td{border:1px solid var(--border);padding:.5rem .65rem;text-align:left}
th{background:var(--bg2);color:var(--muted)}
.code-block{background:#05080f;border:1px solid var(--border);border-radius:8px;padding:1rem;overflow:auto;
  font-size:.82rem;line-height:1.45}
.code-block code{color:#cbd5e1}
h1.doc-title{margin-top:0}
.err{color:var(--err)}
footer{margin-top:2rem;text-align:center;font-size:.8rem;color:#5c708a}
footer a{color:#7d93b0}
CSS;
}

function sanctum_tmp_page_shell_start(string $title, string $subtitle = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' · Sanctum Temp</title>';
    echo '<style>' . sanctum_tmp_brand_css() . '</style></head><body><div class="wrap">';
    echo '<header class="brand"><div class="logo" aria-hidden="true">S</div><div class="brand-text">';
    echo '<h1>Sanctum Temp</h1><p>' . htmlspecialchars($subtitle !== '' ? $subtitle : 'Ephemeral file exchange') . '</p>';
    echo '</div></header><main class="card">';
}

function sanctum_tmp_page_shell_end(): void
{
    echo '</main><footer>Part of <a href="https://sanctumos.org/">Sanctum</a> · ';
    echo '<a href="/docs/api/">API</a> · <a href="/AGENTS.md">Agents</a></footer></div></body></html>';
}

function render_home(): void
{
    if (!ClientMode::wantsBrowserHtml()) {
        render_home_plain();
        return;
    }

    $config = sanctum_tmp_config();
    $maxMb = (int) round($config->maxBytes / 1048576);
    $ttlHours = max(1, (int) round($config->ttlSeconds / 3600));
    sanctum_tmp_page_shell_start('Upload', 'tmp.sanctumos.org');
    echo '<h2>Drop a file</h2>';
    echo '<p class="lead">Temporary uploads for Sanctum workflows — share a link, scan a QR, or hand bytes to an agent. ';
    echo 'Files expire automatically.</p>';
    echo '<p class="meta">Up to <strong>' . htmlspecialchars((string) $maxMb) . ' MiB</strong> · ';
    echo 'TTL ~<strong>' . htmlspecialchars((string) $ttlHours) . ' hours</strong></p>';
    echo '<div class="upload-box"><form method="post" enctype="multipart/form-data" action="/">';
    echo '<input type="file" name="file" required><br>';
    echo '<button class="btn" type="submit">Upload</button></form></div>';
    echo '<div class="doc-links">';
    echo '<a href="/docs/api/">API documentation</a>';
    echo '<a href="/AGENTS.md">AGENTS.md</a>';
    echo '</div>';
    sanctum_tmp_page_shell_end();
}

function render_home_plain(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    $config = sanctum_tmp_config();
    echo "Sanctum temporary file host\n";
    echo $config->baseUrl . "\n\n";
    echo "Upload:  POST /api/v1/upload.php  (multipart field: file)\n";
    echo "API:     GET  /docs/API.md\n";
    echo "Agents:  GET  /AGENTS.md\n";
}

function render_api_docs(): void
{
    $path = sanctum_tmp_doc_path('API.md');
    if ($path === null) {
        http_response_code(404);
        render_home_plain();
        return;
    }

    if (!ClientMode::wantsBrowserHtml()) {
        sanctum_tmp_send_markdown_file($path, 'API.md');
        return;
    }

    $markdown = (string) file_get_contents($path);
    sanctum_tmp_page_shell_start('API', 'HTTP reference');
    echo '<h2 class="doc-title">HTTP API</h2>';
    echo sanctum_tmp_markdown_to_html($markdown);
    echo '<div class="doc-links"><a href="/AGENTS.md">AGENTS.md</a><a href="/">Upload</a></div>';
    sanctum_tmp_page_shell_end();
}

/** @param array<string, mixed> $row */
function render_view(array $row, string $id, string $filename): void
{
    $config = sanctum_tmp_config();
    $direct = $config->baseUrl . $config->dlUrlPrefix . '/' . rawurlencode($id) . '/' . rawurlencode($filename);
    $expires = date('Y-m-d H:i:s T', (int) $row['expires_at']);

    if (!ClientMode::wantsBrowserHtml()) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "filename: {$filename}\n";
        echo 'size_bytes: ' . ($row['size_bytes'] ?? '') . "\n";
        echo 'mime_type: ' . ($row['mime_type'] ?? '') . "\n";
        echo "expires: {$expires}\n";
        echo "direct_url: {$direct}\n";
        return;
    }

    sanctum_tmp_page_shell_start((string) $filename, 'File details');
    echo '<h2>' . htmlspecialchars($filename) . '</h2>';
    echo '<table><tr><th>Size</th><td>' . htmlspecialchars((string) $row['size_bytes']) . ' bytes</td></tr>';
    echo '<tr><th>MIME</th><td>' . htmlspecialchars((string) ($row['mime_type'] ?? '')) . '</td></tr>';
    echo '<tr><th>Expires</th><td>' . htmlspecialchars($expires) . '</td></tr></table>';
    echo '<p class="links"><a class="btn" href="' . htmlspecialchars($direct) . '">Direct download</a></p>';
    echo '<p class="meta">Use the direct URL for bots, Venice, and scripts.</p>';
    sanctum_tmp_page_shell_end();
}

/** @param array{status: string, data: array<string, string>} $payload */
function render_upload_success(array $payload): void
{
    $data = $payload['data'];
    $filename = (string) ($data['filename'] ?? 'upload');
    $viewUrl = (string) ($data['url'] ?? '');
    $directUrl = (string) ($data['direct_url'] ?? '');
    $json = UploadApi::encodePayload($payload);
    $qr = QrSvg::encode($json);

    sanctum_tmp_page_shell_start('Upload complete', 'Ready to share');
    echo '<h2>Upload complete</h2>';
    echo '<p class="lead"><strong>' . htmlspecialchars($filename) . '</strong> is live until expiry.</p>';
    echo '<div class="links">';
    echo '<p><strong>View page</strong><br><a href="' . htmlspecialchars($viewUrl) . '">'
        . htmlspecialchars($viewUrl) . '</a></p>';
    echo '<p><strong>Direct link</strong> (APIs / Venice / scripts)<br><a href="'
        . htmlspecialchars($directUrl) . '">' . htmlspecialchars($directUrl) . '</a></p>';
    echo '</div>';

    echo '<div class="qr-wrap">';
    echo '<p class="meta" style="margin:0;text-align:center">Scan to copy the API JSON on your phone — no OCR.</p>';
    if ($qr !== null) {
        if (str_starts_with($qr, 'data:image/svg+xml')) {
            echo '<img src="' . htmlspecialchars($qr) . '" alt="QR code with upload JSON">';
        } else {
            echo $qr;
        }
    } else {
        echo '<p class="err">QR unavailable; use the JSON block below.</p>';
    }
    echo '</div>';

    echo '<p class="meta">Raw API response</p>';
    echo '<pre class="json">' . htmlspecialchars($json) . '</pre>';
    echo '<p><a class="btn" href="/">Upload another</a></p>';
    sanctum_tmp_page_shell_end();
}

function render_upload_error(string $message, int $status = 400): void
{
    http_response_code($status);
    if (!ClientMode::wantsBrowserHtml()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_SLASHES);
        return;
    }
    sanctum_tmp_page_shell_start('Upload failed');
    echo '<h2>Upload failed</h2>';
    echo '<p class="err">' . htmlspecialchars($message) . '</p>';
    echo '<p><a class="btn" href="/">Try again</a></p>';
    sanctum_tmp_page_shell_end();
}

function render_not_found(): void
{
    http_response_code(404);
    if (!ClientMode::wantsBrowserHtml()) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not found or expired.\n";
        return;
    }
    sanctum_tmp_page_shell_start('Not found');
    echo '<h2>Not found</h2>';
    echo '<p class="meta">This file is missing or has expired.</p>';
    echo '<p><a class="btn" href="/">Home</a></p>';
    sanctum_tmp_page_shell_end();
}
