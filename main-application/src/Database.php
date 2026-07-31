<?php

namespace Jensovic\DbMySync;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private string $dbPath;

    public function __construct(string $dbPath = null)
    {
        $this->dbPath = $dbPath ?? __DIR__ . '/../data/dbmysync.db';
        $this->initialize();
    }

    /**
     * Get PDO instance (singleton)
     */
    public static function getInstance(string $dbPath = null): PDO
    {
        if (self::$instance === null) {
            $db = new self($dbPath);
            self::$instance = $db->connect();
        }
        return self::$instance;
    }

    /**
     * Initialize database
     */
    private function initialize(): void
    {
        // Create data directory if not exists
        $dataDir = dirname($this->dbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        // Create database if not exists
        $isNew = !file_exists($this->dbPath);

        $pdo = $this->connect();

        if ($isNew) {
            $this->createSchema($pdo);
        }

        $this->migrate($pdo);
    }

    /**
     * Apply idempotent schema upgrades to existing databases
     */
    private function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                sort_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        if (!$this->hasColumn($pdo, 'tool_pairs', 'group_id')) {
            // SQLite cannot add a column with a foreign key to an existing table,
            // so the reference is enforced in the application layer instead.
            $pdo->exec("ALTER TABLE tool_pairs ADD COLUMN group_id INTEGER");
        }

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tool_pairs_group ON tool_pairs(group_id)");
    }

    /**
     * Check whether a table has a given column
     */
    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        foreach ($stmt->fetchAll() as $row) {
            if ($row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create database connection
     */
    private function connect(): PDO
    {
        try {
            $pdo = new PDO(
                "sqlite:{$this->dbPath}",
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // Enable foreign keys
            $pdo->exec('PRAGMA foreign_keys = ON');
            
            return $pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Create database schema
     */
    private function createSchema(PDO $pdo): void
    {
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        $pdo->exec($schema);
    }

    /**
     * Reset instance (for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}

