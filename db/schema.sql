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

-- Durable usage log — survives file purge (uploads rows / storage files go away)
CREATE TABLE IF NOT EXISTS usage_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event TEXT NOT NULL,
    upload_id TEXT,
    filename TEXT,
    mime_type TEXT,
    size_bytes INTEGER,
    client_ip TEXT,
    user_agent TEXT,
    created_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_usage_events_created_at ON usage_events (created_at);
CREATE INDEX IF NOT EXISTS idx_usage_events_event ON usage_events (event);
