// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.asRequestBody
import org.json.JSONObject
import java.io.File
import java.util.concurrent.TimeUnit

data class UploadFile(
    val file: File,
    val filename: String,
    val mime: String,
)

data class UploadItemResult(
    val ok: Boolean,
    val filename: String,
    val url: String? = null,
    val directUrl: String? = null,
    val id: String? = null,
    val message: String? = null,
)

data class UploadBatchResult(
    val httpCode: Int,
    val items: List<UploadItemResult>,
)

class UploadClient(
    private val userAgent: String = "Sanctum-Share/${BuildConfig.VERSION_NAME} (Android)",
) {
    private val client = OkHttpClient.Builder()
        .connectTimeout(60, TimeUnit.SECONDS)
        .readTimeout(120, TimeUnit.SECONDS)
        .writeTimeout(120, TimeUnit.SECONDS)
        .build()

    fun upload(uploadUrl: String, files: List<UploadFile>): UploadBatchResult {
        require(files.isNotEmpty()) { "no files" }
        val bodyBuilder = MultipartBody.Builder().setType(MultipartBody.FORM)
        if (files.size == 1) {
            val f = files[0]
            val media = f.mime.toMediaTypeOrNull() ?: "application/octet-stream".toMediaTypeOrNull()
            bodyBuilder.addFormDataPart("file", f.filename, f.file.asRequestBody(media))
        } else {
            for (f in files.take(20)) {
                val media = f.mime.toMediaTypeOrNull() ?: "application/octet-stream".toMediaTypeOrNull()
                bodyBuilder.addFormDataPart("files[]", f.filename, f.file.asRequestBody(media))
            }
        }
        val req = Request.Builder()
            .url(uploadUrl)
            .header("User-Agent", userAgent)
            .post(bodyBuilder.build())
            .build()
        client.newCall(req).execute().use { resp ->
            val text = resp.body?.string().orEmpty()
            return parseResponse(resp.code, text, files)
        }
    }

    private fun parseResponse(code: Int, text: String, files: List<UploadFile>): UploadBatchResult {
        if (text.isBlank()) {
            return UploadBatchResult(
                code,
                listOf(UploadItemResult(false, files.first().filename, message = "Empty response (HTTP $code)")),
            )
        }
        val root = try {
            JSONObject(text)
        } catch (_: Exception) {
            return UploadBatchResult(
                code,
                listOf(UploadItemResult(false, files.first().filename, message = "Non-JSON response (HTTP $code)")),
            )
        }
        if (root.optString("status") == "error") {
            return UploadBatchResult(
                code,
                listOf(
                    UploadItemResult(
                        false,
                        files.first().filename,
                        message = root.optString("message", "error"),
                    ),
                ),
            )
        }
        val data = root.optJSONObject("data")
            ?: return UploadBatchResult(
                code,
                listOf(UploadItemResult(false, files.first().filename, message = "Missing data")),
            )
        if (data.has("files")) {
            val arr = data.getJSONArray("files")
            val items = ArrayList<UploadItemResult>(arr.length())
            for (i in 0 until arr.length()) {
                val o = arr.getJSONObject(i)
                val name = o.optString("filename", "file-$i")
                if (o.optString("status") == "success") {
                    items.add(
                        UploadItemResult(
                            true,
                            name,
                            url = o.optString("url"),
                            directUrl = o.optString("direct_url"),
                            id = o.optString("id"),
                        ),
                    )
                } else {
                    items.add(
                        UploadItemResult(
                            false,
                            name,
                            message = o.optString("message", "failed"),
                        ),
                    )
                }
            }
            return UploadBatchResult(code, items)
        }
        return UploadBatchResult(
            code,
            listOf(
                UploadItemResult(
                    true,
                    data.optString("filename", files.first().filename),
                    url = data.optString("url"),
                    directUrl = data.optString("direct_url"),
                    id = data.optString("id"),
                ),
            ),
        )
    }
}
