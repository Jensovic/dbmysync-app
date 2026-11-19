# Known limitations

Collected while documenting the code (2026-11-19). Each entry says what
actually happens, so nobody has to rediscover it.

## Detection is narrower than the main README claims

The main README lists "different index types / indexed columns" and "different
referenced tables / ON DELETE/UPDATE actions" as detected. The code does not do
this:

- Indexes and foreign keys are matched **by name only**. If an index with the
  same name exists on both sides with different columns or uniqueness, no
  difference is reported.
- The endpoint does not report `ON DELETE` / `ON UPDATE` at all, so the
  generated `FOREIGN KEY` statements never contain them, even though
  `SqlGenerator` would print them if present.
- Index types (FULLTEXT, SPATIAL) are not reported; a FULLTEXT index is
  recreated as a normal index.

## Things that are shown but not generated

- Primary key differences: shown, no SQL.
- Items that exist only in the target: shown, no `DROP` SQL (deliberate).
- Generated columns: the expression is not reported; the SQL carries a
  warning and must be completed by hand.
- Table options (engine, charset, collation, comment) are not compared.
  `CREATE TABLE` always uses `utf8mb4` / `utf8mb4_unicode_ci` and the engine
  configured for the target environment.

## Security

- `EndpointClient` disables TLS certificate verification (`'verify' => false`).
  Fine for local self-signed setups, but it allows a man-in-the-middle to read
  the secret on remote environments.
- The add/edit form validates endpoints with `ajax=validate_endpoint`, which
  puts **URL and secret in the query string**. They can end up in the Apache
  `access.log`. All other AJAX calls only send the pair ID.
- Secrets are stored in plain text in the SQLite file.
- The endpoint answers CORS requests from any origin; protection relies
  entirely on the secret.

## Smaller items

- The endpoint's health answer always says `"version": "1.0.0"`.
- `sync_history` is written but not shown anywhere.
- `groups.sort_order` exists but cannot be set in the UI.
- Twig is a dependency but unused by the views.
- Only `SqlGenerator` has unit tests; comparator and endpoint have none.
