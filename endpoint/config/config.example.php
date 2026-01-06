<?php
/**
 * DbMySync Endpoint Configuration
 * 
 * Copy this file to config.php and adjust the values
 */

return [
    // Database connection
    'db_host' => 'localhost',
    'db_name' => 'your_database_name',
    'db_user' => 'your_database_user',
    'db_pass' => 'your_database_password',
    
    // Security: Generate a strong random secret
    // Example: bin2hex(random_bytes(32))
    'secret' => 'your-secret-key-here-change-this',
];

