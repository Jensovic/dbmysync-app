<?php

namespace Jensovic\DbMySyncAddin;

use PDO;
use PDOException;

class SchemaReader
{
    private PDO $pdo;
    private string $database;

    public function __construct(string $host, string $database, string $user, string $password)
    {
        $this->database = $database;
        
        try {
            $this->pdo = new PDO(
                "mysql:host={$host};dbname={$database};charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get complete database schema
     */
    public function getSchema(): array
    {
        $tables = $this->getTables();
        $result = [];

        foreach ($tables as $table) {
            $result[] = $this->getTableSchema($table);
        }

        return $result;
    }

    /**
     * Get schema for a specific table
     */
    public function getTableSchema(string $tableName): array
    {
        return [
            'name' => $tableName,
            'columns' => $this->getColumns($tableName),
            'primary_key' => $this->getPrimaryKey($tableName),
            'foreign_keys' => $this->getForeignKeys($tableName),
            'indexes' => $this->getIndexes($tableName),
        ];
    }

    /**
     * Get list of all tables
     */
    private function getTables(): array
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get columns for a table
     *
     * @param string $table
     * @return array
     */
    private function getColumns($table)
    {
        $sql = "
            SELECT 
                COLUMN_NAME as name,
                COLUMN_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value,
                EXTRA as extra,
                CHARACTER_SET_NAME as charset,
                COLLATION_NAME as collation
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            ORDER BY ORDINAL_POSITION
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['database' => $this->database, 'table' => $table]);
        
        return array_map(function($col) {
            return [
                'name' => $col['name'],
                'type' => $col['type'],
                'nullable' => $col['nullable'] === 'YES',
                'default' => $col['default_value'],
                'extra' => $col['extra'],
                'charset' => $col['charset'],
                'collation' => $col['collation']
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Get primary key for a table
     */
    private function getPrimaryKey(string $table): ?array
    {
        $sql = "
            SELECT COLUMN_NAME as column_name
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            AND CONSTRAINT_NAME = 'PRIMARY'
            ORDER BY ORDINAL_POSITION
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['database' => $this->database, 'table' => $table]);
        
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return empty($columns) ? null : [
            'columns' => $columns
        ];
    }

    /**
     * Get foreign keys for a table
     */
    private function getForeignKeys(string $table): array
    {
        $sql = "
            SELECT 
                CONSTRAINT_NAME as name,
                COLUMN_NAME as column_name,
                REFERENCED_TABLE_NAME as referenced_table,
                REFERENCED_COLUMN_NAME as referenced_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['database' => $this->database, 'table' => $table]);
        
        $fks = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = $row['name'];
            if (!isset($fks[$name])) {
                $fks[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'referenced_table' => $row['referenced_table'],
                    'referenced_columns' => []
                ];
            }
            $fks[$name]['columns'][] = $row['column_name'];
            $fks[$name]['referenced_columns'][] = $row['referenced_column'];
        }
        
        return array_values($fks);
    }

    /**
     * Get indexes for a table (excluding primary key)
     *
     * @param string $table
     * @return array
     */
    private function getIndexes($table)
    {
        $sql = "
            SELECT
                INDEX_NAME as name,
                COLUMN_NAME as column_name,
                NON_UNIQUE as non_unique,
                SEQ_IN_INDEX as sequence
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
            AND INDEX_NAME != 'PRIMARY'
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['database' => $this->database, 'table' => $table]);

        $indexes = [];
        foreach ($stmt->fetchAll() as $row) {
            $name = $row['name'];
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'unique' => $row['non_unique'] == 0
                ];
            }
            $indexes[$name]['columns'][] = $row['column_name'];
        }

        return array_values($indexes);
    }
}
