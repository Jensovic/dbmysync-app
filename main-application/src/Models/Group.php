<?php

namespace Jensovic\DbMySync\Models;

use PDO;
use Jensovic\DbMySync\Database;

class Group
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all groups, ordered for dashboard display
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM groups
            ORDER BY sort_order ASC, name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get group by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM groups WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get group by name (case-insensitive)
     */
    public function getByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM groups
            WHERE name = :name COLLATE NOCASE
        ");
        $stmt->execute(['name' => trim($name)]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Resolve a group name to an ID, creating the group if needed.
     * Returns null for an empty name (= ungrouped).
     */
    public function resolveOrCreate(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $existing = $this->getByName($name);
        if ($existing) {
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare("INSERT INTO groups (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Delete groups that no longer contain any active tool pair
     */
    public function deleteUnused(): int
    {
        $stmt = $this->db->query("
            DELETE FROM groups
            WHERE id NOT IN (
                SELECT group_id FROM tool_pairs
                WHERE group_id IS NOT NULL AND active = 1
            )
        ");
        return $stmt->rowCount();
    }
}
