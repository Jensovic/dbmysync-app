<?php
$title = 'Dashboard - DbMySync';
ob_start();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Tool Pairs</h2>
    <a href="?action=add" class="btn btn-success">+ Add New Tool Pair</a>
</div>

<?php if (empty($pairs)): ?>
    <div class="card">
        <div class="alert alert-info">
            <strong>No tool pairs configured yet.</strong><br>
            Click "Add New Tool Pair" to configure your first offline/online database pair.
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Environment 1</th>
                    <th>Environment 2</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pairs as $pair): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($pair['name']) ?></strong></td>
                    <td>
                        <strong><?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?></strong><br>
                        <code style="font-size: 11px;"><?= htmlspecialchars($pair['env1_url']) ?></code>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?></strong><br>
                        <code style="font-size: 11px;"><?= htmlspecialchars($pair['env2_url']) ?></code>
                    </td>
                    <td><?= date('d.m.Y H:i', strtotime($pair['created_at'])) ?></td>
                    <td>
                        <a href="?action=compare&id=<?= $pair['id'] ?>" class="btn btn-small">Compare</a>
                        <a href="?action=edit&id=<?= $pair['id'] ?>" class="btn btn-small">Edit</a>
                        <a href="?action=delete&id=<?= $pair['id'] ?>"
                           class="btn btn-small btn-danger"
                           onclick="return confirm('Really delete this tool pair?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Quick Start</h2>
    <ol style="line-height: 2;">
        <li>Install the <strong>DbMySync Endpoint</strong> in both environments: <code>composer require jensovic/dbmysync-addin</code></li>
        <li>Configure database credentials and secrets in each endpoint</li>
        <li>Add a new tool pair above with both endpoint URLs, secrets, and environment names</li>
        <li>Click "Compare" to see database structure differences</li>
        <li>Use "Apply to..." buttons to generate SQL migration scripts</li>
    </ol>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>

