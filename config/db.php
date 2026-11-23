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

/**
 * Athletics (กรีฑา, sport_id = 4) age groups & event categories (single source of truth).
 */
function athletics_age_groups(): array {
    return ['ไม่เกิน 34 ปี', '35–44 ปี', '45–54 ปี', '55 ปีขึ้นไป'];
}

function athletics_event_groups(): array {
    return [
        'วิ่งเดี่ยว (ชาย / หญิง)' => [
            '100 ม. ชาย', '100 ม. หญิง',
            '200 ม. ชาย', '200 ม. หญิง',
            '400 ม. ชาย', '400 ม. หญิง',
            '1,500 ม. ชาย', '1,500 ม. หญิง',
        ],
        'วิ่งผลัด (ชาย / หญิง / ผสม)' => [
            '4×100 ม. ชาย', '4×100 ม. หญิง', '4×100 ม. ผสม',
            '4×400 ม. ชาย', '4×400 ม. หญิง', '4×400 ม. ผสม',
        ],
        'วิ่งรวม (ชาย / หญิง)' => [
            'วิ่งรวม 100×1 ชาย', 'วิ่งรวม 100×1 หญิง',
            'วิ่งรวม 200×1 ชาย', 'วิ่งรวม 200×1 หญิง',
            'วิ่งรวม 300×1 ชาย', 'วิ่งรวม 300×1 หญิง',
            'วิ่งรวม 400×1 ชาย', 'วิ่งรวม 400×1 หญิง',
        ],
    ];
}

function build_athletics_category(string $age, array $events): string {
    $valid_ages = athletics_age_groups();
    if (!in_array($age, $valid_ages, true)) {
        return '';
    }
    $all_events = [];
    foreach (athletics_event_groups() as $list) {
        $all_events = array_merge($all_events, $list);
    }
    $chosen = [];
    foreach ($events as $ev) {
        $ev = trim((string)$ev);
        if (in_array($ev, $all_events, true) && !in_array($ev, $chosen, true)) {
            $chosen[] = $ev;
        }
    }
    if (empty($chosen)) {
        return '';
    }
    return mb_substr("รุ่น {$age} | " . implode(', ', $chosen), 0, 255);
}

function parse_athletics_category(?string $cat): array {
    $cat = trim((string)$cat);
    if ($cat === '' || strpos($cat, '|') === false) {
        return ['age' => '', 'events' => []];
    }
    [$age_part, $ev_part] = array_map('trim', explode('|', $cat, 2));
    $age = trim(preg_replace('/^รุ่น\s*/u', '', $age_part));
    $events = array_values(array_filter(array_map('trim', explode(',', $ev_part)), function ($v) {
        return $v !== '';
    }));
    return ['age' => $age, 'events' => $events];
}

/**
 * Athlete affiliation list (9 clean divisions/faculties mapped to parent budget unit_id).
 */
function athlete_departments(): array {
    return [
        'กองบริการการศึกษา'                  => 1,
        'กองกฎหมาย'                           => 2,
        'กองคลัง'                              => 2,
        'กองทรัพย์สิน'                         => 2,
        'คณะวิทยาศาสตร์'                       => 5,
        'คณะวิทยาศาสตร์การแพทย์'                => 6,
        'คณะวิศวกรรมศาสตร์'                    => 7,
        'คณะเกษตรศาสตร์และทรัพยากรธรรมชาติ'     => 8,
        'สถาบันนวัตกรรมและถ่ายทอดเทคโนโลยี'     => 9,
    ];
}
