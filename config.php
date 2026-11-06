<?php
declare(strict_types=1);

return [
    'db' => [
        'dsn' => getenv('SERENE_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=serene_smile;charset=utf8mb4',
        'user' => getenv('SERENE_DB_USER') ?: 'root',
        'password' => getenv('SERENE_DB_PASSWORD') ?: '',
    ],
    'session' => [
        'name' => 'serene_admin',
        'idle_timeout' => 1800,
        'driver' => getenv('SERENE_SESSION_DRIVER') ?: (getenv('VERCEL') ? 'database' : 'files'),
    ],
];
