<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// รหัสผ่านเข้าใช้งานระบบหลังบ้าน (สามารถตั้งผ่าน Environment Variable ADMIN_USER / ADMIN_PASS ได้)
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'green2027');

function is_admin_logged_in(): bool {
    return !empty($_SESSION['green_admin_logged_in']);
}

function require_admin(): void {
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}
