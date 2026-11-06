<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/config.php';
$pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$admins = $pdo->query('SELECT id, password_hash FROM admins')->fetchAll();
$update = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
$migrated = 0;

foreach ($admins as $admin) {
    if (password_get_info($admin['password_hash'])['algoName'] === 'unknown') {
        $update->execute([password_hash($admin['password_hash'], PASSWORD_DEFAULT), $admin['id']]);
        $migrated++;
    }
}

fwrite(STDOUT, "Migrated {$migrated} legacy administrator password(s).\n");
