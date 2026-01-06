<?php

namespace Jensovic\DbMySync;

class SchemaComparator
{
    /**
     * Compare two schemas and return differences
     */
    public function compare(array $offlineSchema, array $onlineSchema): array
    {
        $differences = [
            'missing_tables_online' => [],
            'missing_tables_offline' => [],
            'table_differences' => []
        ];

        $offlineTables = $this->indexByName($offlineSchema);
        $onlineTables = $this->indexByName($onlineSchema);

        // Find tables missing online
        foreach ($offlineTables as $tableName => $offlineTable) {
            if (!isset($onlineTables[$tableName])) {
                $differences['missing_tables_online'][] = $tableName;
            }
        }

        // Find tables missing offline
        foreach ($onlineTables as $tableName => $onlineTable) {
            if (!isset($offlineTables[$tableName])) {
                $differences['missing_tables_offline'][] = $tableName;
            }
        }

        // Compare tables that exist in both
        foreach ($offlineTables as $tableName => $offlineTable) {
            if (isset($onlineTables[$tableName])) {
                $tableDiff = $this->compareTable($offlineTable, $onlineTables[$tableName]);
                if (!empty($tableDiff)) {
                    $differences['table_differences'][$tableName] = $tableDiff;
                }
            }
        }

        return $differences;
    }

    /**
     * Compare a single table
     */
    private function compareTable(array $offlineTable, array $onlineTable): array
    {
        $diff = [
            'missing_columns_online' => [],
            'missing_columns_offline' => [],
            'column_differences' => [],
            'missing_indexes_online' => [],
            'missing_indexes_offline' => [],
            'missing_foreign_keys_online' => [],
            'missing_foreign_keys_offline' => [],
            'primary_key_difference' => null
        ];

        // Compare columns
        $offlineColumns = $this->indexByName($offlineTable['columns']);
        $onlineColumns = $this->indexByName($onlineTable['columns']);

        foreach ($offlineColumns as $colName => $offlineCol) {
            if (!isset($onlineColumns[$colName])) {
                $diff['missing_columns_online'][] = $offlineCol;
            } else {
                $colDiff = $this->compareColumn($offlineCol, $onlineColumns[$colName]);
                if (!empty($colDiff)) {
                    $diff['column_differences'][$colName] = $colDiff;
                }
            }
        }

        foreach ($onlineColumns as $colName => $onlineCol) {
            if (!isset($offlineColumns[$colName])) {
                $diff['missing_columns_offline'][] = $onlineCol;
            }
        }

        // Compare primary keys
        $pkDiff = $this->comparePrimaryKeys(
            $offlineTable['primary_key'] ?? null,
            $onlineTable['primary_key'] ?? null
        );
        if ($pkDiff) {
            $diff['primary_key_difference'] = $pkDiff;
        }

        // Compare indexes
        $offlineIndexes = $this->indexByName($offlineTable['indexes']);
        $onlineIndexes = $this->indexByName($onlineTable['indexes']);

        foreach ($offlineIndexes as $idxName => $offlineIdx) {
            if (!isset($onlineIndexes[$idxName])) {
                $diff['missing_indexes_online'][] = $offlineIdx;
            }
        }

        foreach ($onlineIndexes as $idxName => $onlineIdx) {
            if (!isset($offlineIndexes[$idxName])) {
                $diff['missing_indexes_offline'][] = $onlineIdx;
            }
        }

        // Compare foreign keys
        $offlineFks = $this->indexByName($offlineTable['foreign_keys']);
        $onlineFks = $this->indexByName($onlineTable['foreign_keys']);

        foreach ($offlineFks as $fkName => $offlineFk) {
            if (!isset($onlineFks[$fkName])) {
                $diff['missing_foreign_keys_online'][] = $offlineFk;
            }
        }

        foreach ($onlineFks as $fkName => $onlineFk) {
            if (!isset($offlineFks[$fkName])) {
                $diff['missing_foreign_keys_offline'][] = $onlineFk;
            }
        }

        // Remove empty arrays
        return array_filter($diff, fn($v) => !empty($v));
    }

    /**
     * Compare two columns
     */
    private function compareColumn(array $offline, array $online): array
    {
        $diff = [];

        if ($offline['type'] !== $online['type']) {
            $diff['type'] = ['offline' => $offline['type'], 'online' => $online['type']];
        }

        if ($offline['nullable'] !== $online['nullable']) {
            $diff['nullable'] = ['offline' => $offline['nullable'], 'online' => $online['nullable']];
        }

        if (($offline['default'] ?? null) !== ($online['default'] ?? null)) {
            $diff['default'] = ['offline' => $offline['default'] ?? null, 'online' => $online['default'] ?? null];
        }

        return $diff;
    }

    /**
     * Compare primary keys
     */
    private function comparePrimaryKeys(?array $offline, ?array $online): ?array
    {
        if ($offline === null && $online === null) {
            return null;
        }

        if ($offline === null) {
            return ['type' => 'missing_offline', 'online' => $online];
        }

        if ($online === null) {
            return ['type' => 'missing_online', 'offline' => $offline];
        }

        // Compare columns
        if ($offline['columns'] !== $online['columns']) {
            return [
                'type' => 'different',
                'offline' => $offline['columns'],
                'online' => $online['columns']
            ];
        }

        return null;
    }

    /**
     * Index array by 'name' field
     */
    private function indexByName(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item['name']] = $item;
        }
        return $indexed;
    }

    /**
     * Count total differences
     */
    public function countDifferences(array $differences): int
    {
        $count = 0;
        $count += count($differences['missing_tables_online'] ?? []);
        $count += count($differences['missing_tables_offline'] ?? []);

        foreach ($differences['table_differences'] ?? [] as $tableDiff) {
            $count += count($tableDiff['missing_columns_online'] ?? []);
            $count += count($tableDiff['missing_columns_offline'] ?? []);
            $count += count($tableDiff['column_differences'] ?? []);
            $count += count($tableDiff['missing_indexes_online'] ?? []);
            $count += count($tableDiff['missing_indexes_offline'] ?? []);
            $count += count($tableDiff['missing_foreign_keys_online'] ?? []);
            $count += count($tableDiff['missing_foreign_keys_offline'] ?? []);
            if (!empty($tableDiff['primary_key_difference'])) {
                $count++;
            }
        }

        return $count;
    }
}
