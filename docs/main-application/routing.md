# Routing

Everything goes through `main-application/public/index.php`. There is no
framework: the file checks the request in this order.

1. `?ajax=...` → `handleAjax()`, JSON response
2. `POST` → `handlePost()`, redirect
3. `?action=...` → page or GET action

`Database::getInstance()` runs first on every request, so schema migrations are
applied before any handler (see [data-model.md](data-model.md)).

## Pages and GET actions

| `action` | Handler | View / effect |
|---|---|---|
| `dashboard` (default) | `showDashboard()` | `views/dashboard.php` with grouped pairs |
| `add` | `showAddForm()` | `views/form.php` (empty) |
| `edit&id=` | `showEditForm()` | `views/form.php` (filled) |
| `compare&id=` | `showComparison()` | fetches both schemas, `views/comparison.php` |
| `duplicate&id=` | `handleDuplicate()` | copies the pair as "Name (Kopie)", opens it in edit |
| `delete&id=` | `handleDelete()` | soft delete (`active = 0`), removes empty groups |

Unknown actions fall back to the dashboard. Missing or unknown IDs redirect to
the dashboard.

## POST

`handlePost()` reads `action` from the form:

| `action` | Effect |
|---|---|
| `create` | `ToolPair::create()` |
| `update` | `ToolPair::update($_POST['id'])` |

Before saving, `group_name` is resolved to a group ID; a new name creates the
group (`Group::resolveOrCreate`). Afterwards groups without active pairs are
deleted (`Group::deleteUnused`).

## AJAX

| `ajax` | Parameters | Used by | Does |
|---|---|---|---|
| `validate_endpoint` | `url`, `secret` | form, while typing | One health check, returns `status` = `success` / `auth_failed` / `not_found` / `unreachable` |
| `smoke_test` | `id` | dashboard on load | Health check of both environments → traffic light levels |
| `refresh_pair` | `id` | dashboard refresh buttons | Health checks, then compare and cache the count if both are green |

Traffic light levels (`smokeTestEnvironment()`):

| Endpoint result | `level` | Light |
|---|---|---|
| `success` | `ok` | green |
| `auth_failed` | `warn` | yellow |
| `not_found`, `unreachable`, no URL | `error` | red |

`refresh_pair` returns the badge as ready-made `class`, `label` and `title`
(built by `diffBadgeState()` in `views/helpers.php`), so a refreshed badge looks
exactly like one rendered after a reload.

## Views

| File | Content |
|---|---|
| `views/layout.php` | Page frame; views fill `$content` via output buffering |
| `views/helpers.php` | `icon()`, `diffBadge()`, `diffBadgeState()`, group summaries, refresh buttons |
| `views/dashboard.php` | Groups, pairs, badges, lights |
| `views/form.php` | Add/edit form incl. storage engine selects and live endpoint check |
| `views/comparison.php` | Difference tables, SQL modal, all generated SQL embedded as JSON |

`styles.css` and `dashboard.js` are linked with a `filemtime()` query string, so
edits reach the browser without a hard reload.
