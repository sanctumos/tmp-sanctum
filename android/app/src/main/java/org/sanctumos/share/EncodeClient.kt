// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

sealed class EncodeResult {
    data class Success(
        val encoded: String,
        val type: String,
        val format: String,
        val mimeType: String?,
        val dataBase64: String?,
        val suggestion: String?,
    ) : EncodeResult()

    data class CapacityExceeded(val encoded: String?, val message: String) : EncodeResult()

    data class Failed(val message: String, val code: String?) : EncodeResult()
}

class EncodeClient(
    private val userAgent: String = "Sanctum-Share/${BuildConfig.VERSION_NAME} (Android)",
) {
    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
        .writeTimeout(60, TimeUnit.SECONDS)
        .build()

    /**
     * QR-first encode. Prefer format=png when the host has GD; SVG is fine —
     * callers should render [EncodeResult.Success.encoded] locally for display.
     */
    fun encode(
        encodeUrl: String,
        content: String,
        type: String = "auto",
        format: String = "png",
        persist: Boolean = false,
    ): EncodeResult {
        val body = JSONObject()
            .put("content", content)
            .put("type", type)
            .put("format", format)
            .put("ecl", "M")
            .put("persist", persist)
            .toString()
        val req = Request.Builder()
            .url(encodeUrl)
            .header("User-Agent", userAgent)
            .header("Content-Type", "application/json; charset=utf-8")
            .post(body.toRequestBody("application/json; charset=utf-8".toMediaType()))
            .build()
        client.newCall(req).execute().use { resp ->
            val text = resp.body?.string().orEmpty()
            return parse(resp.code, text)
        }
    }

    private fun parse(code: Int, text: String): EncodeResult {
        if (text.isBlank()) {
            return EncodeResult.Failed("Empty encode response (HTTP $code)", null)
        }
        val root = try {
            JSONObject(text)
        } catch (_: Exception) {
            return EncodeResult.Failed("Non-JSON encode response (HTTP $code)", null)
        }
        if (root.optString("status") == "success") {
            val data = root.optJSONObject("data")
                ?: return EncodeResult.Failed("Missing encode data", null)
            val suggestion = data.optString("suggestion")
            return EncodeResult.Success(
                encoded = data.optString("encoded"),
                type = data.optString("type", "text"),
                format = data.optString("format", "svg"),
                mimeType = data.optString("mime_type").ifBlank { null },
                dataBase64 = data.optString("data_base64").ifBlank { null },
                suggestion = suggestion.ifBlank { null },
            )
        }
        val errCode = root.optString("code").ifBlank { null }
        val message = root.optString("message", "encode failed")
        if (errCode == "qr_capacity") {
            return EncodeResult.CapacityExceeded(
                encoded = root.optString("encoded").ifBlank { null },
                message = message,
            )
        }
        return EncodeResult.Failed(message, errCode)
    }
}
