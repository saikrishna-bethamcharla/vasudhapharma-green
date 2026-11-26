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
    } elseif (!empty($newPass) && strlen($newPass) < 10) {
      $err = 'New password must be at least 10 characters.';
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
  } elseif ($action === 'enable_2fa') {
    $secret = trim($_POST['totp_secret'] ?? '');
    $code = trim($_POST['totp_code'] ?? '');
    if (!$secret || !$code) {
      $err = 'Please enter the 6-digit code from your Authenticator app.';
    } else {
      $res = staff_enable_2fa($u['email'], $secret, $code);
      if ($res['ok']) {
        $msg = 'Two-Factor Authentication (2FA) is now enabled for your account.';
      } else {
        $err = $res['error'] ?? 'Failed to enable Two-Factor Authentication.';
      }
    }
  } elseif ($action === 'disable_2fa') {
    $curPass = $_POST['disable_password'] ?? '';
    if (!$curPass) {
      $err = 'Your current password is required to disable Two-Factor Authentication.';
    } else {
      $res = staff_disable_2fa($u['email'], $curPass);
      if ($res['ok']) {
        $msg = 'Two-Factor Authentication has been disabled.';
      } else {
        $err = $res['error'] ?? 'Failed to disable Two-Factor Authentication.';
      }
    }
  }
}

$usersList = staff_users();
$userRecord = null;
foreach ($usersList as $ur) {
  if (strtolower($ur['email'] ?? '') === strtolower($u['email'])) {
    $userRecord = $ur;
    break;
  }
}
$has2FA = !empty($userRecord['totp_secret']);
$pendingTotpSecret = $has2FA ? '' : staff_totp_generate_secret(16);
$totpUri = $has2FA ? '' : staff_totp_uri($pendingTotpSecret, $u['email']);

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

<div class="card" style="margin-top:24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
    <div>
      <h2 style="margin:0;font-size:17px;display:flex;align-items:center;gap:8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--sp-primary);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        Two-Factor Authentication (2FA / TOTP)
      </h2>
      <p class="hint" style="margin:4px 0 0;">
        Enhance your account security with Time-based One-Time Passwords (Google Authenticator, Microsoft Authenticator, Apple Passwords).
      </p>
    </div>
    <?php if ($has2FA): ?>
      <span style="display:inline-flex; align-items:center; gap:6px; background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:6px 14px; border-radius:20px; font-size:12.5px; font-weight:700;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
        2FA Active
      </span>
    <?php else: ?>
      <span style="display:inline-flex; align-items:center; gap:6px; background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; padding:6px 14px; border-radius:20px; font-size:12.5px; font-weight:700;">
        Not Configured
      </span>
    <?php endif; ?>
  </div>

  <?php if ($has2FA): ?>
    <div style="background:#F8FAFC; border:1px solid var(--sp-border); border-radius:12px; padding:18px 20px; margin-top:16px;">
      <p style="margin:0 0 14px; font-size:13.5px; color:var(--sp-text-main);">
        Two-factor authentication is active on your account. When signing in, you will be prompted for a 6-digit code from your authenticator app.
      </p>
      <form method="post" onsubmit="return confirm('Are you sure you want to disable Two-Factor Authentication? Your account will only be protected by password.');">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="disable_2fa">
        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
          <div>
            <label style="font-size:12px; margin-bottom:4px; display:block;">Enter Password to Disable</label>
            <input name="disable_password" type="password" required placeholder="Current password" style="width:240px; padding:8px 12px; font-size:13px;">
          </div>
          <button type="submit" class="btn" style="background:#EF4444; color:#FFFFFF; border:none; padding:9px 16px; font-size:13px; font-weight:600;">
            Disable 2FA
          </button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div style="background:#F8FAFC; border:1px solid var(--sp-border); border-radius:12px; padding:20px; margin-top:16px;">
      <h3 style="margin:0 0 8px; font-size:14.5px;">Setup Instructions:</h3>
      <ol style="margin:0 0 16px 20px; padding:0; font-size:13.5px; color:var(--sp-text-muted); line-height:1.7;">
        <li>Open your mobile Authenticator app (Google Authenticator, Microsoft Authenticator, or Bitwarden/1Password).</li>
        <li>Select <strong>Add Account</strong> &rarr; <strong>Enter a setup key manually</strong>.</li>
        <li>Account name: <strong>Vasudha Portal (<?php echo htmlspecialchars($u['email']); ?>)</strong></li>
        <li>Key / Secret: <strong style="font-family:'JetBrains Mono',monospace; letter-spacing:2px; color:var(--sp-primary); background:#E2E8F0; padding:2px 8px; border-radius:4px;"><?php echo htmlspecialchars($pendingTotpSecret); ?></strong></li>
        <li>Enter the 6-digit verification code generated by the app below to confirm setup.</li>
      </ol>

      <form method="post" autocomplete="off">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="enable_2fa">
        <input type="hidden" name="totp_secret" value="<?php echo htmlspecialchars($pendingTotpSecret); ?>">
        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
          <div>
            <label style="font-size:12px; margin-bottom:4px; display:block;">6-Digit Authenticator Code</label>
            <input name="totp_code" type="text" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="123456" required style="width:200px; padding:10px 14px; font-size:16px; font-family:'JetBrains Mono',monospace; letter-spacing:4px; text-align:center;">
          </div>
          <button type="submit" class="btn btn-primary" style="padding:10px 18px; font-size:13.5px; font-weight:600;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            Verify &amp; Activate 2FA
          </button>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
