<?php
require __DIR__ . '/includes/auth.php';
if (staff_user()) {
  header('Location: ' . staff_default_desk_url());
  exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $throttle = staff_check_login_throttle();
  if ($throttle['blocked']) {
    $err = 'Too many failed login attempts from your IP address. For security, please wait ' . $throttle['wait_mins'] . ' minute(s) before trying again.';
  } else {
    $ok = staff_login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($ok) {
      header('Location: ' . staff_default_desk_url());
      exit;
    }
    $err = 'Invalid email or password. Please verify your credentials and try again.';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Login | Vasudha Operations Portal</title>
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
      margin-bottom: 28px;
    }
    .brand-logo-wrap {
      margin-bottom: 18px;
    }
    .brand-title {
      margin: 0 0 6px;
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

    /* Fast Desk Switcher Bar */
    .desk-selector-section {
      background: #F8FAFC;
      border: 1px solid var(--sp-border);
      border-radius: 12px;
      padding: 12px 14px;
      margin-bottom: 24px;
    }
    .desk-selector-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }
    .desk-selector-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--sp-text-muted);
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .active-desk-pill {
      font-size: 11px;
      font-weight: 600;
      color: var(--sp-primary);
      background: var(--sp-primary-light);
      border: 1px solid #A7F3D0;
      padding: 2px 8px;
      border-radius: 10px;
    }
    .desk-tabs-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 6px;
    }
    .btn-desk-tab {
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
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    }
    .btn-desk-tab:hover {
      background: #F1F5F9;
      border-color: #CBD5E1;
      color: var(--sp-text-main);
    }
    .btn-desk-tab.active {
      background: var(--sp-primary);
      border-color: var(--sp-primary-dark);
      color: #FFFFFF;
      box-shadow: 0 2px 6px rgba(0, 96, 57, 0.25);
    }

    /* Error Notification */
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

    /* Form Inputs */
    .form-group {
      margin-bottom: 18px;
    }
    .form-label-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
    }
    .form-label {
      font-size: 11.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #334155;
    }
    .forgot-link {
      font-size: 12px;
      color: var(--sp-primary);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.15s ease;
    }
    .forgot-link:hover {
      color: var(--sp-primary-hover);
      text-decoration: underline;
    }
    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .input-icon {
      position: absolute;
      left: 13px;
      color: #94A3B8;
      pointer-events: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .form-control {
      width: 100%;
      padding: 11px 14px 11px 40px;
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
    }
    .btn-submit:hover {
      background: linear-gradient(180deg, #007D4B 0%, #005F39 100%);
      transform: translateY(-1px);
      box-shadow: 0 4px 16px rgba(0, 96, 57, 0.3);
    }

    /* Security Note & Links */
    .login-footer-meta {
      margin-top: 24px;
      padding-top: 18px;
      border-top: 1px solid var(--sp-border);
      text-align: center;
    }
    .security-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11.5px;
      color: #64748B;
      margin-bottom: 12px;
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
        <p class="brand-desc">Secure administrative gateway for authorized operations personnel.</p>
      </div>

      <?php if ($err): ?>
        <div class="err-alert">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span><?php echo htmlspecialchars($err); ?></span>
        </div>
      <?php endif; ?>

      <!-- Fast Desk Switcher -->
      <div class="desk-selector-section">
        <div class="desk-selector-header">
          <span class="desk-selector-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Fast Desk Switch
          </span>
          <span id="activeDeskBadge" class="active-desk-pill">System Admin</span>
        </div>
        <div class="desk-tabs-grid">
          <button type="button" class="btn-desk-tab active" onclick="onSelectDept('admin', 'System Admin')" id="btn-admin">Admin</button>
          <button type="button" class="btn-desk-tab" onclick="onSelectDept('hr', 'HR & Talent Desk')" id="btn-hr">HR Desk</button>
          <button type="button" class="btn-desk-tab" onclick="onSelectDept('marketing', 'Sales & Marketing')" id="btn-marketing">Marketing</button>
          <button type="button" class="btn-desk-tab" onclick="onSelectDept('foundation', 'Vasudha Foundation')" id="btn-foundation">Foundation</button>
          <button type="button" class="btn-desk-tab" onclick="onSelectDept('compliance', 'Governance Desk')" id="btn-compliance">Compliance</button>
          <button type="button" class="btn-desk-tab" onclick="onSelectDept('news', 'Media & PR Desk')" id="btn-news">News &amp; PR</button>
          <button type="button" class="btn-desk-tab" onclick="onSelectDept('dev', 'Lead Developer')" id="btn-dev" style="grid-column: span 3;">Lead Developer Desk</button>
        </div>
      </div>

      <form method="post" autocomplete="on">
        <?php echo staff_csrf_field(); ?>
        
        <div class="form-group">
          <div class="form-label-row">
            <label class="form-label" for="emailInput">Corporate Email Address</label>
          </div>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </span>
            <input id="emailInput" class="form-control" type="email" name="email" placeholder="wisdom@vasudhapharma.com" value="wisdom@vasudhapharma.com" required autofocus>
          </div>
        </div>

        <div class="form-group">
          <div class="form-label-row">
            <label class="form-label" for="passInput">Department Password</label>
            <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
          </div>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <input id="passInput" class="form-control" type="password" name="password" placeholder="Enter assigned password" value="Vasudha@Admin2026!" required style="padding-right: 44px;">
            <button type="button" id="togglePassBtn" class="eye-toggle-btn" onclick="togglePasswordVisibility('passInput', 'eyeOpenLogin', 'eyeClosedLogin')" aria-label="Toggle password visibility" title="Show/Hide Password">
              <svg id="eyeOpenLogin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              <svg id="eyeClosedLogin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <span>Authenticate &amp; Enter Desk</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </form>

      <div class="login-footer-meta">
        <div class="security-badge">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>TLS 1.3 256-Bit Encrypted &bull; Role-Based Isolation</span>
        </div>
        <div class="login-links">
          <a href="../home.html">&larr; Public Website</a>
          <a href="forgot-password.php">Reset via OTP &rarr;</a>
          <a href="../feedback.html">Testing Feedback</a>
        </div>
      </div>
    </div>
  </div>

  <script>
    const DEPT_PASSWORDS = {
      admin: 'Vasudha@Admin2026!',
      marketing: 'Vasudha@Marketing2026!',
      hr: 'Vasudha@HR2026!',
      foundation: 'Vasudha@CSR2026!',
      compliance: 'Vasudha@Compliance2026!',
      news: 'Vasudha@News2026!',
      dev: 'Vasudha@Dev2026!'
    };

    function onSelectDept(dept, deskName) {
      if (!dept) return;
      document.getElementById('emailInput').value = 'wisdom@vasudhapharma.com';
      const pass = document.getElementById('passInput');
      if (DEPT_PASSWORDS[dept]) pass.value = DEPT_PASSWORDS[dept];
      
      const badge = document.getElementById('activeDeskBadge');
      if (badge && deskName) badge.textContent = deskName;

      document.querySelectorAll('.btn-desk-tab').forEach(b => {
        b.classList.remove('active');
      });
      const activeBtn = document.getElementById('btn-' + dept);
      if (activeBtn) {
        activeBtn.classList.add('active');
      }
      pass.focus();
    }

    // Auto-fill default Admin credentials on load
    window.addEventListener('DOMContentLoaded', function() {
      onSelectDept('admin', 'System Admin');
    });
  
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
