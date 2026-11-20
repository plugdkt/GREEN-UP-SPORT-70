<?php
require_once __DIR__ . '/config/auth.php';

$categories = $pdo->query("SELECT * FROM budget_categories ORDER BY id ASC")->fetchAll();
$sports     = $pdo->query("SELECT * FROM sports ORDER BY sport_no ASC")->fetchAll();

// คำนวณผลรวมงบของทั้ง 11 ชนิดกีฬา (4.1 - 4.11) เพื่อใส่ในหัวข้อ 4. ฝ่ายประสานงานกีฬา อัตโนมัติ
$sport_sum_t1 = 0;
$sport_sum_t2 = 0;
$sport_sum_t3 = 0;
foreach ($sports as $sp) {
    $sport_sum_t1 += (float)$sp['alloc_tranche_1'];
    $sport_sum_t2 += (float)$sp['alloc_tranche_2'];
    $sport_sum_t3 += (float)$sp['alloc_tranche_3'];
}

$unit_sums = $pdo->query("
    SELECT
        SUM(staff_count) AS total_staff,
        SUM(employee_count) AS total_emp,
        SUM(ROUND(staff_count * (expected_participation_pct/100)) * 100) AS expected_headcount_money,
        SUM(exec_support_pledged) AS total_exec_pledged,
        SUM(exec_support_received) AS total_exec_received,
        SUM(headcount_received) AS total_headcount_received
    FROM units
")->fetch();

$total_athletes = $pdo->query("SELECT COUNT(*) FROM sport_members")->fetchColumn();

$t1_realistic = 80000;
$t2_realistic = 50000;
$t3_realistic = (float)($unit_sums['expected_headcount_money'] ?: 36200);
$grand_realistic = $t1_realistic + $t2_realistic + $t3_realistic;

// คำนวณยอดจัดสรรแล้วและยอดคงเหลือของแต่ละก้อน
$sum1 = 0; $sum2 = 0; $sum3 = 0;
foreach ($categories as $cat) {
    if ((int)$cat['id'] === 4) {
        $sum1 += $sport_sum_t1;
        $sum2 += $sport_sum_t2;
        $sum3 += $sport_sum_t3;
    } else {
        $sum1 += (float)$cat['alloc_tranche_1'];
        $sum2 += (float)$cat['alloc_tranche_2'];
        $sum3 += (float)$cat['alloc_tranche_3_realistic'];
    }
}
$sum_grand = $sum1 + $sum2 + $sum3;
$rem1 = $t1_realistic - $sum1;
$rem2 = $t2_realistic - $sum2;
$rem3 = $t3_realistic - $sum3;
$rem_grand = $grand_realistic - $sum_grand;

$page_title   = 'UP SPORT 2027 · รายงานภาพรวมงบประมาณ 3 ก้อน (GREEN TEAM)';
$page_crumb   = 'รายงานภาพรวมงบประมาณ 3 ก้อน';
$page_heading = 'รายงานบริหารงบประมาณ 3 ก้อน · ทัพสีเขียว';
$page_desc    = 'สรุปแผนการจัดสรรงบประมาณ 3 ก้อนของสีเขียว: ก้อนที่ 1 งบผู้บริหาร (80,000 ฿) · ก้อนที่ 2 งบ มพ. อาหารวันปิด (50,000 ฿) · ก้อนที่ 3 เงินรายหัวส่วนงาน';
$page_icon    = '📊';

require_once __DIR__ . '/includes/header.php';
?>

<div class="kpi-grid">
  <div class="kpi-card gold">
    <div class="kpi-label">🟡 ก้อนที่ 1: งบสนับสนุนผู้บริหาร (งวดแรก)</div>
    <div class="kpi-val"><?= number_format($t1_realistic) ?> ฿</div>
    <div class="kpi-sub">จัดสรรแล้ว: <strong><?= number_format($sum1) ?> ฿</strong> · คงเหลือ: <strong style="color:<?= $rem1 < 0 ? '#dc2626' : '#065f46' ?>;"><?= number_format($rem1) ?> ฿</strong></div>
  </div>

  <div class="kpi-card blue">
    <div class="kpi-label">🔵 ก้อนที่ 2: งบมหาวิทยาลัยพะเยา (ล็อกชื่อเบิก)</div>
    <div class="kpi-val"><?= number_format($t2_realistic) ?> ฿</div>
    <div class="kpi-sub">จัดสรรแล้ว: <strong><?= number_format($sum2) ?> ฿</strong> · คงเหลือ: <strong style="color:<?= $rem2 < 0 ? '#dc2626' : '#065f46' ?>;"><?= number_format($rem2) ?> ฿</strong></div>
  </div>

  <div class="kpi-card lime">
    <div class="kpi-label">🟣 ก้อนที่ 3: เงินรายหัว/โครงการคณะ</div>
    <div class="kpi-val"><?= number_format($t3_realistic) ?> ฿</div>
    <div class="kpi-sub">จัดสรรแล้ว: <strong><?= number_format($sum3) ?> ฿</strong> · คงเหลือ: <strong style="color:<?= $rem3 < 0 ? '#dc2626' : '#065f46' ?>;"><?= number_format($rem3) ?> ฿</strong></div>
  </div>

  <div class="kpi-card">
    <div class="kpi-label">🟢 ยอดงบประมาณคงเหลือสุทธิ (รวม 3 ก้อน)</div>
    <div class="kpi-val" style="color:<?= $rem_grand < 0 ? '#dc2626' : 'var(--green-dark)' ?>;"><?= number_format($rem_grand) ?> ฿</div>
    <div class="kpi-sub">จากงบตั้งต้น <strong><?= number_format($grand_realistic) ?> ฿</strong> (จัดสรรแล้ว <strong><?= number_format($sum_grand) ?> ฿</strong>)</div>
  </div>
</div>

<div class="white-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <div>
      <h2 style="font-size:1.2rem; font-weight:800;">รายงานการจัดสรรงบประมาณประจำสีเขียวทั้ง 3 ก้อน (แยกตามฝ่ายงาน 1–6 และชนิดกีฬา 4.1–4.11)</h2>
      <p style="font-size:0.84rem; color:var(--muted);">ก้อนที่ 1 งบผู้บริหาร · ก้อนที่ 2 งบ มพ. · ก้อนที่ 3 เงินรายหัวส่วนงาน</p>
    </div>
    <?php if (is_admin_logged_in()): ?>
      <a href="admin.php?tab=budget" class="btn-primary btn-sm">⚙️ แก้ไขงบในหน้า Admin ›</a>
    <?php else: ?>
      <span class="status-pill">รายงานสถานะล่าสุด</span>
    <?php endif; ?>
  </div>

  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th>ฝ่ายงาน / หมวดค่าใช้จ่าย</th>
          <th>ผู้รับผิดชอบหลัก</th>
          <th>รายละเอียด</th>
          <th style="text-align:right;">🟡 ก้อน 1: ผู้บริหาร</th>
          <th style="text-align:right;">🔵 ก้อน 2: งบ มพ.</th>
          <th style="text-align:right;">🟣 ก้อน 3: รายหัว</th>
          <th style="text-align:right;">🟢 รวมงบจัดสรร</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sum1 = 0; $sum2 = 0; $sum3 = 0;

        foreach ($categories as $cat):
          $cid = (int)$cat['id'];
          if ($cid === 4) {
              // 4. ฝ่ายประสานงานกีฬา (รวมยอดจาก 4.1 - 4.11)
              $c1 = $sport_sum_t1;
              $c2 = $sport_sum_t2;
              $c3 = $sport_sum_t3;
          } else {
              $c1 = (float)$cat['alloc_tranche_1'];
              $c2 = (float)$cat['alloc_tranche_2'];
              $c3 = (float)$cat['alloc_tranche_3_realistic'];
          }

          $row_tot = $c1 + $c2 + $c3;
          $sum1 += $c1; $sum2 += $c2; $sum3 += $c3;
        ?>
        <tr style="<?= $cid === 4 ? 'background:#f0fdf4;' : ($cid === 6 ? 'background:#fefce8;' : '') ?>">
          <td><strong><?= h($cat['category_name']) ?></strong></td>
          <td>
            <strong><?= h($cat['lead_person']) ?></strong>
            <div style="font-size:0.78rem; color:var(--muted);"><?= h($cat['responsible_unit']) ?></div>
          </td>
          <td style="font-size:0.84rem; color:var(--muted);"><?= h($cat['notes']) ?></td>
          <td style="text-align:right; font-weight:750;"><?= $c1 != 0 ? number_format($c1) . ' ฿' : '-' ?></td>
          <td style="text-align:right; font-weight:750; color:#0284c7;"><?= $c2 != 0 ? number_format($c2) . ' ฿' : '-' ?></td>
          <td style="text-align:right; font-weight:750; color:#6d28d9;"><?= $c3 != 0 ? number_format($c3) . ' ฿' : '-' ?></td>
          <td style="text-align:right; font-weight:850; color:var(--green-dark);"><?= number_format($row_tot) ?> ฿</td>
        </tr>

        <?php if ($cid === 4): ?>
          <!-- แสดงรายการย่อย 4.1 ถึง 4.11 ครบทุกชนิดกีฬา -->
          <?php foreach ($sports as $sp):
            $sno = (int)$sp['sport_no'];
            $sp1 = (float)$sp['alloc_tranche_1'];
            $sp2 = (float)$sp['alloc_tranche_2'];
            $sp3 = (float)$sp['alloc_tranche_3'];
            $sp_tot = $sp1 + $sp2 + $sp3;
          ?>
          <tr style="background:#fafdfb; font-size:0.84rem;">
            <td style="padding-left:32px; color:var(--green-dark);">
              <strong>4.<?= $sno ?> <?= h($sp['sport_name']) ?></strong>
              <span style="color:var(--muted);"> (<?= h($sp['sport_type']) ?>)</span>
            </td>
            <td>
              <?= h($sp['manager_name']) ?><?= $sp['sub_manager_name'] ? ' / ' . h($sp['sub_manager_name']) : '' ?>
              <span style="font-size:0.75rem; color:var(--muted);"> (<?= h($sp['manager_unit']) ?>)</span>
            </td>
            <td style="color:var(--muted); font-size:0.8rem;">สนาม: <?= h($sp['default_venue']) ?></td>
            <td style="text-align:right; color:var(--muted);"><?= $sp1 > 0 ? number_format($sp1) . ' ฿' : '-' ?></td>
            <td style="text-align:right; color:var(--muted);"><?= $sp2 > 0 ? number_format($sp2) . ' ฿' : '-' ?></td>
            <td style="text-align:right; color:var(--muted);"><?= $sp3 > 0 ? number_format($sp3) . ' ฿' : '-' ?></td>
            <td style="text-align:right; font-weight:750; color:var(--green-dark);"><?= number_format($sp_tot) ?> ฿</td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f0fdf4; font-weight:800; color:var(--ink);">
          <td colspan="3" style="text-align:right;">รวมงบประมาณที่จัดสรรแล้ว (ฝ่าย 1–6)</td>
          <td style="text-align:right;"><?= number_format($sum1) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($sum2) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($sum3) ?> ฿</td>
          <td style="text-align:right; font-size:1.02rem;"><?= number_format($sum_grand) ?> ฿</td>
        </tr>
        <tr style="background:#f8fafc; font-weight:750; color:var(--muted);">
          <td colspan="3" style="text-align:right;">วงเงินงบประมาณตั้งต้นทั้ง 3 ก้อน</td>
          <td style="text-align:right;"><?= number_format($t1_realistic) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($t2_realistic) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($t3_realistic) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($grand_realistic) ?> ฿</td>
        </tr>
        <tr style="background:#dcfce7; font-weight:900; color:var(--green-dark); border-top:2px solid var(--green);">
          <td colspan="3" style="text-align:right; font-size:1.02rem;">🟢 ยอดงบประมาณคงเหลือสุทธิ (ตั้งต้น − จัดสรรแล้ว)</td>
          <td style="text-align:right; color:<?= $rem1 < 0 ? '#dc2626' : '#065f46' ?>;"><?= number_format($rem1) ?> ฿</td>
          <td style="text-align:right; color:<?= $rem2 < 0 ? '#dc2626' : '#065f46' ?>;"><?= number_format($rem2) ?> ฿</td>
          <td style="text-align:right; color:<?= $rem3 < 0 ? '#dc2626' : '#065f46' ?>;"><?= number_format($rem3) ?> ฿</td>
          <td style="text-align:right; font-size:1.12rem; color:<?= $rem_grand < 0 ? '#dc2626' : '#065f46' ?>;"><?= number_format($rem_grand) ?> ฿</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
