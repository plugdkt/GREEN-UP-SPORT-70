<?php
require_once __DIR__ . '/config/auth.php';

$tx_list = $pdo->query("
    SELECT d.*, bc.category_name, s.sport_name
    FROM disbursements d
    JOIN budget_categories bc ON bc.id = d.category_id
    LEFT JOIN sports s ON s.id = d.sport_id
    ORDER BY d.id DESC
")->fetchAll();

$status_map = [
    'PENDING'  => ['⏳ รออนุมัติ', 'warn'],
    'APPROVED' => ['👍 อนุมัติแล้ว', 'info'],
    'PAID'     => ['💸 จ่ายเงินแล้ว', 'info'],
    'CLEARED'  => ['✅ เคลียร์บิลเรียบร้อย', ''],
];

$page_title   = 'UP SPORT 2027 · รายงานการเบิกจ่ายและเคลียร์บิล';
$page_crumb   = 'รายงานการเบิกจ่ายงบประมาณและเคลียร์บิล';
$page_heading = 'รายงานการเบิกจ่ายงบประมาณสีเขียว';
$page_desc    = 'แสดงรายการเบิกงบประมาณงวดที่ 1 และงวดที่ 2 ยอดใช้จ่ายจริงตามใบเสร็จ และสถานะการเคลียร์บิลของแต่ละฝ่าย';
$page_icon    = '🧾';

require_once __DIR__ . '/includes/header.php';
?>

<div class="white-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <div>
      <h2 style="font-size:1.2rem; font-weight:800;">รายการเบิกจ่ายงบประมาณทัพสีเขียวทั้งหมด</h2>
      <p style="font-size:0.84rem; color:var(--muted);">โปร่งใส ตรวจสอบได้ทุกใบเบิก</p>
    </div>
    <?php if (is_admin_logged_in()): ?>
      <a href="admin.php?tab=disbursements" class="btn-primary btn-sm">⚙️ สร้างใบเบิก/เคลียร์บิลในหน้า Admin ›</a>
    <?php endif; ?>
  </div>

  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th>เลขที่ใบเบิก</th>
          <th>วันที่</th>
          <th>ก้อนงบ</th>
          <th>ฝ่ายงาน / กีฬา</th>
          <th>ผู้ขอเบิก &amp; รายละเอียด</th>
          <th style="text-align:right;">ยอดขอเบิก</th>
          <th style="text-align:right;">ใช้จริงตามบิล</th>
          <th style="text-align:right;">เงินทอนคืน</th>
          <th>สถานะ</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sum_req = 0; $sum_spent = 0; $sum_ret = 0;
        foreach ($tx_list as $tx):
          $req   = (float)$tx['requested_amount'];
          $spent = (float)$tx['actual_spent_amount'];
          $ret   = (float)$tx['returned_amount'];
          $sum_req += $req; $sum_spent += $spent; $sum_ret += $ret;
          [$st_txt, $st_cls] = $status_map[$tx['tx_status']] ?? [$tx['tx_status'], 'warn'];
        ?>
        <tr>
          <td><strong><?= h($tx['doc_no']) ?></strong></td>
          <td><?= h($tx['tx_date']) ?></td>
          <td><span class="status-pill warn">ก้อนที่ <?= (int)$tx['tranche_no'] ?></span></td>
          <td>
            <strong><?= h($tx['category_name']) ?></strong>
            <?php if ($tx['sport_name']): ?>
              <div style="font-size:0.78rem; color:var(--green-dark);">กีฬา: <?= h($tx['sport_name']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= h($tx['requester_name']) ?></strong>
            <div style="font-size:0.8rem; color:var(--muted);"><?= h($tx['description']) ?></div>
          </td>
          <td style="text-align:right; font-weight:800;"><?= number_format($req) ?> ฿</td>
          <td style="text-align:right; font-weight:700; color:var(--green-dark);"><?= number_format($spent) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($ret) ?> ฿</td>
          <td><span class="status-pill <?= $st_cls ?>"><?= h($st_txt) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#dcfce7; font-weight:850; color:var(--green-dark);">
          <td colspan="5" style="text-align:right;">รวมยอดเบิกจ่ายทั้งหมด</td>
          <td style="text-align:right;"><?= number_format($sum_req) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($sum_spent) ?> ฿</td>
          <td style="text-align:right;"><?= number_format($sum_ret) ?> ฿</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
