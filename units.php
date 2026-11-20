<?php
require_once __DIR__ . '/config/auth.php';

$units = $pdo->query("SELECT * FROM units ORDER BY id ASC")->fetchAll();

$status_map = [
    'PENDING' => ['⏳ รอดำเนินการ (ภายใน 30 พ.ย. 69)', 'warn'],
    'PARTIAL' => ['🌗 ส่งบางส่วนแล้ว', 'info'],
    'PAID'    => ['✅ ส่งครบเรียบร้อย', ''],
];

$page_title   = 'UP SPORT 2027 · รายงานการส่งเงินสมทบ 9 ส่วนงาน';
$page_crumb   = 'รายงานงบผู้บริหารและเงินรายหัว 9 ส่วนงาน';
$page_heading = 'รายงานงบสมทบ 9 ส่วนงานสีเขียว';
$page_desc    = 'สรุปยอดงบสนับสนุนผู้บริหารและเงินสมทบรายหัว/โครงการซัพพอร์ตของแต่ละส่วนงาน (กำหนดนำส่งภายใน 30 พฤศจิกายน 2569)';
$page_icon    = '🏢';

require_once __DIR__ . '/includes/header.php';
?>

<div class="white-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <div>
      <h2 style="font-size:1.2rem; font-weight:800;">ตารางรายงานสถานะการส่งเงินสมทบ 9 ส่วนงานสีเขียว (เดดไลน์ 30 พ.ย. 2569)</h2>
      <p style="font-size:0.84rem; color:var(--muted);">ผู้รับผิดชอบการเงินสีเขียว: พี่โอ๊ต (กองคลัง) โทร. 089-2619268</p>
    </div>
    <?php if (is_admin_logged_in()): ?>
      <a href="admin.php?tab=units" class="btn-primary btn-sm">⚙️ อัปเดตยอดรับเงินในหน้า Admin ›</a>
    <?php endif; ?>
  </div>

  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th>ที่</th>
          <th>ส่วนงาน / สายงานในสังกัดสีเขียว</th>
          <th>ผู้ประสานงาน &amp; เบอร์โทร</th>
          <th style="text-align:center;">พนง./ลูกจ้าง</th>
          <th style="text-align:right;">🟣 งบสมทบรายหัว</th>
          <th style="text-align:right;">🟡 งบผู้บริหาร</th>
          <th style="text-align:right;">รับงบผู้บริหารแล้ว</th>
          <th style="text-align:right;">รับเงินรายหัวแล้ว</th>
          <th>สถานะ</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sum_staff = 0; $sum_emp = 0; $sum_full_hc = 0;
        $sum_exec_pledged = 0; $sum_exec_rec = 0; $sum_hc_rec = 0;
        foreach ($units as $idx => $u):
          $id = (int)$u['id'];
          $staff = (int)$u['staff_count'];
          $emp   = (int)$u['employee_count'];
          $full_hc = $staff * 100;
          $exec_p = (float)$u['exec_support_pledged'];
          $exec_r = (float)$u['exec_support_received'];
          $hc_r   = (float)$u['headcount_received'];

          $sum_staff += $staff; $sum_emp += $emp;
          $sum_full_hc += $full_hc;
          $sum_exec_pledged += $exec_p; $sum_exec_rec += $exec_r; $sum_hc_rec += $hc_r;
          [$st_txt, $st_cls] = $status_map[$u['payment_status']] ?? ['รอดำเนินการ', 'warn'];
        ?>
        <tr>
          <td><?= $idx + 1 ?></td>
          <td><strong><?= h($u['unit_name']) ?></strong></td>
          <td>
            <strong><?= h($u['coordinator_name']) ?></strong>
            <div style="font-size:0.78rem; color:var(--green-dark);"><?= h($u['coordinator_phone']) ?></div>
          </td>
          <td style="text-align:center;"><?= $staff ?> / <?= $emp ?></td>
          <td style="text-align:right; font-weight:750; color:#6d28d9;"><?= number_format($full_hc) ?> ฿</td>
          <td style="text-align:right; font-weight:750; color:#b45309;"><?= number_format($exec_p) ?> ฿</td>
          <td style="text-align:right; font-weight:800; color:var(--green-dark);"><?= number_format($exec_r) ?> ฿</td>
          <td style="text-align:right; font-weight:800; color:var(--green-dark);"><?= number_format($hc_r) ?> ฿</td>
          <td><span class="status-pill <?= $st_cls ?>"><?= h($st_txt) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#dcfce7; font-weight:850; color:var(--green-dark);">
          <td colspan="3" style="text-align:right;">รวมทั้งสิ้น (บุคลากร <?= $sum_staff + $sum_emp ?> คน)</td>
          <td style="text-align:center;"><?= $sum_staff ?> / <?= $sum_emp ?></td>
          <td style="text-align:right;"><?= number_format($sum_full_hc) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($sum_exec_pledged) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($sum_exec_rec) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($sum_hc_rec) ?> ฿</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
