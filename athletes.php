<?php
require_once __DIR__ . '/config/auth.php';

$filter_sport = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : (isset($_POST['sport_id']) ? (int)$_POST['sport_id'] : 0);
$dept_map     = athlete_departments();
$filter_dept  = isset($_GET['dept']) ? trim((string)$_GET['dept']) : '';
if ($filter_dept !== '' && !isset($dept_map[$filter_dept])) {
    $filter_dept = '';
}

$reg_error = '';
$reg_ok    = '';
if (isset($_GET['added']) && $_GET['added'] === '1') {
    $reg_ok = 'ลงทะเบียนเพิ่มรายชื่อนักกีฬาเรียบร้อยแล้ว!';
}

$reg_passcode = trim((string)(getenv('REG_PASSCODE') !== false ? getenv('REG_PASSCODE') : 'GREEN70'));

// ============================================================================
// IP-BASED RATE LIMITER FOR PUBLIC ATHLETE REGISTRATION
// ponytail: single-server temp-file limiter; upgrade to Redis if scaled across multiple web nodes
// ============================================================================
function _ath_reg_rate_file(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'green_sport_reg_' . hash('sha256', $ip) . '.json';
}

function ath_reg_rate_check_and_hit(bool $record_hit = false): bool {
    $file = _ath_reg_rate_file();
    $now = time();
    $window = 600; // 10 minutes
    $max_attempts = 20;
    $attempts = [];

    if (file_exists($file)) {
        $data = json_decode((string)@file_get_contents($file), true);
        if (is_array($data)) {
            $attempts = array_values(array_filter($data, function ($t) use ($now, $window) {
                return is_int($t) && ($now - $t) < $window;
            }));
        }
    }

    if (count($attempts) >= $max_attempts) {
        return true; // rate-limited
    }

    if ($record_hit) {
        $attempts[] = $now;
        @file_put_contents($file, json_encode($attempts), LOCK_EX);
    }
    return false;
}

// ============================================================================
// HANDLE PUBLIC / COORDINATOR ATHLETE REGISTRATION POST
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register_athlete') {
    $is_admin_post = is_admin_logged_in();

    if (!verify_csrf()) {
        $reg_error = 'คำขอไม่ถูกต้อง (CSRF Token หมดอายุ) กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง';
    } elseif (!$is_admin_post && trim((string)($_POST['website_url'] ?? '')) !== '') {
        // Honeypot tripped by automated bot
        ath_reg_rate_check_and_hit(true);
        usleep(300000);
        $reg_error = 'ไม่สามารถทำรายการได้ กรุณาลองใหม่อีกครั้ง';
    } elseif (!$is_admin_post && ath_reg_rate_check_and_hit(false)) {
        $reg_error = 'ส่งข้อมูลถี่เกินกำหนด กรุณารอสักครู่ (ประมาณ 10 นาที) แล้วลองใหม่อีกครั้ง';
    } elseif (!$is_admin_post && $reg_passcode !== '' && !hash_equals(strtoupper($reg_passcode), strtoupper(trim((string)($_POST['reg_passcode'] ?? ''))))) {
        ath_reg_rate_check_and_hit(true);
        usleep(300000);
        $reg_error = 'รหัสลงทะเบียนสีเขียวไม่ถูกต้อง กรุณาสอบถามรหัสจากกลุ่ม LINE หรือผู้ประสานงานสีเขียว';
    } else {
        if ($reg_error === '') {
            $sport_id  = (int)($_POST['sport_id'] ?? 0);
            $dept_name = trim((string)($_POST['department_name'] ?? ''));
            $unit_id   = isset($dept_map[$dept_name]) ? (int)$dept_map[$dept_name] : 0;
            $role      = 'ATHLETE';
            $prefix    = mb_substr(trim($_POST['title_prefix'] ?? ''), 0, 50);
            $full_name = mb_substr(trim($_POST['full_name'] ?? ''), 0, 150);
            $nickname  = mb_substr(trim($_POST['nickname'] ?? ''), 0, 60);
            $gender_in = $_POST['gender'] ?? 'M';
            $gender    = in_array($gender_in, ['M', 'F'], true) ? $gender_in : 'M';
            $phone     = mb_substr(trim($_POST['phone'] ?? ''), 0, 40);
            $category  = mb_substr(trim($_POST['competition_category'] ?? ''), 0, 255);
            if ($sport_id === 4) {
                $ath_age    = trim((string)($_POST['athletics_age'] ?? ''));
                $ath_events = is_array($_POST['athletics_events'] ?? null) ? $_POST['athletics_events'] : [];
                $category   = build_athletics_category($ath_age, $ath_events);
            }

            if ($sport_id <= 0 || $unit_id <= 0 || $full_name === '' || $phone === '') {
                $reg_error = 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * (ชนิดกีฬา, ชื่อ-นามสกุล, สังกัด และเบอร์โทรศัพท์) ให้ครบถ้วน';
            } elseif ($sport_id === 4 && $category === '') {
                $reg_error = 'สำหรับกรีฑา กรุณาเลือก "ช่วงอายุ" และ "ประเภทการแข่งขัน" อย่างน้อย 1 รายการ';
            } else {
                // Verify sport_id & unit_id exist
                $chk_sp = $pdo->prepare("SELECT id FROM sports WHERE id = :id");
                $chk_sp->execute([':id' => $sport_id]);
                $chk_un = $pdo->prepare("SELECT id FROM units WHERE id = :id");
                $chk_un->execute([':id' => $unit_id]);

                if (!$chk_sp->fetchColumn() || !$chk_un->fetchColumn()) {
                    $reg_error = 'ไม่พบชนิดกีฬาหรือส่วนงานที่เลือก กรุณาลองใหม่อีกครั้ง';
                } else {
                    // Duplicate check within same sport
                    $chk_dup = $pdo->prepare("SELECT id FROM sport_members WHERE sport_id = :sid AND full_name = :fn LIMIT 1");
                    $chk_dup->execute([':sid' => $sport_id, ':fn' => $full_name]);
                    if ($chk_dup->fetchColumn()) {
                        $reg_error = "รายชื่อ \"{$full_name}\" มีอยู่ในชนิดกีฬานี้แล้ว (ไม่สามารถลงทะเบียนซ้ำได้)";
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO sport_members (sport_id, unit_id, department_name, member_role, title_prefix, full_name, nickname, gender, phone, competition_category)
                            VALUES (:sport_id, :unit_id, :dept_name, :role, :prefix, :full_name, :nickname, :gender, :phone, :cat)
                        ");
                        $ins->execute([
                            ':sport_id'  => $sport_id,
                            ':unit_id'   => $unit_id,
                            ':dept_name' => $dept_name,
                            ':role'      => $role,
                            ':prefix'    => $prefix,
                            ':full_name' => $full_name,
                            ':nickname'  => $nickname,
                            ':gender'    => $gender,
                            ':phone'     => $phone,
                            ':cat'       => $category,
                        ]);
                        if (!$is_admin_post) {
                            ath_reg_rate_check_and_hit(true);
                        }
                        header("Location: athletes.php?sport_id={$sport_id}&added=1");
                        exit;
                    }
                }
            }
        }
    }
}

$sports = $pdo->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM sport_members sm WHERE sm.sport_id = s.id) AS member_count
    FROM sports s
    ORDER BY s.sport_no ASC
")->fetchAll();

$total_all_members = array_sum(array_column($sports, 'member_count'));

$active_sport_info = null;
foreach ($sports as $sp) {
    if ((int)$sp['id'] === $filter_sport) {
        $active_sport_info = $sp;
        break;
    }
}

$units = $pdo->query("SELECT * FROM units ORDER BY id ASC")->fetchAll();

$sql = "
    SELECT sm.*, s.sport_no, s.sport_name,
           COALESCE(NULLIF(sm.department_name, ''), u.unit_name) AS unit_name
    FROM sport_members sm
    JOIN sports s ON s.id = sm.sport_id
    JOIN units u ON u.id = sm.unit_id
    WHERE 1=1
";
$params = [];
if ($filter_sport > 0) {
    $sql .= " AND sm.sport_id = :sport_id";
    $params[':sport_id'] = $filter_sport;
}
if ($filter_dept !== '') {
    $sql .= " AND COALESCE(NULLIF(sm.department_name, ''), u.unit_name) = :dept";
    $params[':dept'] = $filter_dept;
}
$sql .= " ORDER BY s.sport_no ASC, FIELD(sm.member_role, 'MANAGER', 'COACH', 'COORDINATOR', 'ATHLETE', 'SUB_ATHLETE'), sm.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$role_labels = [
    'MANAGER'     => ['ผู้จัดการทีม', 'warn'],
    'COACH'       => ['ผู้จัดการร่วม/โค้ช', 'warn'],
    'COORDINATOR' => ['ผู้ประสานงานส่วนงาน', 'info'],
    'ATHLETE'     => ['นักกีฬา', ''],
    'SUB_ATHLETE' => ['นักกีฬาตัวสำรอง', 'info'],
];

$sport_icons = [
    1 => '⚽', 2 => '🏸', 3 => '🏐', 4 => '🏃', 5 => '🧺',
    6 => '🎾', 7 => '🏀', 8 => '🎪', 9 => '⚪', 10 => '💃', 11 => '🎮'
];

$page_title   = 'UP SPORT 2027 · รายงานทำเนียบนักกีฬาและผู้จัดการทีม';
$page_crumb   = 'รายงานทำเนียบนักกีฬาและผู้จัดการทีม (11 ชนิดกีฬา)';
$page_heading = 'ทำเนียบรายชื่อนักกีฬา & ผู้จัดการทีมสีเขียว';
$page_desc    = 'คลิกเลือกแท็บชนิดกีฬาด้านล่างเพื่อตรวจสอบรายชื่อผู้จัดการทีมและนักกีฬาของแต่ละชนิดกีฬา';
$page_icon    = '👟';

require_once __DIR__ . '/includes/header.php';
?>

<style>
  .sport-tabs-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
    background: #ffffff;
    padding: 14px;
    border-radius: 18px;
    border: 1px solid var(--line);
    box-shadow: var(--shadow);
  }
  .sport-tab-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    border-radius: 12px;
    border: 1px solid var(--line);
    background: #f8fcf9;
    color: var(--ink);
    font-weight: 700;
    font-size: 0.88rem;
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .sport-tab-pill:hover {
    border-color: var(--green);
    background: #ecfdf5;
    transform: translateY(-1px);
  }
  .sport-tab-pill.active {
    background: linear-gradient(135deg, #064e3b, #059669);
    color: #ffffff;
    border-color: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
  }
  .sport-tab-count {
    padding: 2px 8px;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 800;
    background: #dcfce7;
    color: #065f46;
  }
  .sport-tab-pill.active .sport-tab-count {
    background: #bef264;
    color: #032217;
  }
  .ath-picker-box {
    grid-column: 1 / -1;
    background: #ffffff;
    border: 1.5px solid #86efac;
    border-radius: 12px;
    padding: 14px 16px;
  }
  .ath-group-title {
    font-size: 0.84rem;
    font-weight: 800;
    color: var(--green-dark);
    margin: 10px 0 6px;
  }
  .ath-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .ath-check-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 11px;
    border-radius: 9px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    font-size: 0.83rem;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
  }
  .ath-check-pill:has(input:checked) {
    background: #dcfce7;
    border-color: var(--green);
    color: #064e3b;
    font-weight: 800;
  }
  .ath-matrix-cell {
    font-size: 0.8rem;
    vertical-align: top;
    min-width: 125px;
  }
  .ath-runner-chip {
    display: inline-block;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    border-radius: 7px;
    padding: 2px 7px;
    margin: 2px 2px 0 0;
    font-weight: 650;
    font-size: 0.76rem;
  }
</style>

<!-- แถบคลิกเลือก Tab ชนิดกีฬาทั้ง 11 ชนิด -->
<div class="sport-tabs-bar">
  <a href="athletes.php" class="sport-tab-pill <?= $filter_sport === 0 ? 'active' : '' ?>">
    <span>📋 รวมทุกชนิดกีฬา</span>
    <span class="sport-tab-count"><?= (int)$total_all_members ?> คน</span>
  </a>

  <?php foreach ($sports as $sp):
    $sid = (int)$sp['id'];
    $sno = (int)$sp['sport_no'];
    $ico = $sport_icons[$sno] ?? '🏅';
    $is_active = ($filter_sport === $sid);
  ?>
    <a href="athletes.php?sport_id=<?= $sid ?>" class="sport-tab-pill <?= $is_active ? 'active' : '' ?>">
      <span><?= $ico ?> <?= $sno ?>. <?= h($sp['sport_name']) ?></span>
      <span class="sport-tab-count"><?= (int)$sp['member_count'] ?> คน</span>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($active_sport_info): ?>
  <div class="alert-banner" style="margin-bottom:18px;">
    <div>
      <strong style="font-size:1.05rem;">
        <?= $sport_icons[(int)$active_sport_info['sport_no']] ?? '🏅' ?>
        กีฬาที่ <?= (int)$active_sport_info['sport_no'] ?>: <?= h($active_sport_info['sport_name']) ?> (<?= h($active_sport_info['sport_type']) ?>)
      </strong>
      <div style="font-size:0.86rem; font-weight:500; margin-top:2px;">
        ผู้จัดการทีม: <strong><?= h($active_sport_info['manager_name']) ?><?= $active_sport_info['sub_manager_name'] ? ' / ' . h($active_sport_info['sub_manager_name']) : '' ?></strong>
        (<?= h($active_sport_info['manager_unit']) ?> · โทร. <?= h(mask_phone($active_sport_info['manager_phone'], is_admin_logged_in())) ?>)
        | สนามแข่ง: <strong><?= h($active_sport_info['default_venue']) ?></strong>
      </div>
    </div>
    <span class="status-pill" style="font-size:0.88rem; padding:6px 14px;">
      ขึ้นทะเบียนแล้ว <?= (int)$active_sport_info['member_count'] ?> คน
    </span>
  </div>
<?php endif; ?>

<?php if ($filter_sport === 4):
  $ath_ages = athletics_age_groups();
  $ath_groups = athletics_event_groups();
  // สร้างตารางสรุป รายการ × ช่วงอายุ จากรายชื่อกรีฑาทั้งหมด (ไม่ถูกจำกัดด้วยตัวกรองสังกัด หรือกรองตามที่เลือก)
  $ath_matrix = [];
  foreach ($members as $m) {
      $parsed = parse_athletics_category($m['competition_category'] ?? '');
      if ($parsed['age'] !== '' && !empty($parsed['events'])) {
          $label = trim($m['title_prefix'] . ' ' . $m['full_name']);
          if (!empty($m['nickname'])) {
              $label .= ' (' . $m['nickname'] . ')';
          }
          foreach ($parsed['events'] as $ev) {
              $ath_matrix[$ev][$parsed['age']][] = $label;
          }
      }
  }
?>
<div class="white-shell" style="margin-bottom:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
    <div>
      <h2 style="font-size:1.15rem; font-weight:850; color:var(--green-dark); margin:0;">🏃 ตารางสรุปประเภทการแข่งขันกรีฑา (แยกตามรายการ × ช่วงอายุ)</h2>
      <div style="font-size:0.82rem; color:var(--muted); margin-top:2px;">ดูรายชื่อผู้สมัครแต่ละระยะและช่วงอายุ เพื่อช่วยเช็กตำแหน่งที่ยังว่าง (เช่น ไม้ผลัด 4×100, 4×400 และวิ่งรวม)</div>
    </div>
    <button type="button" class="btn-primary btn-sm" onclick="openAthleticsRegForm()">➕ ลงชื่อสมัครกรีฑา</button>
  </div>

  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th style="min-width:170px;">รายการแข่งขัน</th>
          <?php foreach ($ath_ages as $ag): ?>
            <th style="text-align:center;"><?= h($ag) ?></th>
          <?php endforeach; ?>
          <th style="text-align:center; width:72px;">รวม</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ath_groups as $grp_title => $ev_list): ?>
          <tr style="background:#ecfdf5;">
            <td colspan="<?= count($ath_ages) + 2 ?>" style="font-weight:850; color:var(--green-dark); padding:8px 12px;">
              🏅 <?= h($grp_title) ?>
            </td>
          </tr>
          <?php foreach ($ev_list as $ev):
            $row_total = 0;
            foreach ($ath_ages as $ag) {
                $row_total += count($ath_matrix[$ev][$ag] ?? []);
            }
          ?>
          <tr>
            <td><strong><?= h($ev) ?></strong></td>
            <?php foreach ($ath_ages as $ag):
              $runners = $ath_matrix[$ev][$ag] ?? [];
            ?>
              <td class="ath-matrix-cell" style="text-align:<?= empty($runners) ? 'center' : 'left' ?>;">
                <?php if (empty($runners)): ?>
                  <span style="color:#cbd5e1;">-</span>
                <?php else: ?>
                  <?php foreach ($runners as $rn): ?>
                    <span class="ath-runner-chip"><?= h($rn) ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
            <td style="text-align:center; font-weight:800; color:<?= $row_total > 0 ? 'var(--green-dark)' : '#94a3b8' ?>;">
              <?= $row_total > 0 ? $row_total . ' คน' : '-' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="white-shell">
  <?php if ($reg_ok): ?>
    <div class="flash-ok" style="margin-bottom:14px;">✅ <?= h($reg_ok) ?></div>
  <?php endif; ?>
  <?php if ($reg_error): ?>
    <div style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:10px 14px; border-radius:10px; font-weight:700; margin-bottom:14px; font-size:0.88rem;">
      ⚠️ <?= h($reg_error) ?>
    </div>
  <?php endif; ?>

  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <h2 style="font-size:1.18rem; font-weight:800;">
      <?= $active_sport_info ? 'ทำเนียบผู้จัดการทีมและนักกีฬา: ' . h($active_sport_info['sport_name']) : 'ทำเนียบผู้จัดการทีมและนักกีฬาทั้งหมด (11 ชนิดกีฬา)' ?>
    </h2>

    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
      <button type="button" class="btn-primary btn-sm" onclick="toggleRegForm()">➕ เพิ่มรายชื่อนักกีฬา</button>

      <form method="get" style="display:flex; align-items:center; gap:8px; margin:0;">
        <?php if ($filter_sport > 0): ?>
          <input type="hidden" name="sport_id" value="<?= $filter_sport ?>" />
        <?php endif; ?>
        <label style="font-size:0.84rem; font-weight:700; color:var(--muted);">กรองตามสังกัด:</label>
        <select name="dept" class="form-control" style="width:auto; padding:5px 10px;" onchange="this.form.submit()">
          <option value="">-- ทุกส่วนงาน (9 ส่วนงาน) --</option>
          <?php foreach (array_keys($dept_map) as $dname): ?>
            <option value="<?= h($dname) ?>" <?= $filter_dept === $dname ? 'selected' : '' ?>>
              <?= h($dname) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php if (is_admin_logged_in()): ?>
        <a href="admin.php?tab=athletes&amp;sport_id=<?= $filter_sport ?>" class="btn-primary btn-sm" style="background:#0f766e;">⚙️ จัดการ/ลบใน Admin ›</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ฟอร์มลงทะเบียนเพิ่มรายชื่อนักกีฬาโดยตรง -->
  <div id="athleteRegBox" style="display:<?= $reg_error ? 'block' : 'none' ?>; background:#f0fdf4; border:2px solid var(--green); border-radius:14px; padding:18px; margin-bottom:18px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <div>
        <h3 style="font-size:1.05rem; font-weight:850; color:var(--green-dark); margin:0;">📝 ลงทะเบียนเพิ่มรายชื่อนักกีฬาใหม่ (ทัพสีเขียว)</h3>
        <div style="font-size:0.8rem; color:var(--muted); margin-top:2px;">กรอกข้อมูลให้ครบถ้วน เบอร์โทรศัพท์จะถูกซ่อนเลขกลางอัตโนมัติบนหน้าเว็บสาธารณะตามหลัก PDPA</div>
      </div>
      <button type="button" class="btn-sm" style="background:#e2e8f0; color:#0f172a; border:none; cursor:pointer;" onclick="toggleRegForm()">✕ ปิดฟอร์ม</button>
    </div>

    <form method="post" class="form-grid" style="margin-bottom:0;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="register_athlete" />
      <div style="position:absolute; left:-9999px; opacity:0; height:0; width:0; overflow:hidden;" aria-hidden="true">
        <label>Website</label>
        <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off" />
      </div>
      <div class="form-group">
        <label>ชนิดกีฬา (1–11) *</label>
        <select name="sport_id" id="reg_sport_id" class="form-control" onchange="syncAthleticsPicker()" required>
          <?php foreach ($sports as $sp): $sid = (int)$sp['id']; ?>
            <option value="<?= $sid ?>" <?= ($filter_sport === $sid || (isset($_POST['sport_id']) && (int)$_POST['sport_id'] === $sid)) ? 'selected' : '' ?>>
              <?= (int)$sp['sport_no'] ?>. <?= h($sp['sport_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>คำนำหน้า</label>
        <input type="text" name="title_prefix" placeholder="นาย / นางสาว / ผศ.ดร." value="<?= h($_POST['title_prefix'] ?? '') ?>" class="form-control" />
      </div>
      <div class="form-group">
        <label>ชื่อ - นามสกุล *</label>
        <input type="text" name="full_name" placeholder="ระบุชื่อ-นามสกุลจริง" value="<?= h($_POST['full_name'] ?? '') ?>" class="form-control" required />
      </div>
      <div class="form-group">
        <label>ชื่อเล่น</label>
        <input type="text" name="nickname" placeholder="เช่น ปลั๊ก" value="<?= h($_POST['nickname'] ?? '') ?>" class="form-control" />
      </div>
      <div class="form-group">
        <label>เพศ *</label>
        <select name="gender" class="form-control" required>
          <option value="M" <?= ($_POST['gender'] ?? 'M') === 'M' ? 'selected' : '' ?>>ชาย</option>
          <option value="F" <?= ($_POST['gender'] ?? '') === 'F' ? 'selected' : '' ?>>หญิง</option>
        </select>
      </div>
      <div class="form-group">
        <label>สังกัด (ส่วนงาน) *</label>
        <?php $sel_dept = trim((string)($_POST['department_name'] ?? $filter_dept)); ?>
        <select name="department_name" class="form-control" required>
          <option value="">-- เลือกสังกัด (ส่วนงาน) --</option>
          <?php foreach (array_keys($dept_map) as $dname): ?>
            <option value="<?= h($dname) ?>" <?= $sel_dept === $dname ? 'selected' : '' ?>>
              <?= h($dname) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>เบอร์โทรศัพท์ติดต่อ *</label>
        <input type="text" name="phone" placeholder="เช่น 081-2345678" value="<?= h($_POST['phone'] ?? '') ?>" class="form-control" required />
      </div>
      <div class="form-group" id="reg_cat_text_group">
        <label>ประเภท / ตำแหน่ง / IGN (ถ้ามี)</label>
        <input type="text" name="competition_category" placeholder="เช่น ทีมผสม / คู่ชาย / IGN: ..." value="<?= h($_POST['competition_category'] ?? '') ?>" class="form-control" />
      </div>
      <?php
        $sel_ath_age = (string)($_POST['athletics_age'] ?? '');
        $sel_ath_evs = is_array($_POST['athletics_events'] ?? null) ? $_POST['athletics_events'] : [];
      ?>
      <div class="ath-picker-box" id="reg_athletics_box" style="display:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:8px;">
          <strong style="color:var(--green-dark); font-size:0.95rem;">🏃 เลือกช่วงอายุและประเภทการแข่งขันกรีฑา *</strong>
          <div style="display:flex; align-items:center; gap:8px;">
            <label style="font-size:0.84rem; font-weight:800; color:var(--ink); margin:0;">ช่วงอายุ *:</label>
            <select name="athletics_age" id="reg_ath_age" class="form-control" style="width:auto; padding:5px 10px;">
              <option value="">-- เลือกช่วงอายุ --</option>
              <?php foreach (athletics_age_groups() as $ag): ?>
                <option value="<?= h($ag) ?>" <?= $sel_ath_age === $ag ? 'selected' : '' ?>><?= h($ag) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div style="font-size:0.78rem; color:var(--muted); margin-bottom:6px;">เลือกประเภทการแข่งขันที่ต้องการลงสมัครได้มากกว่า 1 รายการ</div>
        <?php foreach (athletics_event_groups() as $grp_title => $ev_list): ?>
          <div class="ath-group-title"><?= h($grp_title) ?></div>
          <div class="ath-checks">
            <?php foreach ($ev_list as $ev): ?>
              <label class="ath-check-pill">
                <input type="checkbox" name="athletics_events[]" value="<?= h($ev) ?>" <?= in_array($ev, $sel_ath_evs, true) ? 'checked' : '' ?> />
                <span><?= h($ev) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (!is_admin_logged_in() && $reg_passcode !== ''): ?>
      <div class="form-group">
        <label>🔑 รหัสลงทะเบียนสีเขียว *</label>
        <input type="text" name="reg_passcode" placeholder="สอบถามจากกลุ่ม LINE / ผู้ประสานงานสีเขียว" autocomplete="off" class="form-control" required />
      </div>
      <?php endif; ?>
      <div class="form-group" style="display:flex; align-items:flex-end;">
        <button type="submit" class="btn-primary" style="width:100%;">💾 บันทึกรายชื่อนักกีฬา</button>
      </div>
    </form>
  </div>

  <script>
    function syncAthleticsPicker() {
      var sel = document.getElementById('reg_sport_id');
      if (!sel) return;
      var isAth = (sel.value === '4');
      var textGrp = document.getElementById('reg_cat_text_group');
      var athBox  = document.getElementById('reg_athletics_box');
      var ageSel  = document.getElementById('reg_ath_age');
      if (textGrp) textGrp.style.display = isAth ? 'none' : '';
      if (athBox)  athBox.style.display  = isAth ? 'block' : 'none';
      if (ageSel)  ageSel.required       = isAth;
    }
    function toggleRegForm() {
      var box = document.getElementById('athleteRegBox');
      box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
      if (box.style.display === 'block') {
        syncAthleticsPicker();
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }
    function openAthleticsRegForm() {
      var box = document.getElementById('athleteRegBox');
      var sel = document.getElementById('reg_sport_id');
      if (sel) sel.value = '4';
      box.style.display = 'block';
      syncAthleticsPicker();
      box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    syncAthleticsPicker();
  </script>

  <?php
    $gender_split_sports = [
        2  => ['icon' => '🏸', 'label' => 'แบดมินตัน'],
        3  => ['icon' => '🏐', 'label' => 'วอลเลย์บอล'],
        9  => ['icon' => '⚪', 'label' => 'เปตอง'],
        11 => ['icon' => '🎮', 'label' => 'E-SPORT'],
    ];
    if (isset($gender_split_sports[$filter_sport])):
      $gs_meta = $gender_split_sports[$filter_sport];
      $gender_groups = [
          'M' => ['title' => $gs_meta['icon'] . ' ทีมชาย (' . $gs_meta['label'] . ' ชาย)', 'bg' => '#ecfdf5', 'border' => '#6ee7b7', 'color' => '#065f46', 'rows' => []],
          'F' => ['title' => $gs_meta['icon'] . ' ทีมหญิง (' . $gs_meta['label'] . ' หญิง)', 'bg' => '#fdf2f8', 'border' => '#f9a8d4', 'color' => '#9d174d', 'rows' => []],
      ];
      foreach ($members as $m) {
          $gkey = ($m['gender'] === 'F') ? 'F' : 'M';
          $gender_groups[$gkey]['rows'][] = $m;
      }
  ?>
    <?php foreach ($gender_groups as $grp): ?>
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin:16px 0 8px; padding:10px 14px; border-radius:12px; background:<?= $grp['bg'] ?>; border:1.5px solid <?= $grp['border'] ?>;">
        <strong style="font-size:1.02rem; color:<?= $grp['color'] ?>;"><?= h($grp['title']) ?></strong>
        <span class="status-pill" style="background:#fff; color:<?= $grp['color'] ?>; border:1px solid <?= $grp['border'] ?>;"><?= count($grp['rows']) ?> คน</span>
      </div>
      <div style="overflow-x:auto; margin-bottom:14px;">
        <table class="up-table">
          <thead>
            <tr>
              <th>ลำดับ</th>
              <th>บทบาท</th>
              <th>ชื่อ - นามสกุล</th>
              <th>สังกัด (ส่วนงาน)</th>
              <th>เบอร์โทรศัพท์ติดต่อ</th>
              <th>ประเภท/ตำแหน่ง</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($grp['rows'])): ?>
              <tr>
                <td colspan="6" style="text-align:center; padding:20px; color:var(--muted);">ยังไม่มีรายชื่อนักกีฬาในทีมนี้</td>
              </tr>
            <?php else: ?>
              <?php foreach ($grp['rows'] as $idx => $m):
                [$r_text, $r_cls] = $role_labels[$m['member_role']] ?? [$m['member_role'], ''];
              ?>
              <tr>
                <td><?= $idx + 1 ?></td>
                <td><span class="status-pill <?= $r_cls ?>"><?= h($r_text) ?></span></td>
                <td>
                  <strong><?= h(trim($m['title_prefix'] . ' ' . $m['full_name'])) ?></strong>
                  <?php if ($m['nickname']): ?>
                    <span style="color:var(--muted);"> (<?= h($m['nickname']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td><?= h($m['unit_name']) ?></td>
                <td style="font-weight:750; color:var(--green-dark);"><?= h(mask_phone($m['phone'], is_admin_logged_in())) ?></td>
                <td><?= h($m['competition_category'] ?: '-') ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th>ลำดับ</th>
          <th>ชนิดกีฬา</th>
          <th>บทบาท</th>
          <th>ชื่อ - นามสกุล</th>
          <th>สังกัด (ส่วนงาน)</th>
          <th>เบอร์โทรศัพท์ติดต่อ</th>
          <th>ประเภท/ตำแหน่ง</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($members)): ?>
          <tr>
            <td colspan="7" style="text-align:center; padding:24px; color:var(--muted);">ยังไม่มีรายชื่อนักกีฬาในหมวดนี้</td>
          </tr>
        <?php else: ?>
          <?php foreach ($members as $idx => $m):
            [$r_text, $r_cls] = $role_labels[$m['member_role']] ?? [$m['member_role'], ''];
          ?>
          <tr>
            <td><?= $idx + 1 ?></td>
            <td><strong><?= (int)$m['sport_no'] ?>. <?= h($m['sport_name']) ?></strong></td>
            <td><span class="status-pill <?= $r_cls ?>"><?= h($r_text) ?></span></td>
            <td>
              <strong><?= h(trim($m['title_prefix'] . ' ' . $m['full_name'])) ?></strong>
              <?php if ($m['nickname']): ?>
                <span style="color:var(--muted);"> (<?= h($m['nickname']) ?>)</span>
              <?php endif; ?>
            </td>
            <td><?= h($m['unit_name']) ?></td>
            <td style="font-weight:750; color:var(--green-dark);"><?= h(mask_phone($m['phone'], is_admin_logged_in())) ?></td>
            <td><?= h($m['competition_category'] ?: '-') ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
