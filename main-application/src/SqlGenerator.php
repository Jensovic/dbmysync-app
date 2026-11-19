<?php

namespace Jensovic\DbMySync;

/**
 * SQL Generator - Generates ALTER/CREATE statements from schema differences
 */
class SqlGenerator
{
    public const DEFAULT_ENGINE = 'InnoDB';

    private array $env1Schema;
    private array $env2Schema;

    /** @var array<string, string> Storage engine for CREATE TABLE per target env ('' = server default) */
    private array $engines;

    /**
     * @param array $options 'env1_engine' / 'env2_engine': engine used when a table is created
     *                       in that environment. Empty string omits the ENGINE clause.
     */
    public function __construct(array $env1Schema, array $env2Schema, array $options = [])
    {
        $this->env1Schema = $env1Schema;
        $this->env2Schema = $env2Schema;
        $this->engines = [
            'env1' => self::sanitizeEngine($options['env1_engine'] ?? self::DEFAULT_ENGINE),
            'env2' => self::sanitizeEngine($options['env2_engine'] ?? self::DEFAULT_ENGINE),
        ];
    }

    /**
     * Engine names end up verbatim in SQL - only plain identifiers are accepted.
     */
    public static function sanitizeEngine($engine): string
    {
        $engine = trim((string) $engine);
        return preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $engine) ? $engine : '';
    }

    /**
     * Generate SQL to apply differences to target environment
     *
     * @param array $differences Schema differences from SchemaComparator
     * @param string $direction 'env1_to_env2' or 'env2_to_env1'
     * @param string|null $tableName Optional: Generate SQL only for specific table
     * @param string|null $columnName Optional: Generate SQL only for specific column
     * @param string|null $diffType Optional: Type of difference (missing_columns, column_differences, etc.)
     * @return array Array of SQL statements
     */
    public function generateSql(array $differences, string $direction, ?string $tableName = null, ?string $columnName = null, ?string $diffType = null): array
    {
        $sql = [];

        if ($direction === 'env1_to_env2') {
            // Apply env1 (source) changes to env2 (target)
            $sql = array_merge($sql, $this->generateForEnv1ToEnv2($differences, $tableName, $columnName, $diffType));
        } else {
            // Apply env2 (source) changes to env1 (target)
            $sql = array_merge($sql, $this->generateForEnv2ToEnv1($differences, $tableName, $columnName, $diffType));
        }

        return $sql;
    }
    
    /**
     * Generate SQL to make env2 match env1
     */
    private function generateForEnv1ToEnv2(array $diff, ?string $filterTable = null, ?string $filterColumn = null, ?string $filterType = null): array
    {
        $sql = [];

        // Create missing tables in env2
        if (!empty($diff['missing_tables_online'])) {
            foreach ($diff['missing_tables_online'] as $table) {
                // If filtering for a specific table and type, only include that table
                if ($filterTable && $filterTable !== $table) {
                    continue;
                }
                if ($filterType && $filterType !== 'missing_table') {
                    continue;
                }

                $sql[] = $this->generateCreateTableSql($table, 'env1');
            }
        }

        // Handle table differences (only if not filtering for missing_table)
        if (!empty($diff['table_differences']) && $filterType !== 'missing_table') {
            foreach ($diff['table_differences'] as $tableName => $tableDiff) {
                if ($filterTable && $tableName !== $filterTable) {
                    continue;
                }
                $sql = array_merge($sql, $this->generateTableDiffSql($tableName, $tableDiff, 'env2', $filterColumn, $filterType));
            }
        }

        return $sql;
    }
    
    /**
     * Generate SQL to make env1 match env2
     */
    private function generateForEnv2ToEnv1(array $diff, ?string $filterTable = null, ?string $filterColumn = null, ?string $filterType = null): array
    {
        $sql = [];

        // Create missing tables in env1
        if (!empty($diff['missing_tables_offline'])) {
            foreach ($diff['missing_tables_offline'] as $table) {
                // If filtering for a specific table and type, only include that table
                if ($filterTable && $filterTable !== $table) {
                    continue;
                }
                if ($filterType && $filterType !== 'missing_table') {
                    continue;
                }

                $sql[] = $this->generateCreateTableSql($table, 'env2');
            }
        }

        // Handle table differences (only if not filtering for missing_table)
        if (!empty($diff['table_differences']) && $filterType !== 'missing_table') {
            foreach ($diff['table_differences'] as $tableName => $tableDiff) {
                if ($filterTable && $tableName !== $filterTable) {
                    continue;
                }
                $sql = array_merge($sql, $this->generateTableDiffSql($tableName, $tableDiff, 'env1', $filterColumn, $filterType));
            }
        }

        return $sql;
    }
    
    /**
     * Generate SQL for table-level differences
     */
    private function generateTableDiffSql(string $tableName, array $tableDiff, string $targetEnv, ?string $filterColumn = null, ?string $filterType = null): array
    {
        $sql = [];
        $missingColsKey = $targetEnv === 'env2' ? 'missing_columns_online' : 'missing_columns_offline';
        $missingIdxKey = $targetEnv === 'env2' ? 'missing_indexes_online' : 'missing_indexes_offline';
        $missingFkKey = $targetEnv === 'env2' ? 'missing_foreign_keys_online' : 'missing_foreign_keys_offline';

        // Add missing columns
        if (!empty($tableDiff[$missingColsKey]) && (!$filterType || $filterType === 'missing_column')) {
            // When the whole set is generated, statements run in source order, so a column
            // added earlier may serve as the anchor for the next one. When a single column
            // is generated, only columns that already exist in the target are valid anchors.
            $anchors = $this->computeColumnAnchors(
                $tableName,
                $tableDiff[$missingColsKey],
                $targetEnv,
                $filterColumn === null
            );

            foreach ($tableDiff[$missingColsKey] as $col) {
                if ($filterColumn && $col['name'] !== $filterColumn) {
                    continue;
                }
                $sql[] = [
                    'type' => 'add_column',
                    'table' => $tableName,
                    'column' => $col['name'],
                    'sql' => $this->generateAddColumnSql($tableName, $col, $anchors[$col['name']] ?? '')
                ];
            }
        }

        // Modify different columns
        if (!empty($tableDiff['column_differences']) && (!$filterType || $filterType === 'column_difference')) {
            foreach ($tableDiff['column_differences'] as $colName => $colDiff) {
                if ($filterColumn && $colName !== $filterColumn) {
                    continue;
                }
                $sql[] = [
                    'type' => 'modify_column',
                    'table' => $tableName,
                    'column' => $colName,
                    'sql' => $this->generateModifyColumnSql($tableName, $colName, $colDiff, $targetEnv),
                    'warning' => 'Review carefully: Modifying columns can affect existing data'
                ];
            }
        }

        // Add missing indexes
        if (!empty($tableDiff[$missingIdxKey]) && (!$filterType || $filterType === 'missing_index')) {
            foreach ($tableDiff[$missingIdxKey] as $idx) {
                if ($filterColumn && !in_array($filterColumn, $idx['columns'])) {
                    continue;
                }
                $sql[] = [
                    'type' => 'add_index',
                    'table' => $tableName,
                    'index' => $idx['name'],
                    'sql' => $this->generateAddIndexSql($tableName, $idx)
                ];
            }
        }

        // Add missing foreign keys
        if (!empty($tableDiff[$missingFkKey]) && (!$filterType || $filterType === 'missing_foreign_key')) {
            foreach ($tableDiff[$missingFkKey] as $fk) {
                if ($filterColumn && !in_array($filterColumn, $fk['columns'])) {
                    continue;
                }
                $sql[] = [
                    'type' => 'add_foreign_key',
                    'table' => $tableName,
                    'constraint' => $fk['name'],
                    'sql' => $this->generateAddForeignKeySql($tableName, $fk)
                ];
            }
        }

        return $sql;
    }
    
    /**
     * Generate ADD COLUMN statement
     *
     * @param string $position Position clause, e.g. " FIRST" or " AFTER `id`" (empty = append at end)
     */
    private function generateAddColumnSql(string $table, array $col, string $position = ''): string
    {
        return $this->warningLines([$col])
            . "ALTER TABLE `{$table}` ADD COLUMN " . $this->columnDefinition($col) . "{$position};";
    }

    /**
     * Full column definition, built ONLY from what the source reports.
     * Used by CREATE TABLE, ADD COLUMN and MODIFY COLUMN alike, so all three
     * produce the same column. MODIFY replaces the whole definition, which is
     * why nothing may be invented here: an added DEFAULT could never be removed.
     */
    public function columnDefinition(array $col): string
    {
        $type = $col['type'];
        $def = "`{$col['name']}` {$type}";

        if (self::isStringType($type)) {
            if (!empty($col['charset'])) {
                $def .= " CHARACTER SET {$col['charset']}";
            }
            if (!empty($col['collation'])) {
                $def .= " COLLATE {$col['collation']}";
            }
        }

        $nullable = !empty($col['nullable']);
        $def .= $nullable ? ' NULL' : ' NOT NULL';
        $def .= $this->defaultClause($col, $nullable);
        $def .= self::extraClause($col['extra'] ?? '');

        if (isset($col['comment']) && $col['comment'] !== '') {
            $def .= " COMMENT '" . str_replace(["\\", "'"], ["\\\\", "''"], $col['comment']) . "'";
        }

        return $def;
    }

    /**
     * DEFAULT clause for a column, or '' when the column has no default.
     */
    private function defaultClause(array $col, bool $nullable): string
    {
        $raw = $col['default'] ?? null;

        // MariaDB reports "no default" on a nullable column as the string NULL
        if ($raw === null || strtoupper(trim((string) $raw)) === 'NULL') {
            return '';
        }

        $raw = (string) $raw;
        $extra = strtolower($col['extra'] ?? '');

        if (self::isExpressionDefault($raw) || strpos($extra, 'default_generated') !== false) {
            return ' DEFAULT ' . self::expressionDefault($raw);
        }

        return ' DEFAULT ' . $this->quoteDefault($raw);
    }

    /**
     * Timestamp functions stay bare (DATETIME/TIMESTAMP require that form),
     * every other expression must be parenthesized for MySQL 8 and MariaDB.
     */
    private static function expressionDefault(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('/^(current_timestamp|now|localtime|localtimestamp)(\s*\(\s*\d*\s*\))?$/i', $raw)) {
            return $raw;
        }

        if ($raw[0] === '(' && substr($raw, -1) === ')') {
            return $raw;
        }

        return "({$raw})";
    }

    /**
     * AUTO_INCREMENT and ON UPDATE from INFORMATION_SCHEMA.EXTRA.
     * MariaDB: "on update current_timestamp()", MySQL 8: "DEFAULT_GENERATED on update CURRENT_TIMESTAMP".
     */
    public static function extraClause(?string $extra): string
    {
        $extra = (string) $extra;
        $clause = '';

        if (stripos($extra, 'auto_increment') !== false) {
            $clause .= ' AUTO_INCREMENT';
        }

        if (preg_match('/on update\s+((?:current_timestamp|now|localtime|localtimestamp)(?:\s*\(\s*\d*\s*\))?)/i', $extra, $m)) {
            $clause .= ' ON UPDATE ' . $m[1];
        }

        return $clause;
    }

    /**
     * Things the generator cannot reproduce, as SQL comments in front of the statement.
     */
    private function warningLines(array $columns): string
    {
        $lines = [];
        $commentsUnknown = false;

        foreach ($columns as $col) {
            $extra = strtolower($col['extra'] ?? '');
            if (preg_match('/\b(virtual|stored|persistent) generated\b/', $extra)) {
                $lines[] = "-- WARNING: `{$col['name']}` is a generated column; its expression is not "
                    . "reported by the endpoint - add 'AS (...)' by hand";
            }
            if (!array_key_exists('comment', $col)) {
                $commentsUnknown = true;
            }
        }

        if ($commentsUnknown) {
            $lines[] = "-- NOTE: this endpoint does not report column comments - existing comments "
                . "are not carried over (update dbmysync-addin)";
        }

        return empty($lines) ? '' : implode("\n", $lines) . "\n";
    }

    public static function isStringType(string $type): bool
    {
        return (bool) preg_match('/^(var)?char\b|^(tiny|medium|long)?text\b|^enum\b|^set\b/i', trim($type));
    }

    /**
     * Determine the position clause (FIRST / AFTER `x`) for every missing column,
     * so that new columns land at the same spot as in the source environment.
     *
     * @param array $missingCols Columns missing in the target, in source order
     * @param bool  $chained     Whether previously added columns count as anchors
     * @return array<string, string> Column name => position clause
     */
    private function computeColumnAnchors(string $table, array $missingCols, string $targetEnv, bool $chained): array
    {
        $sourceSchema = $targetEnv === 'env2' ? $this->env1Schema : $this->env2Schema;
        $targetSchema = $targetEnv === 'env2' ? $this->env2Schema : $this->env1Schema;

        $sourceTable = $this->findTable($sourceSchema, $table);
        $targetTable = $this->findTable($targetSchema, $table);

        if (!$sourceTable || !$targetTable) {
            // Without both sides we cannot place the column reliably - append at the end
            return [];
        }

        // Source column names in their real order
        $sourceOrder = array_column($this->sortByPosition($sourceTable['columns'] ?? []), 'name');

        // Columns that exist in the target right now
        $present = array_flip(array_column($targetTable['columns'] ?? [], 'name'));

        $missing = array_flip(array_column($missingCols, 'name'));

        $anchors = [];
        foreach ($sourceOrder as $index => $name) {
            if (!isset($missing[$name])) {
                continue;
            }

            // Walk backwards to the nearest preceding column that exists in the target
            $anchor = null;
            for ($i = $index - 1; $i >= 0; $i--) {
                if (isset($present[$sourceOrder[$i]])) {
                    $anchor = $sourceOrder[$i];
                    break;
                }
            }

            $anchors[$name] = $anchor === null ? ' FIRST' : " AFTER `{$anchor}`";

            if ($chained) {
                // This column exists once the previous statement has run
                $present[$name] = true;
            }
        }

        return $anchors;
    }

    /**
     * Sort columns by their ordinal position.
     * Falls back to the given array order for endpoints that do not report a position.
     */
    private function sortByPosition(array $columns): array
    {
        $indexed = [];
        foreach ($columns as $index => $col) {
            $indexed[] = [$col['position'] ?? ($index + 1), $index, $col];
        }

        usort($indexed, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_column($indexed, 2);
    }

    /**
     * Find a table definition in a schema
     */
    private function findTable(array $schema, string $tableName): ?array
    {
        foreach ($schema as $table) {
            if ($table['name'] === $tableName) {
                return $table;
            }
        }
        return null;
    }

    /**
     * Generate MODIFY COLUMN statement
     * ALWAYS includes ALL column properties: type, nullable, default, charset, collation
     */
    private function generateModifyColumnSql(string $table, string $colName, array $diff, string $targetEnv): string
    {
        // Get the complete column definition from source environment
        $sourceSchema = $targetEnv === 'env2' ? $this->env1Schema : $this->env2Schema;

        $sourceTable = $this->findTable($sourceSchema, $table);

        // Find the column in the source table
        $sourceColumn = null;
        if ($sourceTable && isset($sourceTable['columns'])) {
            foreach ($sourceTable['columns'] as $col) {
                if ($col['name'] === $colName) {
                    $sourceColumn = $col;
                    break;
                }
            }
        }

        if (!$sourceColumn) {
            return "-- ERROR: column `{$colName}` not found in source table `{$table}` - nothing generated";
        }

        $changes = [];
        foreach ($diff as $prop => $values) {
            $changes[] = "{$prop}: " . json_encode($values['offline']) . ' -> ' . json_encode($values['online']);
        }

        return "-- Changes: " . implode(', ', $changes) . "\n"
            . $this->warningLines([$sourceColumn])
            . "ALTER TABLE `{$table}` MODIFY COLUMN " . $this->columnDefinition($sourceColumn) . ";";
    }

    /**
     * Generate ADD INDEX statement
     */
    private function generateAddIndexSql(string $table, array $idx): string
    {
        $unique = $idx['unique'] ? 'UNIQUE ' : '';
        $columns = implode('`, `', $idx['columns']);

        return "ALTER TABLE `{$table}` ADD {$unique}INDEX `{$idx['name']}` (`{$columns}`);";
    }

    /**
     * Generate ADD FOREIGN KEY statement
     */
    private function generateAddForeignKeySql(string $table, array $fk): string
    {
        $columns = implode('`, `', $fk['columns']);
        $refColumns = implode('`, `', $fk['referenced_columns']);

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fk['name']}` ";
        $sql .= "FOREIGN KEY (`{$columns}`) ";
        $sql .= "REFERENCES `{$fk['referenced_table']}` (`{$refColumns}`)";

        if (!empty($fk['on_delete'])) {
            $sql .= " ON DELETE {$fk['on_delete']}";
        }
        if (!empty($fk['on_update'])) {
            $sql .= " ON UPDATE {$fk['on_update']}";
        }

        return $sql . ";";
    }

    /**
     * Quote default value for SQL
     */
    private function quoteDefault($value): string
    {
        // Handle NULL
        if ($value === null || $value === 'NULL') {
            return 'NULL';
        }

        // Handle MySQL expression defaults (CURRENT_TIMESTAMP, current_timestamp(3), now(), ...)
        // These must never be wrapped in single quotes
        if (self::isExpressionDefault($value)) {
            return $value;
        }

        // Handle numeric values
        if (is_numeric($value)) {
            return $value;
        }

        if (preg_match("/^[bx]'[0-9a-f]*'$/i", $value)) {
            return $value;
        }

        // Check if value is already quoted (starts and ends with single quote)
        if (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            // Already quoted - return as is
            return $value;
        }

        // Not quoted - add quotes and escape internal quotes
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Check whether a default is a MySQL expression instead of a literal value.
     * MySQL 5.x reports "CURRENT_TIMESTAMP", MySQL 8 reports "current_timestamp()"
     * or "current_timestamp(3)" - none of these may be quoted.
     */
    public static function isExpressionDefault($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $normalized = strtolower(trim($value));

        if (preg_match('/^(current_timestamp|now|localtime|localtimestamp|current_date|curdate|current_time|curtime|uuid|utc_timestamp|utc_date|utc_time)(\s*\(\s*\d*\s*\))?$/', $normalized)) {
            return true;
        }

        // Generated / expression defaults in MySQL 8 are wrapped in parentheses
        return strlen($normalized) >= 2 && $normalized[0] === '(' && substr($normalized, -1) === ')';
    }

    /**
     * Generate CREATE TABLE statement
     */
    private function generateCreateTableSql(string $tableName, string $sourceEnv): array
    {
        // Get table from source schema
        $sourceSchema = $sourceEnv === 'env1' ? $this->env1Schema : $this->env2Schema;

        $sourceTable = $this->findTable($sourceSchema, $tableName);

        if (!$sourceTable) {
            return [
                'type' => 'create_table',
                'table' => $tableName,
                'sql' => "-- ERROR: Table '{$tableName}' not found in source schema!",
                'warning' => 'Table not found in source schema'
            ];
        }

        // Build CREATE TABLE statement
        $sql = "CREATE TABLE `{$tableName}` (\n";
        $columnDefs = [];

        foreach ($this->sortByPosition($sourceTable['columns']) as $col) {
            $columnDefs[] = '  ' . $this->columnDefinition($col);
        }

        $sql .= implode(",\n", $columnDefs);

        // Add primary key
        if (!empty($sourceTable['primary_key']) && !empty($sourceTable['primary_key']['columns'])) {
            $sql .= ",\n  PRIMARY KEY (`" . implode('`, `', $sourceTable['primary_key']['columns']) . "`)";
        }

        // Add indexes
        if (!empty($sourceTable['indexes'])) {
            foreach ($sourceTable['indexes'] as $idx) {
                $unique = $idx['unique'] ? 'UNIQUE ' : '';
                $sql .= ",\n  {$unique}KEY `{$idx['name']}` (`" . implode('`, `', $idx['columns']) . "`)";
            }
        }

        $targetEngine = $this->engines[$sourceEnv === 'env1' ? 'env2' : 'env1'];
        $engineClause = $targetEngine !== '' ? " ENGINE={$targetEngine}" : '';
        $sql .= "\n){$engineClause} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $sql = $this->warningLines($sourceTable['columns']) . $sql;

        // Add foreign keys as separate ALTER TABLE statements (must be added after table creation)
        $fkStatements = [];
        if (!empty($sourceTable['foreign_keys'])) {
            foreach ($sourceTable['foreign_keys'] as $fk) {
                $fkSql = "ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$fk['name']}` ";
                $fkSql .= "FOREIGN KEY (`" . implode('`, `', $fk['columns']) . "`) ";
                $fkSql .= "REFERENCES `{$fk['referenced_table']}` (`" . implode('`, `', $fk['referenced_columns']) . "`)";

                if (!empty($fk['on_delete'])) {
                    $fkSql .= " ON DELETE {$fk['on_delete']}";
                }
                if (!empty($fk['on_update'])) {
                    $fkSql .= " ON UPDATE {$fk['on_update']}";
                }

                $fkSql .= ";";
                $fkStatements[] = $fkSql;
            }
        }

        $fullSql = $sql;
        if (!empty($fkStatements)) {
            $fullSql .= "\n\n-- Foreign Keys:\n" . implode("\n", $fkStatements);
        }

        return [
            'type' => 'create_table',
            'table' => $tableName,
            'sql' => $fullSql
        ];
    }
}

