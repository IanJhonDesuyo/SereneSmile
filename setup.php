<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$setupToken = (string)getenv('SERENE_SETUP_TOKEN');
try {
    $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
} catch (PDOException $e) {
    http_response_code(503);
    exit('Database schema is incomplete. Import database/schema.sql before creating an administrator.');
}
if ($setupToken === '' || $adminCount > 0) {
    http_response_code(404);
    exit('Page not found.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $token = (string)($_POST['setup_token'] ?? '');
    $username = post_string('username', 50);
    $email = strtolower(post_string('email', 190));
    $fullName = post_string('full_name', 120);
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    if (!hash_equals($setupToken, $token)) $error = 'The setup token is incorrect.';
    elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $error = 'Use 3-50 letters, numbers, dots, dashes, or underscores for the username.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    elseif ($fullName === '') $error = 'Enter the administrator name.';
    elseif (strlen($password) < 12) $error = 'Use a password with at least 12 characters.';
    elseif ($password !== $confirmation) $error = 'The passwords do not match.';
    else {
        try {
            $stmt = $pdo->prepare('INSERT INTO admins (username, email, password_hash, full_name) SELECT ?, ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM admins)');
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName]);
            if ($stmt->rowCount() !== 1) {
                http_response_code(409);
                exit('An administrator account already exists.');
            }
            redirect('login.php');
        } catch (PDOException $e) {
            $error = 'That username or email is already in use.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Initial Setup · Serene Smile</title><link rel="stylesheet" href="<?=e(url('assets/app.css'))?>"></head><body class="login-page"><section class="login-art"><div class="brand-mark">S</div><h1>Set up<br>securely.</h1><p>Create the first Serene Smile administrator. This page disables itself permanently after the account is created.</p></section><section class="login-box"><div class="login-card"><p class="eyebrow">One-time setup</p><h2>Create administrator</h2><p>Use the private setup token configured in Vercel.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif?><form method="post" autocomplete="off"><?=csrf_field()?><label>Setup token<input type="password" name="setup_token" required autocomplete="off"></label><label>Full name<input name="full_name" maxlength="120" required autocomplete="name" value="<?=e($_POST['full_name']??'')?>"></label><label>Username<input name="username" maxlength="50" required autocomplete="username" value="<?=e($_POST['username']??'')?>"></label><label>Email<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?=e($_POST['email']??'')?>"></label><label>Password<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><label>Confirm password<input type="password" name="password_confirmation" minlength="12" required autocomplete="new-password"></label><button type="submit">Create administrator</button></form></div></section></body></html>
