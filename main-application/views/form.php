<?php
$isEdit = isset($pair);
$title = $isEdit ? 'Edit Tool Pair' : 'Add Tool Pair';
ob_start();
?>

<div style="margin-bottom: 20px;">
    <a href="index.php" class="btn">← Back to Dashboard</a>
</div>

<div class="card">
    <h2><?= $isEdit ? 'Edit' : 'Add New' ?> Tool Pair</h2>
    
    <form method="POST" action="index.php">
        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $pair['id'] ?>">
        <?php endif; ?>
        
        <div class="form-group">
            <label for="name">Tool Name *</label>
            <input type="text" id="name" name="name"
                   value="<?= htmlspecialchars($pair['name'] ?? '') ?>"
                   placeholder="e.g., My CMS" required>
        </div>

        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #2c3e50;">Environment 1</h3>

        <div class="form-group">
            <label for="env1_name">Environment 1 Name *</label>
            <input type="text" id="env1_name" name="env1_name"
                   value="<?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>"
                   placeholder="Dev" required>
            <small style="color: #666;">e.g., Dev, Local, Staging</small>
        </div>

        <div class="form-group">
            <label for="env1_url">Environment 1 Endpoint URL *</label>
            <input type="url" id="env1_url" name="env1_url"
                   value="<?= htmlspecialchars($pair['env1_url'] ?? '') ?>"
                   placeholder="http://localhost/myproject/dbsync/" required>
            <small style="color: #666;">The URL to your endpoint (including trailing slash)</small>
        </div>

        <div class="form-group">
            <label for="env1_secret">Environment 1 Secret *</label>
            <input type="text" id="env1_secret" name="env1_secret"
                   value="<?= htmlspecialchars($pair['env1_secret'] ?? '') ?>"
                   placeholder="your-secret-key" required>
        </div>

        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #2c3e50;">Environment 2</h3>

        <div class="form-group">
            <label for="env2_name">Environment 2 Name *</label>
            <input type="text" id="env2_name" name="env2_name"
                   value="<?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>"
                   placeholder="Prod" required>
            <small style="color: #666;">e.g., Prod, Production, Live</small>
        </div>

        <div class="form-group">
            <label for="env2_url">Environment 2 Endpoint URL *</label>
            <input type="url" id="env2_url" name="env2_url"
                   value="<?= htmlspecialchars($pair['env2_url'] ?? '') ?>"
                   placeholder="https://myproject.com/dbsync/" required>
            <small style="color: #666;">The URL to your endpoint (including trailing slash)</small>
        </div>

        <div class="form-group">
            <label for="env2_secret">Environment 2 Secret *</label>
            <input type="text" id="env2_secret" name="env2_secret"
                   value="<?= htmlspecialchars($pair['env2_secret'] ?? '') ?>"
                   placeholder="your-secret-key" required>
        </div>
        
        <div style="margin-top: 30px;">
            <button type="submit" class="btn btn-success">
                <?= $isEdit ? 'Update' : 'Create' ?> Tool Pair
            </button>
            <a href="index.php" class="btn" style="margin-left: 10px;">Cancel</a>
        </div>
    </form>
</div>

<div class="card">
    <h2>💡 Tips</h2>
    <ul style="line-height: 2;">
        <li>Make sure both endpoints are accessible from this machine</li>
        <li>Use strong, unique secrets for each endpoint</li>
        <li>Test the connection after creating the tool pair</li>
        <li>URLs should end with a trailing slash</li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>

