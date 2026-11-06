<?php
declare(strict_types=1);

function page_header(string $title, string $active = ''): void {
    $adminName = e($_SESSION['admin_name'] ?? 'Administrator');
    $items = ['dashboard'=>'Dashboard','appointments'=>'Appointments','patients'=>'Patients','dentists'=>'Dentists','services'=>'Dental Services','schedules'=>'Schedules','settings'=>'Settings'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.e($title).' · Serene Smile</title><link rel="stylesheet" href="'.e(url('assets/app.css')).'"></head><body>';
    echo '<aside class="sidebar"><a class="brand" href="'.e(url('index.php')).'"><span class="brand-mark">S</span><span>Serene Smile<small>Clinic Admin</small></span></a><nav>';
    foreach ($items as $key=>$label) echo '<a class="'.($active===$key?'active':'').'" href="'.e(url($key==='dashboard'?'index.php':$key.'.php')).'">'.e($label).'</a>';
    echo '</nav><div class="sidebar-user"><span>'.$adminName.'</span><a href="'.e(url('logout.php')).'">Log out</a></div></aside>';
    echo '<main class="main"><header class="topbar"><button class="menu" type="button" aria-label="Open navigation">☰</button><div><p class="eyebrow">Serene Smile Dental Clinic</p><h1>'.e($title).'</h1></div><div class="avatar">'.e(strtoupper(substr($adminName,0,1))).'</div></header><div class="content">';
    foreach (flashes() as $f) echo '<div class="alert '.e($f['type']).'">'.e($f['message']).'</div>';
}
function page_footer(): void { echo '</div></main><script src="'.e(url('assets/app.js')).'"></script></body></html>'; }
function status_badge(string $status): string { return '<span class="badge '.strtolower(e($status)).'">'.e($status).'</span>'; }
function pagination(int $page, int $pages): void {
    if ($pages < 2) return; $q = $_GET;
    echo '<div class="pagination">';
    for ($i=1;$i<=$pages;$i++) { $q['page']=$i; echo '<a class="'.($i===$page?'current':'').'" href="?'.e(http_build_query($q)).'">'.$i.'</a>'; }
    echo '</div>';
}
