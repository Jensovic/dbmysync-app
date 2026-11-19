# Dashboard

Code: `views/dashboard.php`, `views/helpers.php`, `public/dashboard.js`,
AJAX handlers in `public/index.php`. User-facing description:
[main-application/README.md](../../main-application/README.md#dashboard).

## Two independent mechanisms

| | Traffic lights | Difference badge |
|---|---|---|
| Shows | Can each endpoint be reached? | How many structural differences? |
| Source | live, `ajax=smoke_test` | cached `tool_pairs.last_diff_*` |
| When | automatically on every page load | only on refresh or when a comparison is opened |
| Cost | 2 small health requests per pair | 2 full schema downloads per pair |

The split exists because comparing all pairs on every page load was too slow.

## Badge states

Rendered by `diffBadge()` / `diffBadgeState()` (`views/helpers.php`). The same
function produces the JSON for refreshed badges, so both look identical.

| Badge | `last_diff_status` | `last_diff_count` |
|---|---|---|
| grey `?` | `NULL` | – (never checked) |
| green `0` | `ok` | 0 |
| yellow `n` | `ok` | n |
| red `!` | `error` | `NULL`; tooltip shows `last_diff_error` |

The tooltip always includes the check time.

## Refresh flow

Buttons exist for all pairs (header), one group, and one pair. All call
`refreshPairs(pairIds)` in `dashboard.js`, which appends to a queue.
`processRefreshQueue()` handles **one pair at a time** (each check means two
full schema downloads), calling `ajax=refresh_pair`:

1. Health check both environments, update the lights.
2. If one is not green: store `error` with a message naming the broken side
   (`unreachableMessage()`), skip the downloads.
3. Otherwise compare (`compareAndStore()`), store the count, return the badge.
4. Recalculate the group summary.

Refreshes do not write `sync_history`.

## Groups

- Assigned by typing a group name in the form (with suggestions).
- Listed by `sort_order`, then name; ungrouped pairs come last as
  "Ohne Gruppe".
- Collapsing is stored per browser in `localStorage`.
- A collapsed group shows sum and average of differences, counting only pairs
  whose last check succeeded (`groupDiffSummary()`); the tooltip says how many
  pairs contributed.
