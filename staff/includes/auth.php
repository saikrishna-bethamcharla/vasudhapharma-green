<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.cookie_httponly', '1');
  ini_set('session.use_only_cookies', '1');
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
  if ($isHttps) {
    ini_set('session.cookie_secure', '1');
  }
  // Enforce SameSite=Strict on staff administrative portal
  ini_set('session.cookie_samesite', 'Strict');
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
function staff_jobs_backup_file() {
  return staff_root() . '/data/jobs.json';
}
function staff_users() {
  $f = staff_users_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function staff_save_users($list) {
  return file_put_contents(staff_users_file(), json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
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
  // Enforce 30-minute idle session timeout
  if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    staff_logout();
    header('Location: index.php?msg=session_expired');
    exit;
  }
  $_SESSION['last_activity'] = time();
}
function staff_desks() {
  return [
    'marketing'   => ['label' => 'Marketing & Products', 'file' => 'marketing.php', 'icon' => 'tag'],
    'navigation'  => ['label' => 'Header & Menus', 'file' => 'navigation.php', 'icon' => 'layout'],
    'careers'     => ['label' => 'Careers', 'file' => 'jobs.php', 'icon' => 'briefcase'],
    'foundation'  => ['label' => 'Foundation', 'file' => 'foundation.php', 'icon' => 'heart'],
    'documents'   => ['label' => 'Governance', 'file' => 'documents.php', 'icon' => 'file-text'],
    'directors'   => ['label' => 'Board & Management', 'file' => 'directors.php', 'icon' => 'award'],
    'news'        => ['label' => 'News & Events', 'file' => 'news.php', 'icon' => 'newspaper'],
  ];
}
function staff_can($desk, $u = null) {
  $u = $u ?: staff_user();
  if (!$u) return false;
  if ($desk === 'directors') {
    return ($u['role'] ?? '') === 'admin';
  }
  if ($desk === 'navigation') {
    return ($u['role'] ?? '') === 'admin' || ($u['role'] ?? '') === 'marketing' || ($u['dept'] ?? '') === 'marketing';
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

/**
 * Strict image validator and EXIF metadata stripper.
 * Validates extension, magic bytes, dimensions, and strips EXIF if GD is available.
 * Returns ['ok' => true, 'filename' => string, 'path' => string] or ['ok' => false, 'error' => string].
 */
function staff_sanitize_and_save_image(array $fileUpload, string $targetDir, string $filePrefix = 'img_'): array {
  if (empty($fileUpload['tmp_name']) || ($fileUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'error' => 'No file uploaded or upload error occurred.'];
  }
  $tmp = (string)$fileUpload['tmp_name'];
  $size = (int)($fileUpload['size'] ?? 0);
  $name = (string)($fileUpload['name'] ?? 'image');

  // 1. Max size: 5MB
  if ($size > 5 * 1024 * 1024) {
    return ['ok' => false, 'error' => 'Image file size exceeds the 5MB limit.'];
  }

  // 2. Extension check
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
  if (!in_array($ext, $allowedExts, true)) {
    return ['ok' => false, 'error' => 'Invalid image format. Only JPEG, PNG, and WebP are allowed.'];
  }

  // 3. Binary magic bytes check
  $fh = @fopen($tmp, 'rb');
  $header = $fh ? (string)fread($fh, 12) : '';
  if ($fh) fclose($fh);

  $magicOk = false;
  if (($ext === 'jpg' || $ext === 'jpeg') && str_starts_with($header, "\xFF\xD8\xFF")) {
    $magicOk = true;
  } elseif ($ext === 'png' && str_starts_with($header, "\x89PNG\r\n\x1a\n")) {
    $magicOk = true;
  } elseif ($ext === 'webp' && str_starts_with($header, "RIFF") && substr($header, 8, 4) === "WEBP") {
    $magicOk = true;
  }

  if (!$magicOk) {
    return ['ok' => false, 'error' => 'File contents do not match a valid JPEG, PNG, or WebP image.'];
  }

  // 4. Verify valid image dimensions
  $imgInfo = @getimagesize($tmp);
  if (!$imgInfo || empty($imgInfo[0]) || empty($imgInfo[1])) {
    return ['ok' => false, 'error' => 'Uploaded file is not a valid or readable image.'];
  }

  // 5. Target directory safety
  if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
  }
  $safeFilename = $filePrefix . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
  $destination = rtrim($targetDir, '/\\') . '/' . $safeFilename;

  // 6. Strip EXIF metadata if GD library is available
  $exifStripped = false;
  if (extension_loaded('gd')) {
    if ($ext === 'jpg' || $ext === 'jpeg') {
      $img = @imagecreatefromjpeg($tmp);
      if ($img) {
        @imagejpeg($img, $destination, 92);
        @imagedestroy($img);
        $exifStripped = true;
      }
    } elseif ($ext === 'png') {
      $img = @imagecreatefrompng($tmp);
      if ($img) {
        @imagepalettetotruecolor($img);
        @imagealphablending($img, false);
        @imagesavealpha($img, true);
        @imagepng($img, $destination, 8);
        @imagedestroy($img);
        $exifStripped = true;
      }
    } elseif ($ext === 'webp' && function_exists('imagewebp') && function_exists('imagecreatefromwebp')) {
      $img = @imagecreatefromwebp($tmp);
      if ($img) {
        @imagewebp($img, $destination, 90);
        @imagedestroy($img);
        $exifStripped = true;
      }
    }
  }

  if (!$exifStripped) {
    if (!@move_uploaded_file($tmp, $destination)) {
      return ['ok' => false, 'error' => 'Failed to save uploaded file to destination.'];
    }
  }

  return ['ok' => true, 'filename' => $safeFilename, 'path' => $destination];
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
  file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function staff_clear_login_throttle() {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
  $file = staff_root() . '/data/login_throttling.json';
  if (is_file($file)) {
    $data = json_decode(file_get_contents($file), true);
    if (is_array($data) && isset($data[$ip])) {
      unset($data[$ip]);
      file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
  }
}

/**
 * Returns preferred password hashing algorithm:
 * Uses PASSWORD_ARGON2ID (memory-hard, GPU/ASIC resistant) when supported by PHP,
 * or safely falls back to PASSWORD_DEFAULT (Bcrypt).
 */
function staff_password_algo(): string {
  if (defined('PASSWORD_ARGON2ID')) {
    return PASSWORD_ARGON2ID;
  }
  return PASSWORD_DEFAULT;
}

/**
 * TOTP / Two-Factor Authentication Engine (RFC 6238 / RFC 4648)
 * Fully compatible with Google Authenticator, Microsoft Authenticator, and Apple Passwords.
 */
function staff_totp_chars(): string {
  return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
}

function staff_totp_generate_secret(int $length = 16): string {
  $chars = staff_totp_chars();
  $secret = '';
  for ($i = 0; $i < $length; $i++) {
    $secret .= $chars[random_int(0, 31)];
  }
  return $secret;
}

function staff_base32_decode(string $b32): string {
  $b32 = strtoupper(rtrim($b32, '='));
  $chars = staff_totp_chars();
  $bits = '';
  for ($i = 0; $i < strlen($b32); $i++) {
    $val = strpos($chars, $b32[$i]);
    if ($val === false) continue;
    $bits .= sprintf('%05b', $val);
  }
  $len = intdiv(strlen($bits), 8);
  $bytes = '';
  for ($i = 0; $i < $len; $i++) {
    $bytes .= chr(bindec(substr($bits, $i * 8, 8)));
  }
  return $bytes;
}

function staff_totp_code(string $secret, ?int $step = null): string {
  if ($step === null) {
    $step = (int)floor(time() / 30);
  }
  $secretBin = staff_base32_decode($secret);
  $timeBin = pack('N*', 0) . pack('N*', $step);
  $hash = hash_hmac('sha1', $timeBin, $secretBin, true);
  $offset = ord(substr($hash, -1)) & 0x0F;
  $part = substr($hash, $offset, 4);
  $unpacked = unpack('N', $part);
  $val = ($unpacked[1] ?? 0) & 0x7FFFFFFF;
  return str_pad((string)($val % 1000000), 6, '0', STR_PAD_LEFT);
}

function staff_totp_verify(string $secret, string $code, int $window = 1): bool {
  $code = trim($code);
  if (strlen($code) !== 6 || !ctype_digit($code)) {
    return false;
  }
  $currentStep = (int)floor(time() / 30);
  for ($offset = -$window; $offset <= $window; $offset++) {
    $expected = staff_totp_code($secret, $currentStep + $offset);
    if (hash_equals($expected, $code)) {
      return true;
    }
  }
  return false;
}

function staff_totp_uri(string $secret, string $email, string $issuer = 'Vasudha Pharma'): string {
  return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
    . '?secret=' . rawurlencode($secret)
    . '&issuer=' . rawurlencode($issuer)
    . '&period=30&digits=6';
}

function staff_verify_2fa(string $code): bool {
  if (empty($_SESSION['staff_2fa_pending'])) {
    return false;
  }
  $pending = $_SESSION['staff_2fa_pending'];
  if (time() - ($pending['time'] ?? 0) > 300) {
    unset($_SESSION['staff_2fa_pending']);
    return false;
  }
  $users = staff_users();
  $user = null;
  foreach ($users as $u) {
    if (strtolower($u['email'] ?? '') === strtolower($pending['email'] ?? '')) {
      $user = $u;
      break;
    }
  }
  if (!$user || empty($user['totp_secret'])) {
    unset($_SESSION['staff_2fa_pending']);
    return false;
  }
  if (staff_totp_verify($user['totp_secret'], $code)) {
    staff_clear_login_throttle();
    session_regenerate_id(true);
    $_SESSION['staff'] = [
      'email' => $user['email'],
      'name'  => $user['name'],
      'role'  => $user['role'],
      'dept'  => $user['dept'],
    ];
    unset($_SESSION['staff_2fa_pending']);
    staff_log_audit('login_2fa_success', 'Two-factor authentication verified successfully', $user['email'], 'Authentication');
    return true;
  }
  staff_log_audit('login_2fa_failed', 'Invalid 2FA code entered during login', $user['email'], 'Authentication', 'warning');
  return false;
}

function staff_enable_2fa(string $email, string $secret, string $code): array {
  $email = strtolower(trim($email));
  if (!staff_totp_verify($secret, $code)) {
    return ['ok' => false, 'error' => 'Verification code is incorrect or expired. Please check your Authenticator app and try again.'];
  }
  $users = staff_users();
  $found = false;
  foreach ($users as $i => $u) {
    if (strtolower($u['email'] ?? '') === $email) {
      $users[$i]['totp_secret'] = $secret;
      $users[$i]['totp_enabled_at'] = date('Y-m-d H:i:s');
      $found = true;
      break;
    }
  }
  if (!$found || !staff_save_users($users)) {
    return ['ok' => false, 'error' => 'Failed to save two-factor configuration to users directory.'];
  }
  staff_log_audit('2fa_enabled', 'Two-factor TOTP authentication enabled on staff account', $email, 'Authentication');
  return ['ok' => true];
}

function staff_disable_2fa(string $email, string $password): array {
  $email = strtolower(trim($email));
  $users = staff_users();
  $found = false;
  foreach ($users as $i => $u) {
    if (strtolower($u['email'] ?? '') === $email) {
      if (empty($u['hash']) || !password_verify($password, $u['hash'])) {
        return ['ok' => false, 'error' => 'Current password is required to disable Two-Factor Authentication.'];
      }
      unset($users[$i]['totp_secret']);
      unset($users[$i]['totp_enabled_at']);
      $found = true;
      break;
    }
  }
  if (!$found || !staff_save_users($users)) {
    return ['ok' => false, 'error' => 'Failed to update user security settings.'];
  }
  staff_log_audit('2fa_disabled', 'Two-factor TOTP authentication disabled on staff account', $email, 'Authentication', 'warning');
  return ['ok' => true];
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

  // Dynamic department fallback from environment or configuration if set
  $envPass = getenv('VP_DEPT_PASSWORDS');
  $deptPasswords = $envPass ? json_decode($envPass, true) : [];
  if (!is_array($deptPasswords)) {
    $deptPasswords = [];
  }

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
    // Check standard hash (Bcrypt or Argon2id)
    if (!empty($u['hash']) && @password_verify($password, $u['hash'])) {
      $valid = true;
      // Auto-rehash to modern Argon2id or updated parameters if needed
      $algo = staff_password_algo();
      if (password_needs_rehash($u['hash'], $algo)) {
        $users[$i]['hash'] = password_hash($password, $algo);
        staff_save_users($users);
      }
    }
    // Check department password fallback
    elseif (!empty($aliases)) {
      foreach ($aliases as $al) {
        if (isset($deptPasswords[$al]) && $password === $deptPasswords[$al]) {
          $valid = true;
          // Re-hash and save for future logins with preferred algorithm
          $users[$i]['hash'] = password_hash($password, staff_password_algo());
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
    // If Two-Factor Authentication (TOTP) is active for this account, challenge user
    if (!empty($matchedUser['totp_secret'])) {
      $_SESSION['staff_2fa_pending'] = [
        'email' => $matchedUser['email'],
        'name'  => $matchedUser['name'],
        'role'  => $matchedUser['role'],
        'dept'  => $matchedUser['dept'],
        'alias' => $matchedUser['alias'] ?? '',
        'time'  => time(),
      ];
      staff_log_audit('login_2fa_prompted', 'Password verified, prompting for 2FA TOTP code', $matchedUser['email'], 'Authentication');
      return '2fa_required';
    }

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
        $list[$i]['hash'] = password_hash($newPassword, staff_password_algo());
      }
      if (staff_save_users($list)) {
        $_SESSION['staff']['name'] = $list[$i]['name'];
        if (!empty($newPassword)) {
          $domain = 'vasudhapharma.com';
          $notifSubject = '[Security Notice] Vasudha Portal — Profile Password Updated (' . $list[$i]['name'] . ')';
          $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
          $timeStr = date('d M Y, H:i:s T');
          $notifBody = '<p>The password for staff account <strong>' . htmlspecialchars($list[$i]['name']) . '</strong> (' . htmlspecialchars($email) . ') was updated from profile settings on ' . htmlspecialchars($timeStr) . ' (IP: ' . htmlspecialchars($ip) . ').</p>';
          $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: Vasudha Operations Security <noreply@' . $domain . '>',
            'Reply-To: wisdom@vasudhapharma.com',
            'X-Mailer: PHP/' . phpversion(),
          ];
          $headersJoined = implode("\r\n", $headers);
          // Centralized security routing: All password alerts go strictly to wisdom@vasudhapharma.com
          @mail('wisdom@vasudhapharma.com', $notifSubject, $notifBody, $headersJoined);
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
  return file_put_contents(staff_password_resets_file(), json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
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
    'otp_hash'   => password_hash($otp, staff_password_algo()),
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

  $domain = 'vasudhapharma.com';
  $headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Vasudha Operations Security <noreply@' . $domain . '>',
    'Reply-To: wisdom@vasudhapharma.com',
    'X-Mailer: PHP/' . phpversion(),
  ];

  $headersStr = implode("\r\n", $headers);
  // Centralized security routing: All OTP verification codes go strictly to wisdom@vasudhapharma.com
  $mailSent = @mail('wisdom@vasudhapharma.com', $subject . ' (' . ($user['name'] ?? 'Staff') . ')', $htmlBody, $headersStr);

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
      $users[$i]['hash'] = password_hash($newPassword, staff_password_algo());
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
  $domain = 'vasudhapharma.com';
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

  // Centralized security routing: All password change notices go strictly to wisdom@vasudhapharma.com
  @mail('wisdom@vasudhapharma.com', $notifSubject, $notifBody, $headersJoined);

  return ['ok' => true];
}

/* Data File Helpers */
function staff_load_jobs() {
  $f1 = staff_jobs_file();
  $f2 = staff_jobs_backup_file();

  $j1 = is_file($f1) ? @json_decode(@file_get_contents($f1), true) : null;
  $j2 = is_file($f2) ? @json_decode(@file_get_contents($f2), true) : null;

  $use = null;
  if (is_array($j1) && is_array($j2)) {
    $t1 = strtotime($j1['updated'] ?? '1970-01-01');
    $t2 = strtotime($j2['updated'] ?? '1970-01-01');
    $use = ($t2 > $t1) ? $j2 : $j1;
  } elseif (is_array($j1)) {
    $use = $j1;
  } elseif (is_array($j2)) {
    $use = $j2;
  } else {
    $use = ['updated' => date('Y-m-d'), 'jobs' => []];
  }
  if (!isset($use['jobs']) || !is_array($use['jobs'])) $use['jobs'] = [];
  return $use;
}
function staff_save_jobs($data) {
  $data['updated'] = date('Y-m-d H:i');
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;

  $f1 = staff_jobs_file();
  $f2 = staff_jobs_backup_file();

  $w1 = @file_put_contents($f1, $json, LOCK_EX);
  $dataDir = dirname($f2);
  if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
  $w2 = @file_put_contents($f2, $json, LOCK_EX);

  return ($w1 !== false || $w2 !== false);
}

function staff_documents_file() {
  return dirname(staff_root()) . '/documents.json';
}
function staff_documents_backup_file() {
  return staff_root() . '/data/documents.json';
}
function staff_load_documents() {
  $f1 = staff_documents_file();
  $f2 = staff_documents_backup_file();

  $j1 = is_file($f1) ? @json_decode(@file_get_contents($f1), true) : null;
  $j2 = is_file($f2) ? @json_decode(@file_get_contents($f2), true) : null;

  $use = null;
  if (is_array($j1) && is_array($j2)) {
    $t1 = strtotime($j1['updated'] ?? '1970-01-01');
    $t2 = strtotime($j2['updated'] ?? '1970-01-01');
    $use = ($t2 > $t1) ? $j2 : $j1;
  } elseif (is_array($j1)) {
    $use = $j1;
  } elseif (is_array($j2)) {
    $use = $j2;
  } else {
    $use = ['updated' => date('Y-m-d'), 'documents' => []];
  }
  if (!isset($use['documents']) || !is_array($use['documents'])) $use['documents'] = [];
  return $use;
}
function staff_save_documents($data) {
  $data['updated'] = date('Y-m-d H:i');
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;

  $f1 = staff_documents_file();
  $f2 = staff_documents_backup_file();

  $w1 = @file_put_contents($f1, $json, LOCK_EX);
  $dataDir = dirname($f2);
  if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
  $w2 = @file_put_contents($f2, $json, LOCK_EX);

  return ($w1 !== false || $w2 !== false);
}

function staff_news_events_file() {
  return dirname(staff_root()) . '/news-events.json';
}
function staff_news_events_backup_file() {
  return staff_root() . '/data/news-events.json';
}
function staff_load_news_events() {
  $f1 = staff_news_events_file();
  $f2 = staff_news_events_backup_file();

  $j1 = is_file($f1) ? @json_decode(@file_get_contents($f1), true) : null;
  $j2 = is_file($f2) ? @json_decode(@file_get_contents($f2), true) : null;

  $use = null;
  if (is_array($j1) && is_array($j2)) {
    $t1 = strtotime($j1['updated'] ?? '1970-01-01');
    $t2 = strtotime($j2['updated'] ?? '1970-01-01');
    $use = ($t2 > $t1) ? $j2 : $j1;
  } elseif (is_array($j1)) {
    $use = $j1;
  } elseif (is_array($j2)) {
    $use = $j2;
  } else {
    $use = ['updated' => date('Y-m-d H:i'), 'events' => [], 'news' => []];
  }
  if (!isset($use['events']) || !is_array($use['events'])) $use['events'] = [];
  if (!isset($use['news']) || !is_array($use['news'])) $use['news'] = [];
  return $use;
}
function staff_save_news_events($data) {
  $data['updated'] = date('Y-m-d H:i');
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;

  $f1 = staff_news_events_file();
  $f2 = staff_news_events_backup_file();

  $w1 = @file_put_contents($f1, $json, LOCK_EX);
  $dataDir = dirname($f2);
  if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
  $w2 = @file_put_contents($f2, $json, LOCK_EX);

  return ($w1 !== false || $w2 !== false);
}

function staff_marketing_file() {
  return dirname(staff_root()) . '/marketing.json';
}
function staff_marketing_backup_file() {
  return staff_root() . '/data/marketing.json';
}
function staff_load_marketing() {
  $f1 = staff_marketing_file();
  $f2 = staff_marketing_backup_file();

  $j1 = is_file($f1) ? @json_decode(@file_get_contents($f1), true) : null;
  $j2 = is_file($f2) ? @json_decode(@file_get_contents($f2), true) : null;

  $use = null;
  if (is_array($j1) && is_array($j2)) {
    $t1 = strtotime($j1['updated'] ?? '1970-01-01');
    $t2 = strtotime($j2['updated'] ?? '1970-01-01');
    $use = ($t2 > $t1) ? $j2 : $j1;
  } elseif (is_array($j1)) {
    $use = $j1;
  } elseif (is_array($j2)) {
    $use = $j2;
  } else {
    $use = ['updated' => date('Y-m-d H:i'), 'items' => []];
  }
  if (!isset($use['items']) || !is_array($use['items'])) $use['items'] = [];
  return $use;
}
function staff_save_marketing($data) {
  $data['updated'] = date('Y-m-d H:i');
  $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;

  $f1 = staff_marketing_file();
  $f2 = staff_marketing_backup_file();

  $w1 = @file_put_contents($f1, $json, LOCK_EX);
  $dataDir = dirname($f2);
  if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
  $w2 = @file_put_contents($f2, $json, LOCK_EX);

  return ($w1 !== false || $w2 !== false);
}

require_once __DIR__ . '/catalog-sync.php';

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
  if ($desk === 'marketing') {
    return staff_load_marketing();
  }
  $f = staff_desk_file($desk);
  if (!is_file($f)) return ['updated' => '', 'body' => '', 'items' => []];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : ['body' => '', 'items' => []];
}
function staff_save_desk($desk, $data) {
  $data['updated'] = date('Y-m-d H:i');
  if ($desk === 'marketing') {
    $saveOk = staff_save_marketing($data);
    $syncProblems = staff_sync_html_product_catalogs($data, dirname(staff_root()));
    staff_catalog_write_sync_status($syncProblems, $data);
    if (!empty($syncProblems) && function_exists('staff_log_audit')) {
      staff_log_audit(
        'catalog_sync_incomplete',
        'Registry saved but public catalogue could not be fully updated: ' . implode('; ', $syncProblems),
        'marketing',
        'Commercial Leads',
        'warning'
      );
    }
    return $saveOk;
  }
  return file_put_contents(staff_desk_file($desk), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
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
    json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    LOCK_EX
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
    json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    LOCK_EX
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
    json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    LOCK_EX
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
    json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    LOCK_EX
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
    'users_total'      => count($users),'documents_total'  => (function() {
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
    'product_cards_total' => (function() {
      $pf = staff_product_cards_file();
      if (!is_file($pf)) return 0;
      $pj = json_decode(file_get_contents($pf), true);
      return is_array($pj) ? count($pj) : 0;
    })(),
  ];
}

require_once __DIR__ . '/site-sync.php';

/* Product Category Cards Store Helper */
function staff_product_cards_file() {
  return staff_root() . '/data/product-cards.json';
}
function staff_load_product_cards() {
  $f = staff_product_cards_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function staff_save_product_cards($cards) {
  $dir = dirname(staff_product_cards_file());
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  return file_put_contents(
    staff_product_cards_file(),
    json_encode(array_values($cards), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    LOCK_EX
  ) !== false;
}

/* Header & Sub-Menus Navigation Store Helper */
function staff_header_nav_file() {
  return staff_root() . '/data/header-nav.json';
}
function staff_load_header_nav() {
  $f = staff_header_nav_file();
  if (!is_file($f)) return [];
  $j = json_decode(file_get_contents($f), true);
  return is_array($j) ? $j : [];
}
function staff_save_header_nav($nav) {
  $dir = dirname(staff_header_nav_file());
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  return file_put_contents(
    staff_header_nav_file(),
    json_encode($nav, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    LOCK_EX
  ) !== false;
}

