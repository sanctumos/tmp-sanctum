<?php

declare(strict_types=1);

use SanctumTmp\ClientMode;
use SanctumTmp\QrSvg;
use SanctumTmp\UploadApi;

require_once __DIR__ . '/markdown.php';

function sanctum_tmp_brand_css(): string
{
    // SanctumOS palette (sanctumos.org style guide) — not DSC purple-dark dashboard.
    return <<<'CSS'
:root{
  --retro-primary:#3C6E8F;--retro-primary-deep:#2a5a7a;
  --retro-accent:#D2691E;--logo-primary:#1c80aa;--logo-secondary:#4d91aa;--logo-accent:#e16e0a;
  --bg:#F9F9F9;--bg2:#F4F4F4;--card:#FFFFFF;--border:#CCCCCC;--border-soft:#E8E8E8;
  --text:#333333;--muted:#5a6a75;--ok:#28a745;--err:#dc3545;--radius:8px;
  --shadow:0 2px 8px rgba(0,0,0,.1);
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;
  background:var(--bg);color:var(--text);min-height:100vh;line-height:1.55}
.wrap{max-width:760px;margin:0 auto;padding:0 1.25rem 3rem}
.site-bar{background:linear-gradient(135deg,var(--retro-primary) 0%,var(--retro-primary-deep) 100%);
  color:#fff;border-bottom:3px solid var(--retro-accent);box-shadow:0 2px 4px rgba(0,0,0,.12);
  margin:0 -1.25rem 1.5rem;padding:1.1rem 1.25rem}
.brand{display:flex;align-items:center;gap:.75rem;max-width:760px;margin:0 auto}
.brand a.brand-home{display:flex;align-items:center;gap:.75rem;text-decoration:none;color:#fff}
.logo-img{width:44px;height:44px;object-fit:contain;flex-shrink:0;display:block;
  filter:drop-shadow(0 1px 2px rgba(0,0,0,.25))}
.brand-text h1{margin:0;font-family:"Roboto Mono",ui-monospace,monospace;font-size:1.35rem;
  font-weight:700;letter-spacing:2px;line-height:1}
.brand-text p{margin:.2rem 0 0;font-size:.85rem;opacity:.9}
.card{background:var(--card);border:1px solid var(--border-soft);border-radius:var(--radius);
  padding:1.5rem;box-shadow:var(--shadow)}
.card h2{margin:0 0 .75rem;font-size:1.35rem;font-weight:700;color:var(--retro-primary);
  font-family:"Roboto Mono",ui-monospace,monospace;letter-spacing:.5px}
.lead{color:var(--muted);line-height:1.55;margin:.5rem 0 1.25rem}
.meta{color:var(--muted);font-size:.92rem;line-height:1.5}
.upload-box{border:1px dashed var(--border);border-radius:var(--radius);padding:1.25rem;background:var(--bg2);margin:1rem 0}
.upload-box input[type=file]{max-width:100%;margin-bottom:.75rem;color:var(--text)}
.drop-zone{border:2px dashed var(--border);border-radius:var(--radius);padding:2rem 1.25rem;background:var(--bg2);
  margin:1rem 0;text-align:center;transition:border-color .15s,background .15s;cursor:pointer}
.drop-zone.dragover{border-color:var(--logo-accent);background:#fff8f0}
.drop-zone p{margin:.35rem 0;color:var(--muted)}
.drop-zone .hint{font-size:.85rem}
.file-queue{margin:1rem 0}
.file-row{display:flex;flex-direction:column;gap:.35rem;padding:.75rem 0;border-bottom:1px solid var(--border-soft)}
.file-row:last-child{border-bottom:none}
.file-row-head{display:flex;justify-content:space-between;align-items:center;gap:.75rem}
.file-name{font-size:.92rem;word-break:break-all}
.file-status{font-size:.82rem;color:var(--muted);white-space:nowrap}
.file-status.ok{color:var(--ok)}
.file-status.err{color:var(--err)}
.progress{height:6px;background:var(--border-soft);border-radius:4px;overflow:hidden}
.progress>span{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--logo-primary),var(--logo-accent));
  transition:width .12s}
.upload-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1rem;align-items:center}
.upload-actions .meta{margin:0}
.results-table td code{font-size:.78rem;word-break:break-all}
.btn{display:inline-block;padding:.65rem 1.15rem;border:none;border-radius:6px;cursor:pointer;
  background:linear-gradient(135deg,var(--logo-accent),var(--retro-accent));color:#fff;text-decoration:none;
  font-weight:600;font-size:.95rem;box-shadow:0 1px 2px rgba(0,0,0,.12)}
.btn.secondary{background:linear-gradient(180deg,#f7f7f7,#eaeaea);border:1px solid var(--border);color:var(--text);
  box-shadow:none}
.btn:hover{filter:brightness(1.05)}
.links a{color:var(--logo-primary);word-break:break-all}
.links p{margin:.75rem 0}
.link-row{margin:.75rem 0}
.link-row .link-body > strong{display:block}
.link-url-row{display:flex;align-items:flex-start;gap:.5rem;margin-top:.25rem}
.link-url-row a{flex:1;min-width:0;color:var(--logo-primary);word-break:break-all}
.copy-btn{flex-shrink:0;padding:.35rem .65rem;border:1px solid var(--border);border-radius:6px;
  background:var(--bg2);color:var(--muted);cursor:pointer;font-size:.78rem;font-weight:600;
  line-height:1.2;white-space:nowrap;align-self:flex-start}
.copy-btn:hover{color:var(--text);border-color:var(--retro-primary)}
.copy-btn.copied{color:var(--ok);border-color:rgba(40,167,69,.45)}
.json-block{margin:0 0 1rem}
.json-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin:0 0 .5rem}
.json-head .meta{margin:0}
.doc-links{display:flex;flex-wrap:wrap;gap:.75rem;margin:1.25rem 0 0}
.doc-links a{font-size:.9rem;color:var(--muted);text-decoration:none;border-bottom:1px dotted var(--border)}
.doc-links a:hover{color:var(--logo-primary)}
.qr-wrap{display:flex;flex-direction:column;align-items:center;gap:.75rem;margin:1.25rem 0;padding:1rem;
  background:var(--bg2);border:1px dashed var(--border);border-radius:var(--radius)}
.qr-wrap img,.qr-wrap svg{max-width:min(300px,100%);height:auto;background:#fff;padding:.5rem;border-radius:6px;
  border:1px solid var(--border-soft)}
pre.json{background:#2a333c;color:#e8eef2;padding:1rem;border-radius:6px;overflow:auto;font-size:.78rem;line-height:1.45;
  border:1px solid #1e262e;margin:0;font-family:"JetBrains Mono",ui-monospace,monospace}
table{width:100%;border-collapse:collapse;margin:1rem 0;font-size:.92rem}
th,td{border:1px solid var(--border);padding:.5rem .65rem;text-align:left}
th{background:var(--bg2);color:var(--muted)}
.code-block{background:#2a333c;border:1px solid #1e262e;border-radius:6px;padding:1rem;overflow:auto;
  font-size:.82rem;line-height:1.45;font-family:"JetBrains Mono",ui-monospace,monospace}
.code-block code{color:#e8eef2}
h1.doc-title{margin-top:0;color:var(--retro-primary);font-family:"Roboto Mono",ui-monospace,monospace}
.err{color:var(--err)}
footer{margin-top:2rem;text-align:center;font-size:.8rem;color:var(--muted)}
footer a{color:var(--logo-primary)}
.tabs{display:flex;gap:.5rem;margin:0 0 1.25rem;flex-wrap:wrap}
.tab{padding:.45rem .9rem;border:1px solid var(--border);border-radius:6px;background:var(--bg2);
  color:var(--muted);cursor:pointer;font-weight:600;font-size:.88rem}
.tab.is-active{background:linear-gradient(135deg,var(--retro-primary),var(--retro-primary-deep));
  color:#fff;border-color:transparent}
.type-toggle{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}
.type-pill{padding:.35rem .75rem;border:1px solid var(--border);border-radius:6px;background:var(--bg2);
  color:var(--muted);cursor:pointer;font-size:.82rem;font-weight:600}
.type-pill.is-active{border-color:var(--logo-primary);color:var(--retro-primary);background:#eef6f9}
.form-label{display:block;font-size:.85rem;color:var(--muted);margin:0 0 .35rem;font-weight:600}
.form-textarea,.form-input{width:100%;box-sizing:border-box;background:#fff;border:1px solid var(--border);
  border-radius:6px;color:var(--text);padding:.65rem .75rem;font:inherit}
.form-textarea{min-height:6rem;resize:vertical}
.form-textarea:focus,.form-input:focus{outline:2px solid rgba(28,128,170,.35);border-color:var(--logo-primary)}
.studio-grid{display:grid;gap:1rem;margin:1rem 0}
.studio-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
@media(max-width:560px){.studio-row{grid-template-columns:1fr}}
.suggest{margin:1rem 0;padding:.85rem 1rem;border:1px solid var(--border);border-left:4px solid var(--logo-accent);
  border-radius:var(--radius);background:var(--bg2)}
.suggest-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.65rem}
.check-row{display:flex;align-items:center;gap:.5rem;margin:.75rem 0;color:var(--muted);font-size:.9rem}
.check-row input{accent-color:var(--logo-accent)}
.logo-preview{display:flex;align-items:center;gap:.75rem;margin-top:.5rem}
.logo-preview img{width:48px;height:48px;object-fit:contain;background:#fff;border-radius:6px;padding:4px;
  border:1px solid var(--border-soft)}
#qr-result{margin-top:1.25rem}
#qr-status{min-height:1.25rem;margin:.75rem 0;font-size:.9rem;color:var(--muted)}
[hidden]{display:none!important}
CSS;
}

function sanctum_tmp_copy_script(): string
{
    return <<<'JS'
<script>
(function () {
  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
  }
  function flash(btn) {
    var prev = btn.textContent;
    btn.classList.add('copied');
    btn.textContent = 'Copied';
    setTimeout(function () {
      btn.classList.remove('copied');
      btn.textContent = prev;
    }, 1400);
  }
  window.sanctumTmpCopy = function (btn) {
    var text = btn.getAttribute('data-copy') || '';
    var target = btn.getAttribute('data-copy-target');
    if (target) {
      var el = document.getElementById(target);
      if (el) text = el.textContent || '';
    }
    if (!text) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () { flash(btn); }).catch(function () {
        fallbackCopy(text);
        flash(btn);
      });
    } else {
      fallbackCopy(text);
      flash(btn);
    }
  };
})();
</script>
JS;
}

function sanctum_tmp_page_shell_start(string $title, string $subtitle = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' · Sanctum Temp</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">';
    echo '<style>' . sanctum_tmp_brand_css() . '</style></head><body><div class="wrap">';
    echo '<div class="site-bar"><header class="brand">';
    echo '<a class="brand-home" href="/"><img class="logo-img" src="/assets/sanctum-logo.svg" width="44" height="44" alt="SanctumOS">';
    echo '<div class="brand-text"><h1>Sanctum Temp</h1>';
    echo '<p>' . htmlspecialchars($subtitle !== '' ? $subtitle : 'Ephemeral file exchange') . '</p>';
    echo '</div></a></header></div><main class="card">';
}

function sanctum_tmp_page_shell_end(bool $withCopy = false): void
{
    echo '</main><footer>Part of <a href="https://sanctumos.org/">Sanctum</a> · ';
    echo '<a href="/docs/api/">API</a> · <a href="/AGENTS.md">Agents</a></footer></div>';
    if ($withCopy) {
        echo sanctum_tmp_copy_script();
    }
    echo '</body></html>';
}

/** Render a labeled URL with a copy-to-clipboard control. */
function sanctum_tmp_link_row(string $label, string $url, string $hint = ''): void
{
    $safeUrl = htmlspecialchars($url);
    $safeLabel = htmlspecialchars($label);
    $attrUrl = htmlspecialchars($url, ENT_QUOTES);
    echo '<div class="link-row">';
    echo '<div class="link-body"><strong>' . $safeLabel . '</strong>';
    if ($hint !== '') {
        echo ' <span class="meta">' . htmlspecialchars($hint) . '</span>';
    }
    echo '<div class="link-url-row">';
    echo '<a href="' . $safeUrl . '">' . $safeUrl . '</a>';
    echo '<button type="button" class="copy-btn" data-copy="' . $attrUrl
        . '" onclick="sanctumTmpCopy(this)" aria-label="Copy ' . $safeLabel . '">Copy</button>';
    echo '</div></div></div>';
}

/** Render batch/single QR block matching Sanctum Temp styling. */
function sanctum_tmp_render_qr_block(string $json, string $hint = 'Scan to copy the API JSON on your phone — no OCR.'): void
{
    $qr = QrSvg::encode($json);
    echo '<div class="qr-wrap">';
    echo '<p class="meta" style="margin:0;text-align:center">' . htmlspecialchars($hint) . '</p>';
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
    $maxBatch = $config->maxBatchSize;
    sanctum_tmp_page_shell_start('Upload', 'tmp.sanctumos.org');
    echo '<h2>Share bytes or a QR</h2>';
    echo '<p class="lead">Drop a file for a short-lived download URL. Tiny text-like files get offered the QR tab instead. ';
    echo 'Paste / QR studio stays one click away. Agents keep using the upload API.</p>';
    echo '<p class="meta">Files up to <strong>' . htmlspecialchars((string) $maxMb) . ' MiB</strong> · ';
    echo 'TTL ~<strong>' . htmlspecialchars((string) $ttlHours) . ' hours</strong> · ';
    echo 'batch max <strong>' . htmlspecialchars((string) $maxBatch) . '</strong> · ';
    echo 'QR = try-encode, fall back on failure · ephemeral by default</p>';

    echo '<div class="tabs" role="tablist">';
    echo '<button type="button" class="tab is-active" id="tab-files" role="tab" aria-selected="true">Files</button>';
    echo '<button type="button" class="tab" id="tab-qr" role="tab" aria-selected="false">QR / paste</button>';
    echo '</div>';

    // ——— Files panel (default) ———
    echo '<div id="panel-files" role="tabpanel">';
    echo '<p class="meta">Host files under <code>/uploads/</code> with a short TTL. ';
    echo 'A single small text-like file switches you to the QR tab so you can encode it instead of storing bytes.</p>';

    echo '<div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Choose or drop files">';
    echo '<p><strong>Drag files here</strong> or click to browse</p>';
    echo '<p class="hint">Multiple files supported</p>';
    echo '<input type="file" id="file-input" multiple hidden></div>';

    echo '<div class="file-queue" id="file-queue" hidden></div>';
    echo '<div class="upload-actions" id="upload-actions" hidden>';
    echo '<button class="btn" type="button" id="upload-btn">Upload</button>';
    echo '<button class="btn secondary" type="button" id="clear-btn">Clear</button>';
    echo '<p class="meta" id="upload-summary"></p></div>';
    echo '</div>';

    // ——— QR panel ———
    echo '<div id="panel-qr" role="tabpanel" hidden>';
    echo '<div class="type-toggle" role="radiogroup" aria-label="QR content type">';
    foreach (['auto' => 'Auto', 'url' => 'URL', 'email' => 'Email', 'tel' => 'Phone', 'text' => 'Plain text'] as $k => $label) {
        $active = $k === 'auto' ? ' is-active' : '';
        $checked = $k === 'auto' ? 'true' : 'false';
        echo '<button type="button" class="type-pill' . $active . '" data-type="' . htmlspecialchars($k)
            . '" role="radio" aria-checked="' . $checked . '">' . htmlspecialchars($label) . '</button>';
    }
    echo '</div>';

    echo '<label class="form-label" for="qr-content">Content</label>';
    echo '<textarea id="qr-content" class="form-textarea" rows="4" placeholder="example.com · hello@decisionsciencecorp.com · +1 555 010 0123 · or any short note"></textarea>';

    echo '<div class="suggest" id="qr-suggest" hidden><p class="meta" id="qr-suggest-text"></p>';
    echo '<div class="suggest-actions">';
    echo '<button type="button" class="btn" id="qr-suggest-accept">Use suggestion</button>';
    echo '<button type="button" class="btn secondary" id="qr-suggest-dismiss">Keep as typed</button>';
    echo '</div></div>';

    echo '<div class="studio-row">';
    echo '<label><span class="form-label">Error correction</span>';
    echo '<select id="qr-ecl" class="form-input"><option value="L">L — 7%</option>';
    echo '<option value="M" selected>M — 15%</option><option value="Q">Q — 25%</option>';
    echo '<option value="H">H — 30%</option></select></label>';
    echo '<label><span class="form-label">Format</span>';
    echo '<select id="qr-format" class="form-input"><option value="svg" selected>SVG</option>';
    echo '<option value="png">PNG</option></select></label>';
    echo '</div>';

    echo '<div class="studio-grid">';
    echo '<label class="form-label" for="qr-logo-file">Center logo <span class="meta">(optional)</span></label>';
    echo '<input type="file" id="qr-logo-file" accept="image/png,image/jpeg,image/gif,image/webp">';
    echo '<div class="logo-preview" id="qr-logo-preview" hidden>';
    echo '<img id="qr-logo-img" alt="Logo preview"><button type="button" class="btn secondary" id="qr-logo-remove">Remove</button></div>';
    echo '<div id="qr-logo-size-field" hidden><label class="form-label" for="qr-logo-size">Logo size <span id="qr-logo-size-val">22%</span></label>';
    echo '<input id="qr-logo-size" class="form-input" type="range" min="10" max="30" step="1" value="22"></div>';
    echo '</div>';

    echo '<label class="check-row"><input type="checkbox" id="qr-persist"> Also save a short-lived file link (non-default)</label>';

    echo '<div class="upload-actions">';
    echo '<button class="btn" type="button" id="qr-make">Make QR</button>';
    echo '<button class="btn secondary" type="button" id="qr-host-file">Host as file instead</button>';
    echo '</div>';
    echo '<p id="qr-status" class="meta"></p>';

    echo '<div id="qr-result" hidden>';
    echo '<div class="qr-wrap"><img id="qr-result-img" alt="QR code"><p class="meta" id="qr-result-meta"></p></div>';
    echo '<p class="meta">Encoded payload</p><pre class="json" id="qr-result-encoded"></pre>';
    echo '</div></div>';

    echo '<div class="doc-links">';
    echo '<a href="/docs/api/">API documentation</a>';
    echo '<a href="/AGENTS.md">AGENTS.md</a>';
    echo '<a href="https://qr.decisionsciencecorp.com/">QR Code Studio</a>';
    echo '</div>';

    echo '<noscript><div class="upload-box"><form method="post" enctype="multipart/form-data" action="/">';
    echo '<p class="meta">JavaScript is disabled — single-file upload only (file host path).</p>';
    echo '<input type="file" name="file" required><br>';
    echo '<button class="btn" type="submit">Upload</button></form></div></noscript>';

    echo '<script>document.body.setAttribute("data-max-batch",' . (int) $maxBatch . ');</script>';
    echo '<script src="/assets/home-studio.js" defer></script>';
    sanctum_tmp_page_shell_end(true);
}

function render_home_plain(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    $config = sanctum_tmp_config();
    echo "Sanctum temporary file host\n";
    echo $config->baseUrl . "\n\n";
    echo "Upload:  POST /api/v1/upload.php  (multipart field: file)\n";
    echo "Multi:   POST /api/v1/upload.php  (multipart field: files[]; max {$config->maxBatchSize} per batch)\n";
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
    $view = $config->baseUrl . '/' . rawurlencode($id) . '/' . rawurlencode($filename);
    $direct = $config->baseUrl . $config->dlUrlPrefix . '/' . rawurlencode($id) . '/' . rawurlencode($filename);
    $expires = date('Y-m-d H:i:s T', (int) $row['expires_at']);
    $payload = UploadApi::successPayload([
        'view_url' => $view,
        'direct_url' => $direct,
        'id' => $id,
        'filename' => $filename,
    ]);
    $json = UploadApi::encodePayload($payload);

    if (!ClientMode::wantsBrowserHtml()) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "filename: {$filename}\n";
        echo 'size_bytes: ' . ($row['size_bytes'] ?? '') . "\n";
        echo 'mime_type: ' . ($row['mime_type'] ?? '') . "\n";
        echo "expires: {$expires}\n";
        echo "url: {$view}\n";
        echo "direct_url: {$direct}\n";
        return;
    }

    sanctum_tmp_page_shell_start((string) $filename, 'File details');
    echo '<h2>' . htmlspecialchars($filename) . '</h2>';
    echo '<table><tr><th>Size</th><td>' . htmlspecialchars((string) $row['size_bytes']) . ' bytes</td></tr>';
    echo '<tr><th>MIME</th><td>' . htmlspecialchars((string) ($row['mime_type'] ?? '')) . '</td></tr>';
    echo '<tr><th>Expires</th><td>' . htmlspecialchars($expires) . '</td></tr></table>';
    echo '<p class="links"><a class="btn" href="' . htmlspecialchars($direct) . '">Direct download</a></p>';
    echo '<div class="links">';
    sanctum_tmp_link_row('View page', $view);
    sanctum_tmp_link_row('Direct link', $direct, '(APIs / Venice / scripts)');
    echo '</div>';

    sanctum_tmp_render_qr_block($json);

    echo '<div class="json-block">';
    echo '<div class="json-head"><p class="meta">Raw API response</p>';
    echo '<button type="button" class="copy-btn" data-copy-target="view-json"'
        . ' onclick="sanctumTmpCopy(this)" aria-label="Copy JSON">Copy JSON</button></div>';
    echo '<pre class="json" id="view-json">' . htmlspecialchars($json) . '</pre>';
    echo '</div>';
    sanctum_tmp_page_shell_end(true);
}

/** @param array{status: string, data: array<string, string>} $payload */
function render_upload_success(array $payload): void
{
    $data = $payload['data'];
    $filename = (string) ($data['filename'] ?? 'upload');
    $viewUrl = (string) ($data['url'] ?? '');
    $directUrl = (string) ($data['direct_url'] ?? '');
    $json = UploadApi::encodePayload($payload);

    sanctum_tmp_page_shell_start('Upload complete', 'Ready to share');
    echo '<h2>Upload complete</h2>';
    echo '<p class="lead"><strong>' . htmlspecialchars($filename) . '</strong> is live until expiry.</p>';
    echo '<div class="links">';
    sanctum_tmp_link_row('View page', $viewUrl);
    sanctum_tmp_link_row('Direct link', $directUrl, '(APIs / Venice / scripts)');
    echo '</div>';

    sanctum_tmp_render_qr_block($json);

    echo '<div class="json-block">';
    echo '<div class="json-head"><p class="meta">Raw API response</p>';
    echo '<button type="button" class="copy-btn" data-copy-target="upload-json"'
        . ' onclick="sanctumTmpCopy(this)" aria-label="Copy JSON">Copy JSON</button></div>';
    echo '<pre class="json" id="upload-json">' . htmlspecialchars($json) . '</pre>';
    echo '</div>';
    echo '<p><a class="btn" href="/">Upload another</a></p>';
    sanctum_tmp_page_shell_end(true);
}

/**
 * @param array{status: string, data: array{count: int, succeeded: int, failed: int, files: list<array<string, string>>}} $payload
 */
function render_upload_success_multi(array $payload): void
{
    $data = $payload['data'];
    $files = $data['files'] ?? [];
    $json = UploadApi::encodePayload($payload);

    sanctum_tmp_page_shell_start('Upload complete', 'Ready to share');
    echo '<h2>Upload complete</h2>';
    $succeeded = (int) ($data['succeeded'] ?? 0);
    $failed = (int) ($data['failed'] ?? 0);
    echo '<p class="lead"><strong>' . htmlspecialchars((string) $succeeded) . '</strong> of ';
    echo '<strong>' . htmlspecialchars((string) ($data['count'] ?? count($files))) . '</strong> files are live.';
    if ($failed > 0) {
        echo ' <span class="err">' . htmlspecialchars((string) $failed) . ' failed.</span>';
    }
    echo '</p>';

    echo '<table class="results-table"><thead><tr><th>File</th><th>Direct link</th></tr></thead><tbody>';
    foreach ($files as $row) {
        $filename = (string) ($row['filename'] ?? '');
        $status = (string) ($row['status'] ?? '');
        echo '<tr><td>' . htmlspecialchars($filename);
        if ($status !== 'success') {
            echo '<br><span class="err">' . htmlspecialchars((string) ($row['message'] ?? 'Failed')) . '</span>';
        }
        echo '</td><td>';
        if ($status === 'success' && !empty($row['direct_url'])) {
            $direct = (string) $row['direct_url'];
            $attr = htmlspecialchars($direct, ENT_QUOTES);
            echo '<div class="link-url-row">';
            echo '<a href="' . htmlspecialchars($direct) . '">' . htmlspecialchars($direct) . '</a>';
            echo '<button type="button" class="copy-btn" data-copy="' . $attr
                . '" onclick="sanctumTmpCopy(this)" aria-label="Copy direct link">Copy</button>';
            echo '</div>';
        } else {
            echo '—';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';

    sanctum_tmp_render_qr_block(
        $json,
        'Scan to copy the batch API JSON on your phone — no OCR.'
    );

    echo '<div class="json-block">';
    echo '<div class="json-head"><p class="meta">Raw API response</p>';
    echo '<button type="button" class="copy-btn" data-copy-target="upload-json-multi"'
        . ' onclick="sanctumTmpCopy(this)" aria-label="Copy JSON">Copy JSON</button></div>';
    echo '<pre class="json" id="upload-json-multi">' . htmlspecialchars($json) . '</pre>';
    echo '</div>';
    echo '<p><a class="btn" href="/">Upload more</a></p>';
    sanctum_tmp_page_shell_end(true);
}

/** Browser page after JS multi-upload completes (reads sessionStorage client-side). */
function render_upload_results_page(): void
{
    sanctum_tmp_page_shell_start('Upload complete', 'Ready to share');
    echo '<h2>Upload complete</h2>';
    echo '<p class="lead" id="results-lead">Loading results…</p>';
    echo '<table class="results-table" id="results-table" hidden><thead><tr><th>File</th><th>Direct link</th><th>View</th></tr></thead><tbody></tbody></table>';
    echo '<div id="results-qr" hidden></div>';
    echo '<div class="json-block">';
    echo '<div class="json-head"><p class="meta">Raw JSON</p>';
    echo '<button type="button" class="copy-btn" data-copy-target="results-json"'
        . ' onclick="sanctumTmpCopy(this)" aria-label="Copy JSON">Copy JSON</button></div>';
    echo '<pre class="json" id="results-json"></pre>';
    echo '</div>';
    echo '<p><a class="btn" href="/">Upload more</a></p>';
    echo '<script>(function(){';
    echo 'const raw=sessionStorage.getItem("sanctum_tmp_upload_results");';
    echo 'if(!raw){document.getElementById("results-lead").textContent="No results in this session.";return;}';
    echo 'let rows;try{rows=JSON.parse(raw);}catch(e){document.getElementById("results-lead").textContent="Could not read results.";return;}';
    echo 'sessionStorage.removeItem("sanctum_tmp_upload_results");';
    echo 'document.getElementById("results-lead").innerHTML="<strong>"+rows.length+"</strong> file(s) uploaded.";';
    echo 'const tb=document.querySelector("#results-table tbody");rows.forEach(r=>{';
    echo 'const tr=document.createElement("tr");';
    echo 'const direct=r.direct_url||"";';
    echo 'const view=r.url||"";';
    echo 'const tdFile=document.createElement("td");tdFile.textContent=r.filename||"";';
    echo 'const tdDirect=document.createElement("td");';
    echo 'if(direct){';
    echo '  const wrap=document.createElement("div");wrap.className="link-url-row";';
    echo '  const a=document.createElement("a");a.href=direct;a.textContent=direct;';
    echo '  const btn=document.createElement("button");btn.type="button";btn.className="copy-btn";';
    echo '  btn.setAttribute("data-copy",direct);btn.setAttribute("aria-label","Copy direct link");';
    echo '  btn.textContent="Copy";btn.onclick=function(){sanctumTmpCopy(btn);};';
    echo '  wrap.appendChild(a);wrap.appendChild(btn);tdDirect.appendChild(wrap);';
    echo '}else{tdDirect.textContent="—";}';
    echo 'const tdView=document.createElement("td");';
    echo 'if(view){';
    echo '  const wrap=document.createElement("div");wrap.className="link-url-row";';
    echo '  const a=document.createElement("a");a.href=view;a.textContent="View";';
    echo '  const btn=document.createElement("button");btn.type="button";btn.className="copy-btn";';
    echo '  btn.setAttribute("data-copy",view);btn.setAttribute("aria-label","Copy view link");';
    echo '  btn.textContent="Copy";btn.onclick=function(){sanctumTmpCopy(btn);};';
    echo '  wrap.appendChild(a);wrap.appendChild(btn);tdView.appendChild(wrap);';
    echo '}else{tdView.textContent="—";}';
    echo 'tr.appendChild(tdFile);tr.appendChild(tdDirect);tr.appendChild(tdView);tb.appendChild(tr);});';
    echo 'document.getElementById("results-table").hidden=false;';
    echo 'const payload={status:"success",data:{files:rows,count:rows.length,succeeded:rows.length,failed:0}};';
    echo 'const jsonText=JSON.stringify(payload);';
    echo 'document.getElementById("results-json").textContent=JSON.stringify(payload,null,2);';
    echo 'fetch("/api/v1/qr.php",{method:"POST",headers:{"Content-Type":"application/json"},body:jsonText})';
    echo '.then(r=>r.ok?r.text():Promise.reject(new Error("QR HTTP "+r.status)))';
    echo '.then(html=>{const el=document.getElementById("results-qr");el.innerHTML=html;el.hidden=false;})';
    echo '.catch(()=>{const el=document.getElementById("results-qr");el.innerHTML=\'<div class="qr-wrap"><p class="err">QR unavailable; use the JSON block below.</p></div>\';el.hidden=false;});';
    echo '})();</script>';
    sanctum_tmp_page_shell_end(true);
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
