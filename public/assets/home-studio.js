/**
 * Sanctum Temp browser studio — QR-first for encodable payloads, file-host fallback.
 * upload.php contract for agents is unchanged; this UI is human click-through only.
 */
(function () {
  'use strict';

  var encodeEndpoint = '/api/v1/encode.php';
  var uploadEndpoint = '/api/v1/upload.php';
  var softMaxBytes = 3500; // try encode; server falls back on capacity failure

  var $tabQr = document.getElementById('tab-qr');
  var $tabFiles = document.getElementById('tab-files');
  var $panelQr = document.getElementById('panel-qr');
  var $panelFiles = document.getElementById('panel-files');
  var $content = document.getElementById('qr-content');
  var $typePills = document.querySelectorAll('.type-pill');
  var $ecl = document.getElementById('qr-ecl');
  var $format = document.getElementById('qr-format');
  var $persist = document.getElementById('qr-persist');
  var $logoFile = document.getElementById('qr-logo-file');
  var $logoPreview = document.getElementById('qr-logo-preview');
  var $logoImg = document.getElementById('qr-logo-img');
  var $logoRemove = document.getElementById('qr-logo-remove');
  var $logoSize = document.getElementById('qr-logo-size');
  var $logoSizeVal = document.getElementById('qr-logo-size-val');
  var $logoSizeField = document.getElementById('qr-logo-size-field');
  var $makeBtn = document.getElementById('qr-make');
  var $hostBtn = document.getElementById('qr-host-file');
  var $status = document.getElementById('qr-status');
  var $suggest = document.getElementById('qr-suggest');
  var $suggestText = document.getElementById('qr-suggest-text');
  var $suggestAccept = document.getElementById('qr-suggest-accept');
  var $suggestDismiss = document.getElementById('qr-suggest-dismiss');
  var $result = document.getElementById('qr-result');
  var $resultImg = document.getElementById('qr-result-img');
  var $resultMeta = document.getElementById('qr-result-meta');
  var $resultEncoded = document.getElementById('qr-result-encoded');
  var $forceFile = document.getElementById('force-file-host');

  var selectedType = 'auto';
  var logoBase64 = null;
  var pendingSuggestion = null;

  function setTab( which) {
    var qr = which === 'qr';
    $tabQr.classList.toggle('is-active', qr);
    $tabFiles.classList.toggle('is-active', !qr);
    $tabQr.setAttribute('aria-selected', qr ? 'true' : 'false');
    $tabFiles.setAttribute('aria-selected', qr ? 'false' : 'true');
    $panelQr.hidden = !qr;
    $panelFiles.hidden = qr;
  }

  if ($tabQr) $tabQr.addEventListener('click', function () { setTab('qr'); });
  if ($tabFiles) $tabFiles.addEventListener('click', function () { setTab('files'); });

  $typePills.forEach(function (pill) {
    pill.addEventListener('click', function () {
      $typePills.forEach(function (p) {
        p.classList.remove('is-active');
        p.setAttribute('aria-checked', 'false');
      });
      pill.classList.add('is-active');
      pill.setAttribute('aria-checked', 'true');
      selectedType = pill.getAttribute('data-type') || 'auto';
    });
  });

  if ($logoSize) {
    $logoSize.addEventListener('input', function () {
      $logoSizeVal.textContent = $logoSize.value + '%';
    });
  }

  if ($logoFile) {
    $logoFile.addEventListener('change', function () {
      var file = $logoFile.files && $logoFile.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) {
        setStatus('Logo is too large — keep it under 2 MB.', true);
        return;
      }
      var reader = new FileReader();
      reader.onload = function () {
        logoBase64 = String(reader.result || '');
        $logoImg.src = logoBase64;
        $logoPreview.hidden = false;
        $logoSizeField.hidden = false;
      };
      reader.readAsDataURL(file);
    });
  }

  if ($logoRemove) {
    $logoRemove.addEventListener('click', function () {
      logoBase64 = null;
      $logoFile.value = '';
      $logoPreview.hidden = true;
      $logoSizeField.hidden = true;
    });
  }

  function setStatus(msg, isErr) {
    $status.textContent = msg || '';
    $status.classList.toggle('err', !!isErr);
  }

  function hideSuggest() {
    pendingSuggestion = null;
    $suggest.hidden = true;
  }

  function showSuggest(suggestion, reason) {
    pendingSuggestion = suggestion;
    $suggestText.textContent = (reason ? reason + ': ' : '') + suggestion;
    $suggest.hidden = false;
  }

  if ($suggestAccept) {
    $suggestAccept.addEventListener('click', function () {
      if (pendingSuggestion) {
        var s = pendingSuggestion;
        if (s.indexOf('mailto:') === 0) s = s.slice(7);
        if (s.indexOf('tel:') === 0) s = s.slice(4);
        $content.value = s;
      }
      hideSuggest();
    });
  }
  if ($suggestDismiss) $suggestDismiss.addEventListener('click', hideSuggest);

  function buildBody(extra) {
    var body = {
      content: $content.value,
      type: selectedType,
      format: ($format && $format.value) || 'svg',
      ecl: ($ecl && $ecl.value) || 'M',
      scale: 5,
      persist: !!($persist && $persist.checked),
      logo_size_pct: $logoSize ? parseInt($logoSize.value, 10) || 22 : 22
    };
    if (logoBase64) body.logo_base64 = logoBase64;
    if (extra) {
      Object.keys(extra).forEach(function (k) { body[k] = extra[k]; });
    }
    return body;
  }

  function showQrResult(data) {
    $result.hidden = false;
    $resultImg.src = data.data_uri;
    $resultEncoded.textContent = data.encoded;
    var bits = [
      'Encoded as ' + (data.type || 'text'),
      'ECL ' + (data.ecl_effective || '?'),
      (data.format || 'svg').toUpperCase()
    ];
    if (data.persisted && data.direct_url) {
      bits.push('Saved: ' + data.direct_url);
    } else {
      bits.push('Ephemeral (not stored on disk)');
    }
    $resultMeta.textContent = bits.join(' · ');
    if (data.suggestion) {
      showSuggest(data.suggestion, data.suggestion_reason || 'suggestion');
    }
  }

  async function encodeContent(extra) {
    setStatus('Encoding…');
    hideSuggest();
    $result.hidden = true;
    var res = await fetch(encodeEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(buildBody(extra || {}))
    });
    var payload = null;
    try { payload = await res.json(); } catch (e) {}
    if (res.ok && payload && payload.status === 'success') {
      setStatus('QR ready.');
      showQrResult(payload.data);
      return { ok: true, data: payload.data };
    }
    var msg = (payload && payload.message) ? payload.message : ('HTTP ' + res.status);
    var code = payload && payload.code;
    setStatus(msg, true);
    return { ok: false, code: code, message: msg, payload: payload };
  }

  async function hostAsFile(text, filename) {
    setStatus('Hosting as short-lived file…');
    var blob = new Blob([text], { type: 'text/plain' });
    var fd = new FormData();
    fd.append('file', blob, filename || 'payload.txt');
    var res = await fetch(uploadEndpoint, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      body: fd
    });
    var payload = null;
    try { payload = await res.json(); } catch (e) {}
    if (res.ok && payload && payload.status === 'success') {
      sessionStorage.setItem('sanctum_tmp_upload_results', JSON.stringify([payload.data]));
      location.href = '/?uploaded=1';
      return;
    }
    setStatus((payload && payload.message) || ('Upload failed (HTTP ' + res.status + ')'), true);
  }

  if ($makeBtn) {
    $makeBtn.addEventListener('click', async function () {
      $makeBtn.disabled = true;
      try {
        var out = await encodeContent();
        if (!out.ok && out.code === 'qr_capacity') {
          setStatus(out.message + ' — use “Host as file” or the Files tab.', true);
        }
      } finally {
        $makeBtn.disabled = false;
      }
    });
  }

  if ($hostBtn) {
    $hostBtn.addEventListener('click', function () {
      var text = $content.value;
      if (!text) { setStatus('Nothing to host.', true); return; }
      hostAsFile(text, 'payload.txt');
    });
  }

  // ——— Files tab: QR-first for small files unless forced ———
  var maxBatch = parseInt(document.body.getAttribute('data-max-batch') || '20', 10) || 20;
  var concurrency = 3;
  var drop = document.getElementById('drop-zone');
  var input = document.getElementById('file-input');
  var queue = document.getElementById('file-queue');
  var actions = document.getElementById('upload-actions');
  var uploadBtn = document.getElementById('upload-btn');
  var clearBtn = document.getElementById('clear-btn');
  var summary = document.getElementById('upload-summary');
  var items = [];

  function fmtBytes(n) {
    if (n < 1024) return n + ' B';
    if (n < 1048576) return (n / 1024).toFixed(1) + ' KiB';
    return (n / 1048576).toFixed(1) + ' MiB';
  }

  function setItems(files) {
    var list = Array.from(files || []);
    if (!list.length) return;
    var room = maxBatch - items.length;
    if (room <= 0) { alert('Maximum ' + maxBatch + ' files per batch.'); return; }
    var slice = list.slice(0, room);
    if (list.length > room) alert('Only the first ' + room + ' file(s) were added (batch limit ' + maxBatch + ').');
    slice.forEach(function (f) {
      items.push({ file: f, status: 'queued', progress: 0, result: null, error: '', mode: null });
    });
    renderFiles();
  }

  function renderFiles() {
    queue.innerHTML = '';
    if (!items.length) {
      queue.hidden = true;
      actions.hidden = true;
      return;
    }
    queue.hidden = false;
    actions.hidden = false;
    items.forEach(function (it) {
      var row = document.createElement('div');
      row.className = 'file-row';
      var head = document.createElement('div');
      head.className = 'file-row-head';
      var name = document.createElement('span');
      name.className = 'file-name';
      name.textContent = it.file.name + ' (' + fmtBytes(it.file.size) + ')';
      var st = document.createElement('span');
      st.className = 'file-status' + (it.status === 'done' ? ' ok' : it.status === 'error' ? ' err' : '');
      var label = 'Queued';
      if (it.status === 'uploading') label = 'Working…';
      else if (it.status === 'done') label = it.mode === 'qr' ? 'QR ready' : 'Uploaded';
      else if (it.status === 'error') label = it.error || 'Failed';
      st.textContent = label;
      head.appendChild(name);
      head.appendChild(st);
      row.appendChild(head);
      var bar = document.createElement('div');
      bar.className = 'progress';
      var fill = document.createElement('span');
      fill.style.width = (it.progress * 100) + '%';
      bar.appendChild(fill);
      row.appendChild(bar);
      queue.appendChild(row);
    });
    var busy = items.some(function (i) { return i.status === 'uploading'; });
    uploadBtn.disabled = busy || !items.some(function (i) { return i.status === 'queued'; });
    clearBtn.disabled = busy;
    var done = items.filter(function (i) { return i.status === 'done'; }).length;
    var failed = items.filter(function (i) { return i.status === 'error'; }).length;
    if (done || failed) summary.textContent = done + ' done' + (failed ? (', ' + failed + ' failed') : '') + '.';
    else summary.textContent = items.length + ' file(s) ready.';
  }

  function readFileAsText(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload = function () { resolve(String(reader.result || '')); };
      reader.onerror = function () { reject(new Error('read failed')); };
      reader.readAsText(file);
    });
  }

  async function tryQrFile(it) {
    var text = await readFileAsText(it.file);
    var res = await fetch(encodeEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        content: text,
        type: 'text',
        format: 'svg',
        ecl: 'M',
        persist: false,
        filename: it.file.name
      })
    });
    var payload = null;
    try { payload = await res.json(); } catch (e) {}
    if (res.ok && payload && payload.status === 'success') {
      it.status = 'done';
      it.progress = 1;
      it.mode = 'qr';
      it.result = payload.data;
      return true;
    }
    return false;
  }

  function uploadOne(it) {
    return new Promise(function (resolve) {
      it.status = 'uploading';
      it.progress = 0;
      it.mode = 'file';
      renderFiles();
      var xhr = new XMLHttpRequest();
      xhr.open('POST', uploadEndpoint);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) { it.progress = e.loaded / e.total; renderFiles(); }
      };
      xhr.onload = function () {
        var payload = null;
        try { payload = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status >= 200 && xhr.status < 300 && payload && payload.status === 'success') {
          it.status = 'done';
          it.progress = 1;
          it.result = payload.data;
        } else {
          it.status = 'error';
          it.error = (payload && payload.message) ? payload.message : ('HTTP ' + xhr.status);
        }
        renderFiles();
        resolve();
      };
      xhr.onerror = function () { it.status = 'error'; it.error = 'Network error'; renderFiles(); resolve(); };
      var fd = new FormData();
      fd.append('file', it.file, it.file.name);
      xhr.send(fd);
    });
  }

  async function processOne(it) {
    it.status = 'uploading';
    renderFiles();
    var force = $forceFile && $forceFile.checked;
    if (!force && it.file.size > 0 && it.file.size <= softMaxBytes) {
      try {
        var ok = await tryQrFile(it);
        if (ok) { renderFiles(); return; }
      } catch (e) { /* fall through */ }
    }
    await uploadOne(it);
  }

  async function runQueue() {
    uploadBtn.disabled = true;
    var pending = items.filter(function (i) { return i.status === 'queued'; });
    var idx = 0;
    async function worker() {
      while (idx < pending.length) {
        var cur = pending[idx++];
        await processOne(cur);
      }
    }
    await Promise.all(Array.from({ length: Math.min(concurrency, pending.length) }, function () { return worker(); }));

    var qrDone = items.filter(function (i) { return i.status === 'done' && i.mode === 'qr' && i.result; });
    var fileDone = items.filter(function (i) { return i.status === 'done' && i.mode === 'file' && i.result; });

    if (qrDone.length === 1 && fileDone.length === 0) {
      setTab('qr');
      showQrResult(qrDone[0].result);
      setStatus('Encoded file contents as QR (ephemeral). Use Files → “Force file host” to store bytes.');
      return;
    }
    if (fileDone.length) {
      sessionStorage.setItem('sanctum_tmp_upload_results', JSON.stringify(fileDone.map(function (i) { return i.result; })));
      location.href = '/?uploaded=1';
    }
  }

  if (drop) {
    drop.addEventListener('click', function () { input.click(); });
    drop.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
    });
    input.addEventListener('change', function () { setItems(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('dragover'); });
    });
    drop.addEventListener('drop', function (e) { setItems(e.dataTransfer.files); });
    uploadBtn.addEventListener('click', runQueue);
    clearBtn.addEventListener('click', function () {
      if (!items.some(function (i) { return i.status === 'uploading'; })) { items = []; renderFiles(); }
    });
    renderFiles();
  }
})();
