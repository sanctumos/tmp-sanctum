-- Idempotent schema for tmp-sanctum (SQLite)
CREATE TABLE IF NOT EXISTS uploads (
    id TEXT NOT NULL PRIMARY KEY,
    filename TEXT NOT NULL,
    mime_type TEXT,
    size_bytes INTEGER NOT NULL,
    storage_rel TEXT NOT NULL,
    dl_rel TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_uploads_expires_at ON uploads (expires_at);
