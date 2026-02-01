<?php
/**
 * DbMySync - Main Application Entry Point
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Jensovic\DbMySync\Database;
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

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}

function handlePost()
{
    $toolPair = new ToolPair();
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $toolPair->create($_POST);
        header('Location: index.php');
    } elseif ($postAction === 'update') {
        $toolPair->update($_POST['id'], $_POST);
        header('Location: index.php');
    }
}

function handleDelete($id)
{
    if ($id) {
        $toolPair = new ToolPair();
        $toolPair->delete($id);
    }
    header('Location: index.php');
}

function showDashboard()
{
    $toolPair = new ToolPair();
    $pairs = $toolPair->getAll();
    
    include __DIR__ . '/../views/dashboard.php';
}

function showAddForm()
{
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

