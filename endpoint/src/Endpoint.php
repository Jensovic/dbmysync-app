<?php

namespace Jensovic\DbMySyncAddin;

class Endpoint
{
    private Auth $auth;
    private SchemaReader $schemaReader;

    public function __construct(array $config)
    {
        // Validate config
        $required = ['db_host', 'db_name', 'db_user', 'db_pass', 'secret'];
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                Response::error("Missing required config: {$key}", 500);
            }
        }

        // Initialize components
        $this->auth = new Auth($config['secret']);
        $this->schemaReader = new SchemaReader(
            $config['db_host'],
            $config['db_name'],
            $config['db_user'],
            $config['db_pass']
        );
    }

    /**
     * Handle incoming request
     */
    public function handle(): void
    {
        // Enable CORS if needed
        $this->setCorsHeaders();

        // Handle OPTIONS request (CORS preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Verify authentication
        if (!$this->auth->verify()) {
            $this->auth->sendUnauthorized();
        }

        // Get action from query parameter
        $action = $_GET['action'] ?? 'health';

        try {
            switch ($action) {
                case 'health':
                    $this->handleHealth();
                    break;

                case 'schema':
                    $this->handleSchema();
                    break;

                default:
                    Response::error("Unknown action: {$action}", 404);
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * Health check endpoint
     */
    private function handleHealth(): void
    {
        Response::success([
            'status' => 'ok',
            'version' => '1.0.0',
            'timestamp' => date('c')
        ]);
    }

    /**
     * Schema endpoint
     */
    private function handleSchema(): void
    {
        $table = $_GET['table'] ?? null;

        if ($table) {
            // Get specific table schema
            $schema = $this->schemaReader->getTableSchema($table);
            Response::success($schema);
        } else {
            // Get all tables schema
            $schema = $this->schemaReader->getSchema();
            Response::success([
                'tables' => $schema
            ]);
        }
    }

    /**
     * Set CORS headers for cross-origin requests
     */
    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-DbSync-Secret, Authorization');
        header('Access-Control-Max-Age: 86400');
    }
}

