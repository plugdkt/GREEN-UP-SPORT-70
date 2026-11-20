<?php
require_once __DIR__ . '/config/auth.php';

if (is_admin_logged_in()) {
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        $_SESSION['green_admin_logged_in'] = true;
        $_SESSION['green_admin_user'] = $user;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาลองอีกครั้ง';
    }
}

$page_title   = 'UP SPORT 2027 · เข้าสู่ระบบหลังบ้าน (Admin Login)';
$page_crumb   = 'เข้าสู่ระบบผู้ดูแลระบบ (Admin)';
$page_heading = 'เข้าสู่ระบบบริหารจัดการหลังบ้าน (Admin)';
$page_desc    = 'เฉพาะประธานสีเขียว ฝ่ายเหรัญญิก (กองคลัง) และทีมเลขานุการสีเขียว สำหรับบันทึกและแก้ไขข้อมูล';
$page_icon    = '🔒';

require_once __DIR__ . '/includes/header.php';
?>

<div class="white-shell" style="max-width:480px; margin-inline:auto; padding:32px;">
  <h2 style="font-size:1.3rem; font-weight:850; margin-bottom:6px; color:var(--green-dark);">🔒 เข้าสู่ระบบ Admin สีเขียว</h2>
  <p style="font-size:0.86rem; color:var(--muted); margin-bottom:20px;">กรอกรหัสผ่านผู้ดูแลระบบเพื่อเข้าจัดการงบประมาณ ตารางแข่ง และรายชื่อนักกีฬา</p>

  <?php if ($error): ?>
    <div style="background:#fee2e2; border:1px solid #fca5a5; color:#991b1b; padding:10px 14px; border-radius:10px; font-weight:700; margin-bottom:16px; font-size:0.88rem;">
      ⚠️ <?= h($error) ?>
    </div>
  <?php endif; ?>

  <form method="post" style="display:grid; gap:14px;">
    <div class="form-group">
      <label>ชื่อผู้ใช้งาน (Username)</label>
      <input type="text" name="username" value="admin" class="form-control" required autofocus />
    </div>
    <div class="form-group">
      <label>รหัสผ่าน (Password)</label>
      <input type="password" name="password" placeholder="ค่าเริ่มต้น: green2027" class="form-control" required />
    </div>
    <button type="submit" class="btn-primary" style="padding:11px; font-size:1rem; margin-top:6px;">
      เข้าสู่ระบบจัดการหลังบ้าน ›
    </button>
  </form>

  <div style="margin-top:18px; padding-top:14px; border-top:1px solid var(--line); font-size:0.8rem; color:var(--muted);">
    💡 <strong>ข้อมูลเข้าสู่ระบบเริ่มต้น:</strong><br/>
    Username: <code>admin</code> &nbsp;|&nbsp; Password: <code>green2027</code>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
