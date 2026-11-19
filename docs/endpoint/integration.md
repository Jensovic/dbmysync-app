# Integrating the endpoint into a project

## Install

```bash
composer require jensovic/dbmysync-addin
```

Requires PHP 8.1+ and the `pdo_mysql` extension. The package has no runtime
dependencies.

## Entry file

Create a folder reachable over HTTP, e.g. `<project>/dbmysync/index.php`:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Jensovic\DbMySyncAddin\Endpoint;

(new Endpoint([
    'db_host' => 'localhost',
    'db_name' => 'my_database',
    'db_user' => 'my_user',
    'db_pass' => 'my_password',
    'secret'  => 'long-random-string',
]))->handle();
```

Most projects reuse their own DB configuration instead of repeating the
credentials. All five keys are required.

Generate a secret with `php -r "echo bin2hex(random_bytes(32));"`. Use a
**different secret per environment**; the main application stores both.

`endpoint/public/index.php` and `endpoint/config/config.example.php` in this
repository show the same setup with a separate `config.php`
(which is git-ignored).

## Check it

```bash
curl -H "X-DbSync-Secret: <secret>" "https://example.com/dbmysync/index.php?action=health"
```

Expected: `{"success": true, "data": {"status": "ok", ...}}`. A `401` means the
secret is wrong, HTML or a `404` means the URL does not point at the entry file.

Then add the URL (the entry file itself, with `index.php` if the server does not
default to it) and the secret as one environment of a tool pair in the main
application. The form tests the endpoint while you type.

## Security notes

- The endpoint exposes the full table and column structure to anyone holding
  the secret. Treat the secret like a database password.
- It is read-only: it runs only `SHOW TABLES` and `INFORMATION_SCHEMA` queries.
- The database user only needs read access to `INFORMATION_SCHEMA` for that
  schema; a read-only user is sufficient.
- Use HTTPS for remote environments; the secret travels in a header.
