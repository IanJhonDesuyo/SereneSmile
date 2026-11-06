<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = trim($path, '/');

$pages = [
    '' => 'index.php',
    'index.php' => 'index.php',
    'appointments.php' => 'appointments.php',
    'dentists.php' => 'dentists.php',
    'login.php' => 'login.php',
    'logout.php' => 'logout.php',
    'patients.php' => 'patients.php',
    'schedules.php' => 'schedules.php',
    'services.php' => 'services.php',
    'settings.php' => 'settings.php',
    'setup.php' => 'setup.php',
];

if (!isset($pages[$route])) {
    http_response_code(404);
    exit('Page not found.');
}

$page = $pages[$route];
$_SERVER['SCRIPT_NAME'] = '/' . $page;
$_SERVER['PHP_SELF'] = '/' . $page;

require dirname(__DIR__) . '/' . $page;
