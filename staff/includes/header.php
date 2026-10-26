<?php
if (!isset($u)) $u = staff_user();
$desks = staff_desks();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($staff_title ?? 'Staff'); ?> | Vasudha Operations Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --sp-primary: #006039;
      --sp-primary-dark: #00482B;
      --sp-primary-darker: #06261B;
      --sp-primary-hover: #007545;
      --sp-primary-light: #ECFDF5;
      --sp-primary-subtle: #F0FDF4;
      --sp-accent: #10B981;
      --sp-accent-light: #34D399;
      --sp-bg: #F8FAFC;
      --sp-surface: #FFFFFF;
      --sp-surface-subtle: #F8FAFC;
      --sp-card-bg: #FFFFFF;
      --sp-border: #E2E8F0;
      --sp-border-strong: #CBD5E1;
      --sp-border-focus: #006039;
      --sp-text-main: #0F172A;
      --sp-text-muted: #475569;
      --sp-text-subtle: #64748B;
      --sp-success-bg: #ECFDF5;
      --sp-success-text: #065F46;
      --sp-success-border: #A7F3D0;
      --sp-danger-bg: #FEF2F2;
      --sp-danger-text: #991B1B;
      --sp-danger-border: #FECACA;
      --sp-warning-bg: #FFFBEB;
      --sp-warning-text: #92400E;
      --sp-warning-border: #FDE68A;
      --sp-info-bg: #F0F9FF;
      --sp-info-text: #075985;
      --sp-info-border: #BAE6FD;
      --sp-shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
      --sp-shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
      --sp-shadow-md: 0 4px 12px -2px rgba(15, 23, 42, 0.06), 0 2px 4px -1px rgba(15, 23, 42, 0.04);
      --sp-shadow-lg: 0 12px 28px -4px rgba(15, 23, 42, 0.08), 0 4px 8px -2px rgba(15, 23, 42, 0.03);
      --sp-radius-sm: 6px;
      --sp-radius-md: 10px;
      --sp-radius-lg: 14px;
      --sp-radius-full: 9999px;
    }
    *, *::before, *::after { box-sizing: border-box; }
    html {
      font-size: 14px;
      scroll-behavior: smooth;
    }
    body {
      margin: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--sp-bg);
      color: var(--sp-text-main);
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      line-height: 1.55;
    }

    /* Top Accent Line */
    .staff-shimmer-bar {
      height: 3px;
      background: linear-gradient(90deg, #00482B 0%, #006039 30%, #10B981 70%, #34D399 100%);
      width: 100%;
    }

    /* Header Bar */
    header.staff-top {
      background: #FFFFFF;
      border-bottom: 1px solid var(--sp-border);
      padding: 12px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
      flex-wrap: wrap;
      box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
    }
    .staff-top-left {
      display: flex;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
    }
    .logo-badge {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      background: #ECFDF5;
      color: #006039;
      border: 1px solid #A7F3D0;
      padding: 4px 10px;
      border-radius: var(--sp-radius-full);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .logo-badge::before {
      content: '';
      width: 6px;
      height: 6px;
      background: #10B981;
      border-radius: 50%;
      box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);
    }

    /* User Meta Section */
    .staff-user-meta {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12.5px;
      color: var(--sp-text-muted);
    }
    .staff-site-link {
      color: var(--sp-text-muted);
      text-decoration: none;
      font-size: 12px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: var(--sp-radius-sm);
      border: 1px solid transparent;
      transition: all 0.18s ease;
    }
    .staff-site-link:hover {
      color: var(--sp-primary);
      background: var(--sp-primary-light);
      border-color: #A7F3D0;
    }
    .staff-avatar-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #F8FAFC;
      border: 1px solid #E2E8F0;
      padding: 6px 14px;
      border-radius: var(--sp-radius-full);
      font-weight: 600;
      color: var(--sp-text-main);
      text-decoration: none;
      transition: all 0.18s ease;
      box-shadow: var(--sp-shadow-xs);
    }
    .staff-avatar-pill:hover {
      background: #FFFFFF;
      border-color: #CBD5E1;
      box-shadow: var(--sp-shadow-sm);
    }
    .staff-avatar-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #10B981;
      box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
    }
    .staff-logout-btn {
      color: #991B1B;
      background: #FEF2F2;
      border: 1px solid #FECACA;
      text-decoration: none;
      font-weight: 600;
      font-size: 12px;
      padding: 6px 14px;
      border-radius: var(--sp-radius-sm);
      transition: all 0.18s ease;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .staff-logout-btn:hover {
      background: #FEE2E2;
      border-color: #F87171;
      color: #7F1D1D;
    }

    /* Executive Forest Navigation Bar */
    nav.staff-nav {
      background: #081C15;
      padding: 4px 32px;
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      align-items: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    }
    nav.staff-nav a {
      color: #94A3B8;
      text-decoration: none;
      font-size: 12.5px;
      font-weight: 500;
      padding: 8px 14px;
      border-radius: var(--sp-radius-sm);
      transition: all 0.18s ease;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      letter-spacing: 0.01em;
    }
    nav.staff-nav a:hover {
      color: #FFFFFF;
      background: rgba(255, 255, 255, 0.08);
    }
    nav.staff-nav a.on {
      color: #FFFFFF;
      background: var(--sp-primary);
      font-weight: 600;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.15);
    }

    /* Content Wrapper */
    .wrap {
      max-width: 1240px;
      margin: 0 auto;
      padding: 32px 28px 72px;
    }

    /* Executive Hero Banner */
    .desk-hero-banner {
      background: linear-gradient(135deg, #06261B 0%, #0A3D2C 55%, #0F523C 100%);
      color: #FFFFFF;
      border-radius: var(--sp-radius-lg);
      padding: 28px 32px;
      margin-bottom: 24px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      box-shadow: var(--sp-shadow-md);
      position: relative;
      overflow: hidden;
    }
    .desk-hero-banner::after {
      content: '';
      position: absolute;
      top: -40%;
      right: -10%;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
      pointer-events: none;
    }

    /* Premium Card System */
    .card {
      background: var(--sp-surface);
      border-radius: var(--sp-radius-lg);
      padding: 24px 28px;
      margin-bottom: 22px;
      border: 1px solid var(--sp-border);
      box-shadow: var(--sp-shadow-sm);
      transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .card:hover {
      box-shadow: var(--sp-shadow-md);
    }

    /* Typography */
    h1, h2, h3, h4 {
      color: var(--sp-text-main);
      font-weight: 700;
      letter-spacing: -0.025em;
      margin-top: 0;
    }
    h1 { font-size: 24px; line-height: 1.25; }
    h2 { font-size: 18px; line-height: 1.3; }
    h3 { font-size: 15px; line-height: 1.35; }
    p { margin: 0 0 12px; color: var(--sp-text-muted); }

    /* Form Controls */
    label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: #334155;
      margin: 14px 0 6px;
      letter-spacing: 0.01em;
      text-transform: uppercase;
    }
    input, textarea, select {
      width: 100%;
      box-sizing: border-box;
      padding: 10px 14px;
      border: 1px solid var(--sp-border-strong);
      border-radius: var(--sp-radius-sm);
      font: inherit;
      font-size: 13.5px;
      background: #FFFFFF;
      color: var(--sp-text-main);
      transition: all 0.18s ease;
      box-shadow: var(--sp-shadow-xs);
    }
    input:focus, textarea:focus, select:focus {
      outline: none;
      border-color: var(--sp-border-focus);
      background: #FFFFFF;
      box-shadow: 0 0 0 3px rgba(0, 96, 57, 0.12);
    }
    input::placeholder, textarea::placeholder {
      color: #94A3B8;
    }
    textarea {
      min-height: 110px;
      resize: vertical;
      line-height: 1.5;
    }
    select {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      padding-right: 36px;
      appearance: none;
    }

    /* Grid Layouts */
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; }
    .row-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 18px; }
    @media (max-width: 820px) {
      .row, .row-3 { grid-template-columns: 1fr; }
      header.staff-top { padding: 12px 18px; }
      nav.staff-nav { padding: 4px 18px; }
      .wrap { padding: 20px 16px 48px; }
    }

    /* Buttons System */
    button, .btn {
      cursor: pointer;
      border: 1px solid transparent;
      border-radius: var(--sp-radius-sm);
      padding: 9px 18px;
      font-weight: 600;
      font-size: 13px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      transition: all 0.18s ease;
      font-family: inherit;
      line-height: 1.4;
      box-shadow: var(--sp-shadow-xs);
    }
    .btn-red, .btn-primary {
      background: var(--sp-primary);
      color: #FFFFFF;
      border-color: var(--sp-primary-dark);
      box-shadow: 0 2px 6px rgba(0, 96, 57, 0.2);
    }
    .btn-red:hover, .btn-primary:hover {
      background: var(--sp-primary-hover);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 96, 57, 0.28);
    }
    .btn-ghost {
      background: #FFFFFF;
      color: #334155;
      border: 1px solid var(--sp-border-strong);
    }
    .btn-ghost:hover {
      background: #F8FAFC;
      border-color: #94A3B8;
      color: #0F172A;
      transform: translateY(-1px);
    }
    .btn-danger {
      background: var(--sp-danger-bg);
      color: var(--sp-danger-text);
      border: 1px solid var(--sp-danger-border);
    }
    .btn-danger:hover {
      background: #FEE2E2;
      border-color: #FCA5A5;
      transform: translateY(-1px);
    }

    /* Modern Table System */
    .table-container {
      border: 1px solid var(--sp-border);
      border-radius: var(--sp-radius-md);
      overflow: hidden;
      background: #FFFFFF;
      box-shadow: var(--sp-shadow-xs);
    }
    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      font-size: 13px;
    }
    th {
      text-align: left;
      padding: 13px 16px;
      background: #F8FAFC;
      color: #475569;
      font-weight: 700;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      border-bottom: 1px solid var(--sp-border);
      white-space: nowrap;
    }
    td {
      padding: 14px 16px;
      border-bottom: 1px solid #F1F5F9;
      vertical-align: middle;
      color: #1E293B;
      line-height: 1.45;
    }
    tr:last-child td { border-bottom: 0; }
    tbody tr {
      transition: background 0.15s ease;
    }
    tbody tr:hover {
      background: #F8FAFC;
    }

    /* Alerts & Banners */
    .ok {
      background: var(--sp-success-bg);
      color: var(--sp-success-text);
      padding: 14px 18px;
      border-radius: var(--sp-radius-md);
      border: 1px solid var(--sp-success-border);
      font-size: 13.5px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: var(--sp-shadow-xs);
    }
    .err-box {
      background: var(--sp-danger-bg);
      color: var(--sp-danger-text);
      padding: 14px 18px;
      border-radius: var(--sp-radius-md);
      border: 1px solid var(--sp-danger-border);
      font-size: 13.5px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: var(--sp-shadow-xs);
    }
    .hint {
      font-size: 13px;
      color: var(--sp-text-muted);
      line-height: 1.6;
      background: #F8FAFC;
      border: 1px solid var(--sp-border);
      border-left: 3px solid var(--sp-primary);
      padding: 14px 18px;
      border-radius: var(--sp-radius-md);
      box-shadow: var(--sp-shadow-xs);
    }

    /* Badges */
    .badge-open, .open, .badge-live {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 10px;
      border-radius: var(--sp-radius-full);
      font-size: 11.5px;
      font-weight: 600;
      background: #ECFDF5;
      color: #065F46;
      border: 1px solid #A7F3D0;
    }
    .badge-closed, .closed {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 10px;
      border-radius: var(--sp-radius-full);
      font-size: 11.5px;
      font-weight: 600;
      background: #F1F5F9;
      color: #475569;
      border: 1px solid #E2E8F0;
    }

    /* Metric KPI Cards */
    .stat-card {
      background: #FFFFFF;
      border: 1px solid var(--sp-border);
      border-radius: var(--sp-radius-md);
      padding: 22px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      box-shadow: var(--sp-shadow-sm);
      transition: all 0.2s ease;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--sp-shadow-md);
      border-color: #CBD5E1;
    }
    .stat-label {
      font-size: 11.5px;
      color: var(--sp-text-subtle);
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .stat-num {
      font-size: 34px;
      font-weight: 800;
      color: #0F172A;
      line-height: 1;
      letter-spacing: -0.035em;
      font-variant-numeric: tabular-nums;
      margin: 4px 0 2px;
    }
    .stat-sub {
      font-size: 12px;
      color: var(--sp-text-muted);
    }

    /* Search Bar */
    .table-search-bar {
      margin-bottom: 18px;
      display: flex;
      gap: 12px;
      align-items: center;
      flex-wrap: wrap;
    }
    .table-search-bar input {
      max-width: 340px;
      background: #FFFFFF;
    }
  </style>
  <link rel="stylesheet" href="../assets/css/heading-gradient.css">
  <link rel="stylesheet" href="../assets/css/vasudha-capsule-logo.css">
</head>
<body>
<div class="staff-shimmer-bar"></div>
<header class="staff-top">
  <div class="staff-top-left">
    <a href="<?php echo htmlspecialchars(staff_default_desk_url($u ?? null)); ?>" class="logo" title="Vasudha Pharma Chem Limited Operations Portal">
      <div class="logo-hex-wrap">
        <div class="logo-hex-body">
          <img src="../assets/vasudha-logo-transparent.png" alt="Vasudha Pharma Chem Limited" class="logo-img">
        </div>
      </div>
      <div class="logo-text">
        <strong>VASUDHA PHARMA CHEM LIMITED</strong>
        <span>Contributing to affordable health care...</span>
      </div>
    </a>
    <span class="logo-badge">Operations Portal</span>
  </div>
  <div class="staff-user-meta">
    <a href="../careers.html" target="_blank" rel="noopener" class="staff-site-link" title="Open public Careers page in a new tab">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      <span>Careers</span>
    </a>
    <a href="../home.html" target="_blank" rel="noopener" class="staff-site-link" title="Open public website in a new tab">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
      <span>Website</span>
    </a>
    <a href="../feedback.html" target="_blank" rel="noopener" class="staff-site-link" style="background:#ECFDF5; color:#065F46; font-weight:600; border-color:#A7F3D0;" title="Open Website Testing Feedback Console">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
      <span>Feedback</span>
    </a>
    <a href="profile.php" class="staff-avatar-pill" title="My Account / Change Password">
      <span class="staff-avatar-dot"></span>
      <span><?php echo htmlspecialchars($u['name'] ?? 'Staff'); ?></span>
      <span style="font-weight:500; font-size:11px; color:#64748B; background:#F1F5F9; padding:2px 8px; border-radius:10px; margin-left:2px;"><?php echo htmlspecialchars($u['dept'] ?? 'Desk'); ?></span>
    </a>
    <a href="logout.php" class="staff-logout-btn" title="Sign out of Operations Portal">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Sign out</span>
    </a>
  </div>
</header>
<nav class="staff-nav">
  <?php if (($u['role'] ?? '') === 'admin'): ?>
  <a href="home.php" class="<?php echo (($staff_title ?? '') === 'Home' || basename($_SERVER['PHP_SELF']) === 'home.php') ? 'on' : ''; ?>">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    <span>Home</span>
  </a>
  <?php endif; ?>
  <?php foreach ($desks as $key => $d):
    if (!staff_can($key)) continue; 
    $isThisDesk = ($staff_title ?? '') === $d['label'] || ($staff_title ?? '') === 'Careers & HR' || ($staff_title ?? '') === 'Board & Management' || ($staff_title ?? '') === 'Board of Directors' || ($staff_title ?? '') === 'Marketing' || ($staff_title ?? '') === ucfirst($key) || basename($_SERVER['PHP_SELF']) === $d['file'] || ($key === 'careers' && in_array(basename($_SERVER['PHP_SELF']), ['jobs.php', 'candidates.php'], true));
    $active = $isThisDesk ? 'on' : ''; ?>
    <a href="<?php echo htmlspecialchars($d['file']); ?>" class="<?php echo $active; ?>"><?php echo htmlspecialchars($d['label']); ?></a>
  <?php endforeach; ?>
  <?php if (($u['role'] ?? '') === 'admin'): ?>
    <a href="users.php" class="<?php echo (($staff_title ?? '') === 'Users' || basename($_SERVER['PHP_SELF']) === 'users.php') ? 'on' : ''; ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Users &amp; Audit</span>
    </a>
  <?php endif; ?>
  <a href="profile.php" class="<?php echo ($staff_title ?? '') === 'My Account' ? 'on' : ''; ?>" style="margin-left:auto;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <span>Account</span>
  </a>
</nav>
<div class="wrap">
