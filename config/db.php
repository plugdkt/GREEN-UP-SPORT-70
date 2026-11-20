<?php
/**
 * Database Connection Configuration (Supports both Docker env & Windows IIS Server)
 */
$env_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_readable($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k !== '' && getenv($k) === false) {
            if (strlen($v) >= 2 && (($v[0] === '"' && $v[-1] === '"') || ($v[0] === "'" && $v[-1] === "'"))) {
                $v = substr($v, 1, -1);
            }
            putenv("{$k}={$v}");
        }
    }
}

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'green_sport_2027';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('[GREEN-SPORT DB ERROR] ' . $e->getMessage());
    http_response_code(500);
    die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้ กรุณาติดต่อผู้ดูแลระบบ');
}

function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Mask Thai phone numbers for PDPA compliance on public pages (full number shown for logged-in Admins).
 */
function mask_phone($phone, bool $show_full = false): string {
    $raw = trim((string)$phone);
    if ($raw === '' || $raw === '-' || $show_full) {
        return $raw;
    }
    $digits = preg_replace('/\D+/', '', $raw);
    if (strlen($digits) >= 9) {
        return substr($digits, 0, 3) . '-XXX-' . substr($digits, -4);
    }
    return $raw;
}
