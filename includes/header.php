<?php
require_once __DIR__ . '/../config/auth.php';
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = is_admin_logged_in();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($page_title ?? 'UP SPORT 2027 · ระบบรายงานทัพสีเขียว (GREEN TEAM)') ?></title>
  <link rel="stylesheet" href="assets/css/green-theme.css?v=3" />
</head>
<body>
  <header class="masthead">
    <div class="wrap header-row">
      <div class="header-top-bar">
        <a href="index.php" class="brand">
          <div class="brand-badge">UP</div>
          <div class="brand-text">
            <strong>UP SPORT 2027 · GREEN TEAM</strong>
            <span>ระบบรายงานงบประมาณและทัพนักกีฬาสีเขียว ม.พะเยา</span>
          </div>
        </a>
        <button type="button" class="nav-toggle-btn" id="mobileNavToggle" aria-expanded="false" aria-controls="mainTopNav" onclick="toggleMobileNav()">
          <span class="nav-toggle-icon" aria-hidden="true">☰</span>
          <span>เมนู</span>
        </button>
      </div>

      <nav class="top-nav" id="mainTopNav">
        <a href="index.php" class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">💰 1. รายงานงบ 3 ก้อน</a>
        <a href="units.php" class="nav-link <?= $current_page === 'units.php' ? 'active' : '' ?>">🏢 2. งบสมทบ 9 ส่วนงาน</a>
        <a href="sports.php" class="nav-link <?= $current_page === 'sports.php' ? 'active' : '' ?>">📅 3. ตารางแข่ง &amp; งบกีฬา</a>
        <a href="athletes.php" class="nav-link <?= $current_page === 'athletes.php' ? 'active' : '' ?>">👟 4. ทำเนียบนักกีฬา</a>
        <a href="medical.php" class="nav-link <?= $current_page === 'medical.php' ? 'active' : '' ?>">🏥 5. รายงานเวชภัณฑ์</a>
        <a href="disbursements.php" class="nav-link <?= $current_page === 'disbursements.php' ? 'active' : '' ?>">🧾 6. รายงานเบิกจ่าย</a>

        <?php if ($is_admin): ?>
          <a href="admin.php" class="nav-link <?= $current_page === 'admin.php' ? 'active' : '' ?>" style="background:#facc15; color:#032217; font-weight:800; margin-left:6px;">
            ⚙️ จัดการหลังบ้าน (Admin)
          </a>
          <a href="logout.php" class="nav-link" style="color:#fca5a5;">ออกระบบ</a>
        <?php else: ?>
          <a href="login.php" class="nav-link <?= $current_page === 'login.php' ? 'active' : '' ?>" style="background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.3); margin-left:6px;">
            🔒 Admin Login
          </a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <script>
    function toggleMobileNav() {
      var nav = document.getElementById('mainTopNav');
      var btn = document.getElementById('mobileNavToggle');
      if (!nav || !btn) return;
      var open = nav.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      var ico = btn.querySelector('.nav-toggle-icon');
      if (ico) ico.textContent = open ? '✕' : '☰';
    }
  </script>

  <main class="wrap inner">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
      <span style="font-size:0.8rem; color:var(--muted);">
        <?= $is_admin ? '🔓 สถานะ: เข้าสู่ระบบผู้ดูแลระบบ (Admin Mode)' : '📊 โหมดแสดงผลรายงานสำหรับบุคลากรสีเขียว (Read-Only Report)' ?>
      </span>
      <span class="notice-pill">🟢 ประธานสีเขียว: คณบดีคณะวิทยาศาสตร์การแพทย์ · โครงการ 14 ธ.ค. 69 – 14 ม.ค. 70</span>
    </div>

    <section class="public-page-title">
      <div class="public-title-copy">
        <div class="public-crumbs">
          <span>ทัพสีเขียว (GREEN TEAM)</span> <span>›</span> <span><?= h($page_crumb ?? 'รายงาน') ?></span>
        </div>
        <span class="public-title-kicker">UNIVERSITY OF PHAYAO · SOCIETAL WELL-BEING</span>
        <h1><?= h($page_heading ?? 'ระบบรายงานงบประมาณและทัพนักกีฬาสีเขียว') ?></h1>
        <p><?= h($page_desc ?? 'รายงานข้อมูลโปร่งใสแบบ Real-time สำหรับผู้บริหาร ผู้ประสานงาน 9 ส่วนงาน และนักกีฬาสีเขียวทั้ง 11 ชนิดกีฬา') ?></p>
      </div>
      <div class="public-title-icon"><?= $page_icon ?? '🏆' ?></div>
    </section>
