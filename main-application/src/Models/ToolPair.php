<?php

namespace Jensovic\DbMySync\Models;

use PDO;
use Jensovic\DbMySync\Database;

class ToolPair
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get all tool pairs
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM tool_pairs 
            WHERE active = 1 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get tool pair by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tool_pairs 
            WHERE id = :id AND active = 1
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Create new tool pair
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tool_pairs (
                name, env1_name, env1_url, env1_secret,
                env2_name, env2_url, env2_secret
            ) VALUES (
                :name, :env1_name, :env1_url, :env1_secret,
                :env2_name, :env2_url, :env2_secret
            )
        ");

        $stmt->execute([
            'name' => $data['name'],
            'env1_name' => $data['env1_name'] ?? 'Dev',
            'env1_url' => $data['env1_url'],
            'env1_secret' => $data['env1_secret'],
            'env2_name' => $data['env2_name'] ?? 'Prod',
            'env2_url' => $data['env2_url'],
            'env2_secret' => $data['env2_secret']
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update tool pair
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tool_pairs SET
                name = :name,
                env1_name = :env1_name,
                env1_url = :env1_url,
                env1_secret = :env1_secret,
                env2_name = :env2_name,
                env2_url = :env2_url,
                env2_secret = :env2_secret,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'env1_name' => $data['env1_name'] ?? 'Dev',
            'env1_url' => $data['env1_url'],
            'env1_secret' => $data['env1_secret'],
            'env2_name' => $data['env2_name'] ?? 'Prod',
            'env2_url' => $data['env2_url'],
            'env2_secret' => $data['env2_secret']
        ]);
    }

    /**
     * Delete tool pair (soft delete)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tool_pairs SET active = 0 
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Get sync history for a tool pair
     */
    public function getSyncHistory(int $toolPairId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM sync_history 
            WHERE tool_pair_id = :tool_pair_id 
            ORDER BY synced_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue('tool_pair_id', $toolPairId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Add sync history entry
     */
    public function addSyncHistory(int $toolPairId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO sync_history (
                tool_pair_id, env1_status, env2_status,
                differences_found, error_message
            ) VALUES (
                :tool_pair_id, :env1_status, :env2_status,
                :differences_found, :error_message
            )
        ");

        $stmt->execute([
            'tool_pair_id' => $toolPairId,
            'env1_status' => $data['env1_status'] ?? 'success',
            'env2_status' => $data['env2_status'] ?? 'success',
            'differences_found' => $data['differences_found'] ?? 0,
            'error_message' => $data['error_message'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }
}

