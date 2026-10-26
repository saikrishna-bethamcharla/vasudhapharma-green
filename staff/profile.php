<?php
require __DIR__ . '/includes/auth.php';
staff_require_login();
$u = staff_user();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';
  if ($action === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $curPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($curPass)) {
      $err = 'Current password is required to save any changes.';
    } elseif (!empty($newPass) && $newPass !== $confirmPass) {
      $err = 'New password and confirmation do not match.';
    } elseif (!empty($newPass) && strlen($newPass) < 6) {
      $err = 'New password must be at least 6 characters.';
    } else {
      $res = staff_update_profile($u['email'], $name, $curPass, $newPass);
      if ($res['ok']) {
        staff_log_audit('profile_updated', 'Profile updated for ' . $u['email'] . (!empty($newPass) ? ' (password changed)' : ''), $u['email'], 'Authentication');
        $msg = 'Your profile and password have been successfully updated.';
        $u = staff_user(); // Refresh session data
      } else {
        staff_log_audit('profile_update_failed', 'Profile update failed for ' . $u['email'] . ': ' . ($res['error'] ?? 'Unknown error'), $u['email'], 'Authentication', 'warning');
        $err = $res['error'] ?? 'Failed to update profile.';
      }
    }
  }
}

$staff_title = 'My Account';
require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
  <div class="card ok">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
    <span><?php echo htmlspecialchars($msg); ?></span>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="card err-box">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?php echo htmlspecialchars($err); ?></span>
  </div>
<?php endif; ?>

<div class="desk-hero-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; position:relative; z-index:1;">
    <div style="display:flex; align-items:center; gap:16px;">
      <div style="width:54px; height:54px; border-radius:50%; background:#FFFFFF; color:var(--sp-primary); display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:800; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        <?php echo strtoupper(substr($u['name'] ?? 'S', 0, 1)); ?>
      </div>
      <div>
        <h1 style="margin:0 0 4px; font-size:22px; color:#FFFFFF;"><?php echo htmlspecialchars($u['name'] ?? 'Staff Member'); ?></h1>
        <p style="margin:0; font-size:13px; color:#E2E8F0;">
          <span><?php echo htmlspecialchars($u['email']); ?></span> &bull; 
          <span style="text-transform:capitalize; font-weight:600;"><?php echo htmlspecialchars($u['dept'] ?? 'Desk'); ?></span> Desk &bull;
          <span style="text-transform:uppercase; font-size:11px; background:rgba(255,255,255,0.2); color:#FFFFFF; padding:2px 8px; border-radius:10px; font-weight:700;"><?php echo htmlspecialchars($u['role'] ?? 'user'); ?></span>
        </p>
      </div>
    </div>
    <div>
      <a class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);" href="<?php echo htmlspecialchars(staff_default_desk_url($u ?? null)); ?>">
        &larr; Back to <?php echo (($u['role'] ?? '') === 'admin') ? 'Dashboard' : 'Desk'; ?>
      </a>
    </div>
  </div>
</div>
  </div>
</div>

<div class="card">
  <h2 style="margin-top:0;font-size:17px;">Account Credentials &amp; Profile</h2>
  <p class="hint" style="margin-bottom:20px;">
    Update your display name or set a new password. Your current password is required for security verification.
  </p>
  <form method="post" autocomplete="off">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="update_profile">

    <div class="row">
      <div>
        <label>Your Name</label>
        <input name="name" type="text" value="<?php echo htmlspecialchars($u['name'] ?? ''); ?>" required>
      </div>
      <div>
        <label>Email Address</label>
        <input type="email" value="<?php echo htmlspecialchars($u['email']); ?>" disabled style="background:#F1F5F9;cursor:not-allowed;" title="Email can only be changed by an administrator.">
      </div>
    </div>

    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--sp-border);">
      <div class="row">
        <div>
          <label>New Password <span style="font-weight:400;color:var(--sp-text-muted);">(Leave blank to keep current password)</span></label>
          <input name="new_password" type="password" minlength="6" placeholder="At least 6 characters">
        </div>
        <div>
          <label>Confirm New Password</label>
          <input name="confirm_password" type="password" minlength="6" placeholder="Re-type new password">
        </div>
      </div>
    </div>

    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--sp-border);">
      <div style="max-width:380px;">
        <label style="color:#B91C1C;">Current Password <span style="font-weight:400;">(Required to confirm changes)</span></label>
        <input name="current_password" type="password" required placeholder="Enter current password">
        <div style="margin-top:6px;">
          <a href="forgot-password.php" style="font-size:12px; color:var(--sp-primary); text-decoration:none; font-weight:600;">Forgot current password? Reset via Email OTP &rarr;</a>
        </div>
      </div>
    </div>

    <p style="margin-top:24px;">
      <button class="btn btn-primary" type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Account Changes
      </button>
      <a class="btn btn-ghost" href="<?php echo htmlspecialchars(staff_default_desk_url($u ?? null)); ?>">Back to <?php echo (($u['role'] ?? '') === 'admin') ? 'Dashboard' : 'Desk'; ?></a>
    </p>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
