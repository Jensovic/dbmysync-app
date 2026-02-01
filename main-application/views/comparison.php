<?php
$title = 'Comparison - ' . htmlspecialchars($pair['name']);

// Initialize SQL Generator with schemas (only if schemas are available)
use Jensovic\DbMySync\SqlGenerator;
$sqlGenerator = null;
if ($offlineSchema !== null && $onlineSchema !== null) {
    $sqlGenerator = new SqlGenerator($offlineSchema, $onlineSchema);
}

ob_start();
?>

<style>
@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}
</style>

<div style="margin-bottom: 20px;">
    <a href="index.php" class="btn">← Back to Dashboard</a>
    <a href="?action=compare&id=<?= $pair['id'] ?>" class="btn" style="margin-left: 10px;">🔄 Refresh</a>
</div>

<div class="card">
    <h2>Comparing: <?= htmlspecialchars($pair['name']) ?></h2>
    <p style="color: #666; margin-top: 10px;">
        <strong><?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>:</strong> <code><?= htmlspecialchars($pair['env1_url']) ?></code><br>
        <strong><?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>:</strong> <code><?= htmlspecialchars($pair['env2_url']) ?></code>
    </p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">
        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
    </div>
<?php else: ?>
    
    <?php
    $totalDiffs = $comparator->countDifferences($differences);
    ?>
    
    <?php if ($totalDiffs === 0): ?>
        <div class="alert alert-success">
            <strong>✓ Databases are in sync!</strong><br>
            No differences found between environments.
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <strong>⚠ Found <?= $totalDiffs ?> difference(s)</strong><br>
            Review the differences below and apply necessary changes.
        </div>

        <!-- SQL Generator Buttons -->
        <div class="card" style="background: #f8f9fa;">
            <h3 style="margin-bottom: 15px;">🔧 Generate Migration SQL</h3>
            <p style="margin-bottom: 15px; color: #666;">
                Generate SQL statements to synchronize your databases:
            </p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="showSqlModal('env1_to_env2', null)" class="btn btn-success">
                    📝 SQL to adjust <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>
                </button>
                <button onclick="showSqlModal('env2_to_env1', null)" class="btn">
                    📝 SQL to adjust <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Missing Tables in Env2 -->
    <?php if (!empty($differences['missing_tables_online'])): ?>
        <div class="card">
            <h2>📋 Tables Missing in <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?> (<?= count($differences['missing_tables_online']) ?>)</h2>
            <p style="color: #666; margin-bottom: 15px;">These tables exist in <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?> but not in <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>:</p>
            <table style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($differences['missing_tables_online'] as $table): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($table) ?></code></td>
                            <td>
                                <button onclick="showSqlModal('env1_to_env2', '<?= htmlspecialchars($table) ?>', null, 'missing_table')" class="btn btn-small btn-success">
                                    CREATE TABLE for <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Missing Tables in Env1 -->
    <?php if (!empty($differences['missing_tables_offline'])): ?>
        <div class="card">
            <h2>📋 Tables Missing in <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?> (<?= count($differences['missing_tables_offline']) ?>)</h2>
            <p style="color: #666; margin-bottom: 15px;">These tables exist in <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?> but not in <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>:</p>
            <table style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($differences['missing_tables_offline'] as $table): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($table) ?></code></td>
                            <td>
                                <button onclick="showSqlModal('env2_to_env1', '<?= htmlspecialchars($table) ?>', null, 'missing_table')" class="btn btn-small">
                                    CREATE TABLE for <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Table Differences -->
    <?php if (!empty($differences['table_differences'])): ?>
        <?php foreach ($differences['table_differences'] as $tableName => $tableDiff): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">🔧 Table: <?= htmlspecialchars($tableName) ?></h2>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="showSqlModal('env1_to_env2', '<?= htmlspecialchars($tableName) ?>')" class="btn btn-small btn-success">
                            SQL for <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>
                        </button>
                        <button onclick="showSqlModal('env2_to_env1', '<?= htmlspecialchars($tableName) ?>')" class="btn btn-small">
                            SQL for <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>
                        </button>
                    </div>
                </div>
                
                <!-- Missing Columns Online -->
                <?php if (!empty($tableDiff['missing_columns_online'])): ?>
                    <h3 style="margin-top: 20px; color: #e74c3c;">Columns Missing in <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>:</h3>
                    <table style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Type</th>
                                <th>Nullable</th>
                                <th>Default</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableDiff['missing_columns_online'] as $col): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($col['name']) ?></code></td>
                                    <td><?= htmlspecialchars($col['type']) ?></td>
                                    <td><?= $col['nullable'] ? 'YES' : 'NO' ?></td>
                                    <td><?= htmlspecialchars($col['default'] ?? 'NULL') ?></td>
                                    <td>
                                        <button onclick="showSqlModal('env1_to_env2', '<?= htmlspecialchars($tableName) ?>', '<?= htmlspecialchars($col['name']) ?>', 'missing_column')" class="btn btn-small btn-success">
                                            SQL for <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <!-- Missing Columns Offline -->
                <?php if (!empty($tableDiff['missing_columns_offline'])): ?>
                    <h3 style="margin-top: 20px; color: #f39c12;">Columns Missing in <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>:</h3>
                    <table style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Column</th>
                                <th>Type</th>
                                <th>Nullable</th>
                                <th>Default</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableDiff['missing_columns_offline'] as $col): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($col['name']) ?></code></td>
                                    <td><?= htmlspecialchars($col['type']) ?></td>
                                    <td><?= $col['nullable'] ? 'YES' : 'NO' ?></td>
                                    <td><?= htmlspecialchars($col['default'] ?? 'NULL') ?></td>
                                    <td>
                                        <button onclick="showSqlModal('env2_to_env1', '<?= htmlspecialchars($tableName) ?>', '<?= htmlspecialchars($col['name']) ?>', 'missing_column')" class="btn btn-small">
                                            SQL for <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Column Differences -->
                <?php if (!empty($tableDiff['column_differences'])): ?>
                    <h3 style="margin-top: 20px; color: #3498db;">Column Differences:</h3>
                    <?php foreach ($tableDiff['column_differences'] as $colName => $colDiff): ?>
                        <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border-left: 3px solid #3498db;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <strong><?= htmlspecialchars($colName) ?>:</strong>
                                    <ul style="margin-top: 5px;">
                                        <?php foreach ($colDiff as $prop => $values): ?>
                                            <li>
                                                <strong><?= ucfirst($prop) ?>:</strong>
                                                <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>: <code><?= htmlspecialchars(json_encode($values['offline'])) ?></code>,
                                                <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>: <code><?= htmlspecialchars(json_encode($values['online'])) ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <div style="display: flex; gap: 5px; margin-left: 15px;">
                                    <button onclick="showSqlModal('env1_to_env2', '<?= htmlspecialchars($tableName) ?>', '<?= htmlspecialchars($colName) ?>', 'column_difference')" class="btn btn-small btn-success">
                                        SQL for <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>
                                    </button>
                                    <button onclick="showSqlModal('env2_to_env1', '<?= htmlspecialchars($tableName) ?>', '<?= htmlspecialchars($colName) ?>', 'column_difference')" class="btn btn-small">
                                        SQL for <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Missing Indexes in Env2 -->
                <?php if (!empty($tableDiff['missing_indexes_online'])): ?>
                    <h3 style="margin-top: 20px; color: #e74c3c;">Indexes Missing in <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>:</h3>
                    <table style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Index Name</th>
                                <th>Type</th>
                                <th>Columns</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableDiff['missing_indexes_online'] as $idx): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($idx['name']) ?></code></td>
                                    <td><?= $idx['unique'] ? '<span class="badge badge-warning">UNIQUE</span>' : 'INDEX' ?></td>
                                    <td><?= implode(', ', array_map('htmlspecialchars', $idx['columns'])) ?></td>
                                    <td>
                                        <button onclick="showSqlModal('env1_to_env2', '<?= htmlspecialchars($tableName) ?>', '<?= htmlspecialchars($idx['name']) ?>', 'missing_index')" class="btn btn-small btn-success">
                                            SQL for <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Missing Indexes in Env1 -->
                <?php if (!empty($tableDiff['missing_indexes_offline'])): ?>
                    <h3 style="margin-top: 20px; color: #f39c12;">Indexes Missing in <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>:</h3>
                    <table style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Index Name</th>
                                <th>Type</th>
                                <th>Columns</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableDiff['missing_indexes_offline'] as $idx): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($idx['name']) ?></code></td>
                                    <td><?= $idx['unique'] ? '<span class="badge badge-warning">UNIQUE</span>' : 'INDEX' ?></td>
                                    <td><?= implode(', ', array_map('htmlspecialchars', $idx['columns'])) ?></td>
                                    <td>
                                        <button onclick="showSqlModal('env2_to_env1', '<?= htmlspecialchars($tableName) ?>', '<?= htmlspecialchars($idx['name']) ?>', 'missing_index')" class="btn btn-small">
                                            SQL for <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Missing Foreign Keys in Env2 -->
                <?php if (!empty($tableDiff['missing_foreign_keys_online'])): ?>
                    <h3 style="margin-top: 20px; color: #e74c3c;">Foreign Keys Missing in <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>:</h3>
                    <table style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Constraint Name</th>
                                <th>Columns</th>
                                <th>References</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableDiff['missing_foreign_keys_online'] as $fk): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($fk['name']) ?></code></td>
                                    <td><?= implode(', ', array_map('htmlspecialchars', $fk['columns'])) ?></td>
                                    <td><?= htmlspecialchars($fk['referenced_table']) ?> (<?= implode(', ', array_map('htmlspecialchars', $fk['referenced_columns'])) ?>)</td>
                                    <td>
                                        <button onclick="showSqlModal('env1_to_env2', '<?= htmlspecialchars($tableName) ?>', '<?= htmlspecialchars($fk['name']) ?>', 'missing_foreign_key')" class="btn btn-small btn-success">
                                            SQL for <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Missing Foreign Keys in Env1 -->
                <?php if (!empty($tableDiff['missing_foreign_keys_offline'])): ?>
                    <h3 style="margin-top: 20px; color: #f39c12;">Foreign Keys Missing in <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>:</h3>
                    <table style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Constraint Name</th>
                                <th>Columns</th>
                                <th>References</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableDiff['missing_foreign_keys_offline'] as $fk): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($fk['name']) ?></code></td>
                                    <td><?= implode(', ', array_map('htmlspecialchars', $fk['columns'])) ?></td>
                                    <td><?= htmlspecialchars($fk['referenced_table']) ?> (<?= implode(', ', array_map('htmlspecialchars', $fk['referenced_columns'])) ?>)</td>
                                    <td>
                                        <button onclick="showSqlModal('env2_to_env1', '<?= htmlspecialchars($tableName) ?>', '<?= htmlspecialchars($fk['name']) ?>', 'missing_foreign_key')" class="btn btn-small">
                                            SQL for <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Primary Key Difference -->
                <?php if (!empty($tableDiff['primary_key_difference'])): ?>
                    <h3 style="margin-top: 20px; color: #9b59b6;">Primary Key Difference:</h3>
                    <div style="background: #f8f9fa; padding: 10px; margin: 10px 0;">
                        <?php $pkDiff = $tableDiff['primary_key_difference']; ?>
                        <?php if ($pkDiff['type'] === 'missing_offline'): ?>
                            <strong>Missing in <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>:</strong> (<?= implode(', ', array_map('htmlspecialchars', $pkDiff['online']['columns'])) ?>)
                        <?php elseif ($pkDiff['type'] === 'missing_online'): ?>
                            <strong>Missing in <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>:</strong> (<?= implode(', ', array_map('htmlspecialchars', $pkDiff['offline']['columns'])) ?>)
                        <?php else: ?>
                            <strong>Different:</strong><br>
                            <?= htmlspecialchars($pair['env1_name'] ?? 'Dev') ?>: (<?= implode(', ', array_map('htmlspecialchars', $pkDiff['offline'])) ?>)<br>
                            <?= htmlspecialchars($pair['env2_name'] ?? 'Prod') ?>: (<?= implode(', ', array_map('htmlspecialchars', $pkDiff['online'])) ?>)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

<!-- SQL Modal -->
<div id="sqlModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: relative; max-width: 900px; margin: 50px auto; background: white; border-radius: 8px; padding: 30px; max-height: 80vh; overflow-y: auto;">
        <button onclick="closeSqlModal()" style="position: absolute; top: 15px; right: 15px; background: #e74c3c; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">✕ Close</button>

        <h2 id="sqlModalTitle" style="margin-bottom: 20px;">Migration SQL</h2>

        <div id="sqlModalContent"></div>

        <div style="margin-top: 20px;">
            <button onclick="copyAllSql()" class="btn btn-success">📋 Copy All SQL</button>
            <button onclick="closeSqlModal()" class="btn" style="margin-left: 10px;">Close</button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<?php if ($sqlGenerator !== null && $differences !== null): ?>
<script>
// Store SQL data - generate for all tables
const allSqlData = {
    env1_to_env2: <?= json_encode($sqlGenerator->generateSql($differences, 'env1_to_env2')) ?>,
    env2_to_env1: <?= json_encode($sqlGenerator->generateSql($differences, 'env2_to_env1')) ?>
};

// Store table-specific SQL data
const tableSqlData = {};
const columnSqlData = {};

// Store missing table SQL data
<?php if (!empty($differences['missing_tables_online'])): ?>
    <?php foreach ($differences['missing_tables_online'] as $tableName): ?>
        tableSqlData['<?= addslashes($tableName) ?>_missing_table'] = {
            env1_to_env2: <?= json_encode($sqlGenerator->generateSql($differences, 'env1_to_env2', $tableName, null, 'missing_table')) ?>
        };
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($differences['missing_tables_offline'])): ?>
    <?php foreach ($differences['missing_tables_offline'] as $tableName): ?>
        tableSqlData['<?= addslashes($tableName) ?>_missing_table'] = {
            env2_to_env1: <?= json_encode($sqlGenerator->generateSql($differences, 'env2_to_env1', $tableName, null, 'missing_table')) ?>
        };
    <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($differences['table_differences'])): ?>
    <?php foreach ($differences['table_differences'] as $tableName => $tableDiff): ?>
        tableSqlData['<?= addslashes($tableName) ?>'] = {
            env1_to_env2: <?= json_encode($sqlGenerator->generateSql($differences, 'env1_to_env2', $tableName)) ?>,
            env2_to_env1: <?= json_encode($sqlGenerator->generateSql($differences, 'env2_to_env1', $tableName)) ?>
        };

        // Store column-specific SQL
        columnSqlData['<?= addslashes($tableName) ?>'] = {};

        <?php if (!empty($tableDiff['missing_columns_online'])): ?>
            <?php foreach ($tableDiff['missing_columns_online'] as $col): ?>
                columnSqlData['<?= addslashes($tableName) ?>']['<?= addslashes($col['name']) ?>_missing_column'] = {
                    env1_to_env2: <?= json_encode($sqlGenerator->generateSql($differences, 'env1_to_env2', $tableName, $col['name'], 'missing_column')) ?>
                };
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($tableDiff['missing_columns_offline'])): ?>
            <?php foreach ($tableDiff['missing_columns_offline'] as $col): ?>
                columnSqlData['<?= addslashes($tableName) ?>']['<?= addslashes($col['name']) ?>_missing_column'] = {
                    env2_to_env1: <?= json_encode($sqlGenerator->generateSql($differences, 'env2_to_env1', $tableName, $col['name'], 'missing_column')) ?>
                };
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($tableDiff['column_differences'])): ?>
            <?php foreach (array_keys($tableDiff['column_differences']) as $colName): ?>
                columnSqlData['<?= addslashes($tableName) ?>']['<?= addslashes($colName) ?>_column_difference'] = {
                    env1_to_env2: <?= json_encode($sqlGenerator->generateSql($differences, 'env1_to_env2', $tableName, $colName, 'column_difference')) ?>,
                    env2_to_env1: <?= json_encode($sqlGenerator->generateSql($differences, 'env2_to_env1', $tableName, $colName, 'column_difference')) ?>
                };
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($tableDiff['missing_indexes_online'])): ?>
            <?php foreach ($tableDiff['missing_indexes_online'] as $idx): ?>
                columnSqlData['<?= addslashes($tableName) ?>']['<?= addslashes($idx['name']) ?>_missing_index'] = {
                    env1_to_env2: <?= json_encode($sqlGenerator->generateSql($differences, 'env1_to_env2', $tableName, $idx['name'], 'missing_index')) ?>
                };
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($tableDiff['missing_indexes_offline'])): ?>
            <?php foreach ($tableDiff['missing_indexes_offline'] as $idx): ?>
                columnSqlData['<?= addslashes($tableName) ?>']['<?= addslashes($idx['name']) ?>_missing_index'] = {
                    env2_to_env1: <?= json_encode($sqlGenerator->generateSql($differences, 'env2_to_env1', $tableName, $idx['name'], 'missing_index')) ?>
                };
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($tableDiff['missing_foreign_keys_online'])): ?>
            <?php foreach ($tableDiff['missing_foreign_keys_online'] as $fk): ?>
                columnSqlData['<?= addslashes($tableName) ?>']['<?= addslashes($fk['name']) ?>_missing_foreign_key'] = {
                    env1_to_env2: <?= json_encode($sqlGenerator->generateSql($differences, 'env1_to_env2', $tableName, $fk['name'], 'missing_foreign_key')) ?>
                };
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($tableDiff['missing_foreign_keys_offline'])): ?>
            <?php foreach ($tableDiff['missing_foreign_keys_offline'] as $fk): ?>
                columnSqlData['<?= addslashes($tableName) ?>']['<?= addslashes($fk['name']) ?>_missing_foreign_key'] = {
                    env2_to_env1: <?= json_encode($sqlGenerator->generateSql($differences, 'env2_to_env1', $tableName, $fk['name'], 'missing_foreign_key')) ?>
                };
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

const envNames = {
    env1: <?= json_encode($pair['env1_name'] ?? 'Dev') ?>,
    env2: <?= json_encode($pair['env2_name'] ?? 'Prod') ?>
};

function showSqlModal(direction, tableName = null, columnName = null, diffType = null) {
    const modal = document.getElementById('sqlModal');
    const title = document.getElementById('sqlModalTitle');
    const content = document.getElementById('sqlModalContent');

    const target = direction === 'env1_to_env2' ? envNames.env2 : envNames.env1;

    let statements;

    if (columnName && tableName && diffType) {
        // Column-specific SQL (indexes, foreign keys, columns)
        const key = columnName + '_' + diffType;
        statements = columnSqlData[tableName][key][direction];
        title.textContent = `SQL to adjust ${target} - ${tableName}.${columnName}`;
    } else if (tableName && diffType === 'missing_table') {
        // Missing table SQL
        const key = tableName + '_missing_table';
        statements = tableSqlData[key][direction];
        title.textContent = `SQL to adjust ${target} - CREATE TABLE: ${tableName}`;
    } else if (tableName) {
        // Table-specific SQL (all changes for a table)
        statements = tableSqlData[tableName][direction];
        title.textContent = `SQL to adjust ${target} - Table: ${tableName}`;
    } else {
        // All changes
        statements = allSqlData[direction];
        title.textContent = `SQL to adjust ${target} - All Changes`;
    }

    currentSqlStatements = statements; // Store for copy function

    if (statements.length === 0) {
        content.innerHTML = '<div class="alert alert-info">No SQL statements needed.</div>';
    } else {
        let html = '<div style="margin-bottom: 15px; color: #666;">';
        html += `<strong>${statements.length}</strong> SQL statement(s) to execute on <strong>${target}</strong>:`;
        html += '</div>';

        statements.forEach((stmt, index) => {
            html += '<div style="background: #f8f9fa; padding: 15px; margin-bottom: 15px; border-left: 3px solid #3498db; border-radius: 4px;">';
            html += `<div style="margin-bottom: 10px;"><strong>#${index + 1}</strong> - ${stmt.type.replace(/_/g, ' ').toUpperCase()}</div>`;

            if (stmt.warning) {
                html += `<div style="background: #fff3cd; padding: 10px; margin-bottom: 10px; border-radius: 4px; color: #856404;">⚠️ ${stmt.warning}</div>`;
            }

            html += '<pre style="background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; overflow-x: auto; margin: 0;">';
            html += escapeHtml(stmt.sql);
            html += '</pre>';

            html += `<button onclick="copySql(${index})" class="btn btn-small" style="margin-top: 10px;">📋 Copy</button>`;
            html += '</div>';
        });

        content.innerHTML = html;
    }

    modal.style.display = 'block';
}

function closeSqlModal() {
    document.getElementById('sqlModal').style.display = 'none';
}

function copySql(index) {
    const sql = currentSqlStatements[index].sql;
    navigator.clipboard.writeText(sql).then(() => {
        showToast('✓ SQL statement copied to clipboard!', 'success');
    }).catch(() => {
        showToast('✗ Failed to copy SQL', 'error');
    });
}

let currentSqlStatements = [];

function copyAllSql() {
    const allSql = currentSqlStatements.map(stmt => stmt.sql).join('\n\n');

    navigator.clipboard.writeText(allSql).then(() => {
        showToast(`✓ All ${currentSqlStatements.length} SQL statement(s) copied to clipboard!`, 'success');
    }).catch(() => {
        showToast('✗ Failed to copy SQL', 'error');
    });
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');

    const bgColor = type === 'success' ? '#27ae60' : '#e74c3c';

    toast.style.cssText = `
        background: ${bgColor};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        font-weight: 500;
        animation: slideIn 0.3s ease-out;
        min-width: 300px;
    `;

    toast.textContent = message;
    container.appendChild(toast);

    // Auto-remove after 2 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, 2000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeSqlModal();
    }
});
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
