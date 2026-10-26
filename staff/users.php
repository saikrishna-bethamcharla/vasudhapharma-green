<?php
require __DIR__ . '/includes/auth.php';
staff_require_login();
$u = staff_user();
if (($u['role'] ?? '') !== 'admin') { 
  http_response_code(403); 
  echo 'Admin only.'; 
  exit; 
}

$tab = $_GET['tab'] ?? 'audit';
$msg = '';
$err = '';
$list = staff_users();
$desks = staff_desks();

// 1. CSV Export for Audit Trail
if (isset($_GET['export']) && $_GET['export'] === 'audit_csv') {
  $allLogs = staff_load_audit_log(5000);
  staff_log_audit('audit_export_csv', 'Exported system audit trail to CSV (' . count($allLogs) . ' events)', 'audit_trail.csv', 'User Admin');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="vasudha_audit_trail_' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Event ID', 'Timestamp', 'Actor Name', 'Actor Email', 'Role', 'Category', 'Action', 'Details', 'Target', 'IP Address', 'Status']);
  foreach ($allLogs as $log) {
    fputcsv($out, [
      $log['id'] ?? '',
      $log['timestamp'] ?? '',
      $log['actor_name'] ?? '',
      $log['actor_email'] ?? '',
      $log['actor_role'] ?? '',
      $log['category'] ?? 'General',
      $log['action'] ?? '',
      $log['details'] ?? '',
      $log['target'] ?? '',
      $log['ip'] ?? '127.0.0.1',
      $log['status'] ?? 'success'
    ]);
  }
  fclose($out);
  exit;
}

// 2. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';
  
  if ($action === 'add') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $dept = trim($_POST['dept'] ?? 'careers');
    $role = $dept === 'admin' ? 'admin' : ($dept === 'careers' ? 'hr' : 'user');
    $pass = $_POST['password'] ?? '';

    // Check duplicate email
    $duplicate = false;
    foreach ($list as $usr) {
      if (strtolower($usr['email']) === $email) {
        $duplicate = true;
        break;
      }
    }

    if (!$email || !$name) {
      $err = 'Full name and email are required.';
    } elseif ($duplicate) {
      $err = 'A user with this email address already exists.';
    } elseif (strlen($pass) < 6) {
      $err = 'Temporary password must be at least 6 characters.';
    } else {
      $list[] = [
        'email' => $email,
        'name' => $name,
        'role' => $role,
        'dept' => $dept === 'admin' ? 'admin' : $dept,
        'hash' => password_hash($pass, PASSWORD_DEFAULT),
      ];
      staff_save_users($list);
      staff_log_audit('user_created', "Created staff account for $name ($email) with role $role on $dept desk", $email, 'User Admin');
      $msg = 'Staff account for ' . htmlspecialchars($name) . ' was successfully created.';
    }
  }

  if ($action === 'update') {
    $old = strtolower(trim($_POST['old_email'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $dept = trim($_POST['dept'] ?? 'careers');
    $role = $dept === 'admin' ? 'admin' : ($dept === 'careers' ? 'hr' : 'user');
    $pass = $_POST['password'] ?? '';
    
    // Prevent admin from removing their own admin privilege
    if ($old === strtolower($u['email']) && $dept !== 'admin') {
      $err = 'You cannot demote your own administrator account.';
    } else {
      $ok = false;
      foreach ($list as $i => $row) {
        if (strtolower($row['email']) !== $old) continue;
        $list[$i]['email'] = $email ?: $row['email'];
        $list[$i]['name'] = $name ?: $row['name'];
        $list[$i]['dept'] = $dept === 'admin' ? 'admin' : $dept;
        $list[$i]['role'] = $role;
        if (!empty($pass)) {
          if (strlen($pass) < 6) {
            $err = 'New password must be at least 6 characters.';
            $ok = false;
            break;
          }
          $list[$i]['hash'] = password_hash($pass, PASSWORD_DEFAULT);
        }
        $ok = true;
        if ($old === strtolower($u['email'])) {
          $_SESSION['staff']['email'] = $list[$i]['email'];
          $_SESSION['staff']['name'] = $list[$i]['name'];
          $_SESSION['staff']['dept'] = $list[$i]['dept'];
          $_SESSION['staff']['role'] = $list[$i]['role'];
        }
      }
      if ($ok) {
        staff_save_users($list);
        staff_log_audit('user_updated', "Updated staff account details for $email" . (!empty($pass) ? ' (password reset)' : ''), $email, 'User Admin');
        $msg = 'User details successfully updated.';
      } elseif (!$err) {
        $err = 'User record not found.';
      }
    }
  }

  if ($action === 'delete') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if ($email === strtolower($u['email'])) {
      $err = 'You cannot delete your own logged-in account.';
    } else {
      $list = array_values(array_filter($list, fn($x) => strtolower($x['email']) !== $email));
      staff_save_users($list);
      staff_log_audit('user_deleted', "Removed staff account $email", $email, 'User Admin', 'warning');
      $msg = 'Account has been removed.';
    }
  }

  if ($action === 'send_reset_otp') {
    $targetId = trim($_POST['target_id'] ?? '');
    $res = staff_send_reset_otp($targetId);
    if ($res['ok']) {
      staff_log_audit('otp_dispatched', "Dispatched password reset OTP email to " . $res['email'], $res['email'], 'Authentication');
      $msg = 'Password reset OTP was sent to ' . htmlspecialchars($res['email']) . ' for ' . htmlspecialchars($res['name']) . '.';
    } else {
      $err = $res['error'] ?? 'Failed to send OTP.';
    }
  }

  if ($action === 'archive_audit') {
    $currentLogs = staff_load_audit_log(5000);
    $archiveFile = staff_root() . '/data/audit_archive_' . date('Ymd_His') . '.json';
    @file_put_contents($archiveFile, json_encode($currentLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    $resetEntry = [
      'id' => 'AUD-' . date('Ymd') . '-' . bin2hex(random_bytes(3)),
      'timestamp' => date('Y-m-d H:i:s'),
      'actor_email' => $u['email'],
      'actor_name' => $u['name'],
      'actor_role' => 'admin',
      'category' => 'User Admin',
      'action' => 'audit_archived',
      'details' => 'Archived ' . count($currentLogs) . ' events to ' . basename($archiveFile) . ' and reset active log',
      'target' => basename($archiveFile),
      'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
      'status' => 'info'
    ];
    @file_put_contents(staff_audit_file(), json_encode([$resetEntry], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $msg = 'Audit log successfully archived and reset.';
  }
}

$auditLogs = staff_load_audit_log(1000);
$pageTitle = ($tab === 'audit') ? 'Security & Activity Audit Trail' : 'User Management';
require __DIR__ . '/includes/header.php'; 
?>

<!-- Executive Desk Header Banner -->
<div class="desk-hero-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; position:relative; z-index:1;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.1em; opacity:0.85; font-weight:700; margin-bottom:6px;">
        Security Administration &bull; Access Governance
      </div>
      <h1 style="margin:0 0 4px; font-size:24px; color:#FFFFFF;">
        <?php echo ($tab === 'audit') ? 'System Audit Trail &amp; Activity Log' : 'Staff Accounts &amp; Role-Based Access'; ?>
      </h1>
      <p style="margin:0; opacity:0.9; font-size:13px; color:#E2E8F0; max-width:800px; line-height:1.5;">
        Configure department credentials, inspect real-time audit trails, review role isolation, and manage operations staff.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <?php if ($tab === 'audit'): ?>
        <a href="users.php?tab=audit&export=audit_csv" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export Audit Log (CSV)
        </a>
      <?php else: ?>
        <a href="#userForm" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          + Add Staff Account
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Tab Switcher Bar -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
  <div style="display:inline-flex; background:#E2E8F0; padding:4px; border-radius:10px; gap:4px;">
    <a href="users.php?tab=audit" style="display:inline-flex; align-items:center; gap:8px; padding:8px 18px; border-radius:8px; font-size:13.5px; font-weight:600; text-decoration:none; transition:all 0.15s; <?php echo ($tab === 'audit') ? 'background:#0E8F6C; color:#FFFFFF; box-shadow:0 2px 6px rgba(14,143,108,0.25);' : 'color:#475569; background:transparent;'; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><circle cx="12" cy="11" r="3"/></svg>
      <span>Activity &amp; Security Audit Trail</span>
      <span style="font-size:11px; padding:2px 7px; border-radius:999px; <?php echo ($tab === 'audit') ? 'background:rgba(255,255,255,0.25); color:#fff;' : 'background:#CBD5E1; color:#334155;'; ?>"><?php echo count($auditLogs); ?></span>
    </a>
    <a href="users.php?tab=users" style="display:inline-flex; align-items:center; gap:8px; padding:8px 18px; border-radius:8px; font-size:13.5px; font-weight:600; text-decoration:none; transition:all 0.15s; <?php echo ($tab === 'users') ? 'background:#0E8F6C; color:#FFFFFF; box-shadow:0 2px 6px rgba(14,143,108,0.25);' : 'color:#475569; background:transparent;'; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Staff Accounts &amp; Roles</span>
      <span style="font-size:11px; padding:2px 7px; border-radius:999px; <?php echo ($tab === 'users') ? 'background:rgba(255,255,255,0.25); color:#fff;' : 'background:#CBD5E1; color:#334155;'; ?>"><?php echo count($list); ?></span>
    </a>
  </div>

  <?php if ($tab === 'audit'): ?>
    <div style="display:flex; gap:8px;">
      <a href="users.php?tab=audit&export=audit_csv" class="btn" style="background:#0E8F6C; color:#FFFFFF; font-weight:600; font-size:12.5px; display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; box-shadow:0 2px 8px rgba(14,143,108,0.2); text-decoration:none;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Export Audit Log (CSV)</span>
      </a>
      <form method="post" style="display:inline" onsubmit="return confirm('Archive current audit trail events to JSON and initialize a clean active log?');">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="archive_audit">
        <button class="btn btn-ghost" style="padding:8px 12px; font-size:12px; color:#64748B;">Archive &amp; Reset</button>
      </form>
    </div>
  <?php else: ?>
    <a href="#userForm" class="btn btn-primary" style="font-size:12.5px; display:inline-flex; align-items:center; gap:6px;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <span>Add New Staff Account</span>
    </a>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success" style="background:#ECFDF5; border:1px solid #A7F3D0; color:#065F46; padding:12px 16px; border-radius:8px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 6L9 17l-5-5"/></svg>
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="alert alert-danger" style="background:#FEF2F2; border:1px solid #FECACA; color:#991B1B; padding:12px 16px; border-radius:8px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?php echo htmlspecialchars($err); ?></span>
  </div>
<?php endif; ?>

<?php if ($tab === 'audit'): ?>
  <!-- AUDIT TRAIL TAB -->
  <?php
    $totalEvents = count($auditLogs);
    $uniqueActors = count(array_unique(array_filter(array_column($auditLogs, 'actor_email'))));
    $govCount = count(array_filter($auditLogs, fn($l) => in_array($l['category'] ?? '', ['Corporate Governance', 'Governance'])));
    $hrCount = count(array_filter($auditLogs, fn($l) => in_array($l['category'] ?? '', ['Candidate ATS', 'Talent Community', 'Careers & Requisitions'])));
    $authCount = count(array_filter($auditLogs, fn($l) => in_array($l['category'] ?? '', ['Authentication', 'User Admin'])));
    $foundationCount = count(array_filter($auditLogs, fn($l) => ($l['category'] ?? '') === 'Vasudha Foundation'));
  ?>

  <!-- Metric Summary Cards -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:14px; margin-bottom:20px;">
    <div class="card" style="margin:0; padding:18px; border-left:4px solid #0E8F6C;">
      <div style="font-size:11.5px; font-weight:700; text-transform:uppercase; color:#64748B; letter-spacing:0.04em;">Total Logged Events</div>
      <div style="font-size:26px; font-weight:800; color:#0F172A; margin:6px 0 2px;"><?php echo $totalEvents; ?></div>
      <div style="font-size:12px; color:#0E8F6C; font-weight:500;">Immutable system log</div>
    </div>
    <div class="card" style="margin:0; padding:18px; border-left:4px solid #2563EB;">
      <div style="font-size:11.5px; font-weight:700; text-transform:uppercase; color:#64748B; letter-spacing:0.04em;">Careers &amp; Candidate Actions</div>
      <div style="font-size:26px; font-weight:800; color:#0F172A; margin:6px 0 2px;"><?php echo $hrCount; ?></div>
      <div style="font-size:12px; color:#2563EB; font-weight:500;">Status moves &amp; talent bench</div>
    </div>
    <div class="card" style="margin:0; padding:18px; border-left:4px solid #7C3AED;">
      <div style="font-size:11.5px; font-weight:700; text-transform:uppercase; color:#64748B; letter-spacing:0.04em;">Corporate Governance</div>
      <div style="font-size:26px; font-weight:800; color:#0F172A; margin:6px 0 2px;"><?php echo $govCount; ?></div>
      <div style="font-size:12px; color:#7C3AED; font-weight:500;">Board updates &amp; filings</div>
    </div>
    <div class="card" style="margin:0; padding:18px; border-left:4px solid #D97706;">
      <div style="font-size:11.5px; font-weight:700; text-transform:uppercase; color:#64748B; letter-spacing:0.04em;">Security &amp; Auth Events</div>
      <div style="font-size:26px; font-weight:800; color:#0F172A; margin:6px 0 2px;"><?php echo $authCount; ?></div>
      <div style="font-size:12px; color:#D97706; font-weight:500;">Logins, OTPs &amp; admin controls</div>
    </div>
  </div>

  <div class="card hint" style="margin-bottom:20px;">
    <strong>Centralized Governance &amp; Regulatory Audit Trail:</strong> This desk maintains an immutable chronological record of every critical action executed across the Vasudha Operations Portal (including Annual Report replacements, candidate stage transitions, commercial lead exports, and authentication requests). Visible strictly to Administrators.
  </div>

  <!-- Search & Category Filters -->
  <div class="card" style="padding:16px 20px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
      <div style="flex:1; min-width:280px; position:relative;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2.5" style="position:absolute; left:12px; top:50%; transform:translateY(-50%);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="auditSearchInput" placeholder="Filter by actor, keyword, event ID, details, or IP..." style="padding-left:36px; background:#FFFFFF; margin:0;" oninput="filterAuditTable()">
      </div>
      <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <button type="button" class="btn btn-ghost audit-cat-pill active" onclick="setAuditCat('ALL', this)" style="font-size:12px; padding:6px 12px; border-radius:999px; background:#0E8F6C; color:#fff; font-weight:600;">All Events</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('Candidate ATS', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">Candidate ATS</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('Talent Community', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">Talent Pool</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('Careers & Requisitions', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">Job Requisitions</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('Corporate Governance', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">Governance</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('Vasudha Foundation', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">Foundation</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('Commercial Leads', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">Commercial Leads</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('News & PR', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">News &amp; PR</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('Authentication', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">Auth &amp; Security</button>
        <button type="button" class="btn btn-ghost audit-cat-pill" onclick="setAuditCat('User Admin', this)" style="font-size:12px; padding:6px 12px; border-radius:999px;">User Admin</button>
      </div>
    </div>
  </div>

  <!-- Audit Table -->
  <div class="card" style="padding:0; overflow:hidden;">
    <div style="overflow-x:auto;">
      <table style="margin:0;" id="auditTable">
        <thead>
          <tr style="background:#F8FAFC;">
            <th style="width:130px;">Event ID</th>
            <th style="width:160px;">Timestamp</th>
            <th style="width:220px;">Actor / Desk</th>
            <th style="width:140px;">Category</th>
            <th style="width:180px;">Action Type</th>
            <th>Details &amp; Subject Target</th>
            <th style="width:110px; text-align:right;">Status &amp; IP</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($auditLogs)): ?>
          <tr>
            <td colspan="7" style="text-align:center; padding:36px; color:#64748B;">No audit trail events recorded yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($auditLogs as $log): 
            $cat = $log['category'] ?? 'General';
            $act = $log['action'] ?? '';
            $status = $log['status'] ?? 'success';

            // Category color
            $catBg = '#F1F5F9';
            $catColor = '#475569';
            if ($cat === 'Candidate ATS') { $catBg = '#EFF6FF'; $catColor = '#1D4ED8'; }
            elseif ($cat === 'Talent Community') { $catBg = '#ECFDF5'; $catColor = '#047857'; }
            elseif ($cat === 'Corporate Governance' || $cat === 'Governance') { $catBg = '#F5F3FF'; $catColor = '#6D28D9'; }
            elseif ($cat === 'Vasudha Foundation') { $catBg = '#FEF3C7'; $catColor = '#92400E'; }
            elseif ($cat === 'Careers & Requisitions') { $catBg = '#E0E7FF'; $catColor = '#3730A3'; }
            elseif ($cat === 'News & PR') { $catBg = '#FCE7F3'; $catColor = '#9D174D'; }
            elseif ($cat === 'Commercial Leads') { $catBg = '#FFFBEB'; $catColor = '#B45309'; }
            elseif ($cat === 'Authentication') { $catBg = '#FEF2F2'; $catColor = '#B91C1C'; }
            elseif ($cat === 'User Admin') { $catBg = '#EEF2FF'; $catColor = '#4338CA'; }

            // Action color badge
            $actBg = '#F1F5F9';
            $actColor = '#334155';
            if (strpos($act, 'export') !== false) { $actBg = '#F3E8FF'; $actColor = '#7E22CE'; }
            elseif (strpos($act, 'upload') !== false || strpos($act, 'created') !== false || strpos($act, 'published') !== false) { $actBg = '#DCFCE7'; $actColor = '#15803D'; }
            elseif (strpos($act, 'status') !== false || strpos($act, 'modified') !== false || strpos($act, 'updated') !== false || strpos($act, 'reorder') !== false) { $actBg = '#DBEAFE'; $actColor = '#1E40AF'; }
            elseif (strpos($act, 'delete') !== false || strpos($act, 'failed') !== false) { $actBg = '#FEE2E2'; $actColor = '#991B1B'; }
          ?>
            <tr class="audit-row" data-cat="<?php echo htmlspecialchars($cat); ?>">
              <td>
                <span style="font-family:monospace; font-size:11.5px; font-weight:700; color:#334155; background:#F1F5F9; padding:2px 6px; border-radius:4px;">
                  <?php echo htmlspecialchars($log['id'] ?? ''); ?>
                </span>
              </td>
              <td>
                <div style="font-size:12.5px; font-weight:600; color:#0F172A;"><?php echo htmlspecialchars(date('d M Y', strtotime($log['timestamp'] ?? 'now'))); ?></div>
                <div style="font-size:11px; color:#64748B;"><?php echo htmlspecialchars(date('H:i:s T', strtotime($log['timestamp'] ?? 'now'))); ?></div>
              </td>
              <td>
                <div style="font-weight:600; font-size:13px; color:#0F172A;"><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></div>
                <div style="font-size:11.5px; color:#64748B;"><?php echo htmlspecialchars($log['actor_email'] ?? ''); ?></div>
                <span style="font-size:10px; font-weight:700; text-transform:uppercase; padding:1px 6px; border-radius:4px; background:#F1F5F9; color:#475569; display:inline-block; margin-top:2px;">
                  <?php echo htmlspecialchars($log['actor_role'] ?? 'user'); ?>
                </span>
              </td>
              <td>
                <span style="font-size:11.5px; font-weight:600; padding:3px 9px; border-radius:999px; background:<?php echo $catBg; ?>; color:<?php echo $catColor; ?>; display:inline-block; white-space:nowrap;">
                  <?php echo htmlspecialchars($cat); ?>
                </span>
              </td>
              <td>
                <span style="font-family:monospace; font-size:11px; font-weight:700; padding:3px 8px; border-radius:6px; background:<?php echo $actBg; ?>; color:<?php echo $actColor; ?>; display:inline-block; white-space:nowrap;">
                  <?php echo htmlspecialchars($act); ?>
                </span>
              </td>
              <td>
                <div style="font-size:13px; line-height:1.4; color:#1E293B; font-weight:500;">
                  <?php echo htmlspecialchars($log['details'] ?? ''); ?>
                </div>
                <?php if (!empty($log['target'])): ?>
                  <div style="margin-top:3px;">
                    <span style="font-size:11px; color:#64748B;">Target: </span>
                    <span style="font-family:monospace; font-size:11px; font-weight:600; color:#0E8F6C; background:#ECFDF5; padding:1px 5px; border-radius:3px;">
                      <?php echo htmlspecialchars($log['target']); ?>
                    </span>
                  </div>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <div>
                  <span style="font-size:10.5px; font-weight:700; text-transform:uppercase; padding:2px 6px; border-radius:4px; <?php echo ($status === 'warning') ? 'background:#FEF2F2; color:#B91C1C;' : (($status === 'info') ? 'background:#EFF6FF; color:#1D4ED8;' : 'background:#DCFCE7; color:#15803D;'); ?>">
                    <?php echo htmlspecialchars($status); ?>
                  </span>
                </div>
                <div style="font-family:monospace; font-size:10.5px; color:#94A3B8; margin-top:3px;">
                  <?php echo htmlspecialchars($log['ip'] ?? '127.0.0.1'); ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    var currentCat = 'ALL';
    function setAuditCat(cat, btn) {
      currentCat = cat;
      document.querySelectorAll('.audit-cat-pill').forEach(function(b){
        b.style.background = 'transparent';
        b.style.color = '#475569';
        b.style.fontWeight = '500';
      });
      btn.style.background = '#0E8F6C';
      btn.style.color = '#FFFFFF';
      btn.style.fontWeight = '600';
      filterAuditTable();
    }

    function filterAuditTable() {
      var query = (document.getElementById('auditSearchInput').value || '').toLowerCase().trim();
      var rows = document.querySelectorAll('#auditTable tbody tr.audit-row');
      rows.forEach(function(r) {
        var cat = r.getAttribute('data-cat') || '';
        var text = r.textContent.toLowerCase();
        var catMatch = (currentCat === 'ALL') || (cat.toLowerCase() === currentCat.toLowerCase());
        var queryMatch = !query || (text.indexOf(query) !== -1);
        r.style.display = (catMatch && queryMatch) ? '' : 'none';
      });
    }
  </script>

<?php else: ?>
  <!-- USERS DIRECTORY TAB -->
  <div class="card hint" style="margin-bottom:20px;">
    <strong>Staff User Directory &amp; Role-Based Access:</strong> Administrators have full access across all operations desks. Department staff logins are strictly constrained to their operational responsibilities.
  </div>

  <?php
  $editEmail = strtolower(trim($_GET['edit'] ?? ''));
  $edit = null;
  if ($editEmail) {
    foreach ($list as $usr) {
      if (strtolower($usr['email']) === $editEmail) {
        $edit = $usr;
        break;
      }
    }
  }
  ?>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h2 style="margin:0; font-size:18px;">Configured Accounts (<?php echo count($list); ?>)</h2>
      <a href="#userForm" class="btn btn-primary" style="font-size:12.5px;">+ Add Staff</a>
    </div>

    <table>
      <thead>
        <tr>
          <th>Staff Member</th>
          <th>Corporate Email</th>
          <th>Assigned Desk</th>
          <th>System Role</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($list as $row): 
        $isSelf = (strtolower($row['email']) === strtolower($u['email']));
        $deskLabel = ($row['dept'] === 'admin') ? 'All Desks (Executive Admin)' : ($desks[$row['dept']]['label'] ?? ucfirst($row['dept'])) . ' Desk';
      ?>
        <tr>
          <td>
            <div style="font-weight:600; color:var(--sp-text-main);">
              <?php echo htmlspecialchars($row['name']); ?>
              <?php if ($isSelf): ?>
                <span style="font-size:11px; color:var(--sp-primary); font-weight:700;">(You)</span>
              <?php endif; ?>
            </div>
          </td>
          <td><code><?php echo htmlspecialchars($row['email']); ?></code></td>
          <td>
            <span style="font-weight:500;"><?php echo htmlspecialchars($deskLabel); ?></span>
          </td>
          <td>
            <span style="text-transform:uppercase; font-size:11px; font-weight:700; letter-spacing:0.04em; background:<?php echo ($row['role'] === 'admin') ? '#ECFDF5' : '#F1F5F9'; ?>; color:<?php echo ($row['role'] === 'admin') ? 'var(--sp-primary)' : '#475569'; ?>; padding:3px 8px; border-radius:6px;">
              <?php echo htmlspecialchars($row['role']); ?>
            </span>
          </td>
          <td style="text-align:right; white-space:nowrap;">
            <form method="post" style="display:inline" onsubmit="return confirm('Send a 6-digit password reset OTP email to <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['email']); ?>)?');">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="send_reset_otp">
              <input type="hidden" name="target_id" value="<?php echo htmlspecialchars($row['alias'] ?: $row['email']); ?>">
              <button class="btn btn-ghost" style="padding:5px 9px; font-size:12px; color:var(--sp-primary); font-weight:600;" title="Send Password Reset OTP to staff email">Send OTP</button>
            </form>
            <a class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" href="users.php?tab=users&edit=<?php echo urlencode($row['email']); ?>#userForm">Edit</a>
            <?php if (!$isSelf): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Permanently remove access for <?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['email']); ?>)?');">
                <?php echo staff_csrf_field(); ?>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($row['email']); ?>">
                <button class="btn btn-danger" style="padding:5px 9px; font-size:12px;" name="action" value="delete">Remove</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card" id="userForm">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
      <h2 style="margin:0; font-size:18px;"><?php echo $edit ? ('Edit Staff: ' . htmlspecialchars($edit['name'])) : 'Add New Staff Member'; ?></h2>
      <?php if ($edit): ?>
        <a class="btn btn-ghost" href="users.php?tab=users" style="font-size:12px">Cancel Edit</a>
      <?php endif; ?>
    </div>
    
    <form method="post" action="users.php?tab=users">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'add'; ?>">
      <input type="hidden" name="old_email" value="<?php echo htmlspecialchars($edit['email'] ?? ''); ?>">
      
      <div class="row">
        <div>
          <label>Full Name</label>
          <input id="userNameInput" name="name" required placeholder="e.g. John Doe" value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>">
        </div>
        <div>
          <label>Corporate Email Address</label>
          <input type="email" name="email" required placeholder="name@vasudhapharma.com" value="<?php echo htmlspecialchars($edit['email'] ?? ''); ?>">
        </div>
      </div>

      <div class="row">
        <div>
          <label>Assigned Department Desk</label>
          <select name="dept">
            <option value="admin" <?php echo (($edit['dept'] ?? '')==='admin')?'selected':''; ?>>Administrator (Access to All Desks &amp; User Control)</option>
            <optgroup label="Department Specific Desks">
              <?php foreach ($desks as $k => $d): ?>
                <option value="<?php echo htmlspecialchars($k); ?>" <?php echo (($edit['dept'] ?? '')===$k)?'selected':''; ?>>
                  <?php echo htmlspecialchars($d['label']); ?> Desk
                </option>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </div>
        <div>
          <label><?php echo $edit ? 'Reset Password (leave blank to keep current)' : 'Temporary Initial Password'; ?></label>
          <input name="password" type="password" <?php echo $edit ? '' : 'required'; ?> minlength="6" placeholder="At least 6 characters">
        </div>
      </div>

      <p style="margin-top:22px">
        <button class="btn btn-primary" type="submit">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <?php echo $edit ? 'Save User Changes' : 'Create Staff Login'; ?>
        </button>
        <?php if ($edit): ?>
          <a class="btn btn-ghost" href="users.php?tab=users">Cancel</a>
        <?php endif; ?>
      </p>
    </form>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
