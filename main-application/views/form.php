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
            <div id="env1_url_status" class="endpoint-status"></div>
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
            <div id="env2_url_status" class="endpoint-status"></div>
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

<style>
.endpoint-status {
    margin-top: 8px;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    display: none;
}

.endpoint-status.checking {
    display: block;
    background-color: #f0f0f0;
    color: #666;
    border-left: 3px solid #999;
}

.endpoint-status.success {
    display: block;
    background-color: #d4edda;
    color: #155724;
    border-left: 3px solid #28a745;
}

.endpoint-status.warning {
    display: block;
    background-color: #fff3cd;
    color: #856404;
    border-left: 3px solid #ffc107;
}

.endpoint-status.error {
    display: block;
    background-color: #f8d7da;
    color: #721c24;
    border-left: 3px solid #dc3545;
}

.endpoint-status::before {
    margin-right: 6px;
}

.endpoint-status.checking::before {
    content: "⏳";
}

.endpoint-status.success::before {
    content: "✓";
}

.endpoint-status.warning::before {
    content: "⚠";
}

.endpoint-status.error::before {
    content: "✗";
}
</style>

<script>
// Debounce function to avoid too many requests
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Validate endpoint URL
async function validateEndpoint(urlFieldId, secretFieldId, statusDivId) {
    const urlField = document.getElementById(urlFieldId);
    const secretField = document.getElementById(secretFieldId);
    const statusDiv = document.getElementById(statusDivId);

    const url = urlField.value.trim();
    const secret = secretField.value.trim();

    if (!url) {
        statusDiv.style.display = 'none';
        return;
    }

    // Show checking status
    statusDiv.className = 'endpoint-status checking';
    statusDiv.textContent = 'Prüfe Endpoint...';

    try {
        const response = await fetch(`index.php?ajax=validate_endpoint&url=${encodeURIComponent(url)}&secret=${encodeURIComponent(secret)}`);
        const data = await response.json();

        // Handle different status codes
        switch (data.status) {
            case 'success':
                statusDiv.className = 'endpoint-status success';
                statusDiv.textContent = data.message;
                break;

            case 'auth_failed':
                statusDiv.className = 'endpoint-status warning';
                statusDiv.textContent = data.message;
                break;

            case 'not_found':
                statusDiv.className = 'endpoint-status error';
                statusDiv.textContent = data.message;
                break;

            case 'unreachable':
                statusDiv.className = 'endpoint-status error';
                statusDiv.textContent = data.message;
                break;

            default:
                statusDiv.className = 'endpoint-status error';
                statusDiv.textContent = 'Unbekannter Fehler';
        }
    } catch (error) {
        statusDiv.className = 'endpoint-status error';
        statusDiv.textContent = 'Endpoint nicht erreichbar';
    }
}

// Create debounced versions
const validateEnv1 = debounce(() => validateEndpoint('env1_url', 'env1_secret', 'env1_url_status'), 800);
const validateEnv2 = debounce(() => validateEndpoint('env2_url', 'env2_secret', 'env2_url_status'), 800);

// Attach event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const env1UrlField = document.getElementById('env1_url');
    const env1SecretField = document.getElementById('env1_secret');
    const env2UrlField = document.getElementById('env2_url');
    const env2SecretField = document.getElementById('env2_secret');

    // Environment 1
    env1UrlField.addEventListener('blur', validateEnv1);
    env1UrlField.addEventListener('input', validateEnv1);
    env1SecretField.addEventListener('blur', validateEnv1);
    env1SecretField.addEventListener('input', validateEnv1);

    // Environment 2
    env2UrlField.addEventListener('blur', validateEnv2);
    env2UrlField.addEventListener('input', validateEnv2);
    env2SecretField.addEventListener('blur', validateEnv2);
    env2SecretField.addEventListener('input', validateEnv2);

    // Validate on page load if values exist
    if (env1UrlField.value.trim()) {
        validateEnv1();
    }
    if (env2UrlField.value.trim()) {
        validateEnv2();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>

