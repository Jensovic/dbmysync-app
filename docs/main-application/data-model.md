# Data model

The main application stores its own state in SQLite:
`main-application/data/dbmysync.db` (git-ignored). Backups next to it follow the
pattern `dbmysync.db.backup-YYYYMMDD-HHMMSS`, also ignored.

## Tables

### `tool_pairs`

| Column | Meaning |
|---|---|
| `id`, `name` | `name` is unique; duplicates get " (Kopie)", " (Kopie 2)", ... |
| `group_id` | Optional group; `NULL` = "Ohne Gruppe" |
| `env1_name`, `env1_url`, `env1_secret` | First environment (default name "Dev") |
| `env2_name`, `env2_url`, `env2_secret` | Second environment (default name "Prod") |
| `env1_engine`, `env2_engine` | Storage engine for generated `CREATE TABLE` in that environment; `''` = no `ENGINE` clause |
| `active` | `0` = deleted. Rows are never physically deleted |
| `last_diff_count` | Cached difference count, `NULL` if never checked or failed |
| `last_diff_status` | `ok` / `error` / `NULL` |
| `last_diff_error` | Error message of the last failed check |
| `last_diff_checked_at` | UTC timestamp of the last check |
| `created_at`, `updated_at` | |

Secrets are stored in plain text. The database file must stay local.

### `groups`

`id`, `name` (unique), `sort_order` (no UI yet, always 0), `created_at`.
Groups are created implicitly from the form and removed automatically once no
active pair uses them.

### `sync_history`

One row per opened comparison (`action=compare`), **not** per dashboard
refresh, so "refresh all" cannot drown the real runs.

`tool_pair_id`, `synced_at`, `env1_status`, `env2_status`
(`success`/`error`), `differences_found`, `error_message`.

There is no UI for the history yet; `ToolPair::getSyncHistory()` exists.

## Schema creation and migrations

`Database::__construct()`:

1. Creates `data/` if missing.
2. For a new file, runs `database/schema.sql` (the full current schema).
3. Always runs `migrate()`, which adds anything missing to older databases:
   the `groups` table, `tool_pairs.group_id`, the `last_diff_*` columns and the
   engine columns, plus the group index.

`migrate()` is idempotent (checks `PRAGMA table_info` before each
`ALTER TABLE`). To add a column:

1. Add it to `database/schema.sql` (new installations).
2. Add it to the list in `Database::migrate()` (existing installations).
3. Read/write it in `Models/ToolPair.php` (`create`, `update`).

SQLite cannot add a column with a foreign key to an existing table, so
`group_id` is only enforced in application code for migrated databases.

`database/migrate_to_env_names.sql` is a one-off manual script from the rename
`offline/online` → `env1/env2`. It is not run automatically and is only needed
for databases from that era.

## Backup

Copy the file while the app is idle:

```bash
cp main-application/data/dbmysync.db main-application/data/dbmysync.db.backup-$(date +%Y%m%d-%H%M%S)
```
