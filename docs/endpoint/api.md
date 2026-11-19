# Endpoint HTTP API

Code: `endpoint/src/`. Entry point in a project: any PHP file that builds an
`Endpoint` with a config array and calls `handle()`.

## Authentication

Every request, including `health`, needs the secret:

```
X-DbSync-Secret: <secret>
```

`Authorization: Bearer <secret>` is accepted as a fallback. Comparison uses
`hash_equals`. Missing or wrong secret → `401` with
`{"success": false, "error": "Unauthorized - Invalid or missing secret"}`.

The main application relies on that exact wording (`Unauthorized`) to show a
yellow light ("endpoint found, secret wrong") instead of a red one.

`OPTIONS` requests return `200` without authentication (CORS preflight). CORS
allows any origin.

## Actions

Selected with the query parameter `action` (default `health`).

| Request | Response `data` |
|---|---|
| `GET ?action=health` | `{"status": "ok", "version": "1.0.0", "timestamp": "<ISO 8601>"}` |
| `GET ?action=schema` | `{"tables": [<table>, ...]}` – every table of the configured database |
| `GET ?action=schema&table=<name>` | a single `<table>` object (not wrapped in `tables`) |
| anything else | `404`, `{"success": false, "error": "Unknown action: ..."}` |

The `version` in the health answer is hard-coded and does not follow the
package version.

## Envelope

```json
{ "success": true,  "data": { ... } }
{ "success": false, "error": "message" }
```

Exceptions (e.g. database connection failure) become `500` with the message.
A missing config key also yields `500` (`Missing required config: <key>`).

## Table object

Read by `SchemaReader` from `INFORMATION_SCHEMA` of the configured database.

```json
{
  "name": "users",
  "columns": [
    {
      "name": "email",
      "type": "varchar(255)",
      "nullable": false,
      "default": null,
      "extra": "",
      "charset": "utf8mb4",
      "collation": "utf8mb4_unicode_ci",
      "position": 3,
      "comment": ""
    }
  ],
  "primary_key": { "columns": ["id"] },
  "foreign_keys": [
    {
      "name": "fk_orders_user",
      "columns": ["user_id"],
      "referenced_table": "users",
      "referenced_columns": ["id"]
    }
  ],
  "indexes": [
    { "name": "idx_email", "columns": ["email"], "unique": true }
  ]
}
```

| Field | Source | Notes |
|---|---|---|
| `columns[].type` | `COLUMN_TYPE` | Reported verbatim; MySQL 8 omits integer widths, MariaDB does not |
| `columns[].default` | `COLUMN_DEFAULT` | MariaDB quotes strings and reports a missing default as the text `NULL` |
| `columns[].extra` | `EXTRA` | `auto_increment`, `on update ...`, `DEFAULT_GENERATED`, `VIRTUAL GENERATED` |
| `columns[].position` | `ORDINAL_POSITION` | Added later; older endpoints omit it |
| `columns[].comment` | `COLUMN_COMMENT` | Added later; older endpoints omit it |
| `primary_key` | `KEY_COLUMN_USAGE` | `null` when the table has none |
| `foreign_keys` | `KEY_COLUMN_USAGE` | No `ON DELETE` / `ON UPDATE` and no `REFERENTIAL_CONSTRAINTS` lookup |
| `indexes` | `STATISTICS` | Primary key excluded; no index type (FULLTEXT/SPATIAL) |

Views are listed by `SHOW TABLES` and appear like tables (without keys).

The main application treats missing optional fields (`position`, `comment`) as
"not reported" rather than as a difference, so old and new endpoints can be
compared with each other.

## Changing the contract

The endpoint is installed in many projects and updated independently, so:

- Only **add** fields; never rename or remove one.
- Make the main application tolerate the field being absent
  (`array_key_exists` / `??`), as done for `comment` and `position`.
- Release a new endpoint version afterwards
  ([operations/releasing-endpoint.md](../operations/releasing-endpoint.md)).
