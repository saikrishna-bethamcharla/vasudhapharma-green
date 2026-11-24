<?php
declare(strict_types=1);

/**
 * Vasudha Pharma Chem Limited — Centralized Environment Configuration
 * Safely loads environment variables from system environment or local .env file.
 */

if (!function_exists('vp_load_env')) {
  function vp_load_env(?string $path = null): array {
    static $cachedEnv = null;
    if ($cachedEnv !== null) {
      return $cachedEnv;
    }

    $cachedEnv = [];
    $envPath = $path ?: (__DIR__ . '/.env');
    if (!is_file($envPath) || !is_readable($envPath)) {
      return $cachedEnv;
    }

    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
      return $cachedEnv;
    }

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || str_starts_with($line, '#')) {
        continue;
      }
      $parts = explode('=', $line, 2);
      if (count($parts) === 2) {
        $k = trim($parts[0]);
        $v = trim($parts[1]);
        // Strip outer quotes if present
        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
          $v = substr($v, 1, -1);
        }
        $cachedEnv[$k] = $v;
      }
    }
    return $cachedEnv;
  }
}

if (!function_exists('vp_env')) {
  function vp_env(string $key, $default = null) {
    // 1. Check system getenv()
    $sysVal = getenv($key);
    if ($sysVal !== false && $sysVal !== '') {
      return $sysVal;
    }

    // 2. Check $_ENV superglobal
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
      return $_ENV[$key];
    }

    // 3. Check parsed .env file
    $envMap = vp_load_env();
    if (isset($envMap[$key]) && $envMap[$key] !== '') {
      return $envMap[$key];
    }

    return $default;
  }
}
