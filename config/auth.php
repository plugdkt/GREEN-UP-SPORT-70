<?php
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443)
    || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Security headers fallback for PHP/Apache (IIS also sets these via web.config)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

require_once __DIR__ . '/db.php';

// Admin credentials:
// Recommended on IIS/Production: set ADMIN_USER and ADMIN_PASS_HASH (bcrypt) or ADMIN_PASS via Environment Variables
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS_HASH', getenv('ADMIN_PASS_HASH') ?: '');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: 'green2027');
define('SESSION_IDLE_TIMEOUT', 3600); // 60 minutes

function is_admin_logged_in(): bool {
    if (empty($_SESSION['green_admin_logged_in'])) {
        return false;
    }
    $last = $_SESSION['green_admin_last_activity'] ?? 0;
    if ($last > 0 && (time() - $last) > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_destroy();
        return false;
    }
    $_SESSION['green_admin_last_activity'] = time();
    return true;
}

function require_admin(): void {
    if (!is_admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function verify_admin_credentials(string $user, string $pass): bool {
    $user_ok = hash_equals(ADMIN_USER, $user);
    if (ADMIN_PASS_HASH !== '') {
        $pass_ok = password_verify($pass, ADMIN_PASS_HASH);
    } else {
        $pass_ok = hash_equals(ADMIN_PASS, $pass);
    }
    return $user_ok && $pass_ok;
}

// ============================================================================
// CSRF Protection
// ============================================================================
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '" />';
}

function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================================
// Login Brute-Force Rate Limiting (Max 5 failed attempts / 15 minutes per IP)
// ============================================================================
function _rate_limit_file(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'green_sport_login_' . hash('sha256', $ip) . '.json';
}

function login_rate_limit_check(): array {
    $file = _rate_limit_file();
    $now = time();
    $window = 900; // 15 minutes
    $max_attempts = 5;
    $attempts = [];

    if (file_exists($file)) {
        $data = json_decode((string)@file_get_contents($file), true);
        if (is_array($data)) {
            $attempts = array_values(array_filter($data, fn($ts) => is_int($ts) && ($now - $ts) < $window));
        }
    }

    if (count($attempts) >= $max_attempts) {
        $wait_sec = $window - ($now - min($attempts));
        return [true, max(1, (int)ceil($wait_sec / 60))];
    }
    return [false, 0];
}

function login_rate_limit_record_failure(): void {
    $file = _rate_limit_file();
    $now = time();
    $window = 900;
    $attempts = [];
    if (file_exists($file)) {
        $data = json_decode((string)@file_get_contents($file), true);
        if (is_array($data)) {
            $attempts = array_values(array_filter($data, fn($ts) => is_int($ts) && ($now - $ts) < $window));
        }
    }
    $attempts[] = $now;
    @file_put_contents($file, json_encode($attempts), LOCK_EX);
}

function login_rate_limit_clear(): void {
    $file = _rate_limit_file();
    if (file_exists($file)) {
        @unlink($file);
    }
}
