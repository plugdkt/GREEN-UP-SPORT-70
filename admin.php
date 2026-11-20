<?php
require_once __DIR__ . '/config/auth.php';
require_admin();

$active_tab   = $_GET['tab'] ?? ($_POST['tab'] ?? 'budget');
$filter_sport = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : (isset($_POST['sport_id']) ? (int)$_POST['sport_id'] : 0);
$msg = '';

// ============================================================================
// HANDLE POST ACTIONS FROM ADMIN FORMS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        http_response_code(403);
        die('คำขอไม่ถูกต้อง (Invalid CSRF Token) กรุณาย้อนกลับและรีเฟรชหน้าเว็บ');
    }
    $act = $_POST['action'];

    // 1. Update 3-Tranche Category & Sports Allocations
    if ($act === 'save_budget_categories') {
        $stmt = $pdo->prepare("
            UPDATE budget_categories
            SET lead_person = :lead,
                alloc_tranche_1 = :t1,
                alloc_tranche_2 = :t2,
                alloc_tranche_3_realistic = :t3
            WHERE id = :id
        ");
        if (!empty($_POST['cat'])) {
            foreach ($_POST['cat'] as $id => $v) {
                $stmt->execute([
                    ':lead' => trim($v['lead'] ?? ''),
                    ':t1'   => (float)($v['t1'] ?? 0),
                    ':t2'   => (float)($v['t2'] ?? 0),
                    ':t3'   => (float)($v['t3'] ?? 0),
                    ':id'   => (int)$id,
                ]);
            }
        }

        if (!empty($_POST['sp_budget'])) {
            $stmt_sp = $pdo->prepare("
                UPDATE sports
                SET manager_name    = :mgr,
                    manager_phone   = :phone,
                    default_venue   = :venue,
                    target_athletes = :target,
                    alloc_tranche_1 = :t1,
                    alloc_tranche_2 = :t2,
                    alloc_tranche_3 = :t3
                WHERE id = :id
            ");
            foreach ($_POST['sp_budget'] as $sid => $sv) {
                $stmt_sp->execute([
                    ':mgr'    => trim($sv['mgr'] ?? ''),
                    ':phone'  => trim($sv['phone'] ?? ''),
                    ':venue'  => trim($sv['venue'] ?? ''),
                    ':target' => (int)($sv['target'] ?? 15),
                    ':t1'     => (float)($sv['t1'] ?? 0),
                    ':t2'     => (float)($sv['t2'] ?? 0),
                    ':t3'     => (float)($sv['t3'] ?? 0),
                    ':id'     => (int)$sid,
                ]);
            }
        }

        $active_tab = 'budget';
        $msg = 'บันทึกการจัดสรรงบประมาณทั้ง 6 ฝ่ายงาน และ 11 ชนิดกีฬา (4.1–4.11) เรียบร้อยแล้ว!';
    }

    // 2.1 Update 9 Units Staff/Employee & Coordinators
    elseif ($act === 'save_units_staff') {
        $stmt = $pdo->prepare("
            UPDATE units
            SET coordinator_name = :coord,
                coordinator_phone = :phone,
                staff_count       = :staff,
                employee_count    = :emp
            WHERE id = :id
        ");
        foreach ($_POST['u'] as $id => $v) {
            $stmt->execute([
                ':coord' => trim($v['coord'] ?? ''),
                ':phone' => trim($v['phone'] ?? ''),
                ':staff' => (int)($v['staff'] ?? 0),
                ':emp'   => (int)($v['emp'] ?? 0),
                ':id'    => (int)$id,
            ]);
        }
        $active_tab = 'units';
        $msg = 'บันทึกข้อมูลจำนวนพนักงาน ลูกจ้าง และผู้ประสานงาน 9 ส่วนงานเรียบร้อยแล้ว!';
    }

    // 2.2 Update 9 Units Revenue & Payment Status
    elseif ($act === 'save_units_payments') {
        $stmt = $pdo->prepare("
            UPDATE units
            SET exec_support_pledged  = :exec_pledged,
                exec_support_received = :exec_rec,
                headcount_received    = :head_rec,
                payment_status        = :status
            WHERE id = :id
        ");
        foreach ($_POST['u'] as $id => $v) {
            $stmt->execute([
                ':exec_pledged' => (float)($v['exec_pledged'] ?? 0),
                ':exec_rec'     => (float)($v['exec_rec'] ?? 0),
                ':head_rec'     => (float)($v['head_rec'] ?? 0),
                ':status'       => $v['status'] ?? 'PENDING',
                ':id'           => (int)$id,
            ]);
        }
        $active_tab = 'units';
        $msg = 'บันทึกยอดรับเงินสมทบและสถานะการส่งเงิน 9 ส่วนงานเรียบร้อยแล้ว!';
    }

    // 3. Update 11 Sports Budget & Manager Info
    elseif ($act === 'save_sports') {
        $stmt = $pdo->prepare("
            UPDATE sports
            SET manager_name           = :mgr,
                manager_phone          = :phone,
                default_venue          = :venue,
                target_athletes        = :target,
                practice_days          = :p_days,
                practice_cost_per_day  = :p_cost,
                match_days             = :m_days,
                match_cost_per_day     = :m_cost,
                extra_consumables_cost = :extra
            WHERE id = :id
        ");
        foreach ($_POST['sp'] as $id => $v) {
            $stmt->execute([
                ':mgr'    => trim($v['mgr'] ?? ''),
                ':phone'  => trim($v['phone'] ?? ''),
                ':venue'  => trim($v['venue'] ?? ''),
                ':target' => (int)($v['target'] ?? 15),
                ':p_days' => (int)($v['p_days'] ?? 0),
                ':p_cost' => (float)($v['p_cost'] ?? 0),
                ':m_days' => (int)($v['m_days'] ?? 0),
                ':m_cost' => (float)($v['m_cost'] ?? 0),
                ':extra'  => (float)($v['extra'] ?? 0),
                ':id'     => (int)$id,
            ]);
        }
        $active_tab = 'sports';
        $msg = 'บันทึกข้อมูลผู้จัดการทีมและงบประมาณทั้ง 11 ชนิดกีฬาเรียบร้อยแล้ว!';
    }

    // 4. Add Match Schedule
    elseif ($act === 'add_match') {
        $stmt = $pdo->prepare("
            INSERT INTO match_schedules (sport_id, match_date, start_time, end_time, team_division, opponent_color, venue, round_name, water_ice_budget, sponsor_bottles_needed, medical_bag_code)
            VALUES (:sport_id, :match_date, :start_time, :end_time, :team_div, :opp, :venue, 'ตามกำหนด', :water_budget, :sponsor, :bag)
        ");
        $stmt->execute([
            ':sport_id'     => (int)$_POST['sport_id'],
            ':match_date'   => $_POST['match_date'],
            ':start_time'   => $_POST['start_time'],
            ':end_time'     => $_POST['end_time'] ?: null,
            ':team_div'     => trim($_POST['team_division']),
            ':opp'          => trim($_POST['opponent_color']),
            ':venue'        => trim($_POST['venue']),
            ':water_budget' => (float)$_POST['water_ice_budget'],
            ':sponsor'      => (int)$_POST['sponsor_bottles_needed'],
            ':bag'          => $_POST['medical_bag_code'],
        ]);
        $active_tab = 'matches';
        $msg = 'เพิ่มโปรแกรมการแข่งขันใหม่เรียบร้อยแล้ว!';
    }

    // 5. Delete Match Schedule
    elseif ($act === 'delete_match') {
        $stmt = $pdo->prepare("DELETE FROM match_schedules WHERE id = :id");
        $stmt->execute([':id' => (int)$_POST['match_id']]);
        $active_tab = 'matches';
        $msg = 'ลบรายการแข่งขันเรียบร้อยแล้ว!';
    }

    // 6. Add Athlete / Team Manager
    elseif ($act === 'add_member') {
        $stmt = $pdo->prepare("
            INSERT INTO sport_members (sport_id, unit_id, member_role, title_prefix, full_name, nickname, gender, phone, competition_category)
            VALUES (:sport_id, :unit_id, :role, :prefix, :full_name, :nickname, :gender, :phone, :cat)
        ");
        $stmt->execute([
            ':sport_id'  => (int)$_POST['sport_id'],
            ':unit_id'   => (int)$_POST['unit_id'],
            ':role'      => $_POST['member_role'],
            ':prefix'    => trim($_POST['title_prefix']),
            ':full_name' => trim($_POST['full_name']),
            ':nickname'  => trim($_POST['nickname']),
            ':gender'    => $_POST['gender'],
            ':phone'     => trim($_POST['phone']),
            ':cat'       => trim($_POST['competition_category']),
        ]);
        $active_tab = 'athletes';
        $filter_sport = (int)$_POST['sport_id'];
        $msg = 'เพิ่มรายชื่อนักกีฬา/ผู้จัดการทีมเรียบร้อยแล้ว!';
    }

    // 7. Delete Athlete / Team Manager
    elseif ($act === 'delete_member') {
        $stmt = $pdo->prepare("DELETE FROM sport_members WHERE id = :id");
        $stmt->execute([':id' => (int)$_POST['member_id']]);
        $active_tab = 'athletes';
        $msg = 'ลบรายชื่อเรียบร้อยแล้ว!';
    }

    // 8.1 Save Per-Sport Medical Request & Auto-Sum into medical_items.total_required
    elseif ($act === 'save_sport_medical') {
        $med_sport_id = (int)$_POST['med_sport_id'];
        $stmt = $pdo->prepare("
            INSERT INTO sport_medical_requests (sport_id, medical_item_id, qty)
            VALUES (:sport_id, :med_id, :qty)
            ON DUPLICATE KEY UPDATE qty = VALUES(qty)
        ");
        foreach ($_POST['req_qty'] as $med_id => $qty) {
            $stmt->execute([
                ':sport_id' => $med_sport_id,
                ':med_id'   => (int)$med_id,
                ':qty'      => max(0, (int)$qty),
            ]);
        }

        // Auto-sum total_required across all sports into medical_items
        $pdo->exec("
            UPDATE medical_items mi
            SET mi.total_required = COALESCE((
                SELECT SUM(smr.qty)
                FROM sport_medical_requests smr
                WHERE smr.medical_item_id = mi.id
            ), 0)
        ");

        $active_tab = 'medical';
        $_GET['med_sport_id'] = $med_sport_id;
        $msg = 'บันทึกความต้องการเวชภัณฑ์ของชนิดกีฬานี้ และรวมยอดเข้าคลังกลางอัตโนมัติเรียบร้อยแล้ว!';
    }

    // 8.2 Save Medical & Sponsor Inventory
    elseif ($act === 'save_medical') {
        $stmt = $pdo->prepare("
            UPDATE medical_items
            SET total_required       = :req,
                in_stock_or_borrowed = :stock,
                unit_price           = :price
            WHERE id = :id
        ");
        foreach ($_POST['med'] as $id => $v) {
            $stmt->execute([
                ':req'   => (int)($v['req'] ?? 0),
                ':stock' => (int)($v['stock'] ?? 0),
                ':price' => (float)($v['price'] ?? 0),
                ':id'    => (int)$id,
            ]);
        }
        $active_tab = 'medical';
        $msg = 'อัปเดตคลังยาและขวดสปอนเซอร์เรียบร้อยแล้ว!';
    }

    // 9. Create & Update Disbursements
    elseif ($act === 'create_disbursement') {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM disbursements")->fetchColumn() + 1;
        $doc_no = sprintf('GRN-2027-%03d', $count + 10);
        $stmt = $pdo->prepare("
            INSERT INTO disbursements (doc_no, tx_date, tranche_no, category_id, sport_id, requester_name, description, requested_amount, tx_status)
            VALUES (:doc_no, :tx_date, :tranche, :cat_id, :sport_id, :req_name, :desc, :amount, 'APPROVED')
        ");
        $stmt->execute([
            ':doc_no'   => $doc_no,
            ':tx_date'  => $_POST['tx_date'],
            ':tranche'  => (int)$_POST['tranche_no'],
            ':cat_id'   => (int)$_POST['category_id'],
            ':sport_id' => !empty($_POST['sport_id']) ? (int)$_POST['sport_id'] : null,
            ':req_name' => trim($_POST['requester_name']),
            ':desc'     => trim($_POST['description']),
            ':amount'   => (float)$_POST['requested_amount'],
        ]);
        $active_tab = 'disbursements';
        $msg = "สร้างใบเบิกงบเลขที่ {$doc_no} เรียบร้อยแล้ว!";
    }
    elseif ($act === 'update_disbursement') {
        $stmt = $pdo->prepare("
            UPDATE disbursements
            SET actual_spent_amount = :spent,
                returned_amount     = :ret,
                tx_status           = :status
            WHERE id = :id
        ");
        foreach ($_POST['tx'] as $id => $v) {
            $stmt->execute([
                ':spent'  => (float)($v['spent'] ?? 0),
                ':ret'    => (float)($v['ret'] ?? 0),
                ':status' => $v['status'],
                ':id'     => (int)$id,
            ]);
        }
        $active_tab = 'disbursements';
        $msg = 'บันทึกยอดใช้จริงและสถานะการเคลียร์บิลเรียบร้อยแล้ว!';
    }
    elseif ($act === 'delete_disbursement') {
        $stmt = $pdo->prepare("DELETE FROM disbursements WHERE id = :id");
        $stmt->execute([':id' => (int)$_POST['tx_id']]);
        $active_tab = 'disbursements';
        $msg = 'ลบรายการใบเบิกงบประมาณเรียบร้อยแล้ว!';
    }
}

// ============================================================================
// FETCH DATA FOR ADMIN VIEWS
// ============================================================================
$categories = $pdo->query("SELECT * FROM budget_categories ORDER BY id ASC")->fetchAll();
$units      = $pdo->query("SELECT * FROM units ORDER BY id ASC")->fetchAll();
$sports     = $pdo->query("
    SELECT s.*,
           (SELECT COUNT(*) FROM sport_members sm WHERE sm.sport_id = s.id) AS member_count
    FROM sports s
    ORDER BY s.sport_no ASC
")->fetchAll();
$schedules  = $pdo->query("
    SELECT ms.*, s.sport_name
    FROM match_schedules ms
    JOIN sports s ON s.id = ms.sport_id
    ORDER BY ms.match_date ASC, ms.start_time ASC
")->fetchAll();
$med_items  = $pdo->query("SELECT * FROM medical_items ORDER BY item_code ASC")->fetchAll();
$tx_list    = $pdo->query("
    SELECT d.*, bc.category_name, s.sport_name
    FROM disbursements d
    JOIN budget_categories bc ON bc.id = d.category_id
    LEFT JOIN sports s ON s.id = d.sport_id
    ORDER BY d.id DESC
")->fetchAll();

$sql_mem = "
    SELECT sm.*, s.sport_no, s.sport_name, u.unit_name
    FROM sport_members sm
    JOIN sports s ON s.id = sm.sport_id
    JOIN units u ON u.id = sm.unit_id
";
$params_mem = [];
if ($filter_sport > 0) {
    $sql_mem .= " WHERE sm.sport_id = :sid";
    $params_mem[':sid'] = $filter_sport;
}
$sql_mem .= " ORDER BY s.sport_no ASC, FIELD(sm.member_role, 'MANAGER', 'COACH', 'COORDINATOR', 'ATHLETE', 'SUB_ATHLETE'), sm.id ASC";
$stmt_m = $pdo->prepare($sql_mem);
$stmt_m->execute($params_mem);
$members = $stmt_m->fetchAll();

$page_title   = 'UP SPORT 2027 · ระบบจัดการหลังบ้าน (Admin Control Center)';
$page_crumb   = 'ระบบบริหารจัดการข้อมูลหลังบ้าน (Admin)';
$page_heading = 'ศูนย์ควบคุมหลังบ้าน (Admin Control Center)';
$page_desc    = 'บริหารจัดการงบประมาณ 3 ก้อน, อัปเดตยอดส่งเงิน 9 ส่วนงาน, ตั้งค่างบกีฬาและตารางแข่ง, เพิ่มรายชื่อนักกีฬา, จัดการคลังยา และอนุมัติใบเบิก';
$page_icon    = '⚙️';

require_once __DIR__ . '/includes/header.php';
?>

<style>
  .admin-nav-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    background: #fff;
    padding: 12px;
    border-radius: 16px;
    border: 2px solid var(--green);
    box-shadow: var(--shadow);
    margin-bottom: 20px;
  }
  .admin-tab-btn {
    padding: 10px 16px;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: #f8fcf9;
    color: var(--ink);
    font-weight: 750;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.15s;
  }
  .admin-tab-btn:hover { background: #ecfdf5; border-color: var(--green); }
  .admin-tab-btn.active {
    background: linear-gradient(135deg, #064e3b, #059669);
    color: #fff;
    border-color: #047857;
  }
</style>

<?php if ($msg): ?>
  <div class="flash-ok">✅ <?= h($msg) ?></div>
<?php endif; ?>

<!-- Admin Sub-navigation Tabs -->
<div class="admin-nav-tabs">
  <a href="admin.php?tab=budget" class="admin-tab-btn <?= $active_tab === 'budget' ? 'active' : '' ?>">💰 1. จัดการงบ 3 ก้อน &amp; 11 กีฬา</a>
  <a href="admin.php?tab=units" class="admin-tab-btn <?= $active_tab === 'units' ? 'active' : '' ?>">🏢 2. บันทึกเงิน 9 ส่วนงาน</a>
  <a href="admin.php?tab=matches" class="admin-tab-btn <?= $active_tab === 'matches' ? 'active' : '' ?>">📅 3. จัดการตารางแข่งขัน</a>
  <a href="admin.php?tab=athletes" class="admin-tab-btn <?= $active_tab === 'athletes' ? 'active' : '' ?>">👟 4. จัดการรายชื่อนักกีฬา</a>
  <a href="admin.php?tab=medical" class="admin-tab-btn <?= $active_tab === 'medical' ? 'active' : '' ?>">🏥 5. จัดการเวชภัณฑ์</a>
  <a href="admin.php?tab=disbursements" class="admin-tab-btn <?= $active_tab === 'disbursements' ? 'active' : '' ?>">🧾 6. อนุมัติเบิกจ่าย &amp; เคลียร์บิล</a>
</div>

<!-- ==========================================================================
     ADMIN TAB 1: จัดการงบ 3 ก้อน
     ========================================================================== -->
<?php if ($active_tab === 'budget'):
  $sport_sum_t1 = array_sum(array_column($sports, 'alloc_tranche_1'));
  $sport_sum_t2 = array_sum(array_column($sports, 'alloc_tranche_2'));
  $sport_sum_t3 = array_sum(array_column($sports, 'alloc_tranche_3'));
?>
<div class="white-shell">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_budget_categories" />
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h2 style="font-size:1.2rem; font-weight:800;">⚙️ ตั้งค่าการจัดสรรงบประมาณ 3 ก้อน (ฝ่ายงาน 1–6 และชนิดกีฬา 4.1–4.11)</h2>
      <button type="submit" class="btn-primary">💾 บันทึกการเปลี่ยนแปลง</button>
    </div>
    <div style="overflow-x:auto;">
      <table class="up-table">
        <thead>
          <tr>
            <th>ฝ่ายงาน / หมวดค่าใช้จ่าย</th>
            <th>ชื่อผู้รับผิดชอบหลัก</th>
            <th style="text-align:right;">🟡 ก้อน 1: ผู้บริหาร (฿)</th>
            <th style="text-align:right;">🔵 ก้อน 2: งบ มพ. (฿)</th>
            <th style="text-align:right;">🟣 ก้อน 3: รายหัว (฿)</th>
            <th style="text-align:right;">รวมงบจัดสรร</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat):
            $id = (int)$cat['id'];
            if ($id === 4) {
                $c1 = $sport_sum_t1; $c2 = $sport_sum_t2; $c3 = $sport_sum_t3;
            } else {
                $c1 = (float)$cat['alloc_tranche_1'];
                $c2 = (float)$cat['alloc_tranche_2'];
                $c3 = (float)$cat['alloc_tranche_3_realistic'];
            }
            $tot = $c1 + $c2 + $c3;
          ?>
          <tr style="<?= $id === 4 ? 'background:#f0fdf4;' : ($id === 6 ? 'background:#fefce8;' : '') ?>">
            <td><strong><?= h($cat['category_name']) ?></strong><div style="font-size:0.78rem; color:var(--muted);"><?= h($cat['responsible_unit']) ?></div></td>
            <td><input type="text" class="form-control" name="cat[<?= $id ?>][lead]" value="<?= h($cat['lead_person']) ?>" /></td>
            <?php if ($id === 4): ?>
              <td style="text-align:right; font-weight:800; color:var(--green-dark);">
                <?= number_format($c1) ?> ฿
                <input type="hidden" name="cat[<?= $id ?>][t1]" value="<?= (int)$c1 ?>" />
              </td>
              <td style="text-align:right; font-weight:800; color:var(--green-dark);">
                <?= number_format($c2) ?> ฿
                <input type="hidden" name="cat[<?= $id ?>][t2]" value="<?= (int)$c2 ?>" />
              </td>
              <td style="text-align:right; font-weight:800; color:var(--green-dark);">
                <?= number_format($c3) ?> ฿
                <input type="hidden" name="cat[<?= $id ?>][t3]" value="<?= (int)$c3 ?>" />
              </td>
            <?php else: ?>
              <td style="text-align:right;"><input type="number" step="100" class="num-input" name="cat[<?= $id ?>][t1]" value="<?= (int)$c1 ?>" /></td>
              <td style="text-align:right;"><input type="number" step="100" class="num-input" name="cat[<?= $id ?>][t2]" value="<?= (int)$c2 ?>" /></td>
              <td style="text-align:right;"><input type="number" step="100" class="num-input" name="cat[<?= $id ?>][t3]" value="<?= (int)$c3 ?>" /></td>
            <?php endif; ?>
            <td style="text-align:right; font-weight:850; color:var(--green-dark);"><?= number_format($tot) ?> ฿</td>
          </tr>

          <?php if ($id === 4): ?>
            <?php foreach ($sports as $sp):
              $sid = (int)$sp['id'];
              $sno = (int)$sp['sport_no'];
              $sp1 = (float)$sp['alloc_tranche_1'];
              $sp2 = (float)$sp['alloc_tranche_2'];
              $sp3 = (float)$sp['alloc_tranche_3'];
              $sp_tot = $sp1 + $sp2 + $sp3;
            ?>
            <tr style="background:#fafdfb;">
              <td style="padding-left:28px;">
                <strong>4.<?= $sno ?> <?= h($sp['sport_name']) ?></strong>
                <div style="font-size:0.75rem; color:var(--muted);"><?= h($sp['manager_unit']) ?></div>
              </td>
              <td>
                <div style="display:flex; gap:6px; align-items:center;">
                  <input type="text" class="form-control" placeholder="ชื่อ ผจก.ทีม" name="sp_budget[<?= $sid ?>][mgr]" value="<?= h($sp['manager_name']) ?>" />
                  <input type="text" class="form-control" style="width:125px;" placeholder="เบอร์โทร" name="sp_budget[<?= $sid ?>][phone]" value="<?= h($sp['manager_phone']) ?>" />
                  <input type="hidden" name="sp_budget[<?= $sid ?>][venue]" value="<?= h($sp['default_venue']) ?>" />
                  <input type="hidden" name="sp_budget[<?= $sid ?>][target]" value="<?= (int)$sp['target_athletes'] ?>" />
                </div>
              </td>
              <td style="text-align:right;"><input type="number" step="100" class="num-input" name="sp_budget[<?= $sid ?>][t1]" value="<?= (int)$sp1 ?>" /></td>
              <td style="text-align:right;"><input type="number" step="100" class="num-input" name="sp_budget[<?= $sid ?>][t2]" value="<?= (int)$sp2 ?>" /></td>
              <td style="text-align:right;"><input type="number" step="100" class="num-input" name="sp_budget[<?= $sid ?>][t3]" value="<?= (int)$sp3 ?>" /></td>
              <td style="text-align:right; font-weight:750; color:var(--green-dark);"><?= number_format($sp_tot) ?> ฿</td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>

          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ==========================================================================
     ADMIN TAB 2: แยก 2 ตาราง (1. จำนวน พนง./ลูกจ้าง & ผู้ประสานงาน | 2. ยอดรับเงินสมทบ)
     ========================================================================== -->
<?php if ($active_tab === 'units'): ?>
<div class="white-shell" style="margin-bottom:22px;">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_units_staff" />
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
      <div>
        <h2 style="font-size:1.2rem; font-weight:800;">👥 ตารางที่ 1: จัดการจำนวน พนง. / ลูกจ้าง และผู้ประสานงาน 9 ส่วนงาน</h2>
        <p style="font-size:0.84rem; color:var(--muted);">แก้ไขจำนวนพนักงาน (คิดรายหัว 100 บ./คน) จำนวนลูกจ้าง และข้อมูลติดต่อผู้ประสานงาน</p>
      </div>
      <button type="submit" class="btn-primary">💾 บันทึกจำนวนบุคลากร</button>
    </div>
    <div style="overflow-x:auto;">
      <table class="up-table">
        <thead>
          <tr>
            <th>ที่</th>
            <th>ส่วนงาน</th>
            <th>ผู้ประสานงาน</th>
            <th>เบอร์โทร</th>
            <th style="text-align:center;">จำนวนพนักงาน (คน)</th>
            <th style="text-align:center;">จำนวนลูกจ้าง (คน)</th>
            <th style="text-align:center;">รวมบุคลากร (คน)</th>
            <th style="text-align:right;">🟣 งบรายหัว (100 บ./พนง.)</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sum_s = 0; $sum_e = 0;
          foreach ($units as $idx => $u):
            $id = (int)$u['id'];
            $staff = (int)$u['staff_count'];
            $emp   = (int)$u['employee_count'];
            $sum_s += $staff; $sum_e += $emp;
          ?>
          <tr>
            <td><?= $idx + 1 ?></td>
            <td><strong><?= h($u['unit_name']) ?></strong></td>
            <td><input type="text" class="form-control" style="width:150px;" name="u[<?= $id ?>][coord]" value="<?= h($u['coordinator_name']) ?>" /></td>
            <td><input type="text" class="form-control" style="width:135px;" name="u[<?= $id ?>][phone]" value="<?= h($u['coordinator_phone']) ?>" /></td>
            <td style="text-align:center;">
              <input type="number" class="num-input" style="width:80px; text-align:center;" name="u[<?= $id ?>][staff]" value="<?= $staff ?>" />
            </td>
            <td style="text-align:center;">
              <input type="number" class="num-input" style="width:80px; text-align:center;" name="u[<?= $id ?>][emp]" value="<?= $emp ?>" />
            </td>
            <td style="text-align:center; font-weight:750;"><?= number_format($staff + $emp) ?> คน</td>
            <td style="text-align:right; font-weight:750; color:#6d28d9;"><?= number_format($staff * 100) ?> ฿</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#dcfce7; font-weight:850; color:var(--green-dark);">
            <td colspan="4" style="text-align:right;">รวมบุคลากรทั้งหมด</td>
            <td style="text-align:center;"><?= number_format($sum_s) ?> คน</td>
            <td style="text-align:center;"><?= number_format($sum_e) ?> คน</td>
            <td style="text-align:center;"><?= number_format($sum_s + $sum_e) ?> คน</td>
            <td style="text-align:right;"><?= number_format($sum_s * 100) ?> ฿</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </form>
</div>

<div class="white-shell">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_units_payments" />
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
      <div>
        <h2 style="font-size:1.2rem; font-weight:800;">💰 ตารางที่ 2: บันทึกยอดรับเงินสมทบ 9 ส่วนงาน</h2>
        <p style="font-size:0.84rem; color:var(--muted);">บันทึกยอดเงินสนับสนุนผู้บริหารและเงินรายหัวที่ส่งเข้ากองกลางแล้ว</p>
      </div>
      <button type="submit" class="btn-primary">💾 บันทึกยอดรับเงิน</button>
    </div>
    <div style="overflow-x:auto;">
      <table class="up-table">
        <thead>
          <tr>
            <th>ที่</th>
            <th>ส่วนงาน</th>
            <th style="text-align:right;">🟣 งบรายหัวตั้งต้น</th>
            <th style="text-align:right;">🟡 งบผู้บริหารตั้งต้น</th>
            <th style="text-align:right;">รับงบผู้บริหารแล้ว (฿)</th>
            <th style="text-align:right;">รับเงินรายหัวแล้ว (฿)</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sum_ep = 0; $sum_er = 0; $sum_hr = 0;
          foreach ($units as $idx => $u):
            $id = (int)$u['id'];
            $staff = (int)$u['staff_count'];
            $ep = (float)$u['exec_support_pledged'];
            $er = (float)$u['exec_support_received'];
            $hr = (float)$u['headcount_received'];
            $sum_ep += $ep; $sum_er += $er; $sum_hr += $hr;
          ?>
          <tr>
            <td><?= $idx + 1 ?></td>
            <td><strong><?= h($u['unit_name']) ?></strong></td>
            <td style="text-align:right; font-weight:750; color:#6d28d9;"><?= number_format($staff * 100) ?> ฿</td>
            <td style="text-align:right;">
              <input type="number" step="100" class="num-input" style="width:100px;" name="u[<?= $id ?>][exec_pledged]" value="<?= (int)$ep ?>" />
            </td>
            <td style="text-align:right;">
              <input type="number" step="100" class="num-input" style="width:100px;" name="u[<?= $id ?>][exec_rec]" value="<?= (int)$er ?>" />
            </td>
            <td style="text-align:right;">
              <input type="number" step="100" class="num-input" style="width:100px;" name="u[<?= $id ?>][head_rec]" value="<?= (int)$hr ?>" />
            </td>
            <td>
              <select name="u[<?= $id ?>][status]" class="form-control" style="padding:4px 6px;">
                <option value="PENDING" <?= $u['payment_status'] === 'PENDING' ? 'selected' : '' ?>>⏳ รอส่ง</option>
                <option value="PARTIAL" <?= $u['payment_status'] === 'PARTIAL' ? 'selected' : '' ?>>🌗 ส่งบางส่วน</option>
                <option value="PAID" <?= $u['payment_status'] === 'PAID' ? 'selected' : '' ?>>✅ ส่งครบแล้ว</option>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#dcfce7; font-weight:850; color:var(--green-dark);">
            <td colspan="2" style="text-align:right;">รวมยอดเงินทั้งหมด</td>
            <td style="text-align:right;"><?= number_format($sum_s * 100) ?> ฿</td>
            <td style="text-align:right;"><?= number_format($sum_ep) ?> ฿</td>
            <td style="text-align:right;"><?= number_format($sum_er) ?> ฿</td>
            <td style="text-align:right;"><?= number_format($sum_hr) ?> ฿</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ==========================================================================
     ADMIN TAB 3: จัดการตารางแข่งขัน
     ========================================================================== -->
<?php if ($active_tab === 'matches'): ?>
<div class="white-shell">
  <h2 style="font-size:1.2rem; font-weight:800; margin-bottom:12px;">⚙️ เพิ่มโปรแกรมการแข่งขันใหม่</h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_match" />
    <div class="form-group">
      <label>ชนิดกีฬา</label>
      <select name="sport_id" class="form-control" required>
        <?php foreach ($sports as $sp): ?>
          <option value="<?= (int)$sp['id'] ?>"><?= (int)$sp['sport_no'] ?>. <?= h($sp['sport_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>วันที่แข่งขัน</label><input type="date" name="match_date" value="2026-12-17" class="form-control" required /></div>
    <div class="form-group"><label>เวลาเริ่ม</label><input type="time" name="start_time" value="17:00" class="form-control" required /></div>
    <div class="form-group"><label>เวลาสิ้นสุด</label><input type="time" name="end_time" value="18:00" class="form-control" /></div>
    <div class="form-group"><label>ประเภท / รุ่น</label><input type="text" name="team_division" placeholder="เช่น ทีมชาย / คู่ผสม" class="form-control" required /></div>
    <div class="form-group"><label>คู่แข่งขัน</label><input type="text" name="opponent_color" placeholder="เช่น สีฟ้า – สีเขียว" class="form-control" required /></div>
    <div class="form-group">
      <label>สนามแข่งขัน</label>
      <select name="venue" class="form-control">
        <option value="อาคารสงวนเสริมศรี">อาคารสงวนเสริมศรี</option>
        <option value="สนามกีฬากลางมหาวิทยาลัยพะเยา">สนามกีฬากลางมหาวิทยาลัยพะเยา</option>
        <option value="สนามเปตอง มหาวิทยาลัยพะเยา">สนามเปตอง มหาวิทยาลัยพะเยา</option>
        <option value="สนามเทนนิส มหาวิทยาลัยพะเยา">สนามเทนนิส มหาวิทยาลัยพะเยา</option>
      </select>
    </div>
    <div class="form-group"><label>งบน้ำ/น้ำแข็งนัดนี้ (฿)</label><input type="number" name="water_ice_budget" value="200" class="form-control" /></div>
    <div class="form-group"><label>สปอนเซอร์ (ขวด)</label><input type="number" name="sponsor_bottles_needed" value="15" class="form-control" /></div>
    <div class="form-group">
      <label>กระเป๋ายา</label>
      <select name="medical_bag_code" class="form-control">
        <option value="BAG-01">BAG-01 (อาคารสงวนฯ)</option>
        <option value="BAG-02">BAG-02 (สนามกีฬากลาง)</option>
        <option value="BAG-03">BAG-03 (เปตอง/เทนนิส)</option>
      </select>
    </div>
    <button type="submit" class="btn-primary">+ เพิ่มตารางแข่ง</button>
  </form>

  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th>วันที่</th>
          <th>เวลา</th>
          <th>ชนิดกีฬา</th>
          <th>คู่แข่งขัน / รุ่น</th>
          <th>สนาม</th>
          <th style="text-align:right;">งบน้ำ</th>
          <th style="text-align:center;">สปอนเซอร์</th>
          <th>ลบ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($schedules as $m): ?>
        <tr>
          <td><strong><?= h($m['match_date']) ?></strong></td>
          <td><?= substr(h($m['start_time']), 0, 5) ?> น.</td>
          <td><strong><?= h($m['sport_name']) ?></strong></td>
          <td><?= h($m['opponent_color']) ?> (<?= h($m['team_division']) ?>)</td>
          <td><?= h($m['venue']) ?></td>
          <td style="text-align:right;"><?= number_format((float)$m['water_ice_budget']) ?> ฿</td>
          <td style="text-align:center;"><?= (int)$m['sponsor_bottles_needed'] ?> ขวด</td>
          <td>
            <form method="post" style="margin:0;" onsubmit="return confirm('ลบรายการแข่งขันนี้?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_match" />
              <input type="hidden" name="match_id" value="<?= (int)$m['id'] ?>" />
              <button type="submit" class="btn-sm btn-danger">ลบ</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ==========================================================================
     ADMIN TAB 5: จัดการรายชื่อนักกีฬา & ผู้จัดการทีม
     ========================================================================== -->
<?php if ($active_tab === 'athletes'): ?>
<div class="white-shell">
  <h2 style="font-size:1.2rem; font-weight:800; margin-bottom:12px;">⚙️ เพิ่มรายชื่อนักกีฬา / ผู้จัดการทีม</h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_member" />
    <div class="form-group">
      <label>ชนิดกีฬา (1–11)</label>
      <select name="sport_id" class="form-control" required>
        <?php foreach ($sports as $sp): ?>
          <option value="<?= (int)$sp['id'] ?>" <?= $filter_sport === (int)$sp['id'] ? 'selected' : '' ?>><?= (int)$sp['sport_no'] ?>. <?= h($sp['sport_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>บทบาท</label>
      <select name="member_role" class="form-control">
        <option value="ATHLETE">นักกีฬา</option>
        <option value="SUB_ATHLETE">นักกีฬาตัวสำรอง</option>
        <option value="MANAGER">ผู้จัดการทีม</option>
        <option value="COACH">ผู้จัดการร่วม / โค้ช</option>
      </select>
    </div>
    <div class="form-group"><label>คำนำหน้า</label><input type="text" name="title_prefix" placeholder="นาย / นางสาว / ผศ.ดร." class="form-control" /></div>
    <div class="form-group"><label>ชื่อ - นามสกุล *</label><input type="text" name="full_name" class="form-control" required /></div>
    <div class="form-group"><label>ชื่อเล่น</label><input type="text" name="nickname" class="form-control" /></div>
    <div class="form-group">
      <label>สังกัด (ส่วนงาน) *</label>
      <select name="unit_id" class="form-control" required>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= h($u['unit_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>เบอร์ติดต่อ</label><input type="text" name="phone" value="-" class="form-control" required /></div>
    <div class="form-group"><label>เพศ</label><select name="gender" class="form-control"><option value="M">ชาย</option><option value="F">หญิง</option></select></div>
    <div class="form-group"><label>ประเภท/ตำแหน่ง</label><input type="text" name="competition_category" class="form-control" /></div>
    <button type="submit" class="btn-primary">+ บันทึกรายชื่อ</button>
  </form>

  <!-- Filter by Sport -->
  <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px;">
    <a href="admin.php?tab=athletes" class="btn-sm" style="background:<?= $filter_sport === 0 ? 'var(--green)' : '#e2e8f0' ?>; color:<?= $filter_sport === 0 ? '#fff' : '#0f172a' ?>;">รวมทุกกีฬา</a>
    <?php foreach ($sports as $sp): $sid = (int)$sp['id']; ?>
      <a href="admin.php?tab=athletes&amp;sport_id=<?= $sid ?>" class="btn-sm" style="background:<?= $filter_sport === $sid ? 'var(--green)' : '#e2e8f0' ?>; color:<?= $filter_sport === $sid ? '#fff' : '#0f172a' ?>;">
        <?= (int)$sp['sport_no'] ?>. <?= h($sp['sport_name']) ?> (<?= (int)$sp['member_count'] ?>)
      </a>
    <?php endforeach; ?>
  </div>

  <div style="overflow-x:auto;">
    <table class="up-table">
      <thead>
        <tr>
          <th>ที่</th>
          <th>ชนิดกีฬา</th>
          <th>บทบาท</th>
          <th>ชื่อ - นามสกุล</th>
          <th>สังกัด</th>
          <th>เบอร์ติดต่อ</th>
          <th>ลบ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $idx => $m): ?>
        <tr>
          <td><?= $idx + 1 ?></td>
          <td><strong><?= (int)$m['sport_no'] ?>. <?= h($m['sport_name']) ?></strong></td>
          <td><?= h($m['member_role']) ?></td>
          <td><strong><?= h(trim($m['title_prefix'] . ' ' . $m['full_name'])) ?></strong></td>
          <td><?= h($m['unit_name']) ?></td>
          <td><?= h($m['phone']) ?></td>
          <td>
            <form method="post" style="margin:0;" onsubmit="return confirm('ลบรายชื่อนี้?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_member" />
              <input type="hidden" name="sport_id" value="<?= $filter_sport ?>" />
              <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>" />
              <button type="submit" class="btn-sm btn-danger">ลบ</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ==========================================================================
     ADMIN TAB 6: จัดการเวชภัณฑ์แยกตามชนิดกีฬา + สรุปคลังรวม
     ========================================================================== -->
<?php if ($active_tab === 'medical'):
  $med_sport_id = isset($_GET['med_sport_id']) ? (int)$_GET['med_sport_id'] : (int)$sports[0]['id'];
  $stmt_smr = $pdo->prepare("SELECT medical_item_id, qty FROM sport_medical_requests WHERE sport_id = :sid");
  $stmt_smr->execute([':sid' => $med_sport_id]);
  $sport_med_map = $stmt_smr->fetchAll(PDO::FETCH_KEY_PAIR);

  // นับจำนวนรายการที่แต่ละกีฬาขอเบิก (> 0)
  $sport_req_counts = $pdo->query("
      SELECT sport_id, COUNT(*) AS item_cnt, SUM(qty) AS total_units
      FROM sport_medical_requests
      WHERE qty > 0
      GROUP BY sport_id
  ")->fetchAll(PDO::FETCH_UNIQUE);

  $selected_med_sport = null;
  foreach ($sports as $sp) {
      if ((int)$sp['id'] === $med_sport_id) { $selected_med_sport = $sp; break; }
  }
?>
<div class="white-shell" style="margin-bottom:22px;">
  <div style="margin-bottom:14px;">
    <h2 style="font-size:1.2rem; font-weight:800;">🏥 ส่วนที่ 1: กำหนดเวชภัณฑ์และเครื่องดื่มแยกตามชนิดกีฬา (1–11)</h2>
    <p style="font-size:0.84rem; color:var(--muted);">คลิกเลือกชนิดกีฬาด้านล่าง แล้วกรอกจำนวนที่ต้องการใช้งาน — ระบบจะบวกยอดรวมเข้าตารางสรุปคลังกลางให้อัตโนมัติ</p>
  </div>

  <!-- Sport Selector Pills -->
  <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px;">
    <?php foreach ($sports as $sp):
      $sid = (int)$sp['id'];
      $is_sel = ($sid === $med_sport_id);
      $cnt_info = $sport_req_counts[$sid] ?? null;
    ?>
      <a href="admin.php?tab=medical&amp;med_sport_id=<?= $sid ?>"
         style="padding:7px 12px; border-radius:10px; font-size:0.85rem; font-weight:750; text-decoration:none; border:1px solid <?= $is_sel ? '#047857' : 'var(--line)' ?>; background:<?= $is_sel ? 'var(--green)' : '#f8fcf9' ?>; color:<?= $is_sel ? '#fff' : 'var(--ink)' ?>;">
        <?= (int)$sp['sport_no'] ?>. <?= h($sp['sport_name']) ?>
        <?php if ($cnt_info): ?>
          <span style="margin-left:4px; padding:1px 6px; border-radius:99px; font-size:0.72rem; background:<?= $is_sel ? '#bef264' : '#dcfce7' ?>; color:#065f46;">
            <?= (int)$cnt_info['item_cnt'] ?> รายการ
          </span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_sport_medical" />
    <input type="hidden" name="med_sport_id" value="<?= $med_sport_id ?>" />
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px; background:#ecfdf5; padding:10px 14px; border-radius:10px; border:1px solid #a7f3d0;">
      <div>
        <strong style="color:var(--green-dark); font-size:1rem;">
          กำลังกำหนดรายการสำหรับ: <?= (int)$selected_med_sport['sport_no'] ?>. <?= h($selected_med_sport['sport_name']) ?>
        </strong>
        <span style="font-size:0.84rem; color:var(--muted); margin-left:8px;">
          (ผู้จัดการทีม: <?= h($selected_med_sport['manager_name']) ?> · สนาม: <?= h($selected_med_sport['default_venue']) ?>)
        </span>
      </div>
      <button type="submit" class="btn-primary">💾 บันทึกรายการของ<?= h($selected_med_sport['sport_name']) ?></button>
    </div>

    <div style="overflow-x:auto;">
      <table class="up-table">
        <thead>
          <tr>
            <th>รหัส</th>
            <th>รายการยา / เวชภัณฑ์ / เครื่องดื่ม</th>
            <th>หน่วยนับ</th>
            <th style="text-align:right;">ราคา/หน่วย</th>
            <th style="text-align:center;">จำนวนที่<?= h($selected_med_sport['sport_name']) ?>ต้องการ</th>
            <th style="text-align:right;">คิดเป็นเงิน (฿)</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $sport_med_cost = 0;
          foreach ($med_items as $m):
            $mid = (int)$m['id'];
            $q = (int)($sport_med_map[$mid] ?? 0);
            $pr = (float)$m['unit_price'];
            $sub_c = $q * $pr;
            $sport_med_cost += $sub_c;
          ?>
          <tr style="<?= $q > 0 ? 'background:#f0fdf4;' : '' ?>">
            <td><strong><?= h($m['item_code']) ?></strong></td>
            <td><strong><?= h($m['item_name']) ?></strong></td>
            <td><?= h($m['unit_name']) ?></td>
            <td style="text-align:right;"><?= fmod($pr, 1) > 0 ? number_format($pr, 2) : number_format($pr) ?> ฿</td>
            <td style="text-align:center;">
              <input type="number" min="0" class="num-input" style="width:80px; text-align:center;" name="req_qty[<?= $mid ?>]" value="<?= $q ?>" />
            </td>
            <td style="text-align:right; font-weight:750; color:var(--green-dark);">
              <?= $sub_c > 0 ? (fmod($sub_c, 1) > 0 ? number_format($sub_c, 2) : number_format($sub_c)) . ' ฿' : '-' ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#dcfce7; font-weight:850; color:var(--green-dark);">
            <td colspan="5" style="text-align:right;">รวมงบเวชภัณฑ์เฉพาะกีฬา <?= h($selected_med_sport['sport_name']) ?></td>
            <td style="text-align:right; font-size:1.05rem;"><?= fmod($sport_med_cost, 1) > 0 ? number_format($sport_med_cost, 2) : number_format($sport_med_cost) ?> ฿</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </form>
</div>

<div class="white-shell">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_medical" />
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
      <div>
        <h2 style="font-size:1.2rem; font-weight:800;">📦 ส่วนที่ 2: ตารางสรุปยอดรวมเวชภัณฑ์ทุกชนิดกีฬา (คลังกลาง)</h2>
        <p style="font-size:0.84rem; color:var(--muted);">ยอดที่ต้องใช้จะรวมจากส่วนที่ 1 ให้อัตโนมัติ (หรือปรับแก้สต๊อกที่มีอยู่แล้ว/ราคาต่อหน่วยได้ที่นี่)</p>
      </div>
      <button type="submit" class="btn-primary">💾 บันทึกคลังรวม</button>
    </div>
    <div style="overflow-x:auto;">
      <table class="up-table">
        <thead>
          <tr>
            <th>รหัส</th>
            <th>รายการ</th>
            <th>หน่วยนับ</th>
            <th style="text-align:center;">ยอดที่ต้องใช้รวม</th>
            <th style="text-align:center;">มีแล้ว/ยืมได้</th>
            <th style="text-align:right;">ราคา/หน่วย (฿)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($med_items as $m): $id = (int)$m['id']; ?>
          <tr>
            <td><strong><?= h($m['item_code']) ?></strong></td>
            <td><strong><?= h($m['item_name']) ?></strong></td>
            <td><?= h($m['unit_name']) ?></td>
            <td style="text-align:center;"><input type="number" class="num-input" style="width:75px; text-align:center;" name="med[<?= $id ?>][req]" value="<?= (int)$m['total_required'] ?>" /></td>
            <td style="text-align:center;"><input type="number" class="num-input" style="width:75px; text-align:center;" name="med[<?= $id ?>][stock]" value="<?= (int)$m['in_stock_or_borrowed'] ?>" /></td>
            <td style="text-align:right;"><input type="number" step="0.50" class="num-input" style="width:85px;" name="med[<?= $id ?>][price]" value="<?= fmod((float)$m['unit_price'], 1) > 0 ? number_format((float)$m['unit_price'], 2, '.', '') : (int)$m['unit_price'] ?>" /></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ==========================================================================
     ADMIN TAB 7: อนุมัติใบเบิก & เคลียร์บิล
     ========================================================================== -->
<?php if ($active_tab === 'disbursements'): ?>
<div class="white-shell">
  <h2 style="font-size:1.2rem; font-weight:800; margin-bottom:12px;">⚙️ สร้างใบเบิกงบประมาณรายการใหม่</h2>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_disbursement" />
    <div class="form-group"><label>วันที่เบิก</label><input type="date" name="tx_date" value="<?= date('Y-m-d') ?>" class="form-control" required /></div>
    <div class="form-group">
      <label>เบิกจากงบก้อนที่</label>
      <select name="tranche_no" class="form-control">
        <option value="1">ก้อนที่ 1 (ผู้บริหาร)</option>
        <option value="2">ก้อนที่ 2 (งบ มพ.)</option>
        <option value="3">ก้อนที่ 3 (รายหัวส่วนงาน)</option>
      </select>
    </div>
    <div class="form-group">
      <label>ฝ่ายงาน</label>
      <select name="category_id" class="form-control">
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h($c['category_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>ชนิดกีฬา (ถ้ามี)</label>
      <select name="sport_id" class="form-control">
        <option value="">-- ส่วนกลางฝ่าย --</option>
        <?php foreach ($sports as $sp): ?>
          <option value="<?= (int)$sp['id'] ?>"><?= (int)$sp['sport_no'] ?>. <?= h($sp['sport_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>ชื่อผู้ขอเบิก *</label><input type="text" name="requester_name" class="form-control" required /></div>
    <div class="form-group"><label>รายละเอียด *</label><input type="text" name="description" class="form-control" required /></div>
    <div class="form-group"><label>ยอดขอเบิก (฿) *</label><input type="number" step="50" name="requested_amount" class="form-control" required /></div>
    <button type="submit" class="btn-primary">+ บันทึกใบเบิก</button>
  </form>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_disbursement" />
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <h3 style="font-size:1.1rem; font-weight:800;">อัปเดตยอดใช้จริงและสถานะการเคลียร์บิล</h3>
      <button type="submit" class="btn-primary btn-sm">💾 บันทึกการเคลียร์บิล</button>
    </div>
    <div style="overflow-x:auto;">
      <table class="up-table">
        <thead>
          <tr>
            <th>เลขที่ใบเบิก</th>
            <th>ฝ่ายงาน / ผู้ขอเบิก</th>
            <th style="text-align:right;">ยอดขอเบิก</th>
            <th style="text-align:right;">ใช้จริงตามบิล</th>
            <th style="text-align:right;">เงินทอนคืน</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tx_list as $tx): $id = (int)$tx['id']; ?>
          <tr>
            <td><strong><?= h($tx['doc_no']) ?></strong><div style="font-size:0.76rem; color:var(--muted);"><?= h($tx['tx_date']) ?></div></td>
            <td><strong><?= h($tx['requester_name']) ?></strong><div style="font-size:0.8rem; color:var(--muted);"><?= h($tx['description']) ?></div></td>
            <td style="text-align:right; font-weight:800;"><?= number_format((float)$tx['requested_amount']) ?> ฿</td>
            <td style="text-align:right;"><input type="number" step="10" class="num-input" name="tx[<?= $id ?>][spent]" value="<?= (int)$tx['actual_spent_amount'] ?>" /></td>
            <td style="text-align:right;"><input type="number" step="10" class="num-input" name="tx[<?= $id ?>][ret]" value="<?= (int)$tx['returned_amount'] ?>" /></td>
            <td>
              <select name="tx[<?= $id ?>][status]" class="form-control">
                <option value="PENDING" <?= $tx['tx_status'] === 'PENDING' ? 'selected' : '' ?>>⏳ รออนุมัติ</option>
                <option value="APPROVED" <?= $tx['tx_status'] === 'APPROVED' ? 'selected' : '' ?>>👍 อนุมัติแล้ว</option>
                <option value="PAID" <?= $tx['tx_status'] === 'PAID' ? 'selected' : '' ?>>💸 จ่ายเงินแล้ว</option>
                <option value="CLEARED" <?= $tx['tx_status'] === 'CLEARED' ? 'selected' : '' ?>>✅ เคลียร์บิลจบ</option>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
