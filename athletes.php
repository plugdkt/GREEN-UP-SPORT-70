<?php
require_once __DIR__ . '/config/auth.php';

$filter_sport = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;
$filter_unit  = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : 0;

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
    SELECT sm.*, s.sport_no, s.sport_name, u.unit_name
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
if ($filter_unit > 0) {
    $sql .= " AND sm.unit_id = :unit_id";
    $params[':unit_id'] = $filter_unit;
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
      <span class="sport-tab-count"><?= (int)$sp['member_count'] ?>/<?= (int)$sp['target_athletes'] ?></span>
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
      ขึ้นทะเบียนแล้ว <?= (int)$active_sport_info['member_count'] ?> / เป้าหมาย <?= (int)$active_sport_info['target_athletes'] ?> คน
    </span>
  </div>
<?php endif; ?>

<div class="white-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <h2 style="font-size:1.18rem; font-weight:800;">
      <?= $active_sport_info ? 'ทำเนียบผู้จัดการทีมและนักกีฬา: ' . h($active_sport_info['sport_name']) : 'ทำเนียบผู้จัดการทีมและนักกีฬาทั้งหมด (11 ชนิดกีฬา)' ?>
    </h2>

    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
      <form method="get" style="display:flex; align-items:center; gap:8px; margin:0;">
        <?php if ($filter_sport > 0): ?>
          <input type="hidden" name="sport_id" value="<?= $filter_sport ?>" />
        <?php endif; ?>
        <label style="font-size:0.84rem; font-weight:700; color:var(--muted);">กรองตามสังกัด:</label>
        <select name="unit_id" class="form-control" style="width:auto; padding:5px 10px;" onchange="this.form.submit()">
          <option value="0">-- ทุกส่วนงาน (9 ส่วนงาน) --</option>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $filter_unit === (int)$u['id'] ? 'selected' : '' ?>>
              <?= h($u['unit_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <?php if (is_admin_logged_in()): ?>
        <a href="admin.php?tab=athletes&amp;sport_id=<?= $filter_sport ?>" class="btn-primary btn-sm">⚙️ เพิ่ม/แก้ไขรายชื่อในหน้า Admin ›</a>
      <?php endif; ?>
    </div>
  </div>

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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
