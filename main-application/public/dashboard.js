/**
 * DbMySync dashboard
 *
 * Two independent jobs on this page:
 *  - the endpoint traffic lights, checked automatically on load
 *  - the difference counts, which are rendered from the last stored result and
 *    only re-checked when the user presses one of the refresh buttons
 */

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
