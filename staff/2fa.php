<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';

if (staff_user()) {
  header('Location: ' . staff_default_desk_url());
  exit;
}

if (empty($_SESSION['staff_2fa_pending'])) {
  header('Location: index.php');
  exit;
}

$pending = $_SESSION['staff_2fa_pending'];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? 'verify';

  if ($action === 'cancel') {
    unset($_SESSION['staff_2fa_pending']);
    header('Location: index.php');
    exit;
  }

  $code = trim((string)($_POST['totp_code'] ?? ''));
  if (empty($code)) {
    $err = 'Please enter your 6-digit authentication code.';
  } else {
    if (staff_verify_2fa($code)) {
      header('Location: ' . staff_default_desk_url());
      exit;
    }
    $err = 'Invalid or expired verification code. Please check your Authenticator app and try again.';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Two-Factor Authentication | Vasudha Operations Portal</title>
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
    html, body { min-height: 100%; }
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
    }
    .top-accent-bar {
      position: fixed;
      top: 0; left: 0; right: 0; height: 4px;
      background: linear-gradient(90deg, #00482B 0%, #006039 30%, #10B981 70%, #34D399 100%);
      z-index: 999;
    }
    .auth-container {
      width: 100%;
      max-width: 440px;
      margin: auto;
    }
    .auth-card {
      background: var(--sp-card-bg);
      padding: 40px 36px;
      border-radius: 18px;
      border: 1px solid var(--sp-border);
      box-shadow: var(--sp-shadow-card);
      text-align: center;
    }
    .icon-wrap {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: var(--sp-primary-light);
      color: var(--sp-primary);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
    }
    h1 {
      margin: 0 0 8px;
      font-size: 22px;
      font-weight: 700;
      color: var(--sp-text-main);
    }
    p.lead {
      margin: 0 0 24px;
      font-size: 14px;
      color: var(--sp-text-muted);
      line-height: 1.5;
    }
    .user-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #F1F5F9;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      color: var(--sp-text-muted);
      margin-bottom: 24px;
    }
    .code-input {
      width: 100%;
      letter-spacing: 12px;
      font-size: 28px;
      font-weight: 700;
      font-family: 'JetBrains Mono', monospace;
      text-align: center;
      padding: 14px 16px;
      border-radius: 10px;
      border: 2px solid var(--sp-border-strong);
      background: #FAFAFA;
      color: var(--sp-text-main);
      outline: none;
      transition: all 0.2s;
    }
    .code-input:focus {
      border-color: var(--sp-primary);
      background: #FFFFFF;
      box-shadow: 0 0 0 4px rgba(0, 96, 57, 0.12);
    }
    .btn-submit {
      width: 100%;
      margin-top: 20px;
      padding: 13px 20px;
      background: var(--sp-primary);
      color: #FFFFFF;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background 0.2s;
    }
    .btn-submit:hover {
      background: var(--sp-primary-hover);
    }
    .btn-cancel {
      display: inline-block;
      margin-top: 16px;
      background: none;
      border: none;
      color: var(--sp-text-subtle);
      font-size: 13px;
      cursor: pointer;
      text-decoration: underline;
    }
    .alert-err {
      background: #FEF2F2;
      border: 1px solid #FCA5A5;
      color: #991B1B;
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 13.5px;
      margin-bottom: 20px;
      text-align: left;
    }
  </style>
</head>
<body>
  <div class="top-accent-bar"></div>
  <div class="auth-container">
    <div class="auth-card">
      <div class="icon-wrap">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>

      <h1>Two-Step Verification</h1>
      <p class="lead">Open your Authenticator app (Google Authenticator, Microsoft Authenticator, or iCloud) and enter the 6-digit code for your staff account.</p>

      <div class="user-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        <span><?php echo htmlspecialchars($pending['email']); ?></span>
      </div>

      <?php if ($err): ?>
        <div class="alert-err"><?php echo htmlspecialchars($err); ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="verify">
        <input type="text" name="totp_code" class="code-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000" autofocus required>

        <button type="submit" class="btn-submit">
          <span>Verify &amp; Sign In</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
      </form>

      <form method="post" style="display:inline;">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn-cancel">Cancel and return to login</button>
      </form>
    </div>
  </div>
</body>
</html>
