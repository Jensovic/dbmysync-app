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
            SELECT tp.*, g.name AS group_name, g.sort_order AS group_sort_order
            FROM tool_pairs tp
            LEFT JOIN groups g ON g.id = tp.group_id
            WHERE tp.active = 1
            ORDER BY tp.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get all tool pairs bucketed by group.
     *
     * Returns a list of ['id' => ?int, 'name' => string, 'pairs' => array],
     * groups first (by sort order, then name), ungrouped pairs last.
     */
    public function getAllGrouped(): array
    {
        $buckets = [];
        $ungrouped = [];

        foreach ($this->getAll() as $pair) {
            if ($pair['group_id'] === null) {
                $ungrouped[] = $pair;
                continue;
            }

            $key = (int) $pair['group_id'];
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'id' => $key,
                    'name' => $pair['group_name'],
                    'sort_order' => (int) ($pair['group_sort_order'] ?? 0),
                    'pairs' => []
                ];
            }
            $buckets[$key]['pairs'][] = $pair;
        }

        usort($buckets, function ($a, $b) {
            return [$a['sort_order'], $a['name']] <=> [$b['sort_order'], $b['name']];
        });

        if (!empty($ungrouped)) {
            $buckets[] = [
                'id' => null,
                'name' => 'Ohne Gruppe',
                'sort_order' => PHP_INT_MAX,
                'pairs' => $ungrouped
            ];
        }

        return $buckets;
    }

    /**
     * Get tool pair by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT tp.*, g.name AS group_name
            FROM tool_pairs tp
            LEFT JOIN groups g ON g.id = tp.group_id
            WHERE tp.id = :id AND tp.active = 1
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
                name, group_id, env1_name, env1_url, env1_secret,
                env2_name, env2_url, env2_secret
            ) VALUES (
                :name, :group_id, :env1_name, :env1_url, :env1_secret,
                :env2_name, :env2_url, :env2_secret
            )
        ");

        $stmt->execute([
            'name' => $data['name'],
            'group_id' => $data['group_id'] ?? null,
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
                group_id = :group_id,
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
            'group_id' => $data['group_id'] ?? null,
            'env1_name' => $data['env1_name'] ?? 'Dev',
            'env1_url' => $data['env1_url'],
            'env1_secret' => $data['env1_secret'],
            'env2_name' => $data['env2_name'] ?? 'Prod',
            'env2_url' => $data['env2_url'],
            'env2_secret' => $data['env2_secret']
        ]);
    }

    /**
     * Store the outcome of a difference check on the tool pair itself.
     *
     * The dashboard renders this cached value instead of comparing both schemas
     * on every page load; it is only refreshed on an explicit user request.
     *
     * @param int      $id    Tool pair
     * @param int|null $count Number of differences, or null when the check failed
     * @param string   $status 'ok' or 'error'
     * @param string|null $error Error message for a failed check
     */
    public function updateDiffResult(int $id, ?int $count, string $status = 'ok', ?string $error = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tool_pairs SET
                last_diff_count = :count,
                last_diff_status = :status,
                last_diff_error = :error,
                last_diff_checked_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'count' => $count,
            'status' => $status,
            'error' => $error
        ]);
    }

    /**
     * Duplicate a tool pair including group, URLs and secrets.
     *
     * @return int|null ID of the new tool pair, or null if the source does not exist
     */
    public function duplicate(int $id): ?int
    {
        $source = $this->getById($id);
        if (!$source) {
            return null;
        }

        $data = $source;
        $data['name'] = $this->makeUniqueName($source['name']);

        return $this->create($data);
    }

    /**
     * Build a name that is not yet taken, e.g. "My CMS (Kopie 2)"
     */
    private function makeUniqueName(string $baseName): string
    {
        // Strip an existing "(Kopie)" / "(Kopie N)" suffix so copies of copies
        // do not accumulate the suffix over and over again.
        $baseName = preg_replace('/\s*\(Kopie(?:\s+\d+)?\)$/u', '', $baseName);

        $candidate = $baseName . ' (Kopie)';
        $counter = 2;

        while ($this->nameExists($candidate)) {
            $candidate = $baseName . ' (Kopie ' . $counter . ')';
            $counter++;
        }

        return $candidate;
    }

    /**
     * Check whether a name is already used (including soft-deleted pairs,
     * because the UNIQUE constraint covers those too)
     */
    private function nameExists(string $name): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM tool_pairs WHERE name = :name");
        $stmt->execute(['name' => $name]);
        return (bool) $stmt->fetchColumn();
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

