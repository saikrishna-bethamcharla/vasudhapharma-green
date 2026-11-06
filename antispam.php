<?php
/**
 * Shared abuse protection for the public form endpoints (contact, enquiry,
 * apply, talent register).
 *
 * These endpoints append straight into the staff JSON stores and fan out email
 * plus CRM relays, so an unprotected flood both bloats the portal data and burns
 * the mail quota. Three cheap layers, no third-party service:
 *
 *   1. Honeypot — a hidden field real visitors never fill.
 *   2. Throttle — per IP + endpoint, stored outside the web root's readable data.
 *   3. Content shape — link-stuffed and oversized payloads are rejected.
 *
 * A blocked request gets HTTP 429 (throttle) or 400 (content) with a JSON body
 * so the existing front-end error handling keeps working.
 */

if (!function_exists('vp_antispam_client_ip')) {
  function vp_antispam_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
  }
}

if (!function_exists('vp_antispam_store_file')) {
  function vp_antispam_store_file() {
    $dir = __DIR__ . '/staff/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir . '/rate_limit.json';
  }
}

if (!function_exists('vp_antispam_read_store')) {
  function vp_antispam_read_store() {
    $file = vp_antispam_store_file();
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
  }
}

if (!function_exists('vp_antispam_write_store')) {
  function vp_antispam_write_store($store) {
    $file = vp_antispam_store_file();
    // Keep the file bounded: newest 400 keys only.
    if (count($store) > 400) {
      uasort($store, function ($a, $b) {
        $ta = is_array($a) && $a ? max($a) : 0;
        $tb = is_array($b) && $b ? max($b) : 0;
        return $tb <=> $ta;
      });
      $store = array_slice($store, 0, 400, true);
    }
    @file_put_contents($file, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
  }
}

/**
 * @param array  $data     Decoded payload ($_POST or JSON body).
 * @param string $endpoint Short endpoint key, e.g. 'contact'.
 * @param array  $opts     limit (per window), window (seconds), daily, max_links.
 * @return array{ok:bool,error:string,status:int}
 */
if (!function_exists('vp_antispam_guard')) {
  function vp_antispam_guard($data, $endpoint, $opts = []) {
    if (!is_array($data)) $data = [];

    $honeypots = $opts['honeypot'] ?? ['website', 'company_url', 'hp_field'];
    foreach ($honeypots as $field) {
      if (!empty($data[$field])) {
        return ['ok' => false, 'status' => 400, 'error' => 'Submission rejected. Please contact us directly by email.'];
      }
    }

    // Content shape: a genuine enquiry does not carry a pile of URLs.
    $maxLinks = $opts['max_links'] ?? 4;
    $text = '';
    foreach (['name', 'company', 'organisation', 'message', 'product', 'note'] as $field) {
      if (isset($data[$field]) && is_string($data[$field])) $text .= ' ' . $data[$field];
    }
    if ($maxLinks > 0 && $text !== '') {
      if (preg_match_all('#https?://#i', $text) > $maxLinks) {
        return ['ok' => false, 'status' => 400, 'error' => 'Your message contains too many links. Please shorten it and try again.'];
      }
    }

    $maxMessage = $opts['max_message'] ?? 20000;
    if (isset($data['message']) && is_string($data['message']) && strlen($data['message']) > $maxMessage) {
      return ['ok' => false, 'status' => 400, 'error' => 'Your message is too long. Please shorten it and try again.'];
    }

    $limit = (int)($opts['limit'] ?? 5);
    $window = (int)($opts['window'] ?? 600);
    $daily = (int)($opts['daily'] ?? 30);
    $key = vp_antispam_client_ip() . '|' . preg_replace('/[^a-z0-9_-]/i', '', (string)$endpoint);

    $store = vp_antispam_read_store();
    $now = time();
    $hits = isset($store[$key]) && is_array($store[$key]) ? $store[$key] : [];

    // Drop anything older than a day; a day's worth is all the daily cap needs.
    $hits = array_values(array_filter($hits, function ($t) use ($now) {
      return is_numeric($t) && (int)$t > ($now - 86400);
    }));

    $inWindow = 0;
    foreach ($hits as $t) {
      if ((int)$t > ($now - $window)) $inWindow++;
    }

    if ($daily > 0 && count($hits) >= $daily) {
      return ['ok' => false, 'status' => 429, 'error' => 'We have received several messages from your connection today. Please email us directly if this is urgent.'];
    }
    if ($limit > 0 && $inWindow >= $limit) {
      $wait = max(1, (int)ceil(($window - ($now - (int)max($hits ?: [0]))) / 60));
      return ['ok' => false, 'status' => 429, 'error' => 'Too many submissions from your connection. Please try again in about ' . $wait . ' minute(s), or email us directly.'];
    }

    $hits[] = $now;
    $store[$key] = $hits;
    vp_antispam_write_store($store);

    return ['ok' => true, 'status' => 200, 'error' => ''];
  }
}
