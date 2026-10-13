// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.google.android.material.snackbar.Snackbar
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.sanctumos.share.databinding.ActivityMainBinding

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var prefs: Prefs
    private lateinit var history: HistoryStore
    private lateinit var adapter: RecentAdapter
    private val client = UploadClient()

    private val pickFiles = registerForActivityResult(
        ActivityResultContracts.OpenMultipleDocuments(),
    ) { uris: List<Uri> ->
        if (uris.isNotEmpty()) uploadUris(uris)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)
        prefs = Prefs(this)
        history = HistoryStore(this)
        adapter = RecentAdapter(history.list().toMutableList())
        binding.recyclerRecent.layoutManager = LinearLayoutManager(this)
        binding.recyclerRecent.adapter = adapter
        refreshRecentVisibility()
        binding.buttonSettings.setOnClickListener {
            startActivity(Intent(this, SettingsActivity::class.java))
        }
        binding.buttonPick.setOnClickListener {
            pickFiles.launch(arrayOf("*/*"))
        }
    }

    override fun onResume() {
        super.onResume()
        binding.textHost.text = getString(R.string.host_line, prefs.baseUrl())
        adapter.replaceAll(history.list())
        refreshRecentVisibility()
    }

    private fun refreshRecentVisibility() {
        val empty = adapter.itemCount == 0
        binding.textRecentEmpty.visibility = if (empty) android.view.View.VISIBLE else android.view.View.GONE
        binding.recyclerRecent.visibility = if (empty) android.view.View.GONE else android.view.View.VISIBLE
    }

    private fun uploadUris(uris: List<Uri>) {
        Snackbar.make(binding.root, R.string.uploading, Snackbar.LENGTH_SHORT).show()
        lifecycleScope.launch {
            val result = withContext(Dispatchers.IO) {
                val files = uris.map { ContentResolverUtil.toUploadFile(this@MainActivity, it) }
                try {
                    client.upload(prefs.uploadUrl(), files)
                } finally {
                    files.forEach { it.file.delete() }
                }
            }
            val ok = result.items.filter { it.ok }
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
            adapter.replaceAll(history.list())
            refreshRecentVisibility()
            val msg = if (ok.isNotEmpty()) {
                getString(R.string.batch_summary, ok.size, result.items.size - ok.size)
            } else {
                result.items.firstOrNull()?.message ?: getString(R.string.upload_failed)
            }
            Snackbar.make(binding.root, msg, Snackbar.LENGTH_LONG).show()
        }
    }
}
