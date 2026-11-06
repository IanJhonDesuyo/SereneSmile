<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = require dirname(__DIR__) . '/config.php';
try { $pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
catch (PDOException $e) { fwrite(STDERR, "Database connection failed. Import database/schema.sql first.\n"); exit(1); }

function prompt(string $label): string { fwrite(STDOUT, $label); return trim((string)fgets(STDIN)); }
$name = prompt('Full name: ');
$username = prompt('Username: ');
$email = prompt('Email: ');
$password = prompt('Password (12+ characters): ');
if ($name === '' || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Invalid input. Username: 3-50 letters/numbers/._-, valid email, password: 12+ characters.\n"); exit(1);
}
try {
    $stmt = $pdo->prepare('INSERT INTO admins (username,email,password_hash,full_name) VALUES (?,?,?,?)');
    $stmt->execute([$username, strtolower($email), password_hash($password, PASSWORD_DEFAULT), $name]);
    fwrite(STDOUT, "Administrator created.\n");
} catch (PDOException $e) { fwrite(STDERR, "Could not create administrator; username or email may already exist.\n"); exit(1); }
