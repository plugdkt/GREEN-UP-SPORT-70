<?php
require_once __DIR__ . '/config/auth.php';

$sports = $pdo->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM sport_members sm WHERE sm.sport_id = s.id) AS member_count
    FROM sports s
    ORDER BY s.sport_no ASC
")->fetchAll();

$schedules = $pdo->query("
    SELECT ms.*, s.sport_name
    FROM match_schedules ms
    JOIN sports s ON s.id = ms.sport_id
    ORDER BY ms.match_date ASC, ms.start_time ASC
")->fetchAll();

$grouped_matches = [];
foreach ($schedules as $m) {
    $grouped_matches[$m['match_date']][] = $m;
}

$page_title   = 'UP SPORT 2027 · รายงานตารางแข่งขันและงบ 11 ชนิดกีฬา';
$page_crumb   = 'รายงานตารางการแข่งขันและงบประมาณ 11 ชนิดกีฬา';
$page_heading = 'รายงานตารางแข่งขัน & งบประมาณ 11 ชนิดกีฬา';
$page_desc    = 'แสดงกำหนดการลงสนามของทัพสีเขียวและสรุปงบประมาณประจำชนิดกีฬาทั้ง 11 ชนิดกีฬา';
$page_icon    = '📅';

require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. ตารางการแข่งขันทัพสีเขียว (สไตล์ UPSPORT2027) -->
<div class="white-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
    <div>
      <h2 style="font-size:1.2rem; font-weight:800;">1. โปรแกรมการแข่งขันทัพสีเขียว (แยกตามวันที่แข่งขัน)</h2>
      <p style="font-size:0.84rem; color:var(--muted);">ตารางเบื้องต้น อาจมีการปรับวัน เวลา และคู่แข่งขันตามประกาศของมหาวิทยาลัย</p>
    </div>
    <?php if (is_admin_logged_in()): ?>
      <a href="admin.php?tab=matches" class="btn-primary btn-sm">⚙️ จัดการตารางแข่งในหน้า Admin ›</a>
    <?php endif; ?>
  </div>

  <?php foreach ($grouped_matches as $m_date => $matches): ?>
    <div class="date-bar">
      <span>📅 วันที่ <?= h($m_date) ?> (<?= count($matches) ?> รายการแข่งขัน)</span>
    </div>
    <?php foreach ($matches as $m): ?>
      <div class="item-row">
        <div class="item-title-box">
          <div class="sport-circle-icon">🏅</div>
          <div>
            <div class="item-main-title"><?= h($m['opponent_color']) ?></div>
            <div class="item-sub-title"><?= h($m['sport_name']) ?> · <?= h($m['team_division']) ?></div>
          </div>
        </div>
        <div class="item-time">
          <?= substr(h($m['start_time']), 0, 5) ?><?= $m['end_time'] ? '–' . substr(h($m['end_time']), 0, 5) : '' ?> น.
        </div>
        <div class="item-venue"><?= h($m['venue']) ?></div>
        <div style="text-align:right;">
          <span class="status-pill"><?= h($m['round_name'] ?: 'ตามกำหนด') ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>

<!-- 2. รายงานงบประมาณแยกตามชนิดกีฬา (11 ชนิดกีฬา) -->
<div class="white-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <div>
      <h2 style="font-size:1.2rem; font-weight:800;">2. รายงานสรุปงบประมาณแยกตามชนิดกีฬา (11 ชนิดกีฬา)</h2>
      <p style="font-size:0.84rem; color:var(--muted);">สรุปวงเงินงบประมาณสนับสนุนแต่ละชนิดกีฬาของทัพสีเขียว</p>
    </div>
    <?php if (is_admin_logged_in()): ?>
      <a href="admin.php?tab=sports" class="btn-primary btn-sm">⚙️ แก้ไขงบกีฬาในหน้า Admin ›</a>
    <?php endif; ?>
  </div>

  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th>ที่</th>
          <th>ชนิดกีฬา</th>
          <th>ประเภท</th>
          <th>ผู้จัดการทีม &amp; สังกัด</th>
          <th>สนามแข่งขันหลัก</th>
          <th style="text-align:center;">รายชื่อนักกีฬา</th>
          <th style="text-align:right;">🟢 งบประมาณรวม (บาท)</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $grand_sport_total = 0;
        foreach ($sports as $sp):
          $id = (int)$sp['id'];
          $sub_total = (float)$sp['alloc_tranche_1'] + (float)$sp['alloc_tranche_2'] + (float)$sp['alloc_tranche_3'];
          $grand_sport_total += $sub_total;
        ?>
        <tr>
          <td><?= (int)$sp['sport_no'] ?></td>
          <td>
            <a href="athletes.php?sport_id=<?= $id ?>" style="font-weight:800; color:var(--green-dark);">
              <?= h($sp['sport_name']) ?>
            </a>
          </td>
          <td><?= h($sp['sport_type']) ?></td>
          <td>
            <strong><?= h($sp['manager_name']) ?><?= $sp['sub_manager_name'] ? ' / ' . h($sp['sub_manager_name']) : '' ?></strong>
            <div style="font-size:0.76rem; color:var(--muted);"><?= h($sp['manager_unit']) ?> · <?= h(mask_phone($sp['manager_phone'], is_admin_logged_in())) ?></div>
          </td>
          <td><?= h($sp['default_venue']) ?></td>
          <td style="text-align:center;">
            <a href="athletes.php?sport_id=<?= $id ?>" class="status-pill info"><?= (int)$sp['member_count'] ?> / <?= (int)$sp['target_athletes'] ?> คน</a>
          </td>
          <td style="text-align:right; font-weight:850; font-size:0.98rem; color:var(--green-dark);">
            <?= number_format($sub_total) ?> ฿
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#dcfce7; font-weight:850; color:var(--green-dark);">
          <td colspan="6" style="text-align:right;">รวมงบประมาณทั้ง 11 ชนิดกีฬา</td>
          <td style="text-align:right; font-size:1.08rem;"><?= number_format($grand_sport_total) ?> ฿</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
