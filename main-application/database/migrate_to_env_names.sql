-- Migration: Rename offline/online to env1/env2 with custom names
-- Run this if you already have data in your database

-- Step 1: Create new table with new schema
CREATE TABLE IF NOT EXISTS tool_pairs_new (
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

-- Step 2: Copy data from old table (if exists)
INSERT INTO tool_pairs_new (id, name, env1_name, env1_url, env1_secret, env2_name, env2_url, env2_secret, active, created_at, updated_at)
SELECT id, name, 'Dev', offline_url, offline_secret, 'Prod', online_url, online_secret, active, created_at, updated_at
FROM tool_pairs;

-- Step 3: Drop old table
DROP TABLE tool_pairs;

-- Step 4: Rename new table
ALTER TABLE tool_pairs_new RENAME TO tool_pairs;

-- Step 5: Update sync_history table
ALTER TABLE sync_history RENAME COLUMN offline_status TO env1_status;
ALTER TABLE sync_history RENAME COLUMN online_status TO env2_status;

