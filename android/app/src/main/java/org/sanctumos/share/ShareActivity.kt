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

class ShareActivity : AppCompatActivity() {

    private lateinit var binding: ActivityShareBinding
    private lateinit var prefs: Prefs
    private lateinit var history: HistoryStore
    private val client = UploadClient()
    private var primaryDirect: String? = null
    private var primaryView: String? = null

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
            binding.progress.visibility = View.VISIBLE
            binding.textStatus.setText(R.string.uploading)
            val result = withContext(Dispatchers.IO) {
                runCatching { collectAndUpload(intent) }
            }
            binding.progress.visibility = View.GONE
            result.onSuccess { batch -> showSuccess(batch) }
                .onFailure { e -> showError(e.message ?: getString(R.string.upload_failed)) }
        }
    }

    private fun collectAndUpload(intent: Intent): UploadBatchResult {
        val action = intent.action
        val files = mutableListOf<UploadFile>()
        when (action) {
            Intent.ACTION_SEND -> {
                val text = intent.getStringExtra(Intent.EXTRA_TEXT)
                val stream = parcelableUri(intent, Intent.EXTRA_STREAM)
                when {
                    stream != null -> files.add(ContentResolverUtil.toUploadFile(this, stream))
                    !text.isNullOrBlank() -> files.add(ContentResolverUtil.textToUploadFile(this, text))
                    else -> error("Share had no file or text")
                }
            }
            Intent.ACTION_SEND_MULTIPLE -> {
                val streams = parcelableUriList(intent, Intent.EXTRA_STREAM)
                    ?: error("No files in multi-share")
                for (uri in streams) {
                    files.add(ContentResolverUtil.toUploadFile(this, uri))
                }
            }
            else -> error("Unsupported action: $action")
        }
        return try {
            client.upload(prefs.uploadUrl(), files)
        } finally {
            files.forEach { it.file.delete() }
        }
    }

    private fun showSuccess(batch: UploadBatchResult) {
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
        binding.textStatus.setText(R.string.upload_ok)
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
        binding.buttonDone.visibility = View.VISIBLE
    }

    private fun copy(value: String?) {
        if (value.isNullOrBlank()) return
        val cm = getSystemService(CLIPBOARD_SERVICE) as ClipboardManager
        cm.setPrimaryClip(ClipData.newPlainText("url", value))
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
