# DbMySync - Database Synchronization Tool

Compare and synchronize MySQL database structures across different environments (localhost ↔ online).

## Features

- 🔄 Compare database schemas between offline and online environments
- 🔍 Detect missing columns, tables, keys, and indexes
- 📊 Visual diff display
- 🔐 Secure endpoint communication with secrets
- 💾 Manage multiple tool pairs
- 🎯 Generate migration SQL scripts

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

## License

MIT

