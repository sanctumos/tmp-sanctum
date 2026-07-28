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
.drop-zone{border:2px dashed #3a4f6e;border-radius:10px;padding:2rem 1.25rem;background:var(--bg2);
  margin:1rem 0;text-align:center;transition:border-color .15s,background .15s;cursor:pointer}
.drop-zone.dragover{border-color:var(--accent);background:#121a2e}
.drop-zone p{margin:.35rem 0;color:var(--muted)}
.drop-zone .hint{font-size:.85rem}
.file-queue{margin:1rem 0}
.file-row{display:flex;flex-direction:column;gap:.35rem;padding:.75rem 0;border-bottom:1px solid var(--border)}
.file-row:last-child{border-bottom:none}
.file-row-head{display:flex;justify-content:space-between;align-items:center;gap:.75rem}
.file-name{font-size:.92rem;word-break:break-all}
.file-status{font-size:.82rem;color:var(--muted);white-space:nowrap}
.file-status.ok{color:var(--ok)}
.file-status.err{color:var(--err)}
.progress{height:6px;background:#1a2438;border-radius:4px;overflow:hidden}
.progress>span{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--accent),var(--accent2));
  transition:width .12s}
.upload-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1rem;align-items:center}
.upload-actions .meta{margin:0}
.results-table td code{font-size:.78rem;word-break:break-all}
.btn{display:inline-block;padding:.65rem 1.15rem;border:none;border-radius:8px;cursor:pointer;
  background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;text-decoration:none;
  font-weight:600;font-size:.95rem}
.btn.secondary{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn:hover{filter:brightness(1.06)}
.links a{color:var(--accent);word-break:break-all}
.links p{margin:.75rem 0}
.link-row{display:flex;align-items:flex-start;gap:.5rem;margin:.75rem 0}
.link-row .link-body{flex:1;min-width:0}
.link-row .link-body strong{display:block;margin-bottom:.2rem}
.copy-btn{flex-shrink:0;padding:.35rem .65rem;border:1px solid var(--border);border-radius:6px;
  background:var(--bg2);color:var(--muted);cursor:pointer;font-size:.78rem;font-weight:600;
  line-height:1.2;white-space:nowrap}
.copy-btn:hover{color:var(--text);border-color:#3a4f6e}
.copy-btn.copied{color:var(--ok);border-color:rgba(61,214,140,.45)}
.json-block{margin:0 0 1rem}
.json-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin:0 0 .5rem}
.json-head .meta{margin:0}
.doc-links{display:flex;flex-wrap:wrap;gap:.75rem;margin:1.25rem 0 0}
.doc-links a{font-size:.9rem;color:var(--muted);text-decoration:none;border-bottom:1px dotted #4a607a}
.doc-links a:hover{color:var(--accent)}
.qr-wrap{display:flex;flex-direction:column;align-items:center;gap:.75rem;margin:1.25rem 0;padding:1rem;
  background:var(--bg2);border:1px dashed #3a4f6e;border-radius:10px}
.qr-wrap img,.qr-wrap svg{max-width:min(300px,100%);height:auto;background:#fff;padding:.5rem;border-radius:8px}
pre.json{background:#05080f;color:#cbd5e1;padding:1rem;border-radius:8px;overflow:auto;font-size:.78rem;line-height:1.45;
  border:1px solid var(--border);margin:0}
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
    echo '<style>' . sanctum_tmp_brand_css() . '</style></head><body><div class="wrap">';
    echo '<header class="brand"><div class="logo" aria-hidden="true">S</div><div class="brand-text">';
    echo '<h1>Sanctum Temp</h1><p>' . htmlspecialchars($subtitle !== '' ? $subtitle : 'Ephemeral file exchange') . '</p>';
    echo '</div></header><main class="card">';
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
        echo ' <span class="meta">' . htmlspecialchars($hint) . '</span><br>';
    } else {
        echo '<br>';
    }
    echo '<a href="' . $safeUrl . '">' . $safeUrl . '</a></div>';
    echo '<button type="button" class="copy-btn" data-copy="' . $attrUrl
        . '" onclick="sanctumTmpCopy(this)" aria-label="Copy ' . $safeLabel . '">Copy</button>';
    echo '</div>';
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
    echo '<h2>Drop files</h2>';
    echo '<p class="lead">Temporary uploads for Sanctum workflows — share links, scan QR codes, or hand bytes to an agent. ';
    echo 'Files expire automatically.</p>';
    echo '<p class="meta">Up to <strong>' . htmlspecialchars((string) $maxMb) . ' MiB</strong> per file · ';
    echo 'TTL ~<strong>' . htmlspecialchars((string) $ttlHours) . ' hours</strong> · ';
    echo 'max <strong>' . htmlspecialchars((string) $maxBatch) . '</strong> files per batch</p>';

    echo '<div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Choose or drop files">';
    echo '<p><strong>Drag files here</strong> or click to browse</p>';
    echo '<p class="hint">Multiple files supported</p>';
    echo '<input type="file" id="file-input" multiple hidden></div>';

    echo '<div class="file-queue" id="file-queue" hidden></div>';
    echo '<div class="upload-actions" id="upload-actions" hidden>';
    echo '<button class="btn" type="button" id="upload-btn">Upload</button>';
    echo '<button class="btn secondary" type="button" id="clear-btn">Clear</button>';
    echo '<p class="meta" id="upload-summary"></p></div>';

    echo '<div class="doc-links">';
    echo '<a href="/docs/api/">API documentation</a>';
    echo '<a href="/AGENTS.md">AGENTS.md</a>';
    echo '</div>';

    echo '<noscript><div class="upload-box"><form method="post" enctype="multipart/form-data" action="/">';
    echo '<p class="meta">JavaScript is disabled — single-file upload only.</p>';
    echo '<input type="file" name="file" required><br>';
    echo '<button class="btn" type="submit">Upload</button></form></div></noscript>';

    echo '<script>' . sanctum_tmp_upload_js($maxBatch) . '</script>';
    sanctum_tmp_page_shell_end();
}

function sanctum_tmp_upload_js(int $maxBatch): string
{
    $maxBatchJs = (int) $maxBatch;
    return <<<JS
(function(){
  const maxBatch = {$maxBatchJs};
  const concurrency = 3;
  const endpoint = '/api/v1/upload.php';
  const drop = document.getElementById('drop-zone');
  const input = document.getElementById('file-input');
  const queue = document.getElementById('file-queue');
  const actions = document.getElementById('upload-actions');
  const uploadBtn = document.getElementById('upload-btn');
  const clearBtn = document.getElementById('clear-btn');
  const summary = document.getElementById('upload-summary');
  let items = [];

  function fmtBytes(n){
    if(n < 1024) return n + ' B';
    if(n < 1048576) return (n/1024).toFixed(1) + ' KiB';
    return (n/1048576).toFixed(1) + ' MiB';
  }

  function setItems(files){
    const list = Array.from(files || []);
    if(!list.length) return;
    const room = maxBatch - items.length;
    if(room <= 0){ alert('Maximum ' + maxBatch + ' files per batch.'); return; }
    const slice = list.slice(0, room);
    if(list.length > room) alert('Only the first ' + room + ' file(s) were added (batch limit ' + maxBatch + ').');
    slice.forEach(f => items.push({file:f, status:'queued', progress:0, result:null, error:''}));
    render();
  }

  function render(){
    queue.innerHTML = '';
    if(!items.length){
      queue.hidden = true;
      actions.hidden = true;
      return;
    }
    queue.hidden = false;
    actions.hidden = false;
    items.forEach((it, idx) => {
      const row = document.createElement('div');
      row.className = 'file-row';
      const head = document.createElement('div');
      head.className = 'file-row-head';
      const name = document.createElement('span');
      name.className = 'file-name';
      name.textContent = it.file.name + ' (' + fmtBytes(it.file.size) + ')';
      const st = document.createElement('span');
      st.className = 'file-status' + (it.status==='done'?' ok':it.status==='error'?' err':'');
      st.textContent = it.status==='queued'?'Queued':it.status==='uploading'?'Uploading…':it.status==='done'?'Done':it.error||'Failed';
      head.appendChild(name);
      head.appendChild(st);
      row.appendChild(head);
      const bar = document.createElement('div');
      bar.className = 'progress';
      const fill = document.createElement('span');
      fill.style.width = (it.progress*100)+'%';
      bar.appendChild(fill);
      row.appendChild(bar);
      queue.appendChild(row);
    });
    const busy = items.some(i => i.status==='uploading');
    uploadBtn.disabled = busy || !items.some(i => i.status==='queued');
    clearBtn.disabled = busy;
    const done = items.filter(i => i.status==='done').length;
    const failed = items.filter(i => i.status==='error').length;
    if(done || failed) summary.textContent = done + ' uploaded' + (failed?(', '+failed+' failed'):'') + '.';
    else summary.textContent = items.length + ' file(s) ready.';
  }

  function uploadOne(it){
    return new Promise(resolve => {
      it.status = 'uploading';
      it.progress = 0;
      render();
      const xhr = new XMLHttpRequest();
      xhr.open('POST', endpoint);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.onprogress = e => {
        if(e.lengthComputable){ it.progress = e.loaded / e.total; render(); }
      };
      xhr.onload = () => {
        let payload = null;
        try { payload = JSON.parse(xhr.responseText); } catch(e) {}
        if(xhr.status >= 200 && xhr.status < 300 && payload && payload.status === 'success'){
          it.status = 'done';
          it.progress = 1;
          it.result = payload.data;
        } else {
          it.status = 'error';
          it.error = (payload && payload.message) ? payload.message : ('HTTP ' + xhr.status);
        }
        render();
        resolve();
      };
      xhr.onerror = () => { it.status='error'; it.error='Network error'; render(); resolve(); };
      const fd = new FormData();
      fd.append('file', it.file, it.file.name);
      xhr.send(fd);
    });
  }

  async function runQueue(){
    uploadBtn.disabled = true;
    const pending = items.filter(i => i.status==='queued');
    let idx = 0;
    async function worker(){
      while(idx < pending.length){
        const cur = pending[idx++];
        await uploadOne(cur);
      }
    }
    const workers = Array.from({length: Math.min(concurrency, pending.length)}, () => worker());
    await Promise.all(workers);
    const results = items.filter(i => i.status==='done' && i.result).map(i => i.result);
    if(results.length){
      sessionStorage.setItem('sanctum_tmp_upload_results', JSON.stringify(results));
      location.href = '/?uploaded=1';
    }
  }

  drop.addEventListener('click', () => input.click());
  drop.addEventListener('keydown', e => { if(e.key==='Enter' || e.key===' ') { e.preventDefault(); input.click(); }});
  input.addEventListener('change', () => { setItems(input.files); input.value=''; });
  ['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('dragover'); }));
  ['dragleave','drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('dragover'); }));
  drop.addEventListener('drop', e => setItems(e.dataTransfer.files));
  uploadBtn.addEventListener('click', runQueue);
  clearBtn.addEventListener('click', () => { if(!items.some(i=>i.status==='uploading')){ items=[]; render(); }});
  render();
})();
JS;
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
    sanctum_tmp_link_row('Direct link', $direct, '(APIs / Venice / scripts)');
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
            echo '<a href="' . htmlspecialchars($direct) . '">' . htmlspecialchars($direct) . '</a>';
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

    echo '<p class="meta">Raw API response</p>';
    echo '<pre class="json">' . htmlspecialchars($json) . '</pre>';
    echo '<p><a class="btn" href="/">Upload more</a></p>';
    sanctum_tmp_page_shell_end();
}

/** Browser page after JS multi-upload completes (reads sessionStorage client-side). */
function render_upload_results_page(): void
{
    sanctum_tmp_page_shell_start('Upload complete', 'Ready to share');
    echo '<h2>Upload complete</h2>';
    echo '<p class="lead" id="results-lead">Loading results…</p>';
    echo '<table class="results-table" id="results-table" hidden><thead><tr><th>File</th><th>Direct link</th><th>View</th></tr></thead><tbody></tbody></table>';
    echo '<div id="results-qr" hidden></div>';
    echo '<p class="meta">Raw JSON</p><pre class="json" id="results-json"></pre>';
    echo '<p><a class="btn" href="/">Upload more</a></p>';
    echo '<script>(function(){';
    echo 'const raw=sessionStorage.getItem("sanctum_tmp_upload_results");';
    echo 'if(!raw){document.getElementById("results-lead").textContent="No results in this session.";return;}';
    echo 'let rows;try{rows=JSON.parse(raw);}catch(e){document.getElementById("results-lead").textContent="Could not read results.";return;}';
    echo 'sessionStorage.removeItem("sanctum_tmp_upload_results");';
    echo 'document.getElementById("results-lead").innerHTML="<strong>"+rows.length+"</strong> file(s) uploaded.";';
    echo 'const tb=document.querySelector("#results-table tbody");rows.forEach(r=>{';
    echo 'const tr=document.createElement("tr");';
    echo 'tr.innerHTML="<td>"+escapeHtml(r.filename||"")+"</td>"+';
    echo '"<td><a href=\\""+escapeHtml(r.direct_url||"")+"\\">"+escapeHtml(r.direct_url||"")+"</a></td>"+';
    echo '"<td><a href=\\""+escapeHtml(r.url||"")+"\\">View</a></td>";tb.appendChild(tr);});';
    echo 'document.getElementById("results-table").hidden=false;';
    echo 'const payload={status:"success",data:{files:rows,count:rows.length,succeeded:rows.length,failed:0}};';
    echo 'const jsonText=JSON.stringify(payload);';
    echo 'document.getElementById("results-json").textContent=JSON.stringify(payload,null,2);';
    echo 'fetch("/api/v1/qr.php",{method:"POST",headers:{"Content-Type":"application/json"},body:jsonText})';
    echo '.then(r=>r.ok?r.text():Promise.reject(new Error("QR HTTP "+r.status)))';
    echo '.then(html=>{const el=document.getElementById("results-qr");el.innerHTML=html;el.hidden=false;})';
    echo '.catch(()=>{const el=document.getElementById("results-qr");el.innerHTML=\'<div class="qr-wrap"><p class="err">QR unavailable; use the JSON block below.</p></div>\';el.hidden=false;});';
    echo 'function escapeHtml(s){return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");}';
    echo '})();</script>';
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
