<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
ini_set('session.use_strict_mode', '1');
session_name($config['session']['name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']),
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

try {
    $pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(503);
    exit('Database connection failed. Import database/schema.sql and review config.php.');
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
        http_response_code(419); exit('Your session expired. Please go back and try again.');
    }
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function flashes(): array { $v = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $v; }
function require_admin(): void {
    global $config;
    if (empty($_SESSION['admin_id'])) redirect('login.php');
    if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > $config['session']['idle_timeout']) {
        session_unset(); session_destroy(); redirect('login.php?expired=1');
    }
    $_SESSION['last_seen'] = time();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
function post_string(string $key, int $max = 255): string { return mb_substr(trim((string)($_POST[$key] ?? '')), 0, $max); }
function valid_date(string $value): bool { $d = DateTime::createFromFormat('Y-m-d', $value); return $d && $d->format('Y-m-d') === $value; }
function valid_time(string $value): bool { return (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value); }

function audit(PDO $pdo, string $action, ?string $entity = null, ?int $entityId = null): void {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$_SESSION['admin_id'] ?? null, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null]);
}
