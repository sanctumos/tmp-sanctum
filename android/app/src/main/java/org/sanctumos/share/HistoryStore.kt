// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import android.content.Context
import org.json.JSONArray
import org.json.JSONObject

data class ShareRecord(
    val filename: String,
    val url: String,
    val directUrl: String,
    val id: String,
    val savedAtMs: Long,
)

class HistoryStore(context: Context) {
    private val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun list(): List<ShareRecord> {
        val raw = prefs.getString(KEY, "[]") ?: "[]"
        val arr = JSONArray(raw)
        val out = ArrayList<ShareRecord>(arr.length())
        for (i in 0 until arr.length()) {
            val o = arr.getJSONObject(i)
            out.add(
                ShareRecord(
                    filename = o.optString("filename"),
                    url = o.optString("url"),
                    directUrl = o.optString("direct_url"),
                    id = o.optString("id"),
                    savedAtMs = o.optLong("saved_at_ms"),
                ),
            )
        }
        return out
    }

    fun prepend(record: ShareRecord) {
        val cur = list().toMutableList()
        cur.add(0, record)
        while (cur.size > MAX) cur.removeAt(cur.lastIndex)
        val arr = JSONArray()
        for (r in cur) {
            arr.put(
                JSONObject()
                    .put("filename", r.filename)
                    .put("url", r.url)
                    .put("direct_url", r.directUrl)
                    .put("id", r.id)
                    .put("saved_at_ms", r.savedAtMs),
            )
        }
        prefs.edit().putString(KEY, arr.toString()).apply()
    }

    companion object {
        private const val PREFS = "sanctum_share_history"
        private const val KEY = "items"
        private const val MAX = 40
    }
}
