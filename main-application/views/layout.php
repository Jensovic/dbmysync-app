<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'DbMySync' ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" href="favicon.ico">
    <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/../public/styles.css') ?>">
</head>
<body>
    <header>
        <div class="container">
            <h1>🔄 DbMySync</h1>
            <p>Database Synchronization Tool</p>
        </div>
    </header>
    
    <div class="container">
        <?= $content ?>
    </div>
</body>
</html>

