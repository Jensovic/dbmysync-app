<?php

namespace Jensovic\DbMySync;

/**
 * SQL Generator - Generates ALTER/CREATE statements from schema differences
 */
class SqlGenerator
{
    private array $env1Schema;
    private array $env2Schema;

    public function __construct(array $env1Schema, array $env2Schema)
    {
        $this->env1Schema = $env1Schema;
        $this->env2Schema = $env2Schema;
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
            foreach ($tableDiff[$missingColsKey] as $col) {
                if ($filterColumn && $col['name'] !== $filterColumn) {
                    continue;
                }
                $sql[] = [
                    'type' => 'add_column',
                    'table' => $tableName,
                    'column' => $col['name'],
                    'sql' => $this->generateAddColumnSql($tableName, $col)
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
     */
    private function generateAddColumnSql(string $table, array $col): string
    {
        $nullable = $col['nullable'] ? 'NULL' : 'NOT NULL';
        $default = '';

        if ($col['default'] !== null) {
            $default = " DEFAULT " . $this->quoteDefault($col['default']);
        }

        return "ALTER TABLE `{$table}` ADD COLUMN `{$col['name']}` {$col['type']} {$nullable}{$default};";
    }

    /**
     * Generate MODIFY COLUMN statement
     * ALWAYS includes ALL column properties: type, nullable, default, charset, collation
     */
    private function generateModifyColumnSql(string $table, string $colName, array $diff, string $targetEnv): string
    {
        // Get the complete column definition from source environment
        $sourceSchema = $targetEnv === 'env2' ? $this->env1Schema : $this->env2Schema;

        // Find the table in the schema (schema is a numeric array of tables)
        $sourceTable = null;
        foreach ($sourceSchema as $tbl) {
            if ($tbl['name'] === $table) {
                $sourceTable = $tbl;
                break;
            }
        }

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
            // Fallback: Build from diff data
            $sourceKey = $targetEnv === 'env2' ? 'offline' : 'online';
            $type = $diff['type'][$sourceKey] ?? 'VARCHAR(255)';
            $nullable = (isset($diff['nullable']) && $diff['nullable'][$sourceKey]) ? 'NULL' : 'NOT NULL';
            $default = '';

            // For string types, always add DEFAULT
            $isStringType = (stripos($type, 'varchar') !== false ||
                            stripos($type, 'text') !== false ||
                            stripos($type, 'char') !== false);

            if (isset($diff['default'])) {
                $defaultValue = $diff['default'][$sourceKey];
                if ($defaultValue !== null) {
                    $default = " DEFAULT " . $this->quoteDefault($defaultValue);
                } elseif ($isStringType) {
                    $default = " DEFAULT ''";
                }
            } elseif ($isStringType) {
                $default = " DEFAULT ''";
            }

            $sql = "ALTER TABLE `{$table}` MODIFY COLUMN `{$colName}` {$type} {$nullable}{$default};";

            $changes = [];
            foreach ($diff as $prop => $values) {
                $changes[] = "$prop: {$values['offline']} ? {$values['online']}";
            }

            return "-- Changes: " . implode(', ', $changes) . "\n-- WARNING: Built from diff (source column not in schema)\n" . $sql;
        }

        // Build complete column definition from source
        $type = $sourceColumn['type'];
        $nullable = $sourceColumn['nullable'] ? 'NULL' : 'NOT NULL';

        // ALWAYS add DEFAULT for string columns
        $default = '';
        $isStringType = (stripos($type, 'varchar') !== false ||
                        stripos($type, 'text') !== false ||
                        stripos($type, 'char') !== false);

        if (array_key_exists('default', $sourceColumn) && $sourceColumn['default'] !== null) {
            // Has a specific default value
            $default = " DEFAULT " . $this->quoteDefault($sourceColumn['default']);
        } elseif ($isStringType) {
            // String type without default -> use empty string
            $default = " DEFAULT ''";
        }

        // Add charset/collation if present
        $charset = '';
        if (!empty($sourceColumn['charset']) && $isStringType) {
            $charset = " CHARACTER SET {$sourceColumn['charset']}";
        }

        $collation = '';
        if (!empty($sourceColumn['collation']) && $isStringType) {
            $collation = " COLLATE {$sourceColumn['collation']}";
        }

        $sql = "ALTER TABLE `{$table}` MODIFY COLUMN `{$colName}` {$type}{$charset}{$collation} {$nullable}{$default};";

        // Add comment about what changed
        $changes = [];
        foreach ($diff as $prop => $values) {
            $changes[] = "$prop: {$values['offline']} ? {$values['online']}";
        }

        return "-- Changes: " . implode(', ', $changes) . "\n" . $sql;
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
        // Handle NULL and special MySQL values
        if ($value === null || $value === 'NULL' || $value === 'CURRENT_TIMESTAMP') {
            return $value === null ? 'NULL' : $value;
        }

        // Handle numeric values
        if (is_numeric($value)) {
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
     * Generate CREATE TABLE statement
     */
    private function generateCreateTableSql(string $tableName, string $sourceEnv): array
    {
        // Get table from source schema
        $sourceSchema = $sourceEnv === 'env1' ? $this->env1Schema : $this->env2Schema;

        $sourceTable = null;
        foreach ($sourceSchema as $tbl) {
            if ($tbl['name'] === $tableName) {
                $sourceTable = $tbl;
                break;
            }
        }

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

        // Add columns
        foreach ($sourceTable['columns'] as $col) {
            $def = "  `{$col['name']}` {$col['type']}";

            // Add charset/collation for string types
            if (!empty($col['charset']) && (stripos($col['type'], 'varchar') !== false ||
                stripos($col['type'], 'text') !== false || stripos($col['type'], 'char') !== false)) {
                $def .= " CHARACTER SET {$col['charset']}";
            }
            if (!empty($col['collation']) && (stripos($col['type'], 'varchar') !== false ||
                stripos($col['type'], 'text') !== false || stripos($col['type'], 'char') !== false)) {
                $def .= " COLLATE {$col['collation']}";
            }

            // Add nullable
            $def .= $col['nullable'] ? ' NULL' : ' NOT NULL';

            // Add default
            if (array_key_exists('default', $col) && $col['default'] !== null) {
                $def .= " DEFAULT " . $this->quoteDefault($col['default']);
            } elseif ((stripos($col['type'], 'varchar') !== false ||
                      stripos($col['type'], 'text') !== false ||
                      stripos($col['type'], 'char') !== false)) {
                $def .= " DEFAULT ''";
            }

            // Add auto_increment
            if (!empty($col['extra']) && stripos($col['extra'], 'auto_increment') !== false) {
                $def .= ' AUTO_INCREMENT';
            }

            $columnDefs[] = $def;
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

        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

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

