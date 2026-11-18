<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
if (!empty($_SESSION['admin_id'])) redirect('index.php');

$registrationToken = (string)(getenv('SERENE_REGISTRATION_TOKEN') ?: getenv('SERENE_SETUP_TOKEN'));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $token = (string)($_POST['registration_token'] ?? '');
    $fullName = post_string('full_name', 120);
    $username = post_string('username', 50);
    $email = strtolower(post_string('email', 190));
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    if ($registrationToken === '') $error = 'Account registration is not configured.';
    elseif (!hash_equals($registrationToken, $token)) $error = 'The registration token is incorrect.';
    elseif ($fullName === '') $error = 'Enter the administrator name.';
    elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $error = 'Use 3-50 letters, numbers, dots, dashes, or underscores for the username.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    elseif (strlen($password) < 12) $error = 'Use a password with at least 12 characters.';
    elseif ($password !== $confirmation) $error = 'The passwords do not match.';
    else {
        try {
            $stmt = $pdo->prepare('INSERT INTO admins (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName]);
            $adminId = (int)$pdo->lastInsertId();
            audit($pdo, 'admin.registered', 'admin', $adminId);
            redirect('login.php?created=1');
        } catch (PDOException $e) {
            $error = 'That username or email is already in use.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create Account · Serene Smile</title><link rel="stylesheet" href="<?=e(url('assets/app.css'))?>"></head><body class="login-page"><section class="login-art"><div class="brand-mark">S</div><h1>Grow with<br>confidence.</h1><p>Create a secure administrator account using the private registration token provided by the clinic.</p></section><section class="login-box"><div class="login-card register-card"><p class="eyebrow">Serene Smile Dental Clinic</p><h2>Create account</h2><p>Register a new administrator account.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif?><form method="post" autocomplete="off"><?=csrf_field()?><label>Registration token<input type="password" name="registration_token" required autocomplete="off"></label><label>Full name<input name="full_name" maxlength="120" required autocomplete="name" value="<?=e($_POST['full_name']??'')?>"></label><label>Username<input name="username" maxlength="50" required autocomplete="username" value="<?=e($_POST['username']??'')?>"></label><label>Email<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?=e($_POST['email']??'')?>"></label><label>Password<div class="password-wrap"><input id="register-password" type="password" name="password" minlength="12" required autocomplete="new-password"><button type="button" data-password-toggle="register-password">Show</button></div></label><label>Confirm password<div class="password-wrap"><input id="register-confirmation" type="password" name="password_confirmation" minlength="12" required autocomplete="new-password"><button type="button" data-password-toggle="register-confirmation">Show</button></div></label><button type="submit">Create administrator account</button></form><div class="auth-alternative"><span>Already have an account?</span><a class="btn secondary" href="<?=e(url('login.php'))?>">Back to sign in</a></div></div></section><script src="<?=e(url('assets/app.js'))?>"></script></body></html>
