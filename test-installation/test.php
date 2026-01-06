<?php
require_once 'vendor/autoload.php';

use Jensovic\DbMySyncAddin\Endpoint;

echo "✅ Autoload funktioniert!\n";
echo "✅ Namespace gefunden!\n";
echo "✅ Endpoint Klasse verfügbar!\n";

// Test ob Endpoint instanziiert werden kann
try {
    $endpoint = new Endpoint([
        'db_host' => 'localhost',
        'db_name' => 'test',
        'db_user' => 'root',
        'db_pass' => '',
        'secret' => 'test-secret'
    ]);
    echo "✅ Endpoint erfolgreich instanziiert!\n";
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
}

echo "\n🎉 Package Installation erfolgreich!\n";

