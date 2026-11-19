# DbMySync Addin

**Find out what your production database is missing - without SSH, without remote
MySQL access, without a migration framework.**

[![Packagist Version](https://img.shields.io/packagist/v/jensovic/dbmysync-addin)](https://packagist.org/packages/jensovic/dbmysync-addin)
[![PHP](https://img.shields.io/packagist/dependency-v/jensovic/dbmysync-addin/php)](https://packagist.org/packages/jensovic/dbmysync-addin)
[![License](https://img.shields.io/packagist/l/jensovic/dbmysync-addin)](LICENSE)

You add a column locally, deploy the code, and production breaks because the
column is not there. On shared hosting you cannot simply connect to the live
database to check.

This package is the small half of [DbMySync](https://github.com/Jensovic/dbmysync-app):
a read-only HTTP endpoint you drop into your PHP project. It reports the
database structure - tables, columns, types, defaults, primary keys, indexes,
foreign keys - as JSON, protected by a secret. The DbMySync dashboard calls the
endpoint in both environments, shows every difference and writes the
`ALTER TABLE` / `CREATE TABLE` statements to fix them.

```
 your machine                         local project            production (shared hosting)
┌──────────────┐   HTTP + secret    ┌───────────────┐        ┌───────────────┐
│   DbMySync   │ ─────────────────► │ dbmysync-addin│        │ dbmysync-addin│
│  dashboard   │ ─────────────────────────────────────────► │               │
└──────────────┘                    └──────┬────────┘        └──────┬────────┘
                                           ▼                        ▼
                                        MySQL                    MySQL
```

## Why

- **Works where nothing else does** - only needs PHP and HTTP(S). No SSH, no
  open MySQL port, no database dump.
- **Read-only** - runs `SHOW TABLES` and `INFORMATION_SCHEMA` queries, never
  touches your data.
- **No dependencies** - one Composer package, five small classes.
- **MySQL and MariaDB** - reports the raw metadata; DbMySync smooths over
  version differences (`int(11)` vs. `int`, quoted defaults, `current_timestamp()`).
- **Structure only** - table contents are never transferred.

## Install

```bash
composer require jensovic/dbmysync-addin
```

PHP 8.1+ with `pdo_mysql`.

## Set up the endpoint

Create a file that is reachable over HTTP, e.g. `dbmysync/index.php` in your
project:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Jensovic\DbMySyncAddin\Endpoint;

(new Endpoint([
    'db_host' => 'localhost',
    'db_name' => 'my_database',
    'db_user' => 'my_user',
    'db_pass' => 'my_password',
    'secret'  => 'a-long-random-secret', // php -r "echo bin2hex(random_bytes(32));"
]))->handle();
```

Deploy it to every environment you want to compare, each with its own secret.

Check it:

```bash
curl -H "X-DbSync-Secret: a-long-random-secret" "https://example.com/dbmysync/index.php?action=health"
```

Then add both URLs and secrets as a *tool pair* in the
[DbMySync dashboard](https://github.com/Jensovic/dbmysync-app).

## API

All requests need the header `X-DbSync-Secret: <secret>`
(or `Authorization: Bearer <secret>`).

| Request | Returns |
|---|---|
| `GET ?action=health` | `{"status": "ok", ...}` |
| `GET ?action=schema` | all tables |
| `GET ?action=schema&table=users` | one table |

```json
{
  "success": true,
  "data": {
    "tables": [
      {
        "name": "users",
        "columns": [
          { "name": "id", "type": "int(11)", "nullable": false, "default": null,
            "extra": "auto_increment", "charset": null, "collation": null,
            "position": 1, "comment": "" }
        ],
        "primary_key": { "columns": ["id"] },
        "foreign_keys": [],
        "indexes": [ { "name": "idx_email", "columns": ["email"], "unique": true } ]
      }
    ]
  }
}
```

A wrong or missing secret returns `401`. Full field reference:
[docs/endpoint/api.md](https://github.com/Jensovic/dbmysync-app/blob/main/docs/endpoint/api.md).

## Security

- Anyone with the secret can read your table structure (not your data). Treat
  it like a password and use a different one per environment.
- Use HTTPS for remote environments.
- A database user with read access to `INFORMATION_SCHEMA` is enough.

## License

MIT
