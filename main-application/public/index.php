<?php
/**
 * DbMySync - Main Application Entry Point
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Jensovic\DbMySync\Database;
use Jensovic\DbMySync\Models\Group;
use Jensovic\DbMySync\Models\ToolPair;
use Jensovic\DbMySync\EndpointClient;
use Jensovic\DbMySync\SchemaComparator;

// Initialize database
Database::getInstance();

// Simple router
$action = $_GET['action'] ?? 'dashboard';
$id = $_GET['id'] ?? null;

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    handleAjax();
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePost();
    exit;
}

// Handle GET requests
switch ($action) {
    case 'dashboard':
        showDashboard();
        break;

    case 'add':
        showAddForm();
        break;

    case 'edit':
        showEditForm($id);
        break;

    case 'compare':
        showComparison($id);
        break;

    case 'duplicate':
        handleDuplicate($id);
        break;

    case 'delete':
        handleDelete($id);
        break;

    default:
        showDashboard();
}

function handleAjax()
{
    header('Content-Type: application/json');

    $ajaxAction = $_GET['ajax'] ?? '';

    if ($ajaxAction === 'validate_endpoint') {
        $url = $_GET['url'] ?? '';
        $secret = $_GET['secret'] ?? '';

        if (empty($url)) {
            echo json_encode(['success' => false, 'message' => 'URL is required']);
            return;
        }

        $client = new EndpointClient();
        $result = $client->testConnectionDetailed($url, $secret);

        // Map status to success flag
        $success = ($result['status'] === 'success');

        echo json_encode([
            'success' => $success,
            'message' => $result['message'],
            'status' => $result['status'],
            'error' => $result['error']
        ]);
        return;
    }

    if ($ajaxAction === 'smoke_test') {
        $pairId = $_GET['id'] ?? null;

        if (!$pairId) {
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            return;
        }

        $pair = (new ToolPair())->getById((int) $pairId);

        if (!$pair) {
            echo json_encode(['success' => false, 'message' => 'Tool pair not found']);
            return;
        }

        // Secrets stay on the server - the dashboard only receives the resulting status
        $client = new EndpointClient();

        echo json_encode([
            'success' => true,
            'env1' => smokeTestEnvironment($client, $pair['env1_url'], $pair['env1_secret']),
            'env2' => smokeTestEnvironment($client, $pair['env2_url'], $pair['env2_secret'])
        ]);
        return;
    }

    if ($ajaxAction === 'diff_count') {
        $pairId = $_GET['id'] ?? null;

        if (!$pairId) {
            echo json_encode(['success' => false, 'message' => 'ID is required']);
            return;
        }

        $pair = (new ToolPair())->getById((int) $pairId);

        if (!$pair) {
            echo json_encode(['success' => false, 'message' => 'Tool pair not found']);
            return;
        }

        try {
            $client = new EndpointClient();
            $comparator = new SchemaComparator();

            $env1Data = $client->fetchSchema($pair['env1_url'], $pair['env1_secret']);
            $env2Data = $client->fetchSchema($pair['env2_url'], $pair['env2_secret']);

            $differences = $comparator->compare($env1Data['tables'] ?? [], $env2Data['tables'] ?? []);

            // Deliberately no sync_history entry - the dashboard runs this on every
            // page load and would otherwise drown the real comparison runs.
            echo json_encode([
                'success' => true,
                'count' => $comparator->countDifferences($differences)
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

/**
 * Health check for a single environment, reduced to a traffic light level.
 *
 * ok = endpoint answered, warn = endpoint found but secret rejected, error = everything else.
 */
function smokeTestEnvironment(EndpointClient $client, ?string $url, ?string $secret): array
{
    if (empty($url)) {
        return [
            'level' => 'error',
            'status' => 'no_url',
            'message' => 'Keine Endpoint-URL hinterlegt'
        ];
    }

    $result = $client->testConnectionDetailed($url, (string) $secret);

    $levels = [
        'success' => 'ok',
        'auth_failed' => 'warn'
    ];

    return [
        'level' => $levels[$result['status']] ?? 'error',
        'status' => $result['status'],
        'message' => $result['message']
    ];
}

function handlePost()
{
    $toolPair = new ToolPair();
    $group = new Group();
    $postAction = $_POST['action'] ?? '';

    $data = $_POST;
    $data['group_id'] = $group->resolveOrCreate($_POST['group_name'] ?? null);

    if ($postAction === 'create') {
        $toolPair->create($data);
        $group->deleteUnused();
        header('Location: index.php');
    } elseif ($postAction === 'update') {
        $toolPair->update($_POST['id'], $data);
        $group->deleteUnused();
        header('Location: index.php');
    }
}

function handleDuplicate($id)
{
    if (!$id) {
        header('Location: index.php');
        return;
    }

    $toolPair = new ToolPair();
    $newId = $toolPair->duplicate((int) $id);

    if (!$newId) {
        header('Location: index.php');
        return;
    }

    // Open the copy in the edit form so the name can be adjusted right away
    header('Location: index.php?action=edit&id=' . $newId);
}

function handleDelete($id)
{
    if ($id) {
        $toolPair = new ToolPair();
        $toolPair->delete($id);
        (new Group())->deleteUnused();
    }
    header('Location: index.php');
}

function showDashboard()
{
    $toolPair = new ToolPair();
    $pairs = $toolPair->getAll();
    $groupedPairs = $toolPair->getAllGrouped();

    include __DIR__ . '/../views/dashboard.php';
}

function showAddForm()
{
    $groups = (new Group())->getAll();

    include __DIR__ . '/../views/form.php';
}

function showEditForm($id)
{
    if (!$id) {
        header('Location: index.php');
        return;
    }

    $toolPair = new ToolPair();
    $pair = $toolPair->getById($id);

    if (!$pair) {
        header('Location: index.php');
        return;
    }

    $groups = (new Group())->getAll();

    include __DIR__ . '/../views/form.php';
}

function showComparison($id)
{
    if (!$id) {
        header('Location: index.php');
        return;
    }
    
    $toolPairModel = new ToolPair();
    $pair = $toolPairModel->getById($id);
    
    if (!$pair) {
        header('Location: index.php');
        return;
    }
    
    $client = new EndpointClient();
    $comparator = new SchemaComparator();
    
    $error = null;
    $offlineSchema = null;
    $onlineSchema = null;
    $differences = null;
    
    try {
        // Fetch schemas
        $env1Data = $client->fetchSchema($pair['env1_url'], $pair['env1_secret']);
        $env2Data = $client->fetchSchema($pair['env2_url'], $pair['env2_secret']);

        $offlineSchema = $env1Data['tables'] ?? [];
        $onlineSchema = $env2Data['tables'] ?? [];

        // Compare
        $differences = $comparator->compare($offlineSchema, $onlineSchema);
        $diffCount = $comparator->countDifferences($differences);

        // Save to history
        $toolPairModel->addSyncHistory($id, [
            'env1_status' => 'success',
            'env2_status' => 'success',
            'differences_found' => $diffCount
        ]);

    } catch (\Exception $e) {
        $error = $e->getMessage();
        $toolPairModel->addSyncHistory($id, [
            'env1_status' => 'error',
            'env2_status' => 'error',
            'error_message' => $error
        ]);
    }
    
    include __DIR__ . '/../views/comparison.php';
}

