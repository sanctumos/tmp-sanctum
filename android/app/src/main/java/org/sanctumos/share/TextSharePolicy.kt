// SPDX-License-Identifier: AGPL-3.0-or-later
package org.sanctumos.share

/**
 * Mirrors public/assets/home-studio.js QR-candidate rules:
 * small text-like content → prefer encode.php; host-as-file remains an override.
 */
object TextSharePolicy {
    /** Soft ceiling before offering QR (server still enforces capacity). */
    const val SOFT_MAX_BYTES = 3500

    fun looksTextLike(mime: String?, filename: String?): Boolean {
        val t = (mime ?: "").lowercase()
        if (t.startsWith("text/")) return true
        if (t == "application/json" || t == "application/xml" || t == "application/javascript") {
            return true
        }
        val n = (filename ?: "").lowercase()
        if (n.matches(
                Regex(
                    ".*\\.(txt|md|csv|json|log|url|vcard|vcf|ics|xml|html?|css|js|ts|py|sh|env|ya?ml|toml|ini|conf|cfg)$",
                ),
            )
        ) {
            return true
        }
        return t.isEmpty()
    }

    fun isQrCandidate(byteLength: Int, mime: String? = "text/plain", filename: String? = "share.txt"): Boolean {
        return byteLength in 1..SOFT_MAX_BYTES && looksTextLike(mime, filename)
    }

    fun isQrCandidateText(text: String): Boolean {
        val bytes = text.toByteArray(Charsets.UTF_8).size
        return isQrCandidate(bytes, "text/plain", "share.txt") && !text.contains('\u0000')
    }
}
