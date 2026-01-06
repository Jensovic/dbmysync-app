# DbMySync Addin

Database synchronization endpoint for MySQL databases.

## Installation

```bash
composer require jensovic/dbmysync-addin
```

## Usage

### 1. Create endpoint in your project

```php
<?php
require_once 'vendor/autoload.php';

use Jensovic\DbMySyncAddin\Endpoint;

$config = [
    'db_host' => 'localhost',
    'db_name' => 'your_database',
    'db_user' => 'your_user',
    'db_pass' => 'your_password',
    'secret' => 'your-secret-key-here'
];

$endpoint = new Endpoint($config);
$endpoint->handle();
```

### 2. API Endpoints

All requests require `X-DbSync-Secret` header with your secret key.

#### Health Check
```
GET /endpoint.php?action=health
```

#### Get all tables schema
```
GET /endpoint.php?action=schema
```

#### Get specific table schema
```
GET /endpoint.php?action=schema&table=users
```

## Response Format

```json
{
    "success": true,
    "data": {
        "tables": [
            {
                "name": "users",
                "columns": [...],
                "primary_keys": [...],
                "foreign_keys": [...],
                "indexes": [...]
            }
        ]
    }
}
```

## License

MIT

