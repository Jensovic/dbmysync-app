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

<script src="dashboard.js?v=<?= filemtime(__DIR__ . '/../public/dashboard.js') ?>" defer></script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
