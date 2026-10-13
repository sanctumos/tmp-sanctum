package org.sanctumos.share

import org.junit.Assert.assertEquals
import org.junit.Test

class PrefsTest {
    @Test
    fun normalizeStripsSlashAndAddsHttps() {
        assertEquals("https://tmp.sanctumos.org", Prefs.normalizeBaseUrl("https://tmp.sanctumos.org/"))
        assertEquals("https://tmp.example.com", Prefs.normalizeBaseUrl("tmp.example.com"))
        assertEquals(Prefs.DEFAULT_BASE, Prefs.normalizeBaseUrl("  "))
    }
}
