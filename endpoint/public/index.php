<?php
/**
 * DbMySync Endpoint - Example Implementation
 * 
 * This is an example of how to use the DbMySync Addin in your project.
 * Copy this file to your project and adjust the configuration.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Jensovic\DbMySyncAddin\Endpoint;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Create and handle endpoint
$endpoint = new Endpoint($config);
$endpoint->handle();

