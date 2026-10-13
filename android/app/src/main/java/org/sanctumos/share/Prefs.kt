// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import android.content.Context

class Prefs(context: Context) {
    private val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun baseUrl(): String = normalizeBaseUrl(prefs.getString(KEY_BASE, DEFAULT_BASE) ?: DEFAULT_BASE)

    fun setBaseUrl(raw: String) {
        prefs.edit().putString(KEY_BASE, normalizeBaseUrl(raw)).apply()
    }

    fun resetBaseUrl() = setBaseUrl(DEFAULT_BASE)

    fun uploadUrl(): String = "${baseUrl()}/api/v1/upload.php"

    companion object {
        const val DEFAULT_BASE = "https://tmp.sanctumos.org"
        private const val PREFS = "sanctum_share"
        private const val KEY_BASE = "base_url"

        fun normalizeBaseUrl(raw: String): String {
            var s = raw.trim()
            if (s.isEmpty()) return DEFAULT_BASE
            while (s.endsWith("/")) s = s.dropLast(1)
            if (!s.contains("://")) s = "https://$s"
            return s
        }
    }
}
