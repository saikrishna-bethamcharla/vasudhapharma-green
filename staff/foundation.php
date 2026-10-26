<?php
require __DIR__ . '/includes/auth.php';
staff_require_desk('foundation');
$u = staff_user();
$data = staff_load_desk('foundation');
if (!isset($data['items'])) $data['items'] = [];
$msg = '';
$err = '';
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
  if (!is_file($f)) return ['vasudha' => [], 'vrrv' => [], 'moments' => [], 'seva' => []];
  $j = json_decode(file_get_contents($f), true);
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
  return file_put_contents($f, json_encode($g, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
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
        $dir = dirname(staff_root()) . '/assets/foundation';
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
  }

  // 2. Delete photo from a specific Seva Program
  if ($action === 'delete_seva_img') {
    $sevaKey = $_POST['seva_key'] ?? 'aashraya';
    if (!array_key_exists($sevaKey, $SEVA_PROGRAMS)) $sevaKey = 'aashraya';
    $activeSeva = $sevaKey;
    $src = $_POST['src'] ?? '';

    $gal['seva'][$sevaKey] = array_values(array_filter($gal['seva'][$sevaKey], function($item) use ($src) {
      $itemSrc = is_array($item) ? ($item['src'] ?? '') : $item;
      return $itemSrc !== $src;
    }));
    staff_save_gal($gal_file, $gal);

    if (strpos($src, 'assets/foundation/seva-') === 0) {
      $path = dirname(staff_root()) . '/' . $src;
      if (is_file($path)) @unlink($path);
    }
    $msg = 'Photo removed from ' . $SEVA_PROGRAMS[$sevaKey]['name'] . ' gallery.';
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
        $dir = dirname(staff_root()) . '/assets/' . $folder;
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = $which . '-' . date('Ymd-His') . '-' . mt_rand(100,999) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
          $gal[$which][] = 'assets/' . $folder . '/' . $name;
          staff_save_gal($gal_file, $gal);
          $label = ($which === 'vrrv' ? 'VRRV Family' : ($which === 'moments' ? 'Moments' : 'Vasudha Foundation'));
          $msg = 'Image added to the ' . $label . ' marquee stream.';
        } else {
          $err = 'Could not save the uploaded file. Check folder permissions on assets/' . $folder . '.';
        }
      }
    } else {
      $err = 'Please choose an image file to upload.';
    }
  }

  // 4. Delete photo from 3 Marquee Streams
  if ($action === 'delete_img') {
    $which = $_POST['which'] ?? 'vasudha';
    if (!in_array($which, ['vasudha','vrrv','moments'], true)) $which = 'vasudha';
    $src = $_POST['src'] ?? '';
    $gal[$which] = array_values(array_filter($gal[$which], function ($s) use ($src) { return $s !== $src; }));
    staff_save_gal($gal_file, $gal);
    if (strpos($src, 'assets/foundation/') === 0 || strpos($src, 'assets/founder-gallery/') === 0) {
      $path = dirname(staff_root()) . '/' . $src;
      if (is_file($path)) @unlink($path);
    }
    $msg = 'Image removed from marquee stream.';
  }

  // 5. Save CSR Initiative Record
  if ($action === 'save_item') {
    $item = [
      'id' => trim($_POST['id'] ?? '') ?: ('EV-' . time()),
      'title' => trim($_POST['title'] ?? ''),
      'date' => trim($_POST['date'] ?? ''),
      'place' => trim($_POST['place'] ?? ''),
      'status' => ($_POST['status'] ?? 'draft') === 'live' ? 'live' : 'draft',
      'body' => trim($_POST['body'] ?? ''),
    ];
    if ($item['title'] === '') {
      $err = 'Event title is required.';
    } else {
      $found = false;
      foreach ($data['items'] as $i => $it) {
        if ($it['id'] === $item['id']) { $data['items'][$i] = $item; $found = true; break; }
      }
      if (!$found) $data['items'][] = $item;
      staff_save_desk('foundation', $data);
      $msg = 'Foundation initiative successfully saved.';
    }
  }

  // 6. Delete CSR Initiative Record
  if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    $data['items'] = array_values(array_filter($data['items'], function ($it) use ($id) { return $it['id'] !== $id; }));
    staff_save_desk('foundation', $data);
    $msg = 'Initiative removed.';
  }
}

$staff_title = 'Foundation';
require __DIR__ . '/includes/header.php';
$edit = null;
if (isset($_GET['edit'])) {
  foreach ($data['items'] as $it) if ($it['id'] === $_GET['edit']) $edit = $it;
}

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
<div class="card" style="background: linear-gradient(135deg, #096B51 0%, #0E8F6C 55%, #1DB88A 100%); color:#FFFFFF; border:0; padding:24px 28px; box-shadow: 0 8px 24px rgba(14,143,108,0.18);">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.12em; opacity:0.85; font-weight:700; margin-bottom:4px;">
        Community Service &bull; CSR Programs &bull; Visual Storytelling
      </div>
      <h1 style="margin:0; font-size:22px; color:#FFFFFF;">Vasudha Foundation Operations Desk</h1>
      <p style="margin:6px 0 0; opacity:0.9; font-size:13px; max-width:800px; line-height:1.5;">
        Control and upload event photography across the <strong>6 Seva Initiatives (Aashraya, Aadarana, Aalayam, Akshaya, Aarogyam, Aksharam)</strong> individually, and maintain community program records.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <a href="../founder.html#vfSevaShowcase" target="_blank" rel="noopener" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        View Public Seva Showcase
      </a>

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
        ⭐ Multi-Photo Mosaic &amp; Lightbox Manager
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

    <!-- Photo Collage Gallery Grid -->
    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:14px; margin-bottom:20px;">
      <?php foreach ($currPhotos as $pIdx => $photo): 
        $pSrc = is_array($photo) ? ($photo['src'] ?? '') : $photo;
        $pTitle = is_array($photo) ? ($photo['title'] ?? '') : '';
        $pTag = is_array($photo) ? ($photo['tag'] ?? '') : ($pIdx === 0 ? 'Featured Event' : 'Community Care');
      ?>
        <div style="border:1px solid #CBD5E1; border-radius:10px; padding:8px; background:#FFFFFF; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 6px rgba(0,0,0,0.03);">
          <div style="position:relative; margin-bottom:8px;">
            <a href="../<?php echo htmlspecialchars($pSrc); ?>" target="_blank" rel="noopener" title="Click to view full resolution">
              <img src="../<?php echo htmlspecialchars($pSrc); ?>" alt="<?php echo htmlspecialchars($pTitle); ?>" style="width:100%; height:110px; object-fit:cover; border-radius:6px; background:#f1f5f9;">
            </a>

            <span style="position:absolute; bottom:6px; right:6px; background:rgba(255,255,255,0.9); color:#0F172A; font-size:10px; font-weight:700; padding:1px 5px; border-radius:3px;">
              #<?php echo ($pIdx + 1); ?>
            </span>
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
<!-- SECTION 3: CSR INITIATIVES & COMMUNITY RECORDS            -->
<!-- ======================================================== -->
<div class="card" style="margin-top:32px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:18px;">Foundation Programs &amp; Initiatives (<?php echo count($data['items']); ?>)</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Community health camps, education scholarships, and CSR records
      </div>
    </div>
    <a href="foundation.php#itemForm" class="btn btn-primary">+ New Initiative</a>
  </div>

  <table id="foundationTable">
    <thead>
      <tr>
        <th>ID</th>
        <th>Program Title</th>
        <th>Date &amp; Venue</th>
        <th>Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($data['items'] as $it): 
      $isLive = ($it['status'] ?? '') === 'live';
    ?>
      <tr>
        <td><code style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-size:12px;"><?php echo htmlspecialchars($it['id']); ?></code></td>
        <td><strong style="color:var(--sp-text-main);"><?php echo htmlspecialchars($it['title']); ?></strong></td>
        <td><?php echo htmlspecialchars(($it['date'] ?? '') . (!empty($it['place']) ? ' · ' . $it['place'] : '')); ?></td>
        <td>
          <span class="<?php echo $isLive ? 'badge-open' : 'badge-closed'; ?>">
            <?php echo $isLive ? '● Live' : '○ Draft'; ?>
          </span>
        </td>
        <td style="text-align:right; white-space:nowrap;">
          <a class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" href="foundation.php?edit=<?php echo urlencode($it['id']); ?>#itemForm">Edit</a>
          <form method="post" style="display:inline">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($it['id']); ?>">
            <button class="btn btn-danger" style="padding:5px 9px; font-size:12px;" name="action" value="delete" onclick="return confirm('Remove this initiative?');">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data['items']): ?>
      <tr><td colspan="5" style="text-align:center; padding:24px; color:#64748B;">No initiatives recorded yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" id="itemForm">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <h2 style="margin:0; font-size:18px;"><?php echo $edit ? ('Edit Initiative: ' . htmlspecialchars($edit['title'])) : 'Record New Initiative'; ?></h2>
    <?php if ($edit): ?><a class="btn btn-ghost" href="foundation.php" style="font-size:12px">Cancel</a><?php endif; ?>
  </div>

  <form method="post">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="save_item">
    
    <div class="row">
      <div>
        <label>Record ID</label>
        <input name="id" value="<?php echo htmlspecialchars($edit['id'] ?? ''); ?>" placeholder="e.g. CSR-2026-01 (leave blank to auto-create)">
      </div>
      <div>
        <label>Status</label>
        <select name="status">
          <option value="live" <?php echo (($edit['status'] ?? 'live')==='live')?'selected':''; ?>>Live</option>
          <option value="draft" <?php echo (($edit['status'] ?? '')==='draft')?'selected':''; ?>>Draft</option>
        </select>
      </div>
    </div>

    <label>Program Title</label>
    <input name="title" required value="<?php echo htmlspecialchars($edit['title'] ?? ''); ?>" placeholder="e.g. Rural Healthcare &amp; Medical Camp">

    <div class="row">
      <div>
        <label>Date</label>
        <input name="date" value="<?php echo htmlspecialchars($edit['date'] ?? ''); ?>" placeholder="e.g. August 2026">
      </div>
      <div>
        <label>Location / Village / District</label>
        <input name="place" value="<?php echo htmlspecialchars($edit['place'] ?? ''); ?>" placeholder="e.g. Visakhapatnam, Andhra Pradesh">
      </div>
    </div>

    <label>Details &amp; Impact Description</label>
    <textarea name="body" placeholder="Describe community reach, beneficiaries, medical equipment distributed..."><?php echo htmlspecialchars($edit['body'] ?? ''); ?></textarea>

    <p style="margin-top:22px;">
      <button class="btn btn-primary" type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <?php echo $edit ? 'Save Changes' : 'Save Initiative'; ?>
      </button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="foundation.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
