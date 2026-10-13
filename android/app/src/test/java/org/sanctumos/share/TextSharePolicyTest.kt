package org.sanctumos.share

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class TextSharePolicyTest {
    @Test
    fun shortPlainTextIsQrCandidate() {
        assertTrue(TextSharePolicy.isQrCandidateText("hello from share sheet"))
    }

    @Test
    fun oversizedTextIsNotQrCandidate() {
        val big = "x".repeat(TextSharePolicy.SOFT_MAX_BYTES + 1)
        assertFalse(TextSharePolicy.isQrCandidateText(big))
    }

    @Test
    fun textLikeExtensions() {
        assertTrue(TextSharePolicy.looksTextLike(null, "note.md"))
        assertTrue(TextSharePolicy.looksTextLike("application/json", "data.bin"))
        assertFalse(TextSharePolicy.looksTextLike("image/png", "photo.png"))
    }
}
