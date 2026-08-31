<?php
require_once __DIR__ . '/helpers.php';

$title = 'Dashboard - DbMySync';
ob_start();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Tool Pairs</h2>
    <span class="btn-group">
        <?php if (!empty($groupedPairs)): ?>
            <?= diffRefreshButton('all', '', 'Differenzen aller Tool Pairs neu ermitteln', 'btn-refresh-all', 'Alle aktualisieren') ?>
        <?php endif; ?>
        <a href="?action=add" class="btn btn-success"><?= icon('add') ?>Add New Tool Pair</a>
    </span>
</div>

<?php if (empty($groupedPairs)): ?>
    <div class="card">
        <div class="alert alert-info">
            <strong>No tool pairs configured yet.</strong><br>
            Click "Add New Tool Pair" to configure your first offline/online database pair.
        </div>
    </div>
<?php else: ?>
    <?php foreach ($groupedPairs as $group): ?>
        <?php $groupKey = $group['id'] === null ? 'none' : (string) $group['id']; ?>
        <div class="card group-card<?= $group['id'] === null ? ' group-ungrouped' : '' ?>" data-group="<?= htmlspecialchars($groupKey) ?>">
            <div class="group-header">
                <button type="button" class="group-toggle"
                        aria-expanded="true" aria-controls="group-body-<?= htmlspecialchars($groupKey) ?>">
                    <span class="group-caret" aria-hidden="true"></span>
                    <span class="group-icon" aria-hidden="true"></span>
                    <h2><?= htmlspecialchars($group['name']) ?> <span class="group-count">(<?= count($group['pairs']) ?>)</span></h2>
                </button>
                <?= groupSummaryBadges($group['pairs']) ?>
                <?= diffRefreshButton(
                    'group',
                    $groupKey,
                    'Differenzen dieser Gruppe neu ermitteln',
                    'btn-small btn-icon btn-neutral'
                ) ?>
            </div>
            <div class="group-body" id="group-body-<?= htmlspecialchars($groupKey) ?>">
            <table class="table-pairs">
                <thead>
                    <tr>
                        <th class="col-name">Name</th>
                        <th class="col-env">Environment 1</th>
                        <th class="col-env">Environment 2</th>
                        <th class="col-modified">Last Modified</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group['pairs'] as $pair): ?>
                    <tr data-pair-group="<?= htmlspecialchars($groupKey) ?>">
                        <td>
                            <strong><?= htmlspecialchars($pair['name']) ?></strong>
                            <?= diffBadge($pair) ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?></strong>
                            <?= statusDot($pair['id'], 'env1') ?><br>
                            <code class="cell-url" title="<?= htmlspecialchars($pair['env1_url']) ?>"><?= htmlspecialchars($pair['env1_url']) ?></code>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?></strong>
                            <?= statusDot($pair['id'], 'env2') ?><br>
                            <code class="cell-url" title="<?= htmlspecialchars($pair['env2_url']) ?>"><?= htmlspecialchars($pair['env2_url']) ?></code>
                        </td>
                        <td><?= formatDate($pair['updated_at'] ?: $pair['created_at']) ?></td>
                        <td>
                            <span class="btn-group">
                                <?= diffRefreshButton(
                                    'pair',
                                    (string) $pair['id'],
                                    'Differenzen neu ermitteln',
                                    'btn-small btn-icon btn-neutral'
                                ) ?>
                                <a href="?action=compare&id=<?= $pair['id'] ?>"
                                   class="btn btn-small btn-icon" <?= iconButtonAttrs('Compare') ?>><?= icon('compare') ?></a>
                                <a href="?action=edit&id=<?= $pair['id'] ?>"
                                   class="btn btn-small btn-icon btn-neutral" <?= iconButtonAttrs('Edit') ?>><?= icon('edit') ?></a>
                                <a href="?action=duplicate&id=<?= $pair['id'] ?>"
                                   class="btn btn-small btn-icon btn-neutral" <?= iconButtonAttrs('Duplicate') ?>><?= icon('duplicate') ?></a>
                                <a href="?action=delete&id=<?= $pair['id'] ?>"
                                   class="btn btn-small btn-icon btn-danger" <?= iconButtonAttrs('Delete') ?>
                                   onclick="return confirm('Really delete this tool pair?')"><?= icon('delete') ?></a>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="card">
    <h2>Quick Start</h2>
    <ol style="line-height: 2;">
        <li>Install the <strong>DbMySync Endpoint</strong> in both environments: <code>composer require jensovic/dbmysync-addin</code></li>
        <li>Configure database credentials and secrets in each endpoint</li>
        <li>Add a new tool pair above with both endpoint URLs, secrets, and environment names</li>
        <li>Optionally assign a group to keep related tool pairs together</li>
        <li>Click the compare icon to see database structure differences</li>
        <li>Use the SQL buttons to generate migration scripts</li>
    </ol>
</div>

<script>
// Endpoint smoke test: one request per tool pair, both environments checked server side
function applySmokeStatus(dot, level, message) {
    dot.className = 'status-dot status-' + level;
    dot.title = message;
    dot.setAttribute('aria-label', 'Status: ' + message);
}

async function runSmokeTest(pairId, dots) {
    try {
        const response = await fetch('index.php?ajax=smoke_test&id=' + encodeURIComponent(pairId));
        const data = await response.json();

        dots.forEach(dot => {
            const result = data[dot.dataset.smokeEnv];

            if (result) {
                applySmokeStatus(dot, result.level, result.message);
            } else {
                applySmokeStatus(dot, 'error', data.message || 'Unbekannter Fehler');
            }
        });
    } catch (error) {
        dots.forEach(dot => applySmokeStatus(dot, 'error', 'Statusprüfung fehlgeschlagen'));
    }
}

// --------------------------------------------------------------------------
// On-demand refresh
//
// The badges are rendered from the last stored result, so a page load costs no
// endpoint traffic beyond the health check. A refresh button re-checks its
// pairs on demand: one request per pair that updates both traffic lights and
// the difference count, so the two can never disagree about reachability.
// A check means two full schema downloads, so pairs run one after another
// instead of firing every pair at its endpoints at once.
// --------------------------------------------------------------------------
const refreshQueue = [];
let refreshQueueRunning = false;

function badgeFor(pairId) {
    return document.querySelector('.diff-badge[data-diff-pair="' + pairId + '"]');
}

function dotsFor(pairId) {
    return Array.from(document.querySelectorAll('.status-dot[data-smoke-pair="' + pairId + '"]'));
}

function refreshPairs(pairIds, button) {
    const pending = pairIds.filter(id => !refreshQueue.includes(id));

    if (pending.length === 0) {
        return;
    }

    pending.forEach(pairId => {
        const badge = badgeFor(pairId);

        if (badge) {
            badge.className = 'diff-badge badge badge-checking';
            badge.textContent = '…';
            badge.title = 'Wartet auf Prüfung…';
        }

        dotsFor(pairId).forEach(dot => {
            applySmokeStatus(dot, 'checking', 'Status wird geprüft…');
        });

        refreshQueue.push(pairId);
    });

    setButtonBusy(button, true);

    if (!refreshQueueRunning) {
        refreshQueueRunning = true;
        processRefreshQueue().finally(() => {
            refreshQueueRunning = false;
            document.querySelectorAll('.btn-refresh').forEach(btn => setButtonBusy(btn, false));
        });
    }
}

function setButtonBusy(button, busy) {
    if (!button) {
        return;
    }

    button.disabled = busy;
    button.classList.toggle('is-busy', busy);
}

async function processRefreshQueue() {
    while (refreshQueue.length > 0) {
        const pairId = refreshQueue[0];
        const badge = badgeFor(pairId);
        const dots = dotsFor(pairId);

        if (badge) {
            badge.title = 'Vergleiche Strukturen…';
        }

        try {
            const response = await fetch('index.php?ajax=refresh_pair&id=' + encodeURIComponent(pairId));
            const data = await response.json();

            dots.forEach(dot => {
                const result = data[dot.dataset.smokeEnv];

                if (result) {
                    applySmokeStatus(dot, result.level, result.message);
                } else {
                    applySmokeStatus(dot, 'error', data.message || 'Unbekannter Fehler');
                }
            });

            if (badge) {
                // The server sends the badge wording, so a fresh result reads
                // exactly like the stored one after a reload
                badge.className = 'diff-badge badge ' + (data.class || 'badge-unknown');
                badge.textContent = data.label || '?';
                badge.title = data.title || data.message || '';
            }
        } catch (error) {
            dots.forEach(dot => applySmokeStatus(dot, 'error', 'Statusprüfung fehlgeschlagen'));

            if (badge) {
                badge.className = 'diff-badge badge badge-danger';
                badge.textContent = '!';
                badge.title = 'Prüfung fehlgeschlagen';
            }
        }

        if (badge) {
            updateGroupSummary(badge.closest('.group-card'));
        }

        refreshQueue.shift();
    }
}

// --------------------------------------------------------------------------
// Group headers: collapsible, with a summary that survives collapsing
// --------------------------------------------------------------------------

// Mirrors groupDiffSummary()/formatDiffAverage() in helpers.php so a refreshed
// summary reads exactly like the server-rendered one after a reload
function updateGroupSummary(card) {
    if (!card) {
        return;
    }

    const badges = Array.from(card.querySelectorAll('.diff-badge[data-diff-pair]'));
    let sum = 0;
    let checked = 0;

    badges.forEach(badge => {
        const value = parseInt(badge.textContent, 10);

        // '?', '!' and the '…' placeholder are not counts - an unchecked pair
        // must not read as zero differences
        if (!Number.isNaN(value)) {
            sum += value;
            checked++;
        }
    });

    const scope = ' (' + checked + ' von ' + badges.length + ' Tool Pairs geprüft)';
    const sumBox = card.querySelector('.group-sum');
    const avgBox = card.querySelector('.group-avg');

    if (sumBox) {
        sumBox.textContent = checked > 0 ? String(sum) : '-';
        sumBox.title = 'Summe der Differenzen' + scope;
    }

    if (avgBox) {
        avgBox.textContent = checked > 0 ? 'Ø ' + (sum / checked).toFixed(1).replace('.', ',') : 'Ø -';
        avgBox.title = 'Durchschnittliche Differenzen pro Tool Pair' + scope;
    }
}

const COLLAPSED_STORAGE_KEY = 'dbmysync.collapsedGroups';

function readCollapsedGroups() {
    try {
        const stored = JSON.parse(window.localStorage.getItem(COLLAPSED_STORAGE_KEY));
        return Array.isArray(stored) ? stored : [];
    } catch (error) {
        // A blocked or corrupted storage just means every group starts open
        return [];
    }
}

function storeCollapsedGroups(keys) {
    try {
        window.localStorage.setItem(COLLAPSED_STORAGE_KEY, JSON.stringify(keys));
    } catch (error) {
        // Remembering the state is a convenience, not a requirement
    }
}

function setGroupCollapsed(card, collapsed) {
    card.classList.toggle('is-collapsed', collapsed);

    const toggle = card.querySelector('.group-toggle');
    if (toggle) {
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }
}

function initGroupCollapsing() {
    const collapsed = new Set(readCollapsedGroups());

    document.querySelectorAll('.group-card').forEach(card => {
        const key = card.dataset.group;

        setGroupCollapsed(card, collapsed.has(key));

        const toggle = card.querySelector('.group-toggle');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function() {
            const nowCollapsed = !card.classList.contains('is-collapsed');

            setGroupCollapsed(card, nowCollapsed);

            if (nowCollapsed) {
                collapsed.add(key);
            } else {
                collapsed.delete(key);
            }

            storeCollapsedGroups(Array.from(collapsed));
        });
    });
}

function pairIdsForScope(scope, value) {
    let selector = '.diff-badge[data-diff-pair]';

    if (scope === 'pair') {
        selector = '.diff-badge[data-diff-pair="' + value + '"]';
    } else if (scope === 'group') {
        selector = 'tr[data-pair-group="' + value + '"] .diff-badge[data-diff-pair]';
    }

    return Array.from(document.querySelectorAll(selector)).map(badge => badge.dataset.diffPair);
}

document.addEventListener('DOMContentLoaded', function() {
    initGroupCollapsing();

    const byPair = new Map();

    document.querySelectorAll('.status-dot[data-smoke-pair]').forEach(dot => {
        const pairId = dot.dataset.smokePair;
        if (!byPair.has(pairId)) {
            byPair.set(pairId, []);
        }
        byPair.get(pairId).push(dot);
    });

    // Only the cheap health check runs automatically - difference counts wait
    // for an explicit refresh
    byPair.forEach((dots, pairId) => runSmokeTest(pairId, dots));

    document.querySelectorAll('.btn-refresh').forEach(button => {
        button.addEventListener('click', function() {
            refreshPairs(
                pairIdsForScope(button.dataset.refreshScope, button.dataset.refreshValue),
                button
            );
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
