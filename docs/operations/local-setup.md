# Local setup

## Environment

| | Value |
|---|---|
| Web server | XAMPP in `C:\xampp_8_2_12`, Apache on port **82** |
| Dashboard URL | `http://localhost:82/dbmysync/main-application/public/index.php` |
| PHP | 8.2.12 (`C:\xampp_8_2_12\php`, first PHP in the user `PATH`) |
| Composer | via scoop (`composer`) |
| Apache logs | `C:\xampp_8_2_12\apache\logs\error.log` / `access.log` |

The endpoints of the local projects run in the same Apache, e.g.
`http://localhost:82/<project>/dbmysync/index.php`. Requests from the main
application appear in `access.log` with user agent `GuzzleHttp/7`, which helps
when a comparison fails.

### PHP version

Composer resolves package versions for the PHP that runs Composer. With an old
PHP first in `PATH`, it silently installs older releases (e.g. a vulnerable
Twig). Check before installing:

```bash
php -v          # must be 8.1 or newer
```

## Install

```bash
cd main-application
composer install
composer audit

cd ../endpoint
composer install
```

The SQLite database is created on the first request.

## Without Apache

```bash
cd main-application
php -S localhost:8000 -t public
```

## Tests

```bash
cd main-application
vendor/bin/phpunit
```

Only `SqlGenerator` has tests (`tests/SqlGeneratorTest.php`). The endpoint has
none; `test-installation/test.php` only checks that the published package can
be autoloaded and instantiated.

## Debugging a failing comparison

1. Dashboard lights: yellow = wrong secret, red = URL wrong or host down.
2. Call the endpoint directly:
   `curl -H "X-DbSync-Secret: ..." "<url>?action=schema"` – must be JSON.
3. `access.log`: did the request arrive, which status code?
4. `error.log`: PHP errors of the endpoint or the main application.
5. `sync_history` and `tool_pairs.last_diff_error` in the SQLite file hold the
   last error message:

   ```bash
   php -r '$d=new PDO("sqlite:main-application/data/dbmysync.db");
   foreach($d->query("SELECT * FROM sync_history ORDER BY id DESC LIMIT 5") as $r) print_r($r);'
   ```
