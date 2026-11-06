<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
if (getenv('VERCEL')) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
if (!empty($config['db']['ssl_ca'])) {
    if (class_exists('Pdo\\Mysql')) {
        $pdoOptions[constant('Pdo\\Mysql::ATTR_SSL_CA')] = $config['db']['ssl_ca'];
        $pdoOptions[constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')] = true;
    } else {
        $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $config['db']['ssl_ca'];
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
}

try {
    $pdo = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password'], $pdoOptions);
} catch (PDOException $e) {
    http_response_code(503);
    exit('Database connection failed. Import database/schema.sql and review config.php.');
}

final class DatabaseSessionHandler implements SessionHandlerInterface {
    public function __construct(private PDO $pdo) {}
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false {
        $stmt = $this->pdo->prepare('SELECT data FROM sessions WHERE id = ? AND last_activity >= ?');
        $stmt->execute([$id, time() - (int)ini_get('session.gc_maxlifetime')]);
        $data = $stmt->fetchColumn();
        return $data === false ? '' : (string)$data;
    }
    public function write(string $id, string $data): bool {
        $stmt = $this->pdo->prepare('INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)');
        return $stmt->execute([$id, $data, time()]);
    }
    public function destroy(string $id): bool {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
        return $stmt->execute([$id]);
    }
    public function gc(int $max_lifetime): int|false {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE last_activity < ?');
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}

ini_set('session.use_strict_mode', '1');
session_name($config['session']['name']);
if ($config['session']['driver'] === 'database') {
    session_set_save_handler(new DatabaseSessionHandler($pdo), true);
}
$forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => $isHttps || $forwardedProto === 'https',
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

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
