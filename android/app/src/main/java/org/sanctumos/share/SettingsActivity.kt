// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import android.os.Bundle
import android.text.Editable
import android.text.TextWatcher
import androidx.appcompat.app.AppCompatActivity
import com.google.android.material.snackbar.Snackbar
import org.sanctumos.share.databinding.ActivitySettingsBinding

class SettingsActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySettingsBinding
    private lateinit var prefs: Prefs

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySettingsBinding.inflate(layoutInflater)
        setContentView(binding.root)
        prefs = Prefs(this)
        binding.editBaseUrl.setText(prefs.baseUrl())
        binding.switchPreferQr.isChecked = prefs.preferQrForText()
        updatePreview()
        binding.editBaseUrl.addTextChangedListener(
            object : TextWatcher {
                override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) = Unit
                override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) = Unit
                override fun afterTextChanged(s: Editable?) = updatePreview()
            },
        )
        binding.switchPreferQr.setOnCheckedChangeListener { _, checked ->
            prefs.setPreferQrForText(checked)
        }
        binding.buttonSave.setOnClickListener {
            prefs.setBaseUrl(binding.editBaseUrl.text?.toString().orEmpty())
            prefs.setPreferQrForText(binding.switchPreferQr.isChecked)
            binding.editBaseUrl.setText(prefs.baseUrl())
            updatePreview()
            Snackbar.make(binding.root, R.string.save, Snackbar.LENGTH_SHORT).show()
        }
        binding.buttonReset.setOnClickListener {
            prefs.resetBaseUrl()
            prefs.setPreferQrForText(true)
            binding.editBaseUrl.setText(prefs.baseUrl())
            binding.switchPreferQr.isChecked = true
            updatePreview()
        }
    }

    private fun updatePreview() {
        val base = Prefs.normalizeBaseUrl(binding.editBaseUrl.text?.toString().orEmpty())
        binding.textUploadPreview.text = getString(R.string.upload_preview, "$base/api/v1/upload.php")
    }
}
