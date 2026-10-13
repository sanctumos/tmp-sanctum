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
        lifecycleScope.launch {
            resetResultUi()
            binding.progress.visibility = View.VISIBLE
            binding.textStatus.setText(
                if (prefs.preferQrForText() && looksLikeTextShare(intent)) {
                    R.string.encoding_qr
                } else {
                    R.string.uploading
                },
            )
            val outcome = withContext(Dispatchers.IO) {
                runCatching { resolveShare(intent) }
            }
            binding.progress.visibility = View.GONE
            outcome.onSuccess { applyOutcome(it) }
                .onFailure { e ->
                    showError(e.message ?: getString(R.string.upload_failed))
                }
        }
    }

    private sealed class ShareOutcome {
        data class Qr(val encoded: String, val type: String, val sourceText: String) : ShareOutcome()
        data class Upload(val batch: UploadBatchResult, val note: String? = null) : ShareOutcome()
        data class QrFailed(val message: String, val sourceText: String) : ShareOutcome()
    }

    private fun looksLikeTextShare(intent: Intent): Boolean {
        if (intent.action != Intent.ACTION_SEND) return false
        val text = intent.getStringExtra(Intent.EXTRA_TEXT)
        if (!text.isNullOrBlank() && parcelableUri(intent, Intent.EXTRA_STREAM) == null) {
            return TextSharePolicy.isQrCandidateText(text)
        }
        return false
    }

    private fun resolveShare(intent: Intent): ShareOutcome {
        val action = intent.action
        when (action) {
            Intent.ACTION_SEND -> {
                val text = intent.getStringExtra(Intent.EXTRA_TEXT)
                val stream = parcelableUri(intent, Intent.EXTRA_STREAM)
                when {
                    stream != null -> {
                        val asText = maybeReadTextCandidate(stream)
                        if (asText != null && prefs.preferQrForText() && TextSharePolicy.isQrCandidateText(asText)) {
                            return encodeOrFallback(asText)
                        }
                        val file = ContentResolverUtil.toUploadFile(this, stream)
                        return try {
                            ShareOutcome.Upload(uploadClient.upload(prefs.uploadUrl(), listOf(file)))
                        } finally {
                            file.file.delete()
                        }
                    }
                    !text.isNullOrBlank() -> {
                        if (prefs.preferQrForText() && TextSharePolicy.isQrCandidateText(text)) {
                            return encodeOrFallback(text)
                        }
                        return uploadText(text)
                    }
                    else -> error("Share had no file or text")
                }
            }
            Intent.ACTION_SEND_MULTIPLE -> {
                val streams = parcelableUriList(intent, Intent.EXTRA_STREAM)
                    ?: error("No files in multi-share")
                val files = streams.map { ContentResolverUtil.toUploadFile(this, it) }
                return try {
                    ShareOutcome.Upload(uploadClient.upload(prefs.uploadUrl(), files))
                } finally {
                    files.forEach { it.file.delete() }
                }
            }
            else -> error("Unsupported action: $action")
        }
    }

    private fun encodeOrFallback(text: String): ShareOutcome {
        return when (val enc = encodeClient.encode(prefs.encodeUrl(), text)) {
            is EncodeResult.Success -> {
                if (enc.encoded.isBlank()) {
                    uploadText(text, getString(R.string.qr_fallback_upload))
                } else {
                    ShareOutcome.Qr(enc.encoded, enc.type, text)
                }
            }
            is EncodeResult.CapacityExceeded ->
                uploadText(text, getString(R.string.qr_fallback_upload))
            is EncodeResult.Failed ->
                ShareOutcome.QrFailed(enc.message, text)
        }
    }

    private fun uploadText(text: String, note: String? = null): ShareOutcome.Upload {
        val file = ContentResolverUtil.textToUploadFile(this, text)
        return try {
            ShareOutcome.Upload(uploadClient.upload(prefs.uploadUrl(), listOf(file)), note)
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

    private fun applyOutcome(outcome: ShareOutcome) {
        when (outcome) {
            is ShareOutcome.Qr -> showQr(outcome)
            is ShareOutcome.Upload -> {
                if (outcome.note != null) {
                    binding.textStatus.text = outcome.note
                }
                showSuccess(outcome.batch, keepStatus = outcome.note != null)
            }
            is ShareOutcome.QrFailed -> {
                pendingText = outcome.sourceText
                binding.textStatus.setText(R.string.upload_failed)
                binding.textResult.visibility = View.VISIBLE
                binding.textResult.text = outcome.message
                binding.buttonHostFile.visibility = View.VISIBLE
                binding.buttonDone.visibility = View.VISIBLE
            }
        }
    }

    private fun showQr(outcome: ShareOutcome.Qr) {
        pendingText = outcome.sourceText
        qrPayload = outcome.encoded
        binding.textStatus.setText(R.string.qr_ready)
        val bmp = try {
            QrBitmap.fromPayload(outcome.encoded)
        } catch (e: Exception) {
            binding.textStatus.setText(R.string.upload_failed)
            binding.textResult.visibility = View.VISIBLE
            binding.textResult.text = e.message ?: "Could not render QR"
            binding.buttonHostFile.visibility = View.VISIBLE
            binding.buttonDone.visibility = View.VISIBLE
            return
        }
        binding.imageQr.setImageBitmap(bmp)
        binding.imageQr.visibility = View.VISIBLE
        binding.textResult.visibility = View.VISIBLE
        binding.textResult.text = getString(R.string.qr_payload_label) + "\n" + outcome.encoded
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
                runCatching { uploadText(text).batch }
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
        val lines = StringBuilder()
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
