<?php
require __DIR__ . '/includes/auth.php';
staff_require_desk('foundation');
$u = staff_user();

$msg = $_SESSION['staff_flash_msg'] ?? '';
$err = $_SESSION['staff_flash_err'] ?? '';
unset($_SESSION['staff_flash_msg'], $_SESSION['staff_flash_err']);

$gal_file = dirname(staff_root()) . '/foundation-galleries.json';
$gal_dir = dirname(staff_root()) . '/assets/foundation';

$SEVA_PROGRAMS = [
  'aashraya' => [
    'name'   => 'Aashraya',
    'telugu' => 'ఆశ్రయ',
    'sub'    => 'Shelter, Sustenance & Dignified Senior Care',
    'badge'  => '20+ Old Age Homes & Shelters Supported',
    'icon'   => 'home',
    'color'  => '#0E8F6C'
  ],
  'aadarana' => [
    'name'   => 'Aadarana',
    'telugu' => 'ఆదరణ',
    'sub'    => 'Care, Rehabilitation & Lifelong Hope',
    'badge'  => 'Differently-Abled, Special Needs & Orphans',
    'icon'   => 'hand-holding-heart',
    'color'  => '#0088AA'
  ],
  'aalayam' => [
    'name'   => 'Aalayam',
    'telugu' => 'ఆలయం',
    'sub'    => 'Non-Profit Comprehensive Vaidyaalayams',
    'badge'  => 'Universal Free Super-Specialty Healthcare',
    'icon'   => 'hospital',
    'color'  => '#0284C7'
  ],
  'akshaya' => [
    'name'   => 'Akshaya',
    'telugu' => 'అక్షయ',
    'sub'    => 'Rapid Emergency Relief & Calamity Aid',
    'badge'  => 'Flood, Cyclone & Disaster Response Relief',
    'icon'   => 'shield-alt',
    'color'  => '#D97706'
  ],
  'aarogyam' => [
    'name'   => 'Aarogyam',
    'telugu' => 'ఆరోగ్యం',
    'sub'    => 'Healthcare Aid & Rural Anti-Venom Mission',
    'badge'  => 'Life-Saving Anti-Venom & Medical Camps',
    'icon'   => 'heartbeat',
    'color'  => '#DC2626'
  ],
  'aksharam' => [
    'name'   => 'Aksharam',
    'telugu' => 'అక్షరం',
    'sub'    => 'Education, Merit Scholarships & Student Aid',
    'badge'  => 'Higher Studies Grants & School Kits',
    'icon'   => 'graduation-cap',
    'color'  => '#7C3AED'
  ]
];

function staff_load_gal($f) {
  $file = $f;
  $alt = staff_root() . '/data/foundation-galleries.json';
  if (is_file($alt) && (!is_file($file) || filemtime($alt) > filemtime($file))) {
    $file = $alt;
  }
  if (!is_file($file)) return ['vasudha' => [], 'vrrv' => [], 'moments' => [], 'seva' => []];
  $j = json_decode(file_get_contents($file), true);
  if (!is_array($j)) $j = [];
  $j['vasudha'] = isset($j['vasudha']) && is_array($j['vasudha']) ? $j['vasudha'] : [];
  $j['vrrv'] = isset($j['vrrv']) && is_array($j['vrrv']) ? $j['vrrv'] : [];
  $j['moments'] = isset($j['moments']) && is_array($j['moments']) ? $j['moments'] : [];
  if (!isset($j['seva']) || !is_array($j['seva'])) $j['seva'] = [];
  $keys = ['aashraya','aadarana','aalayam','akshaya','aarogyam','aksharam'];
  foreach ($keys as $k) {
    if (!isset($j['seva'][$k]) || !is_array($j['seva'][$k])) $j['seva'][$k] = [];
  }
  return $j;
}

function staff_save_gal($f, $g) {
  $json = json_encode($g, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($json === false) return false;
  
  $primaryOk = @file_put_contents($f, $json, LOCK_EX) !== false;
  
  $altDir = staff_root() . '/data';
  if (!is_dir($altDir)) @mkdir($altDir, 0755, true);
  $altOk = @file_put_contents($altDir . '/foundation-galleries.json', $json, LOCK_EX) !== false;
  
  return ($primaryOk || $altOk);
}

// Synchronize marquee stream tracks into founder.html
function staff_sync_foundation_marquees_to_html($data, $siteRoot) {
  $founderPath = $siteRoot . '/founder.html';
  if (!is_file($founderPath)) return false;
  $html = file_get_contents($founderPath);
  if (!$html) return false;

  // 1. Vasudha Stream
  $vasudha = $data['vasudha'] ?? [];
  $vHtml = '';
  foreach ($vasudha as $src) {
    $vHtml .= '          <div class="vf-shot"><img src="' . htmlspecialchars($src) . '" alt="Vasudha Foundation program" loading="lazy"></div>' . "\n";
  }
  foreach ($vasudha as $src) {
    $vHtml .= '          <div class="vf-shot"><img src="' . htmlspecialchars($src) . '" alt="" loading="lazy" aria-hidden="true"></div>' . "\n";
  }
  $html = preg_replace('/(<div class="vf-track" id="vfTrackVasudha">)[\s\S]*?(<\/div>\s*<\/div>\s*<div id="vrrv-family")/i', '$1' . "\n" . $vHtml . '        $2', $html);

  // 2. VRRV Stream
  $vrrv = $data['vrrv'] ?? [];
  $vrrvHtml = '';
  foreach ($vrrv as $src) {
    $vrrvHtml .= '          <div class="vf-shot"><img src="' . htmlspecialchars($src) . '" alt="VRRV Family Vasudha Foundation event" loading="lazy"></div>' . "\n";
  }
  foreach ($vrrv as $src) {
    $vrrvHtml .= '          <div class="vf-shot"><img src="' . htmlspecialchars($src) . '" alt="" loading="lazy" aria-hidden="true"></div>' . "\n";
  }
  $html = preg_replace('/(<div class="vf-track" id="vfTrackVrrv">)[\s\S]*?(<\/div>\s*<\/div>\s*<\/div>\s*<\/section>)/i', '$1' . "\n" . $vrrvHtml . '        $2', $html);

  // 3. Moments Stream
  $moments = $data['moments'] ?? [];
  $mHtml = '';
  foreach ($moments as $idx => $src) {
    $cls = ($idx === 0) ? 'fg-slide is-active' : 'fg-slide';
    $mHtml .= '          <div class="' . $cls . '"><img src="' . htmlspecialchars($src) . '" alt="Vasudha Foundation moment ' . ($idx + 1) . '"></div>' . "\n";
  }
  foreach ($moments as $src) {
    $mHtml .= '          <div class="fg-slide" aria-hidden="true"><img src="' . htmlspecialchars($src) . '" alt="" loading="lazy"></div>' . "\n";
  }
  $html = preg_replace('/(<div class="fg-track" id="fgTrackMoments">)[\s\S]*?(<\/div>\s*<button type="button" class="fg-nav fg-prev")/i', '$1' . "\n" . $mHtml . '        $2', $html);

  file_put_contents($founderPath, $html);
  return true;
}

$gal = staff_load_gal($gal_file);

// Track active Seva tab from GET/POST
$activeSeva = $_GET['seva'] ?? ($_POST['seva_key'] ?? 'aashraya');
if (!array_key_exists($activeSeva, $SEVA_PROGRAMS)) {
  $activeSeva = 'aashraya';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';

  // 1. Upload photo to one of the 6 Seva Programs
  if ($action === 'upload_seva_img') {
    $sevaKey = $_POST['seva_key'] ?? 'aashraya';
    if (!array_key_exists($sevaKey, $SEVA_PROGRAMS)) $sevaKey = 'aashraya';
    $activeSeva = $sevaKey;

    $title = trim($_POST['title'] ?? '');
    $tag = trim($_POST['tag'] ?? 'Featured Event');
    if ($title === '') {
      $title = $SEVA_PROGRAMS[$sevaKey]['name'] . ' Initiative Outreach';
    }

    if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
      $imgInfo = @getimagesize($_FILES['image']['tmp_name']);
      if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true) || !$imgInfo) {
        $err = 'Unsupported or corrupted format. Please upload a valid JPG, PNG, WEBP, or GIF image.';
      } elseif ($_FILES['image']['size'] > 8 * 1024 * 1024) {
        $err = 'Image must be under 8 MB.';
      } else {
        $siteRoot = dirname(staff_root());
        $dir = $siteRoot . '/assets/foundation';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $fileName = 'seva-' . $sevaKey . '-' . date('Ymd-His') . '-' . mt_rand(100, 999) . '.' . $ext;
        $dest = $dir . '/' . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
          $gal['seva'][$sevaKey][] = [
            'src'   => 'assets/foundation/' . $fileName,
            'title' => $title,
            'tag'   => $tag
          ];
          staff_save_gal($gal_file, $gal);
          $msg = 'Photo successfully added to ' . $SEVA_PROGRAMS[$sevaKey]['name'] . ' (' . $SEVA_PROGRAMS[$sevaKey]['telugu'] . ') gallery!';
        } else {
          $err = 'Could not save the uploaded file to assets/foundation.';
        }
      }
    } else {
      $err = 'Please choose an image file to upload.';
    }

    $_SESSION['staff_flash_msg'] = $msg;
    $_SESSION['staff_flash_err'] = $err;
    header('Location: foundation.php?seva=' . urlencode($sevaKey) . '#sevaSection');
    exit;
  }

  // 2. Delete photo from a specific Seva Program
  if ($action === 'delete_seva_img') {
    $sevaKey = $_POST['seva_key'] ?? 'aashraya';
    if (!array_key_exists($sevaKey, $SEVA_PROGRAMS)) $sevaKey = 'aashraya';
    $activeSeva = $sevaKey;
    $src = trim($_POST['src'] ?? '');
    $cleanSrc = trim(str_replace('\\', '/', $src));
    $cleanSrc = ltrim($cleanSrc, '/');
    $baseName = basename($cleanSrc);

    $origCount = count($gal['seva'][$sevaKey]);
    $gal['seva'][$sevaKey] = array_values(array_filter($gal['seva'][$sevaKey], function($item) use ($cleanSrc, $baseName) {
      $itemSrc = is_array($item) ? ($item['src'] ?? '') : $item;
      $itemSrc = trim(str_replace('\\', '/', $itemSrc));
      $itemSrc = ltrim($itemSrc, '/');
      return ($itemSrc !== $cleanSrc && basename($itemSrc) !== $baseName);
    }));

    if (count($gal['seva'][$sevaKey]) === $origCount) {
      $err = 'Image not found in gallery.';
    } else {
      $saved = staff_save_gal($gal_file, $gal);
      if (!$saved) {
        $err = 'Failed to update gallery file. Check file write permissions.';
      } else {
        $siteRoot = dirname(staff_root());
        $pathsToTry = [
          $siteRoot . '/' . $cleanSrc,
          $siteRoot . '/assets/foundation/' . $baseName
        ];
        foreach ($pathsToTry as $p) {
          if (is_file($p)) @unlink($p);
        }
        staff_log_audit('foundation_seva_deleted', 'Removed photo ' . $baseName . ' from ' . $SEVA_PROGRAMS[$sevaKey]['name'] . ' gallery', $cleanSrc, 'Vasudha Foundation');
        $msg = 'Photo successfully removed from ' . $SEVA_PROGRAMS[$sevaKey]['name'] . ' gallery.';
      }
    }

    $_SESSION['staff_flash_msg'] = $msg;
    $_SESSION['staff_flash_err'] = $err;
    header('Location: foundation.php?seva=' . urlencode($sevaKey) . '#sevaSection');
    exit;
  }

  // 3. Upload photo to 3 Marquee Streams (Vasudha, VRRV, Moments)
  if ($action === 'upload_img') {
    $which = $_POST['which'] ?? 'vasudha';
    if (!in_array($which, ['vasudha','vrrv','moments'], true)) $which = 'vasudha';
    if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
      $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
      $imgInfo = @getimagesize($_FILES['image']['tmp_name']);
      if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true) || !$imgInfo) {
        $err = 'Unsupported or corrupted format. Please upload a valid JPG, PNG, WEBP, or GIF image.';
      } elseif ($_FILES['image']['size'] > 6 * 1024 * 1024) {
        $err = 'Image must be under 6 MB.';
      } else {
        $folder = $which === 'moments' ? 'founder-gallery' : 'foundation';
        $siteRoot = dirname(staff_root());
        $dir = $siteRoot . '/assets/' . $folder;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = $which . '-' . date('Ymd-His') . '-' . mt_rand(100,999) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
          $gal[$which][] = 'assets/' . $folder . '/' . $name;
          $saved = staff_save_gal($gal_file, $gal);
          if ($saved) {
            staff_sync_foundation_marquees_to_html($gal, $siteRoot);
            $label = ($which === 'vrrv' ? 'VRRV Family' : ($which === 'moments' ? 'Moments from the Foundation' : 'Vasudha Foundation'));
            $msg = 'Image added to the ' . $label . ' marquee stream.';
          } else {
            $err = 'Failed to update gallery file on disk.';
          }
        } else {
          $err = 'Could not save the uploaded file. Check folder permissions on assets/' . $folder . '.';
        }
      }
    } else {
      $err = 'Please choose an image file to upload.';
    }

    $_SESSION['staff_flash_msg'] = $msg;
    $_SESSION['staff_flash_err'] = $err;
    header('Location: foundation.php#marqueeSection');
    exit;
  }

  // 4. Delete photo from 3 Marquee Streams (Vasudha, VRRV, Moments)
  if ($action === 'delete_img') {
    $which = $_POST['which'] ?? 'vasudha';
    if (!in_array($which, ['vasudha','vrrv','moments'], true)) $which = 'vasudha';
    $src = trim($_POST['src'] ?? '');
    $cleanSrc = trim(str_replace('\\', '/', $src));
    $cleanSrc = ltrim($cleanSrc, '/');
    $baseName = basename($cleanSrc);

    $origCount = count($gal[$which]);
    $gal[$which] = array_values(array_filter($gal[$which], function ($s) use ($cleanSrc, $baseName) {
      $itemSrc = trim(str_replace('\\', '/', is_array($s) ? ($s['src'] ?? '') : $s));
      $itemSrc = ltrim($itemSrc, '/');
      if ($itemSrc === $cleanSrc || basename($itemSrc) === $baseName) {
        return false;
      }
      return true;
    }));

    if (count($gal[$which]) === $origCount) {
      $err = 'Image not found in the ' . htmlspecialchars($which) . ' marquee stream.';
    } else {
      // Also purge references to this file across any other marquee streams so no ghosts remain
      foreach (['vasudha', 'vrrv', 'moments'] as $otherStream) {
        if ($otherStream !== $which && isset($gal[$otherStream]) && is_array($gal[$otherStream])) {
          $gal[$otherStream] = array_values(array_filter($gal[$otherStream], function ($s) use ($cleanSrc, $baseName) {
            $itemSrc = trim(str_replace('\\', '/', is_array($s) ? ($s['src'] ?? '') : $s));
            $itemSrc = ltrim($itemSrc, '/');
            return !($itemSrc === $cleanSrc || basename($itemSrc) === $baseName);
          }));
        }
      }

      $saved = staff_save_gal($gal_file, $gal);
      if (!$saved) {
        $err = 'Failed to save changes to ' . htmlspecialchars(basename($gal_file)) . '. Please check write permissions.';
      } else {
        $siteRoot = dirname(staff_root());
        $pathsToTry = [
          $siteRoot . '/' . $cleanSrc,
          $siteRoot . '/assets/foundation/' . $baseName,
          $siteRoot . '/assets/founder-gallery/' . $baseName
        ];
        foreach ($pathsToTry as $p) {
          if (is_file($p)) {
            @unlink($p);
          }
        }
        staff_sync_foundation_marquees_to_html($gal, $siteRoot);
        $label = ($which === 'vrrv' ? 'VRRV Family' : ($which === 'moments' ? 'Moments from the Foundation' : 'Vasudha Foundation'));
        staff_log_audit('foundation_marquee_deleted', 'Removed marquee photo ' . $baseName . ' from ' . $label . ' stream', $which, 'Vasudha Foundation');
        $msg = 'Image "' . htmlspecialchars($baseName) . '" successfully removed from ' . $label . ' marquee stream.';
      }
    }

    $_SESSION['staff_flash_msg'] = $msg;
    $_SESSION['staff_flash_err'] = $err;
    header('Location: foundation.php#marqueeSection');
    exit;
  }

  // 5. Drag & Drop Reorder Photos across all Foundation displays (Seva & Marquees)
  if ($action === 'reorder_gallery') {
    $which = $_POST['which'] ?? '';
    $sevaKey = $_POST['seva_key'] ?? '';
    $orderRaw = $_POST['order'] ?? '';
    $isAjax = !empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $orderList = [];
    if (is_array($orderRaw)) {
      $orderList = $orderRaw;
    } else {
      $decoded = json_decode($orderRaw, true);
      if (is_array($decoded)) {
        $orderList = $decoded;
      } elseif (is_string($orderRaw) && $orderRaw !== '') {
        $orderList = array_filter(array_map('trim', explode(',', $orderRaw)));
      }
    }

    $siteRoot = dirname(staff_root());

    if (in_array($which, ['vasudha', 'vrrv', 'moments'], true) && !empty($orderList)) {
      $currentList = $gal[$which] ?? [];
      $reordered = [];
      foreach ($orderList as $src) {
        $cSrc = ltrim(trim(str_replace('\\', '/', $src)), '/');
        foreach ($currentList as $ci) {
          $ciClean = ltrim(trim(str_replace('\\', '/', $ci)), '/');
          if ($ciClean === $cSrc && !in_array($ci, $reordered, true)) {
            $reordered[] = $ci;
            break;
          }
        }
      }
      foreach ($currentList as $ci) {
        if (!in_array($ci, $reordered, true)) $reordered[] = $ci;
      }
      $gal[$which] = array_values($reordered);
      if (staff_save_gal($gal_file, $gal)) {
        staff_sync_foundation_marquees_to_html($gal, $siteRoot);
        $label = ($which === 'vrrv' ? 'VRRV Family' : ($which === 'moments' ? 'Moments' : 'Vasudha'));
        staff_log_audit('foundation_reordered', 'Reordered ' . $label . ' marquee stream sequence via drag/drop', $which, 'Vasudha Foundation');
        if ($isAjax) {
          header('Content-Type: application/json; charset=utf-8');
          echo json_encode(['ok' => true, 'msg' => $label . ' marquee stream order updated and synced live.']);
          exit;
        }
        $_SESSION['staff_flash_msg'] = $label . ' marquee stream order updated.';
      } else {
        if ($isAjax) {
          http_response_code(500);
          echo json_encode(['ok' => false, 'error' => 'Failed to save new order to disk.']);
          exit;
        }
        $_SESSION['staff_flash_err'] = 'Failed to save new order to disk.';
      }
    } elseif ($which === 'seva' && array_key_exists($sevaKey, $SEVA_PROGRAMS) && !empty($orderList)) {
      $currentList = $gal['seva'][$sevaKey] ?? [];
      $reordered = [];
      foreach ($orderList as $src) {
        $cSrc = ltrim(trim(str_replace('\\', '/', $src)), '/');
        foreach ($currentList as $item) {
          $iSrc = is_array($item) ? ($item['src'] ?? '') : $item;
          $iClean = ltrim(trim(str_replace('\\', '/', $iSrc)), '/');
          if ($iClean === $cSrc && !in_array($item, $reordered, true)) {
            $reordered[] = $item;
            break;
          }
        }
      }
      foreach ($currentList as $item) {
        if (!in_array($item, $reordered, true)) $reordered[] = $item;
      }
      $gal['seva'][$sevaKey] = array_values($reordered);
      if (staff_save_gal($gal_file, $gal)) {
        $progName = $SEVA_PROGRAMS[$sevaKey]['name'];
        staff_log_audit('foundation_reordered', 'Reordered ' . $progName . ' photo gallery sequence via drag/drop', $sevaKey, 'Vasudha Foundation');
        if ($isAjax) {
          header('Content-Type: application/json; charset=utf-8');
          echo json_encode(['ok' => true, 'msg' => $progName . ' photo sequence updated live on public site.']);
          exit;
        }
        $_SESSION['staff_flash_msg'] = $progName . ' photo sequence updated.';
      } else {
        if ($isAjax) {
          http_response_code(500);
          echo json_encode(['ok' => false, 'error' => 'Failed to save new order to disk.']);
          exit;
        }
        $_SESSION['staff_flash_err'] = 'Failed to save new order to disk.';
      }
    }

    if (!$isAjax) {
      $redirHash = ($which === 'seva') ? '?seva=' . urlencode($sevaKey) . '#sevaSection' : '#marqueeSection';
      header('Location: foundation.php' . $redirHash);
      exit;
    }
  }

}

$staff_title = 'Foundation';
require __DIR__ . '/includes/header.php';

// Compute total Seva images
$totalSevaImgs = 0;
foreach ($gal['seva'] as $k => $arr) {
  $totalSevaImgs += count($arr);
}
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

<!-- Desk Overview Banner -->
<div class="desk-hero-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; position:relative; z-index:1;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.1em; opacity:0.85; font-weight:700; margin-bottom:6px;">
        Community Service &bull; Corporate Social Responsibility
      </div>
      <h1 style="margin:0 0 4px; font-size:24px; color:#FFFFFF;">Vasudha Foundation Operations Desk</h1>
      <p style="margin:0; opacity:0.9; font-size:13px; color:#E2E8F0; max-width:820px; line-height:1.5;">
        Curate photo streams across the <strong>6 Seva Initiatives (Aashraya, Aadarana, Aalayam, Akshaya, Aarogyam, Aksharam)</strong> and manage the continuous marquee galleries.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="../founder.html#vfSevaShowcase" target="_blank" rel="noopener" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Public Seva Showcase
      </a>
    </div>
  </div>
</div>
  </div>
</div>

<!-- ======================================================== -->
<!-- SECTION 1: SEVA INITIATIVES SEPARATE CONTROLS (6 TABS)    -->
<!-- ======================================================== -->
<div class="card" id="sevaSection" style="border-top: 4px solid #0E8F6C;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
    <div>
      <div style="font-size:11.5px; font-weight:700; color:var(--sp-primary-dark); text-transform:uppercase; letter-spacing:0.05em;">
        Multi-Photo Mosaic &amp; Lightbox Manager
      </div>
      <h2 style="margin:2px 0 0; font-size:19px;">Seva Initiatives Photo Galleries (Each Controlled Separately)</h2>
    </div>
    <span class="badge-live" style="font-size:12px;">
      <?php echo $totalSevaImgs; ?> Seva Photos Live
    </span>
  </div>

  <p style="margin:0 0 16px; font-size:13px; color:var(--sp-text-muted); line-height:1.5;">
    Select any of the 6 Seva programs below to view its current live photos, upload new event photography, or remove outdated shots. 
    Changes sync immediately to the interactive public showcase on <code>founder.html#vfSevaShowcase</code>.
  </p>

  <!-- Seva Programs Tab Navigation -->
  <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:8px; margin-bottom:20px;">
    <?php foreach ($SEVA_PROGRAMS as $sKey => $sInfo): 
      $isActive = ($sKey === $activeSeva);
      $sCount = count($gal['seva'][$sKey] ?? []);
    ?>
      <a href="foundation.php?seva=<?php echo urlencode($sKey); ?>#sevaSection" 
         class="btn-quick" 
         style="text-align:left; padding:10px 12px; border-radius:10px; text-decoration:none; display:flex; flex-direction:column; gap:3px; <?php echo $isActive ? 'background:var(--sp-primary-light); border:2px solid var(--sp-primary); box-shadow:0 3px 10px rgba(14,143,108,0.15);' : 'background:#FAFAFA; border:1px solid #E2E8F0;'; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <strong style="color:<?php echo $isActive ? 'var(--sp-primary-dark)' : '#1E293B'; ?>; font-size:13.5px;">
            <?php echo htmlspecialchars($sInfo['name']); ?>
          </strong>
          <span style="font-size:11px; font-weight:700; color:<?php echo $isActive ? 'var(--sp-primary)' : '#64748B'; ?>; background:rgba(0,0,0,0.04); padding:1px 6px; border-radius:9999px;">
            <?php echo $sCount; ?>
          </span>
        </div>
        <div style="font-size:11px; color:#64748B;">
          <?php echo htmlspecialchars($sInfo['telugu']); ?> &bull; <?php echo htmlspecialchars(explode(' ', $sInfo['name'])[0]); ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Active Seva Detail Panel -->
  <?php 
    $currSeva = $SEVA_PROGRAMS[$activeSeva];
    $currPhotos = $gal['seva'][$activeSeva] ?? [];
  ?>
  <div style="background:#F8FAFC; border:1px solid var(--sp-border); border-radius:12px; padding:20px 22px; margin-bottom:24px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
      <div>
        <div style="display:flex; align-items:center; gap:8px;">
          <h3 style="margin:0; font-size:18px; color:var(--sp-primary-dark);">
            <?php echo htmlspecialchars($currSeva['name']); ?>
            <span style="font-size:14px; font-weight:600; color:#0E8F6C;">(<?php echo htmlspecialchars($currSeva['telugu']); ?>)</span>
          </h3>
          <span class="badge-open" style="font-size:11px;"><?php echo count($currPhotos); ?> Photos Live</span>
        </div>
        <div style="font-size:12.5px; color:#475569; margin-top:3px;">
          <strong><?php echo htmlspecialchars($currSeva['sub']); ?></strong> &bull; <?php echo htmlspecialchars($currSeva['badge']); ?>
        </div>
      </div>
      <a href="../founder.html#panel-<?php echo htmlspecialchars($activeSeva); ?>" target="_blank" rel="noopener" class="btn btn-ghost" style="font-size:12px; padding:5px 10px;">
        Inspect Panel on Live Site &rarr;
      </a>
    </div>

    <!-- Drag & Drop Order Helper Banner -->
    <div style="display:flex; justify-content:space-between; align-items:center; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:9px 14px; margin-bottom:14px; font-size:12px; color:#475569; flex-wrap:wrap; gap:8px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0E8F6C" stroke-width="2.5"><path d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/></svg>
        <span><strong>Live Reordering Enabled:</strong> Drag &amp; drop any card to reorder, or click ◄ ► arrows. Slot #1 automatically becomes the lead featured photo on the public site!</span>
      </div>
      <span style="font-size:11px; color:#0E8F6C; font-weight:700; background:#ECFDF5; padding:2px 8px; border-radius:12px; border:1px solid #A7F3D0;">Auto-saves &amp; syncs live</span>
    </div>

    <!-- Photo Collage Gallery Grid -->
    <div id="sevaPhotoGrid" class="drag-reorder-grid" data-which="seva" data-seva="<?php echo htmlspecialchars($activeSeva); ?>" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:14px; margin-bottom:20px;">
      <?php foreach ($currPhotos as $pIdx => $photo): 
        $pSrc = is_array($photo) ? ($photo['src'] ?? '') : $photo;
        $pTitle = is_array($photo) ? ($photo['title'] ?? '') : '';
        $pTag = is_array($photo) ? ($photo['tag'] ?? '') : ($pIdx === 0 ? 'Featured Event' : 'Community Care');
      ?>
        <div class="drag-reorder-item" draggable="true" data-src="<?php echo htmlspecialchars($pSrc); ?>" style="border:1px solid #CBD5E1; border-radius:10px; padding:10px; background:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 6px rgba(0,0,0,0.03);">
          
          <!-- Reorder Bar with sequence badge & quick arrow buttons -->
          <div class="card-reorder-bar">
            <div class="drag-grip-handle" title="Drag to reorder card position">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                <circle cx="9" cy="6" r="2"/><circle cx="15" cy="6" r="2"/>
                <circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/>
                <circle cx="9" cy="18" r="2"/><circle cx="15" cy="18" r="2"/>
              </svg>
              <span>Move</span>
            </div>

            <div style="display:flex; align-items:center; gap:4px;">
              <span class="order-seq-badge <?php echo ($pIdx === 0 ? 'is-lead' : ''); ?>">
                <?php echo ($pIdx === 0 ? '#1 (Lead)' : '#' . ($pIdx + 1)); ?>
              </span>
              <button type="button" class="btn-order-move btn-move-prev" title="Move Left / Earlier" onclick="moveCard(this, -1)">◄</button>
              <button type="button" class="btn-order-move btn-move-next" title="Move Right / Later" onclick="moveCard(this, 1)">►</button>
            </div>
          </div>

          <div style="position:relative; margin-bottom:8px;">
            <a href="../<?php echo htmlspecialchars($pSrc); ?>" target="_blank" rel="noopener" title="Click to view full resolution">
              <img src="../<?php echo htmlspecialchars($pSrc); ?>" alt="<?php echo htmlspecialchars($pTitle); ?>" style="width:100%; height:110px; object-fit:cover; border-radius:6px; background:#f1f5f9; pointer-events:none;">
            </a>
          </div>

          <div style="font-size:11.5px; font-weight:600; color:#1E293B; margin-bottom:4px; line-height:1.35; height:32px; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($pTitle); ?>">
            <?php echo htmlspecialchars($pTitle ?: basename($pSrc)); ?>
          </div>

          <div style="font-size:10px; color:#94A3B8; margin-bottom:8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars($pSrc); ?>">
            <?php echo htmlspecialchars(basename($pSrc)); ?>
          </div>

          <form method="post" onsubmit="return confirm('Remove this photo from <?php echo htmlspecialchars($currSeva['name']); ?> gallery?');">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="action" value="delete_seva_img">
            <input type="hidden" name="seva_key" value="<?php echo htmlspecialchars($activeSeva); ?>">
            <input type="hidden" name="src" value="<?php echo htmlspecialchars($pSrc); ?>">
            <button class="btn btn-danger" type="submit" style="width:100%; justify-content:center; padding:5px 8px; font-size:11.5px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              Remove Photo
            </button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$currPhotos): ?>
        <div style="grid-column: 1 / -1; padding:28px; text-align:center; color:#94A3B8; background:#FFFFFF; border-radius:8px; border:1px dashed #CBD5E1;">
          No photos uploaded for <?php echo htmlspecialchars($currSeva['name']); ?> yet. Use the form below to add the first event image.
        </div>
      <?php endif; ?>
    </div>

    <!-- Upload New Photo Form for Active Seva -->
    <div style="background:#FFFFFF; border:1px solid #A7F3D0; border-radius:10px; padding:18px 20px;">
      <div style="font-weight:700; color:var(--sp-primary-dark); font-size:13.5px; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload &amp; Add Photo to <?php echo htmlspecialchars($currSeva['name']); ?> (<?php echo htmlspecialchars($currSeva['telugu']); ?>)
      </div>

      <form method="post" enctype="multipart/form-data">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="upload_seva_img">
        <input type="hidden" name="seva_key" value="<?php echo htmlspecialchars($activeSeva); ?>">

        <div style="margin-bottom:12px;">
          <label style="margin-top:0;">Photo Caption / Title *</label>
          <input name="title" required placeholder="e.g. Distribution of assistive kits to rural families" value="" style="width:100%;">
        </div>

        <div style="margin-top:12px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
          <div style="flex:1; min-width:260px;">
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required style="padding:8px 12px; background:#F8FAFC;">
          </div>
          <button class="btn btn-primary" type="submit" style="padding:10px 18px; font-weight:700;">
            + Upload Photo to <?php echo htmlspecialchars($currSeva['name']); ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ======================================================== -->
<!-- SECTION 2: 3-SCROLL MARQUEE & STREAM GALLERIES            -->
<!-- ======================================================== -->
<div id="marqueeSection" style="margin-top: 28px;">
  <?php
  function staff_gal_panel($title, $which, $list) {
    $count = count($list);
    ?>
    <div class="card" style="margin-top: 20px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
        <h2 style="margin:0; font-size:18px;">
          <?php echo htmlspecialchars($title); ?> 
          <span style="font-size:13px; font-weight:600; color:#0E8F6C;">(<?php echo $count; ?> images)</span>
        </h2>
      </div>

      <!-- Drag & Drop Order Helper Banner -->
      <div style="display:flex; justify-content:space-between; align-items:center; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:8px 12px; margin-bottom:14px; font-size:12px; color:#475569; flex-wrap:wrap; gap:8px;">
        <div style="display:flex; align-items:center; gap:8px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0E8F6C" stroke-width="2.5"><path d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/></svg>
          <span><strong>Drag &amp; Drop Reorder:</strong> Drag cards or click ◄ ► arrows to customize the scroll sequence in this continuous marquee stream.</span>
        </div>
        <span style="font-size:11px; color:#0E8F6C; font-weight:700; background:#ECFDF5; padding:2px 8px; border-radius:12px; border:1px solid #A7F3D0;">Auto-syncs live</span>
      </div>
      
      <div id="marqueeGrid-<?php echo htmlspecialchars($which); ?>" class="drag-reorder-grid" data-which="<?php echo htmlspecialchars($which); ?>" data-seva="" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:14px; margin-bottom:20px;">
        <?php foreach ($list as $pIdx => $src): ?>
          <div class="drag-reorder-item" draggable="true" data-src="<?php echo htmlspecialchars($src); ?>" style="border:1px solid var(--sp-border); border-radius:10px; padding:8px; background:#FAFAFA; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
            
            <!-- Reorder Bar with sequence badge & quick arrow buttons -->
            <div class="card-reorder-bar" style="margin-bottom:8px;">
              <div class="drag-grip-handle" title="Drag to reorder card position">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                  <circle cx="9" cy="6" r="2"/><circle cx="15" cy="6" r="2"/>
                  <circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/>
                  <circle cx="9" cy="18" r="2"/><circle cx="15" cy="18" r="2"/>
                </svg>
                <span>Move</span>
              </div>

              <div style="display:flex; align-items:center; gap:4px;">
                <span class="order-seq-badge">
                  #<?php echo ($pIdx + 1); ?>
                </span>
                <button type="button" class="btn-order-move btn-move-prev" title="Move Left / Earlier" onclick="moveCard(this, -1)">◄</button>
                <button type="button" class="btn-order-move btn-move-next" title="Move Right / Later" onclick="moveCard(this, 1)">►</button>
              </div>
            </div>

            <a href="../<?php echo htmlspecialchars($src); ?>" target="_blank" rel="noopener">
              <img src="../<?php echo htmlspecialchars($src); ?>" alt="" style="width:100%; height:96px; object-fit:cover; border-radius:6px; background:#eee; pointer-events:none;">
            </a>
            <div style="font-size:10.5px; color:#64748B; margin:6px 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo htmlspecialchars(basename($src)); ?>">
              <?php echo htmlspecialchars(basename($src)); ?>
            </div>
            <form method="post" onsubmit="return confirm('Are you sure you want to permanently delete this image from the marquee?');">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="delete_img">
              <input type="hidden" name="which" value="<?php echo htmlspecialchars($which); ?>">
              <input type="hidden" name="src" value="<?php echo htmlspecialchars($src); ?>">
              <button class="btn btn-danger" type="submit" style="width:100%; justify-content:center; padding:4px 8px; font-size:11.5px; font-weight:600;">Remove</button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if (!$list): ?>
          <div style="grid-column: 1 / -1; padding:24px; text-align:center; color:#94A3B8; background:#F8FAFC; border-radius:8px; border:1px dashed #CBD5E1;">
            No images in this marquee stream yet.
          </div>
        <?php endif; ?>
      </div>

      <form method="post" enctype="multipart/form-data" style="border-top:1px solid var(--sp-border); padding-top:16px;">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="upload_img">
        <input type="hidden" name="which" value="<?php echo htmlspecialchars($which); ?>">
        <label style="margin-top:0; font-weight:700; color:#334155;">Upload photo to <?php echo htmlspecialchars($title); ?></label>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:6px;">
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required style="max-width:360px;">
          <button class="btn btn-primary" type="submit" style="font-weight:700;">+ Upload &amp; Add to Gallery</button>
        </div>
      </form>
    </div>
    <?php
  }

  staff_gal_panel('Vasudha Foundation Marquee Stream', 'vasudha', $gal['vasudha']);
  staff_gal_panel('VRRV Family Foundation Marquee Stream', 'vrrv', $gal['vrrv']);
  staff_gal_panel('Moments from the Foundation Marquee Stream', 'moments', $gal['moments']);
  ?>
</div>

<!-- Floating Toast Notification for Live Reorder Updates -->
<div id="reorderToast" role="status" aria-live="polite">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
  <span id="reorderToastMsg">Photo sequence updated &amp; synced live.</span>
</div>

<style>
.drag-reorder-grid {
  transition: all 0.2s ease;
}
.drag-reorder-item {
  cursor: grab;
  user-select: none;
  transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
  position: relative;
}
.drag-reorder-item:active {
  cursor: grabbing;
}
.drag-reorder-item.is-dragging {
  opacity: 0.45;
  transform: scale(0.97);
  box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
  border-color: #0E8F6C !important;
}
.card-reorder-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #F1F5F9;
  border-radius: 6px;
  padding: 4px 6px;
  margin-bottom: 8px;
  border: 1px solid #E2E8F0;
}
.drag-grip-handle {
  display: flex;
  align-items: center;
  gap: 3px;
  font-size: 10.5px;
  font-weight: 700;
  color: #64748B;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  cursor: grab;
}
.order-seq-badge {
  font-size: 10.5px;
  font-weight: 700;
  background: #E2E8F0;
  color: #334155;
  padding: 1px 6px;
  border-radius: 4px;
}
.order-seq-badge.is-lead {
  background: #D1FAE5;
  color: #065F46;
  border: 1px solid #A7F3D0;
}
.btn-order-move {
  background: #FFFFFF;
  border: 1px solid #CBD5E1;
  border-radius: 4px;
  width: 22px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 9px;
  cursor: pointer;
  color: #475569;
  padding: 0;
  transition: all 0.12s ease;
}
.btn-order-move:hover {
  background: #0E8F6C;
  color: #FFFFFF;
  border-color: #0E8F6C;
}
.btn-order-move:active {
  transform: scale(0.92);
}

/* Floating Toast */
#reorderToast {
  position: fixed;
  bottom: 28px;
  right: 28px;
  background: #064E3B;
  border-left: 4px solid #10B981;
  color: #FFFFFF;
  padding: 12px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 10px 25px rgba(0,0,0,0.25);
  display: flex;
  align-items: center;
  gap: 10px;
  z-index: 99999;
  opacity: 0;
  transform: translateY(16px);
  pointer-events: none;
  transition: opacity 0.25s ease, transform 0.25s ease;
}
#reorderToast.show {
  opacity: 1;
  transform: translateY(0);
}
#reorderToast.is-error {
  background: #7F1D1D;
  border-left-color: #EF4444;
}
</style>

<script>
(function() {
  const CSRF_TOKEN = <?php echo json_encode(staff_csrf_token()); ?>;
  let toastTimer = null;

  function showToast(msg, isError) {
    const toast = document.getElementById('reorderToast');
    const msgEl = document.getElementById('reorderToastMsg');
    if (!toast || !msgEl) return;
    
    msgEl.textContent = msg;
    toast.classList.remove('is-error');
    if (isError) toast.classList.add('is-error');
    toast.classList.add('show');
    
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.classList.remove('show');
    }, 3200);
  }

  function updateGridBadges(grid) {
    if (!grid) return;
    const isSeva = (grid.dataset.which === 'seva');
    const items = grid.querySelectorAll('.drag-reorder-item');
    items.forEach((item, idx) => {
      const badge = item.querySelector('.order-seq-badge');
      if (badge) {
        if (isSeva && idx === 0) {
          badge.textContent = '#1 (Lead)';
          badge.classList.add('is-lead');
        } else {
          badge.textContent = '#' + (idx + 1);
          badge.classList.remove('is-lead');
        }
      }
    });
  }

  function saveGridOrder(grid) {
    if (!grid) return;
    const which = grid.dataset.which || '';
    const sevaKey = grid.dataset.seva || '';
    const items = Array.from(grid.querySelectorAll('.drag-reorder-item'));
    const order = items.map(el => el.dataset.src).filter(Boolean);

    const formData = new FormData();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('action', 'reorder_gallery');
    formData.append('which', which);
    formData.append('seva_key', sevaKey);
    formData.append('order', JSON.stringify(order));
    formData.append('ajax', '1');

    fetch('foundation.php', {
      method: 'POST',
      body: formData,
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
      if (data && data.ok) {
        showToast(data.msg || 'Order updated and synced live!');
      } else {
        showToast(data && data.error ? data.error : 'Failed to update order.', true);
      }
    })
    .catch(err => {
      console.error('Reorder save error:', err);
      showToast('Error syncing order: ' + err.message, true);
    });
  }

  // Quick ◄ and ► Arrow button movement
  window.moveCard = function(btn, dir) {
    const item = btn.closest('.drag-reorder-item');
    if (!item) return;
    const grid = item.closest('.drag-reorder-grid');
    if (!grid) return;

    if (dir === -1) {
      const prev = item.previousElementSibling;
      if (prev && prev.classList.contains('drag-reorder-item')) {
        grid.insertBefore(item, prev);
        updateGridBadges(grid);
        saveGridOrder(grid);
      }
    } else if (dir === 1) {
      const next = item.nextElementSibling;
      if (next && next.classList.contains('drag-reorder-item')) {
        grid.insertBefore(next, item);
        updateGridBadges(grid);
        saveGridOrder(grid);
      }
    }
  };

  // Drag and Drop Event Setup
  let draggedCard = null;
  let activeGrid = null;

  document.querySelectorAll('.drag-reorder-grid').forEach(grid => {
    grid.addEventListener('dragstart', (e) => {
      const card = e.target.closest('.drag-reorder-item');
      if (!card) return;
      draggedCard = card;
      activeGrid = grid;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.src || '');
      setTimeout(() => card.classList.add('is-dragging'), 0);
    });

    grid.addEventListener('dragend', (e) => {
      const card = e.target.closest('.drag-reorder-item');
      if (card) card.classList.remove('is-dragging');
      if (activeGrid) {
        updateGridBadges(activeGrid);
        saveGridOrder(activeGrid);
      }
      draggedCard = null;
      activeGrid = null;
    });

    grid.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      if (!draggedCard || activeGrid !== grid) return;

      const targetCard = e.target.closest('.drag-reorder-item');
      if (!targetCard || targetCard === draggedCard) return;

      const rect = targetCard.getBoundingClientRect();
      const midX = rect.left + rect.width / 2;
      const isAfter = (e.clientX > midX);

      if (isAfter) {
        grid.insertBefore(draggedCard, targetCard.nextSibling);
      } else {
        grid.insertBefore(draggedCard, targetCard);
      }
      updateGridBadges(grid);
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
