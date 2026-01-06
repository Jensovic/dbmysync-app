-- DbMySync Database Schema

-- Tool Pairs: Stores endpoint configurations for two environments
CREATE TABLE IF NOT EXISTS tool_pairs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    env1_name TEXT DEFAULT 'Dev',
    env1_url TEXT NOT NULL,
    env1_secret TEXT NOT NULL,
    env2_name TEXT DEFAULT 'Prod',
    env2_url TEXT NOT NULL,
    env2_secret TEXT NOT NULL,
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sync History: Track comparison runs
CREATE TABLE IF NOT EXISTS sync_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tool_pair_id INTEGER NOT NULL,
    synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    env1_status TEXT,
    env2_status TEXT,
    differences_found INTEGER DEFAULT 0,
    error_message TEXT,
    FOREIGN KEY (tool_pair_id) REFERENCES tool_pairs(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_sync_history_tool_pair ON sync_history(tool_pair_id);
CREATE INDEX IF NOT EXISTS idx_sync_history_synced_at ON sync_history(synced_at DESC);

