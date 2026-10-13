// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

import android.content.Context
import android.database.Cursor
import android.net.Uri
import android.provider.OpenableColumns
import android.webkit.MimeTypeMap
import java.io.File
import java.io.FileOutputStream

object ContentResolverUtil {
    fun toUploadFile(context: Context, uri: Uri, fallbackName: String = "share.bin"): UploadFile {
        val resolver = context.contentResolver
        val name = queryDisplayName(context, uri) ?: fallbackName
        val mime = resolver.getType(uri)
            ?: MimeTypeMap.getSingleton().getMimeTypeFromExtension(name.substringAfterLast('.', ""))
            ?: "application/octet-stream"
        val dest = File(context.cacheDir, "upload-${System.currentTimeMillis()}-$name")
        resolver.openInputStream(uri).use { input ->
            requireNotNull(input) { "Cannot open $uri" }
            FileOutputStream(dest).use { output -> input.copyTo(output) }
        }
        return UploadFile(dest, name, mime)
    }

    fun textToUploadFile(context: Context, text: String): UploadFile {
        val dest = File(context.cacheDir, "share-${System.currentTimeMillis()}.txt")
        dest.writeText(text)
        return UploadFile(dest, dest.name, "text/plain")
    }

    private fun queryDisplayName(context: Context, uri: Uri): String? {
        var cursor: Cursor? = null
        return try {
            cursor = context.contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)
            if (cursor != null && cursor.moveToFirst()) {
                val idx = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                if (idx >= 0) cursor.getString(idx) else null
            } else {
                null
            }
        } catch (_: Exception) {
            null
        } finally {
            cursor?.close()
        }
    }
}
