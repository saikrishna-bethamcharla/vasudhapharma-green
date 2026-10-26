<?php
require __DIR__ . '/includes/auth.php';

// If already logged in, redirect to home unless explicitly resetting
$u = staff_user();

$step = $_GET['step'] ?? 'request';
$err = '';
$msg = '';
$userKey = $_SESSION['pwd_reset_user_key'] ?? '';
$targetEmail = $_SESSION['pwd_reset_email'] ?? '';
$targetName = $_SESSION['pwd_reset_name'] ?? '';
$targetDept = $_SESSION['pwd_reset_dept'] ?? '';
$mailSent = $_SESSION['pwd_reset_mail_sent'] ?? false;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // 1. Request OTP Code
  if ($action === 'request_otp') {
    $identifier = trim($_POST['identifier'] ?? '');
    $dept = trim($_POST['dept'] ?? '');

    if (!$identifier) {
      $err = 'Please enter your corporate email address or select your department.';
      $step = 'request';
    } else {
      $res = staff_send_reset_otp($identifier, $dept);
      if ($res['ok']) {
        $_SESSION['pwd_reset_user_key'] = $res['user_key'];
        $_SESSION['pwd_reset_email']    = $res['email'];
        $_SESSION['pwd_reset_name']     = $res['name'];
        $_SESSION['pwd_reset_dept']     = $res['dept'];
        $_SESSION['pwd_reset_mail_sent'] = $res['mail_sent'];

        $userKey = $res['user_key'];
        $targetEmail = $res['email'];
        $targetName = $res['name'];
        $targetDept = $res['dept'];
        $mailSent = $res['mail_sent'];

        staff_log_audit('password_reset_otp_requested', 'Password reset verification code requested for ' . $res['email'] . ' (' . $res['dept'] . ')', $res['email'], 'Authentication');

        $msg = 'A 6-digit verification code has been securely generated for ' . htmlspecialchars($res['email']) . '. Please check your inbox.';
        $step = 'verify';
      } else {
        $err = $res['error'] ?? 'Could not find a matching staff account.';
        $step = 'request';
      }
    }
  }

  // 2. Verify OTP and Set New Password
  elseif ($action === 'verify_and_reset') {
    $otp = trim($_POST['otp'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $postUserKey = $_POST['user_key'] ?? $userKey;

    if (!$postUserKey) {
      $err = 'Session expired or invalid reset request. Please start again.';
      $step = 'request';
    } elseif (strlen($otp) !== 6 || !ctype_digit($otp)) {
      $err = 'Please enter a valid 6-digit verification code.';
      $step = 'verify';
    } elseif (strlen($newPass) < 6) {
      $err = 'New password must be at least 6 characters long.';
      $step = 'verify';
    } elseif ($newPass !== $confirmPass) {
      $err = 'New password and confirmation do not match.';
      $step = 'verify';
    } else {
      $res = staff_verify_otp_and_reset_password($postUserKey, $otp, $newPass);
      if ($res['ok']) {
        staff_log_audit('password_reset_completed', 'Password successfully reset via OTP verification for account: ' . $postUserKey, $postUserKey, 'Authentication');
        unset($_SESSION['pwd_reset_user_key'], $_SESSION['pwd_reset_email'], $_SESSION['pwd_reset_name'], $_SESSION['pwd_reset_dept'], $_SESSION['pwd_reset_mail_sent']);
        $step = 'success';
      } else {
        staff_log_audit('password_reset_failed', 'Failed OTP verification attempt for ' . $postUserKey . ': ' . ($res['error'] ?? 'Invalid code'), $postUserKey, 'Authentication', 'warning');
        $err = $res['error'] ?? 'Invalid or expired verification code.';
        $step = 'verify';
      }
    }
  }

  // 3. Resend OTP
  elseif ($action === 'resend_otp') {
    $postUserKey = $_POST['user_key'] ?? $userKey;
    if (!$postUserKey || !$targetEmail) {
      $err = 'Session expired. Please request a new verification code.';
      $step = 'request';
    } else {
      $res = staff_resend_reset_otp($postUserKey);
      if ($res['ok']) {
        $_SESSION['pwd_reset_mail_sent'] = $res['mail_sent'];
        $mailSent = $res['mail_sent'];
        $msg = 'A fresh 6-digit verification code has been generated and sent to ' . htmlspecialchars($targetEmail) . '.';
      } else {
        $err = $res['error'] ?? 'Please wait a moment before requesting another code.';
      }
    }
    $step = 'verify';
  }

  // 4. Cancel / Restart
  elseif ($action === 'restart') {
    unset($_SESSION['pwd_reset_user_key'], $_SESSION['pwd_reset_email'], $_SESSION['pwd_reset_name'], $_SESSION['pwd_reset_dept'], $_SESSION['pwd_reset_mail_sent']);
    header('Location: forgot-password.php');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password | Vasudha Operations Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --sp-primary: #006039;
      --sp-primary-dark: #00482B;
      --sp-primary-hover: #007545;
      --sp-primary-light: #ECFDF5;
      --sp-bg: #F8FAFC;
      --sp-card-bg: #FFFFFF;
      --sp-border: #E2E8F0;
      --sp-border-strong: #CBD5E1;
      --sp-text-main: #0F172A;
      --sp-text-muted: #475569;
      --sp-text-subtle: #64748B;
      --sp-shadow-card: 0 4px 6px -1px rgba(15, 23, 42, 0.04), 0 20px 40px -8px rgba(15, 23, 42, 0.08);
    }
    *, *::before, *::after { box-sizing: border-box; }
    html, body {
      min-height: 100%;
    }
    body {
      margin: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: radial-gradient(ellipse at 50% -10%, #E6F4EA 0%, #F8FAFC 60%, #EEF2F6 100%);
      color: var(--sp-text-main);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 32px 20px;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
    .top-accent-bar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #00482B 0%, #006039 30%, #10B981 70%, #34D399 100%);
      z-index: 999;
    }
    .login-container {
      width: 100%;
      max-width: 480px;
      margin: auto;
    }
    .login-card {
      background: var(--sp-card-bg);
      padding: 40px 36px;
      border-radius: 18px;
      border: 1px solid var(--sp-border);
      box-shadow: var(--sp-shadow-card);
      position: relative;
    }
    .brand-header {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      margin-bottom: 24px;
    }
    .brand-logo-wrap {
      margin-bottom: 16px;
    }
    .brand-title {
      margin: 0 0 4px;
      font-size: 20px;
      font-weight: 800;
      color: var(--sp-text-main);
      letter-spacing: -0.025em;
    }
    .brand-desc {
      margin: 0;
      color: var(--sp-text-subtle);
      font-size: 13px;
      line-height: 1.5;
    }

    /* Fast Pick Grid */
    .desk-picker-box {
      background: #F8FAFC;
      border: 1px solid var(--sp-border);
      border-radius: 12px;
      padding: 12px 14px;
      margin-bottom: 20px;
    }
    .desk-picker-title {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--sp-text-muted);
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .desk-tabs-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 6px;
    }
    .btn-pick {
      background: #FFFFFF;
      border: 1px solid var(--sp-border);
      color: var(--sp-text-muted);
      padding: 7px 8px;
      border-radius: 8px;
      font-size: 11.5px;
      font-weight: 600;
      cursor: pointer;
      text-align: center;
      transition: all 0.16s ease;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    }
    .btn-pick:hover {
      background: #F1F5F9;
      border-color: #CBD5E1;
      color: var(--sp-text-main);
    }

    /* Alerts */
    .err-alert {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      color: #991B1B;
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 20px;
      display: flex;
      align-items: flex-start;
      gap: 9px;
      line-height: 1.45;
    }
    .err-alert svg {
      flex-shrink: 0;
      margin-top: 2px;
    }
    .info-alert {
      background: #ECFDF5;
      border: 1px solid #A7F3D0;
      color: #065F46;
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 20px;
      display: flex;
      align-items: flex-start;
      gap: 9px;
      line-height: 1.45;
    }

    /* Form Fields */
    .form-group {
      margin-bottom: 18px;
    }
    label {
      display: block;
      font-size: 11.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #334155;
      margin-bottom: 6px;
    }
    .form-control {
      width: 100%;
      padding: 11px 14px;
      border: 1px solid var(--sp-border-strong);
      border-radius: 8px;
      font: inherit;
      font-size: 13.5px;
      background: #FFFFFF;
      color: var(--sp-text-main);
      transition: all 0.18s ease;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    }
    .form-control:focus {
      outline: none;
      border-color: var(--sp-primary);
      box-shadow: 0 0 0 3px rgba(0, 96, 57, 0.12);
    }
    select.form-control {
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      padding-right: 36px;
      appearance: none;
    }
    .otp-input {
      text-align: center;
      font-size: 24px;
      letter-spacing: 8px;
      font-weight: 800;
      color: #006039;
      font-family: 'JetBrains Mono', monospace;
      padding: 12px;
    }
    .eye-toggle-btn {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      padding: 6px;
      cursor: pointer;
      color: #64748B;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      transition: color 0.15s ease, background 0.15s ease;
    }
    .eye-toggle-btn:hover {
      color: var(--sp-text-main);
      background: #F1F5F9;
    }

    /* Submit Button */
    .btn-submit {
      width: 100%;
      background: linear-gradient(180deg, #006E42 0%, #005634 100%);
      color: #FFFFFF;
      border: 1px solid #004D2E;
      border-radius: 8px;
      padding: 12px 20px;
      font-size: 13.5px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 8px;
      transition: all 0.18s ease;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05), 0 4px 12px rgba(0, 96, 57, 0.22);
      text-decoration: none;
    }
    .btn-submit:hover {
      background: linear-gradient(180deg, #007D4B 0%, #005F39 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 16px rgba(0, 96, 57, 0.3);
    }

    /* Footer Meta */
    .login-footer-meta {
      margin-top: 24px;
      padding-top: 18px;
      border-top: 1px solid var(--sp-border);
      text-align: center;
    }
    .login-links {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 12px;
      color: var(--sp-text-muted);
    }
    .login-links a {
      color: var(--sp-text-muted);
      text-decoration: none;
      transition: color 0.15s ease;
    }
    .login-links a:hover {
      color: var(--sp-primary);
    }
    .success-icon-wrap {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: #ECFDF5;
      color: #059669;
      border: 2px solid #A7F3D0;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }
  </style>
  <link rel="stylesheet" href="../assets/css/heading-gradient.css">
  <link rel="stylesheet" href="../assets/css/vasudha-capsule-logo.css">
</head>
<body>
  <div class="top-accent-bar"></div>

  <div class="login-container">
    <div class="login-card">
      <div class="brand-header">
        <div class="brand-logo-wrap">
          <a href="../home.html" class="logo" title="Vasudha Pharma Chem Limited">
            <div class="logo-hex-wrap">
              <div class="logo-hex-body">
                <img src="../assets/vasudha-logo.jpg" alt="Vasudha Pharma Chem Limited" class="logo-img">
              </div>
            </div>
            <div class="logo-text">
              <strong>VASUDHA PHARMA CHEM LIMITED</strong>
              <span>Contributing to affordable health care...</span>
            </div>
          </a>
        </div>
        <h1 class="brand-title">Vasudha Operations Portal</h1>
        <p class="brand-desc">Security Verification &amp; Account Recovery Gateway</p>
      </div>

      <?php if ($err): ?>
        <div class="err-alert">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span><?php echo htmlspecialchars($err); ?></span>
        </div>
      <?php endif; ?>

      <?php if ($msg && $step !== 'success'): ?>
        <div class="info-alert">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
          <span><?php echo htmlspecialchars($msg); ?></span>
        </div>
      <?php endif; ?>

      <!-- STEP 1: REQUEST VERIFICATION CODE -->
      <?php if ($step === 'request'): ?>
        <p style="text-align:center; font-size:13px; color:var(--sp-text-muted); margin-bottom:20px;">
          Enter your corporate email address or select your department to receive a 6-digit security code.
        </p>

        <div class="desk-picker-box">
          <div class="desk-picker-title">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Select Department
          </div>
          <div class="desk-tabs-grid">
            <button type="button" class="btn-pick" onclick="onPickDept('admin', 'admin@vasudhapharma.com')">Admin</button>
            <button type="button" class="btn-pick" onclick="onPickDept('careers', 'careers@vasudhapharma.com')">Careers</button>
            <button type="button" class="btn-pick" onclick="onPickDept('marketing', 'marketing@vasudhapharma.com')">Marketing</button>
            <button type="button" class="btn-pick" onclick="onPickDept('foundation', 'foundation@vasudhapharma.com')">Foundation</button>
            <button type="button" class="btn-pick" onclick="onPickDept('documents', 'governance@vasudhapharma.com')">Governance</button>
            <button type="button" class="btn-pick" onclick="onPickDept('news', 'news@vasudhapharma.com')">News &amp; PR</button>
            <button type="button" class="btn-pick" onclick="onPickDept('admin', 'saikrishna@zailabs.co.in')">Lead Dev</button>
          </div>
        </div>

        <form method="post" autocomplete="on">
          <input type="hidden" name="action" value="request_otp">
          
          <div class="form-group">
            <label for="identInput">Corporate Email Address</label>
            <input id="identInput" class="form-control" type="email" name="identifier" placeholder="wisdom@vasudhapharma.com" value="<?php echo htmlspecialchars($targetEmail ?: 'wisdom@vasudhapharma.com'); ?>" required autofocus>
          </div>

          <div class="form-group">
            <label for="deptSelect">Target Department Desk</label>
            <select id="deptSelect" class="form-control" name="dept">
              <option value="admin">VPCL Operations Admin (All Desks)</option>
              <option value="careers">Careers Desk (Jobs &amp; Talent Community)</option>
              <option value="marketing">Marketing Desk (Products &amp; Portfolio)</option>
              <option value="foundation">Vasudha Foundation CSR</option>
              <option value="documents">Governance Desk (Secretarial &amp; MCA)</option>
              <option value="news">Corporate Media &amp; PR</option>
            </select>
          </div>

          <button type="submit" class="btn-submit">
            <span>Send Verification Code</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </form>

        <div class="login-footer-meta">
          <div class="login-links">
            <a href="index.php">&larr; Return to Staff Login</a>
            <a href="../feedback.html">System Support</a>
          </div>
        </div>

      <!-- STEP 2: VERIFY OTP AND SET NEW PASSWORD -->
      <?php elseif ($step === 'verify'): ?>
        <p style="text-align:center; font-size:13px; color:var(--sp-text-muted); margin-bottom:16px;">
          Verification code dispatched to <strong><?php echo htmlspecialchars($targetName ?: 'Staff Member'); ?></strong> (<code><?php echo htmlspecialchars($targetEmail); ?></code>).
        </p>

        <form method="post" autocomplete="off">
          <input type="hidden" name="action" value="verify_and_reset">
          <input type="hidden" name="user_key" value="<?php echo htmlspecialchars($userKey); ?>">

          <div class="form-group" style="text-align:center;">
            <label style="text-align:center;">Enter 6-Digit Code</label>
            <input class="form-control otp-input" type="text" name="otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="••••••" value="" required autofocus>
          </div>

          <div class="form-group">
            <label for="forgotPass1">New Department Password</label>
            <div style="position:relative; display:flex; align-items:center;">
              <input id="forgotPass1" class="form-control" type="password" name="new_password" minlength="6" placeholder="Enter new password (min. 6 characters)" required style="padding-right:44px;">
              <button type="button" class="eye-toggle-btn" onclick="togglePasswordVisibility('forgotPass1', 'eyeOpenF1', 'eyeClosedF1')" aria-label="Toggle password visibility">
                <svg id="eyeOpenF1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="7" r="3"></circle></svg>
                <svg id="eyeClosedF1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label for="forgotPass2">Confirm New Password</label>
            <div style="position:relative; display:flex; align-items:center;">
              <input id="forgotPass2" class="form-control" type="password" name="confirm_password" minlength="6" placeholder="Re-type new password" required style="padding-right:44px;">
              <button type="button" class="eye-toggle-btn" onclick="togglePasswordVisibility('forgotPass2', 'eyeOpenF2', 'eyeClosedF2')" aria-label="Toggle password visibility">
                <svg id="eyeOpenF2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="7" r="3"></circle></svg>
                <svg id="eyeClosedF2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-submit">
            <span>Verify Code &amp; Update Password</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
          </button>
        </form>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; font-size:12px;">
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="resend_otp">
            <input type="hidden" name="user_key" value="<?php echo htmlspecialchars($userKey); ?>">
            <button type="submit" style="background:none; border:none; color:var(--sp-primary); font-weight:600; cursor:pointer; padding:0; font-size:12px;">Resend Code</button>
          </form>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="restart">
            <button type="submit" style="background:none; border:none; color:var(--sp-text-subtle); cursor:pointer; padding:0; font-size:12px;">Change Account</button>
          </form>
        </div>

        <div class="login-footer-meta">
          <div class="login-links">
            <a href="index.php">&larr; Cancel and return to Login</a>
          </div>
        </div>

      <!-- STEP 3: SUCCESS STATE -->
      <?php elseif ($step === 'success'): ?>
        <div style="text-align:center; padding:12px 0;">
          <div class="success-icon-wrap">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <h2 style="font-size:20px; font-weight:800; color:var(--sp-text-main); margin:0 0 8px;">Password Updated Successfully!</h2>
          <p style="font-size:13.5px; color:var(--sp-text-muted); line-height:1.55; margin:0 0 24px;">
            Your department credentials have been securely updated. You can now access your Operations Desk with the new password.
          </p>

          <a class="btn-submit" href="index.php">
            <span>Proceed to Sign In</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <script>
    function onPickDept(dept, alias) {
      const inp = document.getElementById('identInput');
      const sel = document.getElementById('deptSelect');
      if (inp) inp.value = alias || 'wisdom@vasudhapharma.com';
      if (sel && dept) sel.value = dept;
    }

    function togglePasswordVisibility(inputId, openId, closedId) {
      const input = document.getElementById(inputId);
      const open = document.getElementById(openId);
      const closed = document.getElementById(closedId);
      if (!input || !open || !closed) return;
      if (input.type === 'password') {
        input.type = 'text';
        open.style.display = 'none';
        closed.style.display = 'block';
      } else {
        input.type = 'password';
        open.style.display = 'block';
        closed.style.display = 'none';
      }
    }
  </script>
</body>
</html>
