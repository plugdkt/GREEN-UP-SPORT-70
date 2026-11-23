<?php
require_once __DIR__ . '/config/auth.php';

// ซ่อนรายการเครื่องดื่ม M21-M24 ชั่วคราวในหน้ารายงานเวชภัณฑ์ (ข้อมูลใน DB ยังคงอยู่ครบ)
$hidden_drink_codes = "'M21','M22','M23','M24'";
$med_items = $pdo->query("SELECT * FROM medical_items WHERE item_code NOT IN ({$hidden_drink_codes}) ORDER BY item_code ASC")->fetchAll();
$sports    = $pdo->query("SELECT * FROM sports ORDER BY sport_no ASC")->fetchAll();
$filter_sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;

$sql_req = "
    SELECT smr.qty, s.id AS sport_id, s.sport_no, s.sport_name, s.manager_name,
           mi.item_code, mi.item_name, mi.unit_name, mi.unit_price
    FROM sport_medical_requests smr
    JOIN sports s ON s.id = smr.sport_id
    JOIN medical_items mi ON mi.id = smr.medical_item_id
    WHERE smr.qty > 0
      AND mi.item_code NOT IN ({$hidden_drink_codes})
";
$params_req = [];
if ($filter_sport_id > 0) {
    $sql_req .= " AND s.id = :sid";
    $params_req[':sid'] = $filter_sport_id;
}
$sql_req .= " ORDER BY s.sport_no ASC, mi.item_code ASC";

$stmt_req = $pdo->prepare($sql_req);
$stmt_req->execute($params_req);
$sport_requests = $stmt_req->fetchAll();

// จัดกลุ่มตามชนิดกีฬา
$grouped_requests = [];
foreach ($sport_requests as $row) {
    $grouped_requests[$row['sport_id']]['info'] = [
        'sport_no'   => $row['sport_no'],
        'sport_name' => $row['sport_name'],
        'manager'    => $row['manager_name'],
    ];
    $grouped_requests[$row['sport_id']]['items'][] = $row;
}

$page_title   = 'UP SPORT 2027 · รายงานเวชภัณฑ์';
$page_crumb   = 'รายงานเวชภัณฑ์';
$page_heading = 'รายงานเวชภัณฑ์';
$page_desc    = 'ดูแลโดย คณะวิทยาศาสตร์การแพทย์ (ประธานสี & พี่ต่าย) สรุปยอดรวมและรายการเวชภัณฑ์แยกตามชนิดกีฬา';
$page_icon    = '🏥';

require_once __DIR__ . '/includes/header.php';
?>

<!-- ส่วนที่ 1: รายการเวชภัณฑ์รวมทั้งหมด -->
<div class="white-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <h2 style="font-size:1.2rem; font-weight:800;">1. รายการเวชภัณฑ์ประจำสีเขียว (ยอดรวมทั้งหมด)</h2>
    <?php if (is_admin_logged_in()): ?>
      <a href="admin.php?tab=medical" class="btn-primary btn-sm">⚙️ จัดการเวชภัณฑ์ในหน้า Admin ›</a>
    <?php endif; ?>
  </div>
  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th>รหัส</th>
          <th>หมวดหมู่</th>
          <th>รายการยา / เวชภัณฑ์ / สปอนเซอร์</th>
          <th>หน่วยนับ</th>
          <th style="text-align:center;">ยอดที่ต้องใช้</th>
          <th style="text-align:center;">มีแล้ว/ยืมได้</th>
          <th style="text-align:center;">ต้องซื้อเพิ่ม</th>
          <th style="text-align:right;">ราคา/หน่วย</th>
          <th style="text-align:right;">รวมงบซื้อ</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $total_med_cost = 0;
        foreach ($med_items as $m):
          $req   = (int)$m['total_required'];
          $stock = (int)$m['in_stock_or_borrowed'];
          $to_buy = max(0, $req - $stock);
          $price = (float)$m['unit_price'];
          $cost  = $to_buy * $price;
          $total_med_cost += $cost;
        ?>
        <tr>
          <td><strong><?= h($m['item_code']) ?></strong></td>
          <td><?= h($m['category_name']) ?></td>
          <td><strong><?= h($m['item_name']) ?></strong></td>
          <td><?= h($m['unit_name']) ?></td>
          <td style="text-align:center; font-weight:700;"><?= number_format($req) ?></td>
          <td style="text-align:center;"><?= number_format($stock) ?></td>
          <td style="text-align:center; font-weight:800; color:var(--green-dark);"><?= number_format($to_buy) ?></td>
          <td style="text-align:right;"><?= fmod($price, 1) > 0 ? number_format($price, 2) : number_format($price) ?> ฿</td>
          <td style="text-align:right; font-weight:850; color:var(--green-dark);"><?= fmod($cost, 1) > 0 ? number_format($cost, 2) : number_format($cost) ?> ฿</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#dcfce7; font-weight:850; color:var(--green-dark);">
          <td colspan="8" style="text-align:right;">รวมงบจัดซื้อเวชภัณฑ์ทั้งสิ้น</td>
          <td style="text-align:right; font-size:1.05rem;"><?= fmod($total_med_cost, 1) > 0 ? number_format($total_med_cost, 2) : number_format($total_med_cost) ?> ฿</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ส่วนที่ 2: รายงานเวชภัณฑ์แยกตามชนิดกีฬา -->
<div class="white-shell">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
    <div>
      <h2 style="font-size:1.2rem; font-weight:800;">2. รายงานความต้องการเวชภัณฑ์แยกตามชนิดกีฬา (11 ชนิดกีฬา)</h2>
      <p style="font-size:0.84rem; color:var(--muted);">แสดงรายละเอียดว่าแต่ละชนิดกีฬาขอเบิกยา เวชภัณฑ์ และเครื่องดื่มรายการใดบ้าง</p>
    </div>

    <form method="get" style="display:flex; align-items:center; gap:8px; margin:0;">
      <label style="font-size:0.84rem; font-weight:700; color:var(--muted);">เลือกดูชนิดกีฬา:</label>
      <select name="sport_id" class="form-control" style="width:auto; padding:5px 10px;" onchange="this.form.submit()">
        <option value="0">-- แสดงทุกชนิดกีฬา --</option>
        <?php foreach ($sports as $sp): ?>
          <option value="<?= (int)$sp['id'] ?>" <?= $filter_sport_id === (int)$sp['id'] ? 'selected' : '' ?>>
            <?= (int)$sp['sport_no'] ?>. <?= h($sp['sport_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (empty($grouped_requests)): ?>
    <div style="text-align:center; padding:28px; background:#f8fcf9; border-radius:14px; border:1px dashed var(--line); color:var(--muted);">
      ยังไม่มีข้อมูลการขอเบิกเวชภัณฑ์รายชนิดกีฬา (สามารถเข้าไปกรอกได้ที่เมนู <strong>⚙️ จัดการหลังบ้าน › จัดการเวชภัณฑ์</strong>)
    </div>
  <?php else: ?>
    <?php foreach ($grouped_requests as $sid => $grp):
      $info = $grp['info'];
      $items = $grp['items'];
      $grp_total = 0;
    ?>
      <div class="date-bar">
        <span>🏅 <?= (int)$info['sport_no'] ?>. <?= h($info['sport_name']) ?> (ผู้จัดการทีม: <?= h($info['manager']) ?>)</span>
        <span style="font-size:0.8rem; color:#a7f3d0;">จำนวน <?= count($items) ?> รายการ</span>
      </div>
      <div style="overflow-x:auto; margin-bottom:14px;">
        <table class="up-table">
          <thead>
            <tr>
              <th>รหัส</th>
              <th>รายการยา / เวชภัณฑ์ / เครื่องดื่ม</th>
              <th style="text-align:center;">จำนวนที่ขอใช้</th>
              <th>หน่วยนับ</th>
              <th style="text-align:right;">ราคา/หน่วย</th>
              <th style="text-align:right;">รวมเป็นเงิน</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it):
              $q = (int)$it['qty'];
              $pr = (float)$it['unit_price'];
              $sub = $q * $pr;
              $grp_total += $sub;
            ?>
            <tr>
              <td><strong><?= h($it['item_code']) ?></strong></td>
              <td><strong><?= h($it['item_name']) ?></strong></td>
              <td style="text-align:center; font-weight:800; color:var(--green-dark);"><?= number_format($q) ?></td>
              <td><?= h($it['unit_name']) ?></td>
              <td style="text-align:right;"><?= fmod($pr, 1) > 0 ? number_format($pr, 2) : number_format($pr) ?> ฿</td>
              <td style="text-align:right; font-weight:750;"><?= fmod($sub, 1) > 0 ? number_format($sub, 2) : number_format($sub) ?> ฿</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:#f0fdf4; font-weight:800; color:var(--green-dark);">
              <td colspan="5" style="text-align:right;">รวมงบเวชภัณฑ์กีฬา <?= h($info['sport_name']) ?></td>
              <td style="text-align:right;"><?= fmod($grp_total, 1) > 0 ? number_format($grp_total, 2) : number_format($grp_total) ?> ฿</td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
