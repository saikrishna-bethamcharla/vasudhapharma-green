<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.cookie_httponly', 1);
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
  }
  ini_set('session.use_only_cookies', 1);
  ini_set('session.cookie_samesite', 'Lax');
  session_start();
}

function staff_root() {
  return dirname(__DIR__);
}
function staff_users_file() {
  return staff_root() . '/data/users.json';
}
function staff_jobs_file() {
  return dirname(staff_root()) . '/jobs.json';
}
function staff_users() {
  $f = staff_users_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function staff_save_users($list) {
  return file_put_contents(staff_users_file(), json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}
function staff_audit_file() {
  return staff_root() . '/data/audit_log.json';
}
function staff_load_audit_log($limit = 1000) {
  $f = staff_audit_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  if (!is_array($j)) return [];
  return array_slice($j, 0, $limit);
}
function staff_log_audit($action, $details = '', $target = '', $category = 'General', $status = 'success') {
  $u = staff_user();
  $actorEmail = $u['email'] ?? 'system@vasudhapharma.com';
  $actorName = $u['name'] ?? 'System';
  $actorRole = $u['role'] ?? ($u['dept'] ?? 'anonymous');
  $ip = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '127.0.0.1');

  $entry = [
    'id' => 'AUD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
    'timestamp' => date('Y-m-d H:i:s'),
    'actor_email' => $actorEmail,
    'actor_name' => $actorName,
    'actor_role' => $actorRole,
    'category' => $category,
    'action' => $action,
    'details' => $details,
    'target' => $target,
    'ip' => $ip,
    'status' => $status
  ];

  $f = staff_audit_file();
  $dir = dirname($f);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $logs = [];
  if (is_file($f)) {
    $raw = @file_get_contents($f);
    if ($raw) {
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) $logs = $decoded;
    }
  }
  array_unshift($logs, $entry);
  if (count($logs) > 2000) {
    $logs = array_slice($logs, 0, 2000);
  }
  @file_put_contents($f, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
  return $entry;
}
function staff_user() {
  return isset($_SESSION['staff']) ? $_SESSION['staff'] : null;
}
function staff_require_login() {
  if (!staff_user()) {
    header('Location: index.php');
    exit;
  }
}
function staff_desks() {
  return [
    'marketing'   => ['label' => 'Marketing & Products', 'file' => 'marketing.php', 'icon' => 'tag'],
    'careers'     => ['label' => 'Careers', 'file' => 'jobs.php', 'icon' => 'briefcase'],
    'foundation'  => ['label' => 'Foundation', 'file' => 'foundation.php', 'icon' => 'heart'],
    'documents'   => ['label' => 'Governance', 'file' => 'documents.php', 'icon' => 'file-text'],
    'directors'   => ['label' => 'Board & Management', 'file' => 'directors.php', 'icon' => 'award'],
    'news'        => ['label' => 'News & Events', 'file' => 'news.php', 'icon' => 'newspaper'],
    'feedback'    => ['label' => 'Feedback Desk', 'file' => 'feedback.php', 'icon' => 'inbox'],
  ];
}
function staff_can($desk, $u = null) {
  $u = $u ?: staff_user();
  if (!$u) return false;
  if ($desk === 'directors') {
    return ($u['role'] ?? '') === 'admin';
  }
  if (($u['role'] ?? '') === 'admin') return true;
  if ($desk === 'candidates') return staff_can('careers', $u);
  if (($u['role'] ?? '') === 'hr' && ($desk === 'careers' || $desk === 'candidates')) return true;
  if (($u['role'] ?? '') === 'marketing' && $desk === 'marketing') return true;
  if ((($u['dept'] ?? '') === 'documents' || ($u['dept'] ?? '') === 'governance') && ($desk === 'documents' || $desk === 'governance')) return true;
  return ($u['dept'] ?? '') === $desk;
}
function staff_can_jobs($u = null) {
  return staff_can('careers', $u);
}
function staff_can_candidates($u = null) {
  return staff_can('careers', $u);
}

function staff_default_desk_url($u = null) {
  $u = $u ?: staff_user();
  if (!$u) return 'index.php';
  if (($u['role'] ?? '') === 'admin') {
    return 'home.php';
  }
  $dept = strtolower(trim($u['dept'] ?? ''));
  $role = strtolower(trim($u['role'] ?? ''));

  if ($role === 'hr' || $dept === 'careers') {
    return 'jobs.php';
  }
  if ($role === 'marketing' || $dept === 'marketing') {
    return 'marketing.php';
  }
  if ($dept === 'foundation') {
    return 'foundation.php';
  }
  if ($dept === 'documents' || $dept === 'governance') {
    return 'documents.php';
  }
  if ($dept === 'news') {
    return 'news.php';
  }
  if ($dept === 'feedback') {
    return 'feedback.php';
  }

  $desks = staff_desks();
  if (isset($desks[$dept]['file'])) {
    return $desks[$dept]['file'];
  }
  foreach ($desks as $key => $d) {
    if (staff_can($key, $u)) {
      return $d['file'];
    }
  }
  return 'home.php';
}

function staff_require_desk($desk) {
  staff_require_login();
  if (!staff_can($desk)) {
    http_response_code(403);
    echo 'You do not have permission to access this department desk.';
    exit;
  }
}
function staff_check_login_throttle() {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
  $file = staff_root() . '/data/login_throttling.json';
  if (!is_file($file)) return ['blocked' => false, 'wait_mins' => 0];
  $data = json_decode(file_get_contents($file), true);
  if (!is_array($data)) return ['blocked' => false, 'wait_mins' => 0];
  $now = time();
  if (isset($data[$ip])) {
    $rec = $data[$ip];
    if (($rec['attempts'] ?? 0) >= 5 && ($now - ($rec['last_attempt'] ?? 0)) < 900) {
      $remaining = ceil((900 - ($now - $rec['last_attempt'])) / 60);
      return ['blocked' => true, 'wait_mins' => $remaining];
    }
  }
  return ['blocked' => false, 'wait_mins' => 0];
}

function staff_record_failed_login() {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
  $file = staff_root() . '/data/login_throttling.json';
  $data = [];
  if (is_file($file)) {
    $c = json_decode(file_get_contents($file), true);
    if (is_array($c)) $data = $c;
  }
  $now = time();
  foreach ($data as $k => $v) {
    if (($now - ($v['last_attempt'] ?? 0)) > 3600) unset($data[$k]);
  }
  $prev = $data[$ip]['attempts'] ?? 0;
  $data[$ip] = [
    'attempts' => $prev + 1,
    'last_attempt' => $now
  ];
  file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function staff_clear_login_throttle() {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
  $file = staff_root() . '/data/login_throttling.json';
  if (is_file($file)) {
    $data = json_decode(file_get_contents($file), true);
    if (is_array($data) && isset($data[$ip])) {
      unset($data[$ip]);
      file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
  }
}

function staff_login($email, $password) {
  $throttle = staff_check_login_throttle();
  if ($throttle['blocked']) {
    return false;
  }
  $email = strtolower(trim($email));
  if (!$email || !$password) {
    staff_record_failed_login();
    staff_log_audit('login_failed', 'Failed login attempt with empty credentials', $email ?: 'empty', 'Authentication', 'warning');
    return false;
  }

  // Predefined default passwords map for all desks
  $deptPasswords = [
    'saikrishna@zailabs.co.in'    => 'Vasudha@Dev2026!',
    'admin@vasudhapharma.com'      => 'Vasudha@Admin2026!',
    'careers@vasudhapharma.com'    => 'Vasudha@HR2026!',
    'hr@vasudhapharma.com'         => 'Vasudha@HR2026!',
    'foundation@vasudhapharma.com' => 'Vasudha@CSR2026!',
    'marketing@vasudhapharma.com'  => 'Vasudha@Marketing2026!',
    'news@vasudhapharma.com'       => 'Vasudha@News2026!',
    'governance@vasudhapharma.com' => 'Vasudha@Compliance2026!',
    'compliance@vasudhapharma.com' => 'Vasudha@Compliance2026!'
  ];

  $users = staff_users();
  $matchedUser = null;

  foreach ($users as $i => $u) {
    $uEmail = strtolower($u['email'] ?? '');
    $uAlias = strtolower($u['alias'] ?? '');
    $aliases = array_filter([$uAlias]);
    if ($uAlias === 'careers@vasudhapharma.com') $aliases[] = 'hr@vasudhapharma.com';
    if ($uAlias === 'hr@vasudhapharma.com') $aliases[] = 'careers@vasudhapharma.com';
    if ($uAlias === 'governance@vasudhapharma.com') $aliases[] = 'compliance@vasudhapharma.com';
    if ($uAlias === 'compliance@vasudhapharma.com') $aliases[] = 'governance@vasudhapharma.com';

    $match = ($uEmail === $email) || in_array($email, $aliases, true);
    if (!$match) continue;

    $valid = false;
    // Check standard bcrypt hash
    if (!empty($u['hash']) && @password_verify($password, $u['hash'])) {
      $valid = true;
    }
    // Check department password fallback
    elseif (!empty($aliases)) {
      foreach ($aliases as $al) {
        if (isset($deptPasswords[$al]) && $password === $deptPasswords[$al]) {
          $valid = true;
          // Re-hash and save for future logins
          $users[$i]['hash'] = password_hash($password, PASSWORD_DEFAULT);
          staff_save_users($users);
          break;
        }
      }
    }

    if ($valid) {
      $matchedUser = $u;
      break;
    }
  }

  if ($matchedUser) {
    staff_clear_login_throttle();
    session_regenerate_id(true);
    $_SESSION['staff'] = [
      'email' => $matchedUser['email'],
      'name'  => $matchedUser['name'],
      'role'  => $matchedUser['role'],
      'dept'  => $matchedUser['dept'],
    ];
    staff_log_audit('login_success', 'Staff member successfully logged in', $matchedUser['email'] . ' (' . ($matchedUser['alias'] ?? '') . ')', 'Authentication');
    return true;
  }

  staff_record_failed_login();
  staff_log_audit('login_failed', 'Failed login attempt for identifier: ' . $email, $email, 'Authentication', 'warning');
  return false;
}
function staff_logout() {
  $currentUser = staff_user();
  if ($currentUser) {
    staff_log_audit('staff_logout', 'Staff user logged out', $currentUser['email'], 'Authentication');
  }
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}

/* CSRF Protection */
function staff_csrf_token() {
  if (empty($_SESSION['staff_csrf'])) {
    $_SESSION['staff_csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['staff_csrf'];
}
function staff_csrf_field() {
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(staff_csrf_token()) . '">';
}
function staff_verify_csrf() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['staff_csrf']) || !hash_equals($_SESSION['staff_csrf'], $token)) {
      http_response_code(403);
      die('Security validation failed: Invalid or expired CSRF token. Please return to the previous page, refresh, and try again.');
    }
  }
}

/* Self-Service Profile Update */
function staff_update_profile($email, $name, $currentPassword, $newPassword = '') {
  $email = strtolower(trim($email));
  $list = staff_users();
  foreach ($list as $i => $u) {
    if (strtolower($u['email']) === $email) {
      if (!password_verify($currentPassword, $u['hash'])) {
        return ['ok' => false, 'error' => 'Current password is incorrect.'];
      }
      $list[$i]['name'] = trim($name) ?: $u['name'];
      if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
          return ['ok' => false, 'error' => 'New password must be at least 6 characters long.'];
        }
        $list[$i]['hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
      }
      if (staff_save_users($list)) {
        $_SESSION['staff']['name'] = $list[$i]['name'];
        if (!empty($newPassword)) {
          $domain = $_SERVER['SERVER_NAME'] ?? 'vasudhapharma.com';
          $notifSubject = '[Security Notice] Vasudha Portal — Profile Password Updated (' . $list[$i]['name'] . ')';
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
          $timeStr = date('d M Y, H:i:s T');
          $notifBody = '<p>The password for staff account ' . htmlspecialchars($email) . ' was updated from profile settings on ' . htmlspecialchars($timeStr) . ' (IP: ' . htmlspecialchars($ip) . ').</p>';
          $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: Vasudha Operations Security <noreply@' . $domain . '>',
            'Reply-To: wisdom@vasudhapharma.com',
            'X-Mailer: PHP/' . phpversion(),
          ];
          $headersJoined = implode("\r\n", $headers);
          @mail($email, $notifSubject, $notifBody, $headersJoined);
          if (strtolower($email) !== 'wisdom@vasudhapharma.com') {
            @mail('wisdom@vasudhapharma.com', $notifSubject, $notifBody, $headersJoined);
          }
        }
        return ['ok' => true];
      }
      return ['ok' => false, 'error' => 'Failed to write users data. Check file permissions.'];
    }
  }
  return ['ok' => false, 'error' => 'User account could not be found.'];
}

/* Password Reset & OTP Helpers */
function staff_password_resets_file() {
  return staff_root() . '/data/password_resets.json';
}

function staff_load_password_resets() {
  $f = staff_password_resets_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  if (!is_array($j)) return [];
  $now = time();
  $valid = [];
  foreach ($j as $item) {
    if (!empty($item['expires_at']) && $item['expires_at'] > $now) {
      $valid[] = $item;
    }
  }
  return $valid;
}

function staff_save_password_resets($list) {
  return file_put_contents(staff_password_resets_file(), json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

function staff_find_user($identifier, $dept = '') {
  $id = strtolower(trim($identifier));
  if (!$id) return null;
  $users = staff_users();
  // 1. Exact alias match (unique per department)
  foreach ($users as $u) {
    if (!empty($u['alias']) && strtolower($u['alias']) === $id) {
      return $u;
    }
  }
  // 2. Email + Dept match
  if ($dept) {
    foreach ($users as $u) {
      if (strtolower($u['email']) === $id && ($u['dept'] ?? '') === $dept) {
        return $u;
      }
    }
  }
  // 3. Fallback: match by email
  foreach ($users as $u) {
    if (strtolower($u['email']) === $id) {
      return $u;
    }
  }
  return null;
}

function staff_send_reset_otp($identifier, $dept = '') {
  $user = staff_find_user($identifier, $dept);
  if (!$user) {
    return ['ok' => false, 'error' => 'No active staff account found matching that email or department.'];
  }

  $resets = staff_load_password_resets();
  $userKey = strtolower($user['alias'] ?: ($user['email'] . ':' . ($user['dept'] ?? 'admin')));

  // Rate limiting: 45 seconds between requests
  foreach ($resets as $r) {
    if (($r['user_key'] ?? '') === $userKey && (time() - ($r['created_at'] ?? 0)) < 45) {
      $wait = 45 - (time() - $r['created_at']);
      return ['ok' => false, 'error' => "A code was recently sent. Please wait {$wait}s before requesting a new one."];
    }
  }

  // Purge any prior code for this user
  $resets = array_filter($resets, function($r) use ($userKey) {
    return ($r['user_key'] ?? '') !== $userKey;
  });

  $otp = sprintf("%06d", mt_rand(100000, 999999));
  $now = time();
  $expiresAt = $now + 900; // 15 mins

  $record = [
    'user_key'   => $userKey,
    'email'      => $user['email'],
    'alias'      => $user['alias'] ?? '',
    'name'       => $user['name'],
    'dept'       => $user['dept'] ?? '',
    'otp'        => $otp,
    'otp_hash'   => password_hash($otp, PASSWORD_DEFAULT),
    'attempts'   => 0,
    'created_at' => $now,
    'expires_at' => $expiresAt,
  ];

  $resets[] = $record;
  staff_save_password_resets($resets);

  // Send HTML Email via PHP mail()
  $to = $user['email'];
  $subject = 'Vasudha Operations Portal - Password Reset Verification Code';

  $htmlBody = '<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f4fbf7; margin: 0; padding: 24px; color: #0f172a; }
    .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
    .header { text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 24px; }
    .logo { font-size: 19px; font-weight: 800; color: #096b51; letter-spacing: 0.5px; text-transform: uppercase; }
    .sub { font-size: 12px; color: #0e8f6c; margin-top: 4px; font-style: italic; }
    .code-box { background: #ecfdf5; border: 1.5px dashed #0e8f6c; border-radius: 10px; padding: 20px; text-align: center; margin: 24px 0; }
    .otp-code { font-size: 36px; font-weight: 800; color: #096b51; letter-spacing: 8px; font-family: monospace; }
    .expiry { font-size: 12px; color: #047857; margin-top: 8px; font-weight: 600; }
    .footer { margin-top: 28px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 11.5px; color: #64748b; line-height: 1.5; text-align: center; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <div class="logo">Vasudha Operations Portal</div>
      <div class="sub">Vasudha Pharma Chem Limited &bull; Hyderabad, India</div>
    </div>
    <h2 style="font-size: 18px; margin: 0 0 12px; color: #0f172a;">Password Reset Verification</h2>
    <p style="font-size: 14px; line-height: 1.5; color: #334155; margin: 0 0 16px;">
      Hello <strong>' . htmlspecialchars($user['name']) . '</strong>,
    </p>
    <p style="font-size: 13.5px; line-height: 1.5; color: #475569; margin: 0 0 16px;">
      A request was received to reset the password for your <strong>' . htmlspecialchars(ucfirst($user['dept'] ?? 'Operations')) . '</strong> staff account (' . htmlspecialchars($user['email']) . ').
    </p>
    <div class="code-box">
      <div class="otp-code">' . $otp . '</div>
      <div class="expiry">This verification code expires in 15 minutes.</div>
    </div>
    <p style="font-size: 13px; line-height: 1.5; color: #475569; margin: 0 0 12px;">
      Enter this 6-digit code on the password reset screen to verify your identity and set a new password.
    </p>
    <p style="font-size: 12px; color: #94a3b8; margin: 16px 0 0;">
      If you did not request a password reset, please report this immediately to wisdom@vasudhapharma.com. Your current password remains unchanged.
    </p>
    <div class="footer">
      Vasudha Pharma Chem Limited &bull; Operations &amp; Technology Desk<br>
      Plot 78/A, Vengalrao Nagar, Hyderabad - 500 038, Telangana, India
    </div>
  </div>
</body>
</html>';

  $domain = $_SERVER['SERVER_NAME'] ?? 'vasudhapharma.com';
  $headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Vasudha Operations Security <noreply@' . $domain . '>',
    'Reply-To: wisdom@vasudhapharma.com',
    'X-Mailer: PHP/' . phpversion(),
  ];

  $headersStr = implode("\r\n", $headers);
  $mailSent = @mail($to, $subject, $htmlBody, $headersStr);

  // Security Audit: Always notify wisdom@vasudhapharma.com
  if (strtolower($to) !== 'wisdom@vasudhapharma.com') {
    @mail('wisdom@vasudhapharma.com', '[Security Audit] ' . $subject . ' for ' . $user['email'], $htmlBody, $headersStr);
  }

  return [
    'ok'        => true,
    'user_key'  => $userKey,
    'email'     => $user['email'],
    'alias'     => $user['alias'] ?? '',
    'name'      => $user['name'],
    'dept'      => $user['dept'] ?? '',
    'mail_sent' => $mailSent,
  ];
}

function staff_verify_reset_otp($userKey, $otp) {
  $otp = trim($otp);
  if (!$otp || strlen($otp) !== 6) {
    return ['ok' => false, 'error' => 'Verification code must be exactly 6 digits.'];
  }

  $resets = staff_load_password_resets();
  $found = null;
  $foundIdx = null;

  foreach ($resets as $idx => $r) {
    if (($r['user_key'] ?? '') === $userKey) {
      $found = $r;
      $foundIdx = $idx;
      break;
    }
  }

  if (!$found) {
    return ['ok' => false, 'error' => 'No active password reset request found. Please request a new code.'];
  }

  if (time() > ($found['expires_at'] ?? 0)) {
    unset($resets[$foundIdx]);
    staff_save_password_resets($resets);
    return ['ok' => false, 'error' => 'This verification code has expired. Please request a new code.'];
  }

  if (($found['attempts'] ?? 0) >= 5) {
    unset($resets[$foundIdx]);
    staff_save_password_resets($resets);
    return ['ok' => false, 'error' => 'Too many invalid attempts. For security, please request a new verification code.'];
  }

  $matches = ($otp === ($found['otp'] ?? '')) || (!empty($found['otp_hash']) && password_verify($otp, $found['otp_hash']));
  if (!$matches) {
    $resets[$foundIdx]['attempts'] = ($resets[$foundIdx]['attempts'] ?? 0) + 1;
    staff_save_password_resets($resets);
    $left = 5 - $resets[$foundIdx]['attempts'];
    return ['ok' => false, 'error' => "Invalid verification code. {$left} attempt(s) remaining."];
  }

  return ['ok' => true, 'record' => $found];
}

function staff_complete_password_reset($userKey, $otp, $newPassword) {
  $check = staff_verify_reset_otp($userKey, $otp);
  if (!$check['ok']) {
    return $check;
  }

  if (strlen($newPassword) < 6) {
    return ['ok' => false, 'error' => 'New password must be at least 6 characters long.'];
  }

  $record = $check['record'];
  $users = staff_users();
  $updated = false;

  foreach ($users as $i => $u) {
    $matchAlias = !empty($record['alias']) && !empty($u['alias']) && strtolower($u['alias']) === strtolower($record['alias']);
    $matchEmailDept = strtolower($u['email']) === strtolower($record['email']) && ($u['dept'] ?? '') === ($record['dept'] ?? '');
    if ($matchAlias || $matchEmailDept) {
      $users[$i]['hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
      $updated = true;
      break;
    }
  }

  if (!$updated) {
    return ['ok' => false, 'error' => 'Matching staff user record could not be updated.'];
  }

  if (!staff_save_users($users)) {
    return ['ok' => false, 'error' => 'Failed to save updated credentials to database.'];
  }

  // Remove reset record
  $resets = staff_load_password_resets();
  $resets = array_filter($resets, function($r) use ($userKey) {
    return ($r['user_key'] ?? '') !== $userKey;
  });
  staff_save_password_resets($resets);

  // Dispatch confirmation email to account holder AND wisdom@vasudhapharma.com
  $domain = $_SERVER['SERVER_NAME'] ?? 'vasudhapharma.com';
  $notifSubject = '[Security Notice] Vasudha Portal — Password Changed for ' . ($record['name'] ?? 'Staff User');
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
  $timeStr = date('d M Y, H:i:s T');
  $notifBody = '<!DOCTYPE html><html><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f4fbf7;margin:0;padding:24px;color:#0f172a;">
    <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:14px;padding:32px 28px;border:1px solid #e2e8f0;box-shadow:0 4px 18px rgba(0,0,0,0.06);">
      <h3 style="color:#096b51;margin-top:0;font-size:18px;">Password Successfully Changed</h3>
      <p style="font-size:14px;line-height:1.5;color:#334155;">The password for the staff account <strong>' . htmlspecialchars($record['name'] ?? 'Staff') . '</strong> (' . htmlspecialchars($record['email']) . ' &bull; ' . htmlspecialchars(ucfirst($record['dept'] ?? 'Operations')) . ') was successfully changed.</p>
      <table style="width:100%;font-size:13px;border-top:1px solid #f1f5f9;margin:16px 0;">
        <tr><td style="color:#64748b;padding:8px 0;width:30%;">Timestamp:</td><td style="color:#0f172a;font-weight:600;">' . htmlspecialchars($timeStr) . '</td></tr>
        <tr><td style="color:#64748b;padding:8px 0;">Client IP:</td><td style="color:#0f172a;font-family:monospace;">' . htmlspecialchars($ip) . '</td></tr>
      </table>
      <p style="font-size:12px;color:#94a3b8;margin-top:20px;line-height:1.5;">If this change was not made by you, please report this incident immediately to IT Security at <strong>wisdom@vasudhapharma.com</strong>.</p>
      <div style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:11.5px;color:#94a3b8;text-align:center;">
        Vasudha Pharma Chem Limited &bull; Operations &amp; Technology Desk
      </div>
    </div>
  </body></html>';

  $notifHeaders = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Vasudha Operations Security <noreply@' . $domain . '>',
    'Reply-To: wisdom@vasudhapharma.com',
    'X-Mailer: PHP/' . phpversion(),
  ];
  $headersJoined = implode("\r\n", $notifHeaders);

  @mail($record['email'], $notifSubject, $notifBody, $headersJoined);
  if (strtolower($record['email']) !== 'wisdom@vasudhapharma.com') {
    @mail('wisdom@vasudhapharma.com', $notifSubject, $notifBody, $headersJoined);
  }

  return ['ok' => true];
}

/* Data File Helpers */
function staff_load_jobs() {
  $f = staff_jobs_file();
  if (!is_file($f)) return ['updated' => date('Y-m-d'), 'jobs' => []];
  $j = json_decode(file_get_contents($f), true);
  if (!is_array($j)) $j = ['jobs' => []];
  if (!isset($j['jobs']) || !is_array($j['jobs'])) $j['jobs'] = [];
  return $j;
}
function staff_save_jobs($data) {
  $data['updated'] = date('Y-m-d');
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;
  return file_put_contents(staff_jobs_file(), $json) !== false;
}
function staff_documents_file() {
  return dirname(staff_root()) . '/documents.json';
}
function staff_load_documents() {
  $f = staff_documents_file();
  if (!is_file($f)) return ['updated' => date('Y-m-d'), 'documents' => []];
  $j = json_decode(file_get_contents($f), true);
  if (!is_array($j)) $j = ['documents' => []];
  if (!isset($j['documents']) || !is_array($j['documents'])) $j['documents'] = [];
  return $j;
}
function staff_save_documents($data) {
  $data['updated'] = date('Y-m-d H:i');
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;
  return file_put_contents(staff_documents_file(), $json) !== false;
}

function staff_directors_file() {
  return dirname(staff_root()) . '/directors.json';
}
function staff_directors_backup_file() {
  return staff_root() . '/data/directors.json';
}
function staff_load_directors() {
  $f1 = staff_directors_file();
  $f2 = staff_directors_backup_file();

  $j1 = is_file($f1) ? @json_decode(@file_get_contents($f1), true) : null;
  $j2 = is_file($f2) ? @json_decode(@file_get_contents($f2), true) : null;

  $use = null;
  if (is_array($j1) && is_array($j2)) {
    $t1 = strtotime($j1['last_updated'] ?? '1970-01-01');
    $t2 = strtotime($j2['last_updated'] ?? '1970-01-01');
    $use = ($t2 > $t1) ? $j2 : $j1;
  } elseif (is_array($j1)) {
    $use = $j1;
  } elseif (is_array($j2)) {
    $use = $j2;
  } else {
    $use = ['version' => '1.0', 'last_updated' => date('c'), 'directors' => [], 'management' => []];
  }

  if (!isset($use['directors']) || !is_array($use['directors'])) $use['directors'] = [];
  if (!isset($use['management']) || !is_array($use['management'])) $use['management'] = [];
  return $use;
}
function staff_save_directors($data) {
  $data['last_updated'] = date('c');
  $u = staff_user();
  if ($u) $data['updated_by'] = $u['name'] ?? ($u['email'] ?? 'Admin');
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;

  $f1 = staff_directors_file();
  $f2 = staff_directors_backup_file();

  $w1 = @file_put_contents($f1, $json, LOCK_EX);
  $dataDir = dirname($f2);
  if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
  }
  $w2 = @file_put_contents($f2, $json, LOCK_EX);

  return ($w1 !== false || $w2 !== false);
}

function staff_desk_file($desk) {
  $desk = preg_replace('/[^a-z]/', '', strtolower($desk));
  return staff_root() . '/data/' . $desk . '.json';
}
function staff_load_desk($desk) {
  $f = staff_desk_file($desk);
  if (!is_file($f)) return ['updated' => '', 'body' => '', 'items' => []];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : ['body' => '', 'items' => []];
}
function staff_save_desk($desk, $data) {
  $data['updated'] = date('Y-m-d H:i');
  return file_put_contents(staff_desk_file($desk), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
}

/* Candidate Applications Store Helper */
function staff_applications_file() {
  return staff_root() . '/data/applications.json';
}
function staff_load_applications() {
  $f = staff_applications_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function staff_save_applications($list) {
  $dir = dirname(staff_applications_file());
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  return file_put_contents(
    staff_applications_file(),
    json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
  ) !== false;
}

/* Talent Community Pool Store Helper */
function staff_talent_pool_file() {
  return staff_root() . '/data/talent_pool.json';
}
function staff_load_talent_pool() {
  $f = staff_talent_pool_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function staff_save_talent_pool($list) {
  $dir = dirname(staff_talent_pool_file());
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  return file_put_contents(
    staff_talent_pool_file(),
    json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
  ) !== false;
}

/* Commercial Inquiries & RFQs Store Helper */
function staff_enquiries_file() {
  return staff_root() . '/data/enquiries.json';
}
function staff_load_enquiries() {
  $f = staff_enquiries_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function staff_save_enquiries($list) {
  $dir = dirname(staff_enquiries_file());
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  return file_put_contents(
    staff_enquiries_file(),
    json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
  ) !== false;
}

/* Website Contact Inquiries Store Helper */
function staff_contacts_file() {
  return staff_root() . '/data/contacts.json';
}
function staff_load_contacts() {
  $f = staff_contacts_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function staff_save_contacts($list) {
  $dir = dirname(staff_contacts_file());
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  return file_put_contents(
    staff_contacts_file(),
    json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
  ) !== false;
}

/* Operations Statistics Helper */
function staff_portal_stats() {
  $jobsData = staff_load_jobs();
  $jobs = $jobsData['jobs'] ?? [];
  $openJobs = 0;
  $closedJobs = 0;
  foreach ($jobs as $j) {
    if (($j['status'] ?? 'open') === 'open') $openJobs++;
    else $closedJobs++;
  }

  $apps = staff_load_applications();
  $appsTotal = count($apps);
  $appsScreening = 0;
  $appsInterview = 0;
  $appsOffers = 0;
  foreach ($apps as $a) {
    $step = intval($a['currentStep'] ?? 1);
    if ($step === 1) $appsScreening++;
    elseif ($step === 3) $appsInterview++;
    elseif ($step === 4) $appsOffers++;
  }

  $talentPool = staff_load_talent_pool();
  $talentTotal = count($talentPool);

  $neFile = dirname(staff_root()) . '/news-events.json';
  $eventsCount = 0;
  $newsCount = 0;
  if (is_file($neFile)) {
    $ne = json_decode(file_get_contents($neFile), true);
    if (is_array($ne)) {
      $eventsCount = count($ne['events'] ?? []);
      $newsCount = count($ne['news'] ?? []);
    }
  }

  $galFile = dirname(staff_root()) . '/foundation-galleries.json';
  $galCount = 0;
  if (is_file($galFile)) {
    $gal = json_decode(file_get_contents($galFile), true);
    if (is_array($gal)) {
      $galCount = count($gal['vasudha'] ?? []) + count($gal['vrrv'] ?? []) + count($gal['moments'] ?? []);
      if (!empty($gal['seva']) && is_array($gal['seva'])) {
        foreach ($gal['seva'] as $sItems) if (is_array($sItems)) $galCount += count($sItems);
      }
    }
  }

  $users = staff_users();

  $enquiries = staff_load_enquiries();
  $contacts = staff_load_contacts();
  $leadsTotal = count($enquiries) + count($contacts);
  $leadsNew = 0;
  foreach ($enquiries as $enq) {
    if (($enq['status'] ?? 'New') === 'New') $leadsNew++;
  }
  foreach ($contacts as $con) {
    if (($con['status'] ?? 'New') === 'New') $leadsNew++;
  }

  $fbFile = staff_root() . '/data/feedback.json';
  $fbTotal = 0;
  $fbOpen = 0;
  if (is_file($fbFile)) {
    $fb = json_decode(file_get_contents($fbFile), true);
    if (is_array($fb)) {
      $fbTotal = count($fb);
      foreach ($fb as $item) {
        $st = $item['status'] ?? 'Open';
        if ($st === 'Open' || $st === 'In Progress') $fbOpen++;
      }
    }
  }

  return [
    'jobs_total'       => count($jobs),
    'jobs_open'        => $openJobs,
    'jobs_closed'      => $closedJobs,
    'apps_total'       => $appsTotal,
    'apps_screening'   => $appsScreening,
    'apps_interview'   => $appsInterview,
    'apps_offers'      => $appsOffers,
    'talent_total'     => $talentTotal,
    'leads_total'      => $leadsTotal,
    'leads_new'        => $leadsNew,
    'events_total'     => $eventsCount,
    'news_total'       => $newsCount,
    'gallery_total'    => $galCount,
    'users_total'      => count($users),
    'feedback_total'   => $fbTotal,
    'feedback_open'    => $fbOpen,
    'documents_total'  => (function() {
      $df = staff_documents_file();
      if (!is_file($df)) return 0;
      $dj = json_decode(file_get_contents($df), true);
      return is_array($dj) ? count($dj['documents'] ?? []) : 0;
    })(),
    'directors_total'  => (function() {
      $dirf = staff_directors_file();
      if (!is_file($dirf)) return 0;
      $dirj = json_decode(file_get_contents($dirf), true);
      return is_array($dirj) ? count($dirj['directors'] ?? []) : 0;
    })(),
  ];
}
