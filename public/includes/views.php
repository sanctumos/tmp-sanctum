<?php

declare(strict_types=1);

use SanctumTmp\ClientMode;
use SanctumTmp\QrSvg;
use SanctumTmp\UploadApi;

require_once __DIR__ . '/markdown.php';

/**
 * Load SanctumOS web brand pack from public/branding/.
 * Self-hosters: replace brand.css / logo.svg / site.json (see docs/BRANDING.md).
 */
function sanctum_tmp_branding_dir(): string
{
    return dirname(__DIR__) . '/branding';
}

/** @return array{web_name:string,web_subtitle:string,footer_org_name:string,footer_org_url:string,logo_text:string,logo_src:string} */
function sanctum_tmp_site_brand(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }
    $defaults = [
        'web_name' => 'Sanctum Temp',
        'web_subtitle' => 'Ephemeral file exchange',
        'footer_org_name' => 'SanctumOS',
        'footer_org_url' => 'https://sanctumos.org/',
        'logo_text' => 'S',
        // Prefer brand pack; assets/sanctum-logo.svg is the same mark for deploys.
        'logo_src' => '/branding/logo.svg',
    ];
    $path = sanctum_tmp_branding_dir() . '/site.json';
    if (!is_file($path)) {
        $cached = $defaults;
        return $cached;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        $cached = $defaults;
        return $cached;
    }
    $cached = array_merge($defaults, $decoded);
    return $cached;
}

function sanctum_tmp_brand_css(): string
{
    // SanctumOS palette lives in public/branding/brand.css (see docs/BRANDING.md).
    $path = sanctum_tmp_branding_dir() . '/brand.css';
    if (is_file($path)) {
        $css = (string) file_get_contents($path);
        if ($css !== '') {
            return $css;
        }
    }
    // Minimal SanctumOS-tinted fallback if brand.css is missing after a bad deploy.
    return ':root{--bg:#F9F9F9;--text:#333;--logo-primary:#1c80aa;--muted:#5a6a75;--card:#fff;--border:#ccc}'
        . 'body{margin:0;font-family:system-ui,sans-serif;background:var(--bg);color:var(--text)}'
        . '.wrap{max-width:760px;margin:0 auto;padding:2rem 1.25rem}.card{border:1px solid var(--border);padding:1.5rem}';
}

/** Resolve a site-relative logo path under public/. */
function sanctum_tmp_logo_fs(string $logoSrc): string
{
    $path = parse_url($logoSrc, PHP_URL_PATH);
    $rel = ltrim(is_string($path) && $path !== '' ? $path : $logoSrc, '/');
    return dirname(__DIR__) . '/' . $rel;
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
    $brand = sanctum_tmp_site_brand();
    $product = (string) $brand['web_name'];
    $sub = $subtitle !== '' ? $subtitle : (string) $brand['web_subtitle'];
    $logoSrc = (string) $brand['logo_src'];
    $logoText = (string) $brand['logo_text'];
    $logoFs = sanctum_tmp_logo_fs($logoSrc);
    $org = (string) $brand['footer_org_name'];
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' · ' . htmlspecialchars($product) . '</title>';
    echo '<link rel="icon" href="' . htmlspecialchars($logoSrc) . '" type="image/svg+xml">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">';
    echo '<style>' . sanctum_tmp_brand_css() . '</style></head><body><div class="wrap">';
    echo '<div class="site-bar"><header class="brand">';
    echo '<a class="brand-home" href="/">';
    if (is_file($logoFs)) {
        echo '<img class="logo-img" src="' . htmlspecialchars($logoSrc) . '" width="44" height="44" alt="'
            . htmlspecialchars($org) . '">';
    } else {
        echo '<div class="logo" aria-hidden="true">' . htmlspecialchars($logoText) . '</div>';
    }
    echo '<div class="brand-text"><h1>' . htmlspecialchars($product) . '</h1>';
    echo '<p>' . htmlspecialchars($sub) . '</p>';
    echo '</div></a></header></div><main class="card">';
}

function sanctum_tmp_page_shell_end(bool $withCopy = false): void
{
    $brand = sanctum_tmp_site_brand();
    echo '</main><footer>Part of <a href="' . htmlspecialchars((string) $brand['footer_org_url']) . '">'
        . htmlspecialchars((string) $brand['footer_org_name']) . '</a> · ';
    echo '<a href="/docs/api/">API</a> · <a href="/AGENTS.md">Agents</a>'
        . ' · <a href="/docs/android/">Android</a>'
        . ' · <a href="/app/">Get the app</a></footer></div>';
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
    echo '<a href="/app/">Get the app</a>';
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

/**
 * Load published Sanctum Share release metadata from public/downloads/release.json.
 *
 * @return array{
 *   product:string,package_id:string,version_name:string,version_code:int,
 *   build_type:string,apk:string,apk_versioned:string,sha256_file:string,
 *   sha256:string,source_repo:string,source_path:string,published_at:string
 * }
 */
function sanctum_tmp_app_release(): array
{
    $defaults = [
        'product' => 'Sanctum Share',
        'package_id' => 'org.sanctumos.share',
        'version_name' => '0.1.0',
        'version_code' => 1,
        'build_type' => 'debug',
        'apk' => 'sanctum-share-latest.apk',
        'apk_versioned' => 'sanctum-share-0.1.0.apk',
        'sha256_file' => 'SHA256SUMS',
        'sha256' => '',
        'source_repo' => 'https://github.com/sanctumos/tmp-sanctum',
        'source_path' => 'android/',
        'published_at' => '',
    ];
    $path = dirname(__DIR__) . '/downloads/release.json';
    if (!is_file($path)) {
        return $defaults;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    return array_merge($defaults, $decoded);
}

/** Newbie-friendly APK download + sideload + checksum page. */
function render_app_download(): void
{
    $rel = sanctum_tmp_app_release();
    $apkName = (string) $rel['apk'];
    $apkUrl = '/downloads/' . rawurlencode($apkName);
    $sumsUrl = '/downloads/' . rawurlencode((string) $rel['sha256_file']);
    $versionedUrl = '/downloads/' . rawurlencode((string) $rel['apk_versioned']);
    $sha = (string) $rel['sha256'];
    $ver = (string) $rel['version_name'];
    $repo = (string) $rel['source_repo'];
    $shaGithub = rtrim($repo, '/') . '/blob/main/public/downloads/SHA256SUMS';
    $androidSrc = rtrim($repo, '/') . '/tree/main/android';
    $product = (string) $rel['product'];

    sanctum_tmp_page_shell_start('Get the app', $product . ' for Android');
    echo '<h2 class="doc-title">Get ' . htmlspecialchars($product) . '</h2>';
    echo '<p class="lead">Free Android app that appears in your phone’s <strong>Share</strong> menu '
        . 'and uploads files to this site (or your own Sanctum Temp host). '
        . 'Not on the Play Store — you install the APK yourself (sideload). Takes about a minute.</p>';

    echo '<p><a class="btn" href="' . htmlspecialchars($apkUrl) . '" download>Download APK '
        . '(v' . htmlspecialchars($ver) . ')</a> '
        . '<a class="btn secondary" href="' . htmlspecialchars($sumsUrl) . '">SHA256 checksums</a></p>';
    echo '<p class="meta">Package <code>' . htmlspecialchars((string) $rel['package_id']) . '</code>'
        . ' · build <code>' . htmlspecialchars((string) $rel['build_type']) . '</code>'
        . ' · also <a href="' . htmlspecialchars($versionedUrl) . '">'
        . htmlspecialchars((string) $rel['apk_versioned']) . '</a>'
        . ' · source <a href="' . htmlspecialchars($androidSrc) . '">android/</a> on GitHub</p>';

    echo '<h2>Install on your phone (sideload)</h2>';
    echo '<ol class="lead" style="padding-left:1.25rem">';
    echo '<li style="margin:.5rem 0"><strong>Download</strong> the APK with the button above '
        . '(on your phone’s browser is easiest).</li>';
    echo '<li style="margin:.5rem 0">Open the downloaded file. If Android says the install is blocked, '
        . 'tap <strong>Settings</strong> and allow installs from this browser (Chrome, Firefox, etc.). '
        . 'On older phones this is “Install unknown apps” or “Unknown sources”.</li>';
    echo '<li style="margin:.5rem 0">Tap <strong>Install</strong>, then <strong>Open</strong>.</li>';
    echo '<li style="margin:.5rem 0">Try it: open Photos (or Files) → pick something → <strong>Share</strong> → '
        . '<strong>Sanctum Share</strong>. You should get a link back.</li>';
    echo '</ol>';
    echo '<p class="meta">Default upload host is <code>https://tmp.sanctumos.org</code>. '
        . 'Change it anytime in the app’s <strong>Settings</strong> if you self-host Temp.</p>';

    echo '<h2>Check the file isn’t tampered with</h2>';
    echo '<p class="lead">Every published APK has a SHA-256 hash checked into the '
        . '<a href="' . htmlspecialchars($repo) . '">public GitHub repo</a>. '
        . 'If the hash of the file you downloaded matches the hash in git, the APK matches what SanctumOS published.</p>';
    if ($sha !== '') {
        echo '<p><strong>Expected SHA-256 for this release:</strong></p>';
        echo '<pre class="code-block"><code>' . htmlspecialchars($sha) . '  ' . htmlspecialchars($apkName)
            . '</code></pre>';
    }
    echo '<h3>On a computer</h3>';
    echo '<ol class="lead" style="padding-left:1.25rem">';
    echo '<li style="margin:.5rem 0">Download the APK (and optionally '
        . '<a href="' . htmlspecialchars($sumsUrl) . '">SHA256SUMS</a>).</li>';
    echo '<li style="margin:.5rem 0">In a terminal, in the folder with the APK:</li>';
    echo '</ol>';
    echo '<pre class="code-block"><code>sha256sum ' . htmlspecialchars($apkName) . "\n"
        . '# or, if you also saved SHA256SUMS next to it:' . "\n"
        . 'sha256sum -c SHA256SUMS</code></pre>';
    echo '<p class="meta">On macOS: <code>shasum -a 256 ' . htmlspecialchars($apkName) . '</code>. '
        . 'On Windows (PowerShell): <code>Get-FileHash .\\' . htmlspecialchars($apkName)
        . ' -Algorithm SHA256</code>.</p>';
    echo '<h3>Compare to the open-source repo</h3>';
    echo '<ol class="lead" style="padding-left:1.25rem">';
    echo '<li style="margin:.5rem 0">Open the checksum file in git: '
        . '<a href="' . htmlspecialchars($shaGithub) . '">public/downloads/SHA256SUMS</a>.</li>';
    echo '<li style="margin:.5rem 0">Confirm the hash for <code>' . htmlspecialchars($apkName)
        . '</code> (or the versioned name) matches what <code>sha256sum</code> printed.</li>';
    echo '<li style="margin:.5rem 0">If the site’s hash and GitHub’s hash disagree, <strong>don’t install</strong> — '
        . 'something is wrong with one of the copies.</li>';
    echo '</ol>';
    echo '<p class="meta">Optional: clone the repo and build from <code>android/</code> yourself '
        . '(see <a href="/docs/android/">Android docs</a>). A self-built APK may have a '
        . '<em>different</em> hash than this published file (timestamps / signing). '
        . 'That’s normal — the published SHA-256 is the integrity check for <em>this</em> download, '
        . 'not a claim that every rebuild is byte-identical.</p>';

    echo '<h2>Forks &amp; self-hosters</h2>';
    echo '<p class="lead">Want your own logo, name, or default upload host? Branding and publishing steps '
        . 'are in the repo:</p>';
    echo '<ul class="lead">';
    echo '<li><a href="/docs/branding/">Branding</a> — swap website + app assets</li>';
    echo '<li><a href="/docs/android/">Android</a> — build, and publish your own APK + checksums under '
        . '<code>public/downloads/</code></li>';
    echo '</ul>';

    echo '<div class="doc-links"><a href="/docs/api/">API</a><a href="/docs/android/">Android</a>'
        . '<a href="/docs/branding/">Branding</a><a href="/app/">Get the app</a><a href="/">Upload</a></div>';
    sanctum_tmp_page_shell_end(true);
}

function render_api_docs(): void
{
    render_markdown_doc('API.md', 'HTTP API', 'HTTP reference');
}

function render_markdown_doc(string $relative, string $heading, string $subtitle = 'Documentation'): void
{
    $path = sanctum_tmp_doc_path($relative);
    if ($path === null) {
        http_response_code(404);
        render_home_plain();
        return;
    }

    if (!ClientMode::wantsBrowserHtml()) {
        sanctum_tmp_send_markdown_file($path, $relative);
        return;
    }

    $markdown = (string) file_get_contents($path);
    sanctum_tmp_page_shell_start($heading, $subtitle);
    echo '<h2 class="doc-title">' . htmlspecialchars($heading) . '</h2>';
    echo sanctum_tmp_markdown_to_html($markdown);
    echo '<div class="doc-links"><a href="/docs/api/">API</a><a href="/docs/android/">Android</a>'
        . '<a href="/docs/branding/">Branding</a><a href="/app/">Get the app</a><a href="/">Upload</a></div>';
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
