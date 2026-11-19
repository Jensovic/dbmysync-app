# Architecture overview

## The problem it solves

Projects run in two places: a local development database and a production
database at a web host without SSH or remote MySQL access. Structure changes
made locally (new columns, indexes, tables) are easily forgotten when deploying.
Neither side can reach the other's database directly, but both can be reached
over HTTP(S) from the developer's machine.

DbMySync therefore splits into two parts:

- an **endpoint** inside every project that reports its own database structure
  over HTTP, protected by a secret, and
- a **main application** on the developer's machine that asks both endpoints of
  a project, compares the answers and writes the SQL needed to align them.

Only structure travels over the wire, never table contents. DbMySync never
changes a database itself: it shows SQL that you run by hand.

## Components

```
                      developer machine (XAMPP, port 82)
   ┌────────────────────────────────────────────────────────────────┐
   │ main-application/                                              │
   │                                                                │
   │  public/index.php ── router, AJAX handlers                     │
   │      │                                                         │
   │      ├── Models/ToolPair, Models/Group ──► data/dbmysync.db    │
   │      │                                     (SQLite)            │
   │      ├── EndpointClient (Guzzle) ──────────────┐               │
   │      ├── SchemaComparator                      │               │
   │      ├── SqlGenerator                          │               │
   │      └── views/*.php + public/dashboard.js     │               │
   └────────────────────────────────────────────────┼───────────────┘
                                                    │ HTTP GET
                          X-DbSync-Secret header    │ ?action=health|schema
                    ┌───────────────────────────────┴──────────────┐
                    ▼                                              ▼
   ┌──────────────────────────────┐              ┌──────────────────────────────┐
   │ project A, env1 (localhost)  │              │ project A, env2 (web host)   │
   │  <project>/dbmysync/index.php│              │  <project>/dbmysync/index.php│
   │  Endpoint → Auth             │              │  Endpoint → Auth             │
   │           → SchemaReader ──► │ MySQL        │           → SchemaReader ──► │ MySQL
   └──────────────────────────────┘              └──────────────────────────────┘
```

| Part | Code | Responsibility |
|---|---|---|
| Endpoint | `endpoint/src/Endpoint.php` | Route `action`, CORS headers, error handling |
| | `endpoint/src/Auth.php` | Compare the sent secret with the configured one (`hash_equals`) |
| | `endpoint/src/SchemaReader.php` | Read tables, columns, keys from `INFORMATION_SCHEMA` |
| | `endpoint/src/Response.php` | JSON envelope `{success, data|error}` |
| Main app | `main-application/public/index.php` | Router for pages, form posts and AJAX |
| | `src/EndpointClient.php` | HTTP calls to endpoints, classifies health results |
| | `src/SchemaComparator.php` | Structural diff of two schemas, difference count |
| | `src/SqlGenerator.php` | `CREATE TABLE` / `ALTER TABLE` statements per direction |
| | `src/Database.php` | SQLite connection, schema creation, in-place migrations |
| | `src/Models/ToolPair.php`, `Group.php` | Persistence of pairs, groups, cached counts, history |
| | `views/` | Server-rendered pages (dashboard, form, comparison) |
| | `public/dashboard.js` | Traffic lights, refresh queue, collapsible groups |

## Request lifecycle: opening a comparison

1. Browser requests `index.php?action=compare&id=13`.
2. `showComparison()` loads the pair from SQLite.
3. `EndpointClient::fetchSchema()` calls env1, then env2 with `?action=schema`.
   Each endpoint answers with all tables (see [endpoint/api.md](../endpoint/api.md)).
4. `SchemaComparator::compare()` builds the difference structure;
   `countDifferences()` turns it into one number.
5. The number is written to `sync_history` and cached on the pair
   (`updateDiffResult`), so the dashboard badge matches.
6. `views/comparison.php` renders the differences and, through `SqlGenerator`,
   embeds the SQL for every button (whole pair, per table, per item) as JSON.
   The SQL modal only reads that JSON; no further request is made.
7. If either fetch fails, the error is shown, recorded in history and the badge
   turns red.

## Request lifecycle: dashboard

The dashboard never compares on page load. It renders cached counts and then:

- `ajax=smoke_test` per pair: two health checks, result drives the traffic lights.
- `ajax=refresh_pair` only when a refresh button is pressed: health checks plus,
  if both are green, a full comparison. Details in
  [main-application/dashboard.md](../main-application/dashboard.md).

Secrets never reach the browser: AJAX calls pass the pair ID and the server
looks up URL and secret. (Exception: the endpoint check in the add/edit form,
see [known-limitations.md](../known-limitations.md).)

## Technology

| | Main application | Endpoint |
|---|---|---|
| PHP | 8.1+ (local: XAMPP 8.2.12) | 8.1+ |
| Libraries | Guzzle 7 (HTTP), Twig 3 (installed, views are plain PHP), PHPUnit 9 (dev) | none at runtime |
| Storage | SQLite file `main-application/data/dbmysync.db` | the project's MySQL/MariaDB |
| Frontend | server-rendered PHP views, vanilla JS, one stylesheet | – |
