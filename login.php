<?php
require __DIR__ . '/includes/bootstrap.php';
if (!empty($_SESSION['admin_id'])) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identity = post_string('identity', 190); $password = (string)($_POST['password'] ?? '');
    if ($identity === '' || $password === '') $error = 'Enter your username or email and password.';
    else {
        $stmt = $pdo->prepare('SELECT id,full_name,password_hash FROM admins WHERE (username=? OR email=?) AND is_active=1 LIMIT 1');
        $stmt->execute([$identity, strtolower($identity)]); $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true); $_SESSION['admin_id']=(int)$admin['id']; $_SESSION['admin_name']=$admin['full_name']; $_SESSION['last_seen']=time();
            $pdo->prepare('UPDATE admins SET last_login_at=NOW() WHERE id=?')->execute([$admin['id']]); audit($pdo,'admin.login','admin',(int)$admin['id']); redirect('index.php');
        }
        usleep(350000); $error = 'The username/email or password is incorrect.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login · Serene Smile</title><link rel="stylesheet" href="<?=e(url('assets/app.css'))?>"></head><body class="login-page"><section class="login-art"><div class="brand-mark">S</div><h1>Care begins<br>with calm.</h1><p>Secure administration for Serene Smile Dental Clinic—appointments, patients, schedules and services in one thoughtful workspace.</p></section><section class="login-box"><div class="login-card"><p class="eyebrow">Serene Smile Dental Clinic</p><h2>Welcome back</h2><p>Sign in to your administrator account.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php elseif(isset($_GET['expired'])):?><div class="alert error">Your session expired. Please sign in again.</div><?php endif?><form method="post" autocomplete="on"><?=csrf_field()?><label>Username or email<input name="identity" maxlength="190" required autocomplete="username" value="<?=e($_POST['identity']??'')?>"></label><label>Password<div class="password-wrap"><input id="password" type="password" name="password" required autocomplete="current-password"><button type="button" data-password-toggle="password">Show</button></div></label><button type="submit">Sign in securely</button></form></div></section><script src="<?=e(url('assets/app.js'))?>"></script></body></html>
