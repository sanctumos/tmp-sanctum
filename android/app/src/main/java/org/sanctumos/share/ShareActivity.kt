// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.view.View
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.sanctumos.share.databinding.ActivityShareBinding
import java.nio.charset.Charset

class ShareActivity : AppCompatActivity() {

    private lateinit var binding: ActivityShareBinding
    private lateinit var prefs: Prefs
    private lateinit var history: HistoryStore
    private val uploadClient = UploadClient()
    private val encodeClient = EncodeClient()
    private var primaryDirect: String? = null
    private var primaryView: String? = null
    private var pendingText: String? = null
    private var qrPayload: String? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityShareBinding.inflate(layoutInflater)
        setContentView(binding.root)
        prefs = Prefs(this)
        history = HistoryStore(this)
        binding.textHost.text = getString(R.string.host_line, prefs.baseUrl())
        binding.buttonDone.setOnClickListener { finish() }
        binding.buttonCopyDirect.setOnClickListener { copy(primaryDirect) }
        binding.buttonCopyView.setOnClickListener { copy(primaryView) }
        binding.buttonCopyPayload.setOnClickListener { copy(qrPayload) }
        binding.buttonHostFile.setOnClickListener {
            val text = pendingText ?: return@setOnClickListener
            hostTextAsFile(text)
        }
        binding.buttonOpen.setOnClickListener {
            val u = primaryView ?: primaryDirect ?: return@setOnClickListener
            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(u)))
        }
        handleIntent(intent)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handleIntent(intent)
    }

    private fun handleIntent(intent: Intent?) {
        if (intent == null) {
            showError("No share intent")
            return
        }
        // Text → QR immediately (local), same “open and go” feel as file upload.
        if (prefs.preferQrForText()) {
            val text = extractTextCandidate(intent)
            if (text != null) {
                startTextQrWorkflow(text)
                return
            }
        }
        lifecycleScope.launch {
            resetResultUi()
            binding.progress.visibility = View.VISIBLE
            binding.textStatus.setText(R.string.uploading)
            val outcome = withContext(Dispatchers.IO) {
                runCatching { resolveFileShare(intent) }
            }
            binding.progress.visibility = View.GONE
            outcome.onSuccess { showSuccess(it) }
                .onFailure { e -> showError(e.message ?: getString(R.string.upload_failed)) }
        }
    }

    /**
     * Instant path: paint a QR from the shared text right away, then optionally
     * refine via encode.php (URL/email normalize). Override = Do file instead.
     */
    private fun startTextQrWorkflow(text: String) {
        resetResultUi()
        pendingText = text
        binding.progress.visibility = View.VISIBLE
        binding.textStatus.setText(R.string.encoding_qr)

        val localOk = try {
            QrBitmap.fromPayload(text)
            true
        } catch (_: Exception) {
            false
        }

        if (localOk) {
            showQr(text, sourceText = text, refining = true)
            lifecycleScope.launch {
                val refined = withContext(Dispatchers.IO) {
                    runCatching { encodeClient.encode(prefs.encodeUrl(), text) }.getOrNull()
                }
                binding.progress.visibility = View.GONE
                when (refined) {
                    is EncodeResult.Success -> {
                        if (refined.encoded.isNotBlank() && refined.encoded != text) {
                            showQr(refined.encoded, sourceText = text, refining = false)
                        } else {
                            binding.textStatus.setText(R.string.qr_ready)
                        }
                    }
                    is EncodeResult.CapacityExceeded -> {
                        // Local QR already shown; keep it. User can still Do file instead.
                        binding.textStatus.setText(R.string.qr_ready)
                    }
                    else -> binding.textStatus.setText(R.string.qr_ready)
                }
            }
            return
        }

        // Won't fit a local QR — try server encode, else upload as file.
        lifecycleScope.launch {
            binding.progress.visibility = View.VISIBLE
            binding.textStatus.setText(R.string.encoding_qr)
            val outcome = withContext(Dispatchers.IO) {
                runCatching { encodeOrFallback(text) }
            }
            binding.progress.visibility = View.GONE
            outcome.onSuccess { applyEncodeOutcome(it) }
                .onFailure { e ->
                    pendingText = text
                    showError(e.message ?: getString(R.string.upload_failed))
                }
        }
    }

    private sealed class EncodeOutcome {
        data class Qr(val encoded: String, val sourceText: String) : EncodeOutcome()
        data class Upload(val batch: UploadBatchResult, val note: String?) : EncodeOutcome()
        data class Failed(val message: String, val sourceText: String) : EncodeOutcome()
    }

    private fun extractTextCandidate(intent: Intent): String? {
        if (intent.action != Intent.ACTION_SEND) return null
        val stream = parcelableUri(intent, Intent.EXTRA_STREAM)
        val text = intent.getStringExtra(Intent.EXTRA_TEXT)
        if (stream != null) {
            val asText = maybeReadTextCandidate(stream) ?: return null
            return asText.takeIf { TextSharePolicy.isQrCandidateText(it) }
        }
        if (!text.isNullOrBlank() && TextSharePolicy.isQrCandidateText(text)) {
            return text
        }
        return null
    }

    private fun resolveFileShare(intent: Intent): UploadBatchResult {
        val action = intent.action
        when (action) {
            Intent.ACTION_SEND -> {
                val text = intent.getStringExtra(Intent.EXTRA_TEXT)
                val stream = parcelableUri(intent, Intent.EXTRA_STREAM)
                when {
                    stream != null -> {
                        val file = ContentResolverUtil.toUploadFile(this, stream)
                        return try {
                            uploadClient.upload(prefs.uploadUrl(), listOf(file))
                        } finally {
                            file.file.delete()
                        }
                    }
                    !text.isNullOrBlank() -> return uploadText(text)
                    else -> error("Share had no file or text")
                }
            }
            Intent.ACTION_SEND_MULTIPLE -> {
                val streams = parcelableUriList(intent, Intent.EXTRA_STREAM)
                    ?: error("No files in multi-share")
                val files = streams.map { ContentResolverUtil.toUploadFile(this, it) }
                return try {
                    uploadClient.upload(prefs.uploadUrl(), files)
                } finally {
                    files.forEach { it.file.delete() }
                }
            }
            else -> error("Unsupported action: $action")
        }
    }

    private fun encodeOrFallback(text: String): EncodeOutcome {
        return when (val enc = encodeClient.encode(prefs.encodeUrl(), text)) {
            is EncodeResult.Success -> {
                if (enc.encoded.isBlank()) {
                    EncodeOutcome.Upload(uploadText(text), getString(R.string.qr_fallback_upload))
                } else {
                    EncodeOutcome.Qr(enc.encoded, text)
                }
            }
            is EncodeResult.CapacityExceeded ->
                EncodeOutcome.Upload(uploadText(text), getString(R.string.qr_fallback_upload))
            is EncodeResult.Failed ->
                EncodeOutcome.Failed(enc.message, text)
        }
    }

    private fun uploadText(text: String): UploadBatchResult {
        val file = ContentResolverUtil.textToUploadFile(this, text)
        return try {
            uploadClient.upload(prefs.uploadUrl(), listOf(file))
        } finally {
            file.file.delete()
        }
    }

    private fun maybeReadTextCandidate(uri: Uri): String? {
        return try {
            val resolver = contentResolver
            val mime = resolver.getType(uri)
            val name = ContentResolverUtil.displayName(this, uri)
            if (!TextSharePolicy.looksTextLike(mime, name)) return null
            resolver.openInputStream(uri).use { input ->
                requireNotNull(input)
                val bytes = input.readBytes()
                if (!TextSharePolicy.isQrCandidate(bytes.size, mime, name)) return null
                if (bytes.contains(0)) return null
                String(bytes, Charset.forName("UTF-8"))
            }
        } catch (_: Exception) {
            null
        }
    }

    private fun applyEncodeOutcome(outcome: EncodeOutcome) {
        when (outcome) {
            is EncodeOutcome.Qr -> showQr(outcome.encoded, sourceText = outcome.sourceText, refining = false)
            is EncodeOutcome.Upload -> {
                if (outcome.note != null) binding.textStatus.text = outcome.note
                showSuccess(outcome.batch, keepStatus = outcome.note != null)
            }
            is EncodeOutcome.Failed -> {
                pendingText = outcome.sourceText
                binding.textStatus.setText(R.string.upload_failed)
                binding.textResult.visibility = View.VISIBLE
                binding.textResult.text = outcome.message
                binding.buttonHostFile.visibility = View.VISIBLE
                binding.buttonDone.visibility = View.VISIBLE
            }
        }
    }

    private fun showQr(encoded: String, sourceText: String, refining: Boolean) {
        pendingText = sourceText
        qrPayload = encoded
        binding.textStatus.setText(if (refining) R.string.encoding_qr else R.string.qr_ready)
        val bmp = try {
            QrBitmap.fromPayload(encoded)
        } catch (e: Exception) {
            binding.progress.visibility = View.GONE
            binding.textStatus.setText(R.string.upload_failed)
            binding.textResult.visibility = View.VISIBLE
            binding.textResult.text = e.message ?: "Could not render QR"
            binding.buttonHostFile.visibility = View.VISIBLE
            binding.buttonDone.visibility = View.VISIBLE
            return
        }
        if (!refining) {
            binding.progress.visibility = View.GONE
        }
        binding.imageQr.setImageBitmap(bmp)
        binding.imageQr.visibility = View.VISIBLE
        binding.textResult.visibility = View.VISIBLE
        binding.textResult.text = getString(R.string.qr_hint_text) + "\n\n" +
            getString(R.string.qr_payload_label) + "\n" + encoded
        binding.buttonCopyPayload.visibility = View.VISIBLE
        binding.buttonHostFile.visibility = View.VISIBLE
        binding.buttonDone.visibility = View.VISIBLE
    }

    private fun hostTextAsFile(text: String) {
        lifecycleScope.launch {
            binding.progress.visibility = View.VISIBLE
            binding.textStatus.setText(R.string.uploading)
            binding.imageQr.visibility = View.GONE
            binding.buttonCopyPayload.visibility = View.GONE
            binding.buttonHostFile.visibility = View.GONE
            val result = withContext(Dispatchers.IO) {
                runCatching { uploadText(text) }
            }
            binding.progress.visibility = View.GONE
            result.onSuccess { showSuccess(it) }
                .onFailure { e -> showError(e.message ?: getString(R.string.upload_failed)) }
        }
    }

    private fun showSuccess(batch: UploadBatchResult, keepStatus: Boolean = false) {
        val ok = batch.items.filter { it.ok }
        if (ok.isEmpty()) {
            showError(batch.items.firstOrNull()?.message ?: getString(R.string.upload_failed))
            return
        }
        for (item in ok) {
            history.prepend(
                ShareRecord(
                    filename = item.filename,
                    url = item.url.orEmpty(),
                    directUrl = item.directUrl.orEmpty(),
                    id = item.id.orEmpty(),
                    savedAtMs = System.currentTimeMillis(),
                ),
            )
        }
        val first = ok.first()
        primaryDirect = first.directUrl
        primaryView = first.url
        if (!keepStatus) {
            binding.textStatus.setText(R.string.upload_ok)
        }

        // Results QR — same idea as website (scan API JSON / handoff).
        val qrJson = batch.rawJson?.takeIf { it.isNotBlank() }
            ?: buildSuccessJson(first)
        qrPayload = qrJson
        try {
            binding.imageQr.setImageBitmap(QrBitmap.fromPayload(qrJson))
            binding.imageQr.visibility = View.VISIBLE
            binding.buttonCopyPayload.visibility = View.VISIBLE
        } catch (_: Exception) {
            // Payload too large for QR (big batch) — skip image, keep URL buttons.
            binding.imageQr.visibility = View.GONE
            binding.buttonCopyPayload.visibility = View.GONE
            qrPayload = null
        }

        val lines = StringBuilder()
        lines.append(getString(R.string.qr_hint_file)).append('\n')
        lines.append(getString(R.string.batch_summary, ok.size, batch.items.size - ok.size)).append('\n')
        for (item in ok) {
            lines.append('\n').append(item.filename).append('\n')
            lines.append("direct: ").append(item.directUrl).append('\n')
            lines.append("view: ").append(item.url).append('\n')
        }
        binding.textResult.visibility = View.VISIBLE
        binding.textResult.text = lines.toString()
        binding.buttonCopyDirect.visibility = View.VISIBLE
        binding.buttonCopyView.visibility = View.VISIBLE
        binding.buttonOpen.visibility = View.VISIBLE
        binding.buttonDone.visibility = View.VISIBLE
    }

    private fun buildSuccessJson(item: UploadItemResult): String {
        val data = org.json.JSONObject()
            .put("url", item.url.orEmpty())
            .put("direct_url", item.directUrl.orEmpty())
            .put("id", item.id.orEmpty())
            .put("filename", item.filename)
        return org.json.JSONObject()
            .put("status", "success")
            .put("data", data)
            .toString()
    }

    private fun showError(message: String) {
        binding.textStatus.setText(R.string.upload_failed)
        binding.textResult.visibility = View.VISIBLE
        binding.textResult.text = message
        if (pendingText != null) {
            binding.buttonHostFile.visibility = View.VISIBLE
        }
        binding.buttonDone.visibility = View.VISIBLE
    }

    private fun resetResultUi() {
        primaryDirect = null
        primaryView = null
        qrPayload = null
        pendingText = null
        binding.imageQr.setImageDrawable(null)
        binding.imageQr.visibility = View.GONE
        binding.textResult.visibility = View.GONE
        binding.buttonCopyPayload.visibility = View.GONE
        binding.buttonHostFile.visibility = View.GONE
        binding.buttonCopyDirect.visibility = View.GONE
        binding.buttonCopyView.visibility = View.GONE
        binding.buttonOpen.visibility = View.GONE
        binding.buttonDone.visibility = View.GONE
    }

    private fun copy(value: String?) {
        if (value.isNullOrBlank()) return
        val cm = getSystemService(CLIPBOARD_SERVICE) as ClipboardManager
        cm.setPrimaryClip(ClipData.newPlainText("sanctum", value))
        binding.textStatus.setText(R.string.copied)
    }

    private fun parcelableUri(intent: Intent, key: String): Uri? {
        return if (Build.VERSION.SDK_INT >= 33) {
            intent.getParcelableExtra(key, Uri::class.java)
        } else {
            @Suppress("DEPRECATION")
            intent.getParcelableExtra(key)
        }
    }

    private fun parcelableUriList(intent: Intent, key: String): ArrayList<Uri>? {
        return if (Build.VERSION.SDK_INT >= 33) {
            intent.getParcelableArrayListExtra(key, Uri::class.java)
        } else {
            @Suppress("DEPRECATION")
            intent.getParcelableArrayListExtra(key)
        }
    }
}
