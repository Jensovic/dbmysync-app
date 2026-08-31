# DbMySync - Database Synchronization Tool

Compare and synchronize MySQL database structures across different environments (localhost ↔ online).

## Features

- 🔄 Compare database schemas between offline and online environments
- 🔍 Detect missing columns, tables, keys, and indexes
- 📊 Visual diff display
- 🔐 Secure endpoint communication with secrets
- 💾 Manage multiple tool pairs
- 🎯 Generate migration SQL scripts
- 🗂️ Group tool pairs, collapse groups, and see their totals at a glance

## Installation

1. Clone or download this repository
2. Install dependencies:
```bash
composer install
```

3. Start the application:
```bash
php -S localhost:8000 -t public
```

4. Open browser: `http://localhost:8000`

## Requirements

- PHP 8.2 or higher
- SQLite extension (usually included)
- DbMySync Endpoint installed in your projects

## Usage

### 1. Add Tool Pair

Add your offline and online endpoints:
- **Name**: e.g., "My CMS"
- **Offline URL**: `http://localhost/mycms/dbsync/`
- **Offline Secret**: Your offline endpoint secret
- **Online URL**: `https://mycms.com/dbsync/`
- **Online Secret**: Your online endpoint secret

### 2. Compare Schemas

Click "Compare" to fetch and compare database structures.

### 3. Review Differences

See missing tables, columns, keys, and indexes.

### 4. Generate Migration SQL

Get ready-to-use SQL statements to sync your databases.

## Dashboard

### Difference counts are cached, not recomputed

Every tool pair shows a badge with the number of structural differences between
its two environments. That number is the **result of the last check**, stored on
the tool pair itself - opening the dashboard does not compare any schemas, so
the page loads at the same speed no matter how many pairs are configured.

| Badge | Meaning |
| ----- | ------- |
| grey `?` | never checked yet |
| green `0` | both environments match |
| yellow `n` | `n` structural differences |
| red `!` | the last check failed - the tooltip says why |

The tooltip also carries the timestamp of that check, so it is always clear how
old a number is.

### Refreshing

Three refresh buttons, all doing the same thing at a different scope:

- **Alle aktualisieren** in the page header - every tool pair
- the icon in a group header - that group only
- the icon in a table row - that single pair

A refresh re-checks both endpoints **and** recounts the differences in one
request per pair, so the traffic lights and the badge can never disagree about
whether an endpoint is reachable. If an endpoint does not answer, the two schema
downloads are skipped (they would only hit the same failure) and the badge turns
red - a stale count next to a red light would look like a current result.

Pairs are processed one after another rather than all at once, because a single
check means two full schema downloads.

Opening the full comparison view also updates the stored count, so the badge
matches what you just looked at.

### Groups

Click a group header to collapse it. Collapsed, it still shows the pair count
and two yellow boxes: the **sum** and the **average** of that group's
differences. Only pairs with a successful last check count towards these - an
unchecked or failed pair is excluded rather than silently counted as zero, and
the tooltip reports how many pairs contributed (`8 von 14 Tool Pairs geprüft`).

Collapsed groups are remembered in `localStorage` per browser.

### Endpoint traffic lights

Next to each environment name, refreshed automatically on page load (a cheap
health check, no schema downloads):

- 🟢 endpoint answered
- 🟡 endpoint found, but the secret was rejected
- 🔴 not reachable

## Project layout

```
public/
  index.php       # router, AJAX actions, view dispatch
  dashboard.js    # dashboard behaviour (refresh queue, collapsing)
  styles.css      # application styles for every page
  icons/          # 24x24 SVG icons
views/            # templates; layout.php is the shared page frame
src/              # Database, EndpointClient, SchemaComparator, SqlGenerator, models
database/         # schema.sql
data/             # SQLite database (created on first run)
```

`styles.css` and `dashboard.js` are linked with a `filemtime()` cache buster, so
a saved edit reaches the browser without a hard reload.

## Database

The SQLite schema is created on first run and upgraded in place on every start
by `Database::migrate()`, which only applies changes that are missing. Existing
installations pick up new columns automatically - no manual migration step.

Cached check results live on `tool_pairs` in `last_diff_count`,
`last_diff_status`, `last_diff_error` and `last_diff_checked_at`. Full
comparison runs are additionally recorded in `sync_history`; dashboard refreshes
deliberately are not, so they cannot drown the real runs.

## License

MIT

