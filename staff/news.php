<?php
require __DIR__ . '/includes/auth.php';
staff_require_desk('news');
$u = staff_user();
$file = dirname(staff_root()) . '/news-events.json';
$dir = dirname(staff_root()) . '/assets/news-events';

function ne_load($f) {
  return staff_load_news_events();
}
function ne_save($f, $d) {
  return staff_save_news_events($d);
}
$data = ne_load($file);
$msg = '';
$err = '';

function ne_upload($dir, $input = 'image') {
  if (empty($_FILES[$input]['tmp_name']) || !is_uploaded_file($_FILES[$input]['tmp_name'])) return '';
  $ext = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) return false;
  if (!@getimagesize($_FILES[$input]['tmp_name'])) return false;
  if ($_FILES[$input]['size'] > 5 * 1024 * 1024) return false;
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $name = 'ne-' . date('Ymd-His') . '-' . mt_rand(100,999) . '.' . $ext;
  $dest = $dir . '/' . $name;
  if (!move_uploaded_file($_FILES[$input]['tmp_name'], $dest)) return false;
  return 'assets/news-events/' . $name;
}

// Helper: Replace content cleanly between specific HTML comment markers
function staff_replace_between_markers($html, $startMarker, $endMarker, $replacement) {
  $posStart = strpos($html, $startMarker);
  if ($posStart === false) return false;
  $posEnd = strpos($html, $endMarker, $posStart + strlen($startMarker));
  if ($posEnd === false) return false;

  $before = substr($html, 0, $posStart + strlen($startMarker));
  $after = substr($html, $posEnd);
  return $before . "\n" . $replacement . "\n" . $after;
}

function staff_format_booth_html($boothStr) {
  $boothStr = trim($boothStr);
  if ($boothStr === '') return 'Booth: <strong>TBA</strong>';
  if (preg_match('/^(Member Suite|Suite|Booth|Stand|Hall):\s*(.*)$/i', $boothStr, $m)) {
    return htmlspecialchars($m[1]) . ': <strong>' . htmlspecialchars($m[2]) . '</strong>';
  }
  return 'Booth: <strong>' . htmlspecialchars($boothStr) . '</strong>';
}

// Reusable synchronization engine for Homepage Marquee, Events Grid, and News Grid
function staff_sync_news_events_to_html($data, $siteRoot) {
  $syncedFiles = [];

  // Filter live items
  $liveEvents = array_values(array_filter($data['events'] ?? [], function($e) {
    return ($e['status'] ?? 'live') === 'live';
  }));

  $liveNews = array_values(array_filter($data['news'] ?? [], function($n) {
    return ($n['status'] ?? 'live') === 'live';
  }));

  // Build Homepage Glassmorphic Marquee HTML
  $marqueeHtml = '';
  foreach ($liveEvents as $idx => $e) {
    $eNum = $idx + 1;
    $title = htmlspecialchars($e['title'] ?? '');
    $tag = htmlspecialchars($e['tag'] ?? 'Meet us at');
    $place = htmlspecialchars($e['place'] ?? '');
    $dates = htmlspecialchars($e['dates'] ?? '');
    $img = trim($e['image'] ?? '');
    $imgHtml = $img ? '<img src="' . htmlspecialchars($img) . '" alt="' . $title . '" loading="lazy">' : '';
    $boothHtml = staff_format_booth_html($e['booth'] ?? '');
    $mail = 'mailto:wisdom@vasudhapharma.com?subject=' . rawurlencode('Slot request — ' . ($e['title'] ?? 'Event'));

    $marqueeHtml .= "          <!-- Event {$eNum}: {$title} -->\n";
    $marqueeHtml .= "          <article class=\"vp-glass-event-card\">\n";
    $marqueeHtml .= "            <div class=\"vp-glass-event-thumb\">\n";
    if ($imgHtml) $marqueeHtml .= "              {$imgHtml}\n";
    $marqueeHtml .= "              <span class=\"vp-glass-tag\">{$tag}</span>\n";
    $marqueeHtml .= "            </div>\n";
    $marqueeHtml .= "            <div class=\"vp-glass-event-body\">\n";
    $marqueeHtml .= "              <div class=\"vp-glass-event-date\"><i class=\"far fa-calendar-alt\"></i> {$dates}</div>\n";
    $marqueeHtml .= "              <h3 class=\"vp-glass-event-title\">{$title}</h3>\n";
    $marqueeHtml .= "              <p class=\"vp-glass-event-venue\"><i class=\"fas fa-map-marker-alt\"></i> {$place}</p>\n";
    $marqueeHtml .= "              <div class=\"vp-glass-event-footer\">\n";
    $marqueeHtml .= "                <span class=\"vp-glass-booth\"><i class=\"fas fa-store\"></i> {$boothHtml}</span>\n";
    $marqueeHtml .= "                <a href=\"{$mail}\" class=\"vp-glass-book-btn\">Book Slot &rarr;</a>\n";
    $marqueeHtml .= "              </div>\n";
    $marqueeHtml .= "            </div>\n";
    $marqueeHtml .= "          </article>\n\n";
  }
  $marqueeHtml = rtrim($marqueeHtml);

  // Build Standalone Events Page Grid HTML
  $eventsGridHtml = '';
  foreach ($liveEvents as $e) {
    $title = htmlspecialchars($e['title'] ?? '');
    $tag = htmlspecialchars($e['tag'] ?? 'Meet us at');
    $place = htmlspecialchars($e['place'] ?? '');
    $dates = htmlspecialchars($e['dates'] ?? '');
    $booth = htmlspecialchars($e['booth'] ?? '');
    $img = trim($e['image'] ?? '');
    $imgHtml = $img ? '<img src="' . htmlspecialchars($img) . '" alt="' . $title . '">' : '';
    $mail = 'mailto:wisdom@vasudhapharma.com?subject=' . rawurlencode('Slot request — ' . ($e['title'] ?? 'Event'));

    $eventsGridHtml .= "        <article class=\"vp-event-card\">\n";
    $eventsGridHtml .= "          <div class=\"vp-event-photo has-photo\">{$imgHtml}</div>\n";
    $eventsGridHtml .= "          <div class=\"vp-event-head\">\n";
    $eventsGridHtml .= "            <span class=\"vp-event-tag\">{$tag}</span>\n";
    $eventsGridHtml .= "            <h3>{$title}</h3>\n";
    $eventsGridHtml .= "            <p>{$place}</p>\n";
    $eventsGridHtml .= "            <p>{$dates}</p>\n";
    if ($booth) $eventsGridHtml .= "            <p class="vp-event-booth">{$booth}</p>\n";
    $eventsGridHtml .= "          </div>\n";
    $eventsGridHtml .= "          <div class=\"vp-event-foot\">\n";
    $eventsGridHtml .= "            <h4>{$title}</h4>\n";
    $eventsGridHtml .= "            <a href=\"{$mail}\">Book a slot &rarr;</a>\n";
    $eventsGridHtml .= "          </div>\n";
    $eventsGridHtml .= "        </article>\n\n";
  }
  $eventsGridHtml = rtrim($eventsGridHtml);

  // Build News Grid HTML
  $newsGridHtml = '';
  foreach ($liveNews as $n) {
    $title = htmlspecialchars($n['title'] ?? '');
    $kicker = htmlspecialchars($n['kicker'] ?? '');
    $date = htmlspecialchars($n['date'] ?? '');
    $summary = htmlspecialchars($n['summary'] ?? '');
    $link = trim($n['link'] ?? '#');
    $isExt = (bool)preg_match('/^https?:\/\//i', $link);
    $targetAttr = $isExt ? ' target="_blank" rel="noopener noreferrer"' : '';
    $img = trim($n['image'] ?? '');
    $imgHtml = $img ? '<img src="' . htmlspecialchars($img) . '" alt="' . $title . '" loading="lazy">' : '';
    $kickerHtml = $kicker ? '<span class=\"news-card-kicker\">' . $kicker . '</span>' : '';

    $newsGridHtml .= "        <article class=\"news-card\">\n";
    $newsGridHtml .= "          <div class=\"news-card-img\">{$imgHtml}{$kickerHtml}</div>\n";
    $newsGridHtml .= "          <div class=\"news-card-body\">\n";
    if ($date) $newsGridHtml .= "            <div class=\"news-card-date\">{$date}</div>\n";
    $newsGridHtml .= "            <h3>{$title}</h3>\n";
    $newsGridHtml .= "            <p>{$summary}</p>\n";
    $newsGridHtml .= "            <a href=\"" . htmlspecialchars($link) . "\"{$targetAttr}>Read more &rarr;</a>\n";
    $newsGridHtml .= "          </div>\n";
    $newsGridHtml .= "        </article>\n\n";
  }
  $newsGridHtml = rtrim($newsGridHtml);

  // 1. Sync index.html and home.html
  foreach (['index.html', 'home.html'] as $hfile) {
    $path = $siteRoot . '/' . $hfile;
    if (is_file($path)) {
      $c = file_get_contents($path);
      $modified = false;
      if ($marqueeHtml) {
        $repM = staff_replace_between_markers($c, '<!-- START: HOMEPAGE_EVENTS_MARQUEE -->', '<!-- END: HOMEPAGE_EVENTS_MARQUEE -->', $marqueeHtml);
        if ($repM !== false) { $c = $repM; $modified = true; }
      }
      if ($newsGridHtml) {
        $repN = staff_replace_between_markers($c, '<!-- START: HOMEPAGE_NEWS_GRID -->', '<!-- END: HOMEPAGE_NEWS_GRID -->', $newsGridHtml);
        if ($repN !== false) { $c = $repN; $modified = true; }
      }
      if ($modified) {
        file_put_contents($path, $c);
        $syncedFiles[] = $hfile;
      }
    }
  }

  // 2. Sync events.html
  $evPath = $siteRoot . '/events.html';
  if (is_file($evPath)) {
    $c = file_get_contents($evPath);
    $modified = false;
    if ($eventsGridHtml) {
      $repEG = staff_replace_between_markers($c, '<!-- START: EVENTS_PAGE_GRID -->', '<!-- END: EVENTS_PAGE_GRID -->', $eventsGridHtml);
      if ($repEG !== false) { $c = $repEG; $modified = true; }
    }
    if ($newsGridHtml) {
      $repENG = staff_replace_between_markers($c, '<!-- START: EVENTS_PAGE_NEWS_GRID -->', '<!-- END: EVENTS_PAGE_NEWS_GRID -->', $newsGridHtml);
      if ($repENG !== false) { $c = $repENG; $modified = true; }
    }
    if ($modified) {
      file_put_contents($evPath, $c);
      $syncedFiles[] = 'events.html';
    }
  }

  return $syncedFiles;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';
  $kind = ($_POST['kind'] ?? '') === 'news' ? 'news' : 'events';

  if ($action === 'save') {
    $id = trim($_POST['id'] ?? '') ?: (strtoupper(substr($kind,0,2)) . '-' . time());
    $item = ['id' => $id, 'status' => ($_POST['status'] ?? 'live') === 'done' ? 'done' : 'live'];
    if ($kind === 'events') {
      $item += [
        'title' => trim($_POST['title'] ?? ''),
        'place' => trim($_POST['place'] ?? ''),
        'dates' => trim($_POST['dates'] ?? ''),
        'booth' => trim($_POST['booth'] ?? ''),
        'tag' => trim($_POST['tag'] ?? 'Meet us at'),
        'image' => trim($_POST['image'] ?? ''),
      ];
    } else {
      $item += [
        'title' => trim($_POST['title'] ?? ''),
        'kicker' => trim($_POST['kicker'] ?? ''),
        'date' => trim($_POST['date'] ?? ''),
        'summary' => trim($_POST['summary'] ?? ''),
        'link' => trim($_POST['link'] ?? ''),
        'image' => trim($_POST['image'] ?? ''),
      ];
    }
    $up = ne_upload($dir);
    if ($up === false) {
      $err = 'Uploaded image must be JPG, PNG or WEBP under 5 MB.';
    } elseif ($up) {
      foreach ($data[$kind] as $row) {
        if ($row['id'] === $id && !empty($row['image']) && $row['image'] !== $up && strpos($row['image'], 'assets/news-events/') === 0) {
          $oldF = dirname(staff_root()) . '/' . $row['image'];
          if (is_file($oldF)) @unlink($oldF);
        }
      }
      $item['image'] = $up;
    }
    
    if ($item['title'] === '') {
      $err = $err ?: 'Title is required.';
    } else {
      $found = false;
      foreach ($data[$kind] as $i => $row) {
        if ($row['id'] === $id) { $data[$kind][$i] = $item; $found = true; break; }
      }
      if (!$found) $data[$kind][] = $item;
      ne_save($file, $data);
      $synced = staff_sync_news_events_to_html($data, dirname(staff_root()));
      staff_log_audit($found ? ($kind . '_updated') : ($kind . '_published'), ($found ? 'Updated' : 'Published new') . " $kind item: '{$item['title']}'", $id, 'News & PR');
      header('Location: news.php?saved=1&synced=' . count($synced));
      exit;
    }
  }

  if ($action === 'done' || $action === 'live') {
    $id = $_POST['id'] ?? '';
    foreach ($data[$kind] as $i => $row) {
      if ($row['id'] === $id) $data[$kind][$i]['status'] = $action === 'done' ? 'done' : 'live';
    }
    ne_save($file, $data);
    $synced = staff_sync_news_events_to_html($data, dirname(staff_root()));
    staff_log_audit($kind . '_status_changed', "Changed status of $kind item {$id} to " . ($action === 'done' ? 'Concluded / Inactive' : 'Live / Active'), $id, 'News & PR');
    header('Location: news.php?saved=1&synced=' . count($synced));
    exit;
  }

  if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    foreach ($data[$kind] as $row) {
      if ($row['id'] === $id && !empty($row['image']) && strpos($row['image'], 'assets/news-events/') === 0) {
        $oldF = dirname(staff_root()) . '/' . $row['image'];
        if (is_file($oldF)) @unlink($oldF);
      }
    }
    $data[$kind] = array_values(array_filter($data[$kind], function ($row) use ($id) { return $row['id'] !== $id; }));
    ne_save($file, $data);
    $synced = staff_sync_news_events_to_html($data, dirname(staff_root()));
    staff_log_audit($kind . '_deleted', "Deleted $kind item {$id}", $id, 'News & PR', 'warning');
    header('Location: news.php?saved=1&synced=' . count($synced));
    exit;
  }

  if ($action === 'sync_all') {
    $synced = staff_sync_news_events_to_html($data, dirname(staff_root()));
    staff_log_audit('news_events_synced_all', 'Manual re-sync of News & Events marquee and newsroom across public website (' . count($synced) . ' files: ' . implode(', ', $synced) . ')', 'public_html', 'News & PR');
    header('Location: news.php?synced_all=' . count($synced));
    exit;
  }
}

if (isset($_GET['saved'])) {
  $cnt = intval($_GET['synced'] ?? 0);
  $msg = 'Successfully saved. Updated and synchronized across ' . ($cnt > 0 ? ($cnt . ' website pages including the homepage marquee and newsroom.') : 'the public website.');
}
if (isset($_GET['synced_all'])) {
  $cnt = intval($_GET['synced_all'] ?? 0);
  $msg = 'Full synchronization successful! Re-rendered homepage marquee, events grid, and newsroom across ' . $cnt . ' website pages (index.html, home.html, events.html).';
}

$staff_title = 'News & Events';
require __DIR__ . '/includes/header.php';

$editE = $editN = null;
if (isset($_GET['edit']) && isset($_GET['kind'])) {
  $k = $_GET['kind'] === 'news' ? 'news' : 'events';
  foreach ($data[$k] as $row) {
    if ($row['id'] === $_GET['edit']) {
      if ($k === 'news') $editN = $row; else $editE = $row;
    }
  }
}

function ne_thumb($src) {
  if (!$src) return '<div style="width:90px;height:54px;background:#F1F5F9;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#94A3B8;font-size:11px;">No Image</div>';
  return '<img src="../'.htmlspecialchars($src).'" alt="" style="width:90px;height:54px;object-fit:cover;border-radius:6px;border:1px solid #E2E8F0;background:#eee">';
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

<div class="desk-hero-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; position:relative; z-index:1;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.1em; opacity:0.85; font-weight:700; margin-bottom:6px;">
        Corporate Communications &bull; Media Relations
      </div>
      <h1 style="margin:0 0 4px; font-size:24px; color:#FFFFFF;">News, Media &amp; Conferences Desk</h1>
      <p style="margin:0; opacity:0.9; font-size:13px; color:#E2E8F0; max-width:800px; line-height:1.5;">
        Publish international pharmaceutical trade conferences, booth locations, and corporate press releases synchronized with the public newsroom.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
      <form method="post" style="display:inline; margin:0;">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="sync_all">
        <button type="submit" class="btn btn-ghost" style="background:rgba(255,255,255,0.15); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-size:12.5px;" title="Force re-sync homepage marquee and public newsroom">
          🔄 Force Re-Sync Marquee &amp; News
        </button>
      </form>
      <a href="news.php#eventForm" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
        + Add Event
      </a>
      <a href="news.php#newsForm" class="btn" style="background:rgba(255,255,255,0.14); color:#FFFFFF; border:1px solid rgba(255,255,255,0.25); font-weight:600; font-size:12.5px;">
        + Post News Article
      </a>
    </div>
  </div>
</div>

<!-- ==================== SECTION 1: EVENTS ==================== -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:18px;">Global Conferences &amp; Events (<?php echo count($data['events']); ?>)</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Exhibitions, summits, and booth locations
      </div>
    </div>
    <a href="news.php#eventForm" class="btn btn-primary">+ Add New Event</a>
  </div>

  <div class="table-search-bar">
    <input type="text" placeholder="Search events by name, city, dates..." data-search-target="#eventsTable">
  </div>

  <table id="eventsTable">
    <thead>
      <tr>
        <th style="width:100px;">Banner</th>
        <th>Event Title</th>
        <th>Venue &amp; Dates</th>
        <th>Booth</th>
        <th>Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($data['events'] as $row): 
      $isLive = ($row['status'] ?? 'live') === 'live';
    ?>
      <tr>
        <td><?php echo ne_thumb($row['image'] ?? ''); ?></td>
        <td><strong style="color:var(--sp-text-main);"><?php echo htmlspecialchars($row['title']); ?></strong></td>
        <td>
          <div><?php echo htmlspecialchars($row['place'] ?? ''); ?></div>
          <div style="font-size:11.5px; color:#64748B;"><?php echo htmlspecialchars($row['dates'] ?? ''); ?></div>
        </td>
        <td><code style="background:#F1F5F9; padding:2px 6px; border-radius:4px; font-size:12px;"><?php echo htmlspecialchars($row['booth'] ?? '—'); ?></code></td>
        <td>
          <span class="<?php echo $isLive ? 'badge-open' : 'badge-closed'; ?>">
            <?php echo $isLive ? '● Live' : '○ Archived'; ?>
          </span>
        </td>
        <td style="text-align:right; white-space:nowrap;">
          <a class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" href="news.php?kind=events&edit=<?php echo urlencode($row['id']); ?>#eventForm">Edit</a>
          <form method="post" style="display:inline">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="kind" value="events">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
            <?php if ($isLive): ?>
              <button class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" name="action" value="done">Archive</button>
            <?php else: ?>
              <button class="btn btn-ghost" style="padding:5px 9px; font-size:12px; color:#059669;" name="action" value="live">Make Live</button>
            <?php endif; ?>
            <button class="btn btn-danger" style="padding:5px 9px; font-size:12px;" name="action" value="delete" onclick="return confirm('Remove event <?php echo htmlspecialchars($row['title']); ?>?');">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data['events']): ?><tr><td colspan="6" style="text-align:center; padding:24px; color:#64748B;">No events configured yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" id="eventForm">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <h2 style="margin:0; font-size:18px;"><?php echo $editE ? ('Edit Event: ' . htmlspecialchars($editE['title'])) : 'Add New Conference Event'; ?></h2>
    <?php if ($editE): ?>
      <a class="btn btn-ghost" href="news.php" style="font-size:12px">Cancel Edit</a>
    <?php endif; ?>
  </div>
  
  <form method="post" enctype="multipart/form-data">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="kind" value="events">
    <input type="hidden" name="image" value="<?php echo htmlspecialchars($editE['image'] ?? ''); ?>">
    
    <div class="row">
      <div>
        <label>Event ID</label>
        <input name="id" value="<?php echo htmlspecialchars($editE['id'] ?? ''); ?>" placeholder="e.g. EV-CPHI-2026 (leave blank to auto-create)">
      </div>
      <div>
        <label>Display Status</label>
        <select name="status">
          <option value="live" <?php echo (($editE['status']??'live')==='live')?'selected':''; ?>>Live (Visible on Public Website)</option>
          <option value="done" <?php echo (($editE['status']??'')==='done')?'selected':''; ?>>Archived / Done (Hidden)</option>
        </select>
      </div>
    </div>

    <label>Event Title</label>
    <input name="title" required value="<?php echo htmlspecialchars($editE['title'] ?? ''); ?>" placeholder="e.g. CPHI Milan 2026">

    <label>Venue / Location</label>
    <input name="place" value="<?php echo htmlspecialchars($editE['place'] ?? ''); ?>" placeholder="e.g. Fiera Milano, Milan, Italy">

    <div class="row">
      <div>
        <label>Dates</label>
        <input name="dates" value="<?php echo htmlspecialchars($editE['dates'] ?? ''); ?>" placeholder="e.g. 6 – 8 October, 2026">
      </div>
      <div>
        <label>Booth Details</label>
        <input name="booth" value="<?php echo htmlspecialchars($editE['booth'] ?? ''); ?>" placeholder="e.g. Hall 3, Booth 3F103">
      </div>
    </div>

    <div class="row">
      <div>
        <label>Tag / Badge Text</label>
        <input name="tag" value="<?php echo htmlspecialchars($editE['tag'] ?? 'Meet us at'); ?>" placeholder="e.g. Meet us at">
      </div>
      <div>
        <label style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
          <span>Event Cover Photo</span>
          <span style="font-size:11px; font-weight:600; color:#059669; background:#ECFDF5; border:1px solid #A7F3D0; padding:2px 8px; border-radius:12px;">📐 16:9 (800 × 450 px ideal)</span>
        </label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <div style="font-size:11px; color:#64748B; margin-top:4px;">Recommended: 16:9 ratio, 800 × 450 px (Min 640 × 360 px), JPG/PNG/WEBP under 5 MB.</div>
      </div>
    </div>

    <?php if (!empty($editE['image'])): ?>
      <div style="margin-top:12px; display:flex; align-items:center; gap:12px;">
        <?php echo ne_thumb($editE['image']); ?>
        <span style="font-size:12px; color:#64748B;">Current image: <code><?php echo htmlspecialchars($editE['image']); ?></code></span>
      </div>
    <?php endif; ?>

    <p style="margin-top:22px;">
      <button class="btn btn-primary" type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <?php echo $editE ? 'Save Event Changes' : 'Publish Event'; ?>
      </button>
      <?php if ($editE): ?><a class="btn btn-ghost" href="news.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<!-- ==================== SECTION 2: NEWS ==================== -->
<div class="card" style="margin-top:36px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div>
      <h2 style="margin:0; font-size:18px;">Company News &amp; Updates (<?php echo count($data['news']); ?>)</h2>
      <div style="font-size:12.5px; color:var(--sp-text-muted); margin-top:2px;">
        Press releases, rating updates, and company announcements
      </div>
    </div>
    <a href="news.php#newsForm" class="btn btn-primary">+ Add New News Item</a>
  </div>

  <div class="table-search-bar">
    <input type="text" placeholder="Search news by headline, kicker, summary..." data-search-target="#newsTable">
  </div>

  <table id="newsTable">
    <thead>
      <tr>
        <th style="width:100px;">Photo</th>
        <th>Headline</th>
        <th>Category / Date</th>
        <th>Status</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($data['news'] as $row): 
      $isLive = ($row['status'] ?? 'live') === 'live';
    ?>
      <tr>
        <td><?php echo ne_thumb($row['image'] ?? ''); ?></td>
        <td>
          <div style="font-weight:600; color:var(--sp-text-main);"><?php echo htmlspecialchars($row['title']); ?></div>
          <div style="font-size:12px; color:var(--sp-text-muted); margin-top:3px; max-width:480px;">
            <?php echo htmlspecialchars(mb_strimwidth($row['summary'] ?? '', 0, 95, '...')); ?>
          </div>
        </td>
        <td>
          <div><span style="font-size:11px; font-weight:700; background:#ECFDF5; color:var(--sp-primary); padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($row['kicker'] ?? 'Update'); ?></span></div>
          <div style="font-size:11.5px; color:#64748B; margin-top:4px;"><?php echo htmlspecialchars($row['date'] ?? 'Recent'); ?></div>
        </td>
        <td>
          <span class="<?php echo $isLive ? 'badge-open' : 'badge-closed'; ?>">
            <?php echo $isLive ? '● Live' : '○ Archived'; ?>
          </span>
        </td>
        <td style="text-align:right; white-space:nowrap;">
          <a class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" href="news.php?kind=news&edit=<?php echo urlencode($row['id']); ?>#newsForm">Edit</a>
          <form method="post" style="display:inline">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="kind" value="news">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
            <?php if ($isLive): ?>
              <button class="btn btn-ghost" style="padding:5px 9px; font-size:12px;" name="action" value="done">Archive</button>
            <?php else: ?>
              <button class="btn btn-ghost" style="padding:5px 9px; font-size:12px; color:#059669;" name="action" value="live">Make Live</button>
            <?php endif; ?>
            <button class="btn btn-danger" style="padding:5px 9px; font-size:12px;" name="action" value="delete" onclick="return confirm('Remove news item <?php echo htmlspecialchars($row['title']); ?>?');">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$data['news']): ?><tr><td colspan="5" style="text-align:center; padding:24px; color:#64748B;">No news published yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card" id="newsForm">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
    <h2 style="margin:0; font-size:18px;"><?php echo $editN ? ('Edit News: ' . htmlspecialchars($editN['title'])) : 'Publish Company News'; ?></h2>
    <?php if ($editN): ?>
      <a class="btn btn-ghost" href="news.php" style="font-size:12px">Cancel Edit</a>
    <?php endif; ?>
  </div>

  <form method="post" enctype="multipart/form-data">
    <?php echo staff_csrf_field(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="kind" value="news">
    <input type="hidden" name="image" value="<?php echo htmlspecialchars($editN['image'] ?? ''); ?>">
    
    <div class="row">
      <div>
        <label>News ID</label>
        <input name="id" value="<?php echo htmlspecialchars($editN['id'] ?? ''); ?>" placeholder="e.g. NW-CREDIT (leave blank to auto-create)">
      </div>
      <div>
        <label>Display Status</label>
        <select name="status">
          <option value="live" <?php echo (($editN['status']??'live')==='live')?'selected':''; ?>>Live (Visible on Public Website)</option>
          <option value="done" <?php echo (($editN['status']??'')==='done')?'selected':''; ?>>Archived / Done (Hidden)</option>
        </select>
      </div>
    </div>

    <label>Headline Title</label>
    <input name="title" required value="<?php echo htmlspecialchars($editN['title'] ?? ''); ?>" placeholder="e.g. Strong Credit Rating Affirmed">

    <div class="row">
      <div>
        <label>Kicker / Category Tag</label>
        <input name="kicker" value="<?php echo htmlspecialchars($editN['kicker'] ?? ''); ?>" placeholder="e.g. Company Update, Sustainability, R&D">
      </div>
      <div>
        <label>Date Label</label>
        <input name="date" value="<?php echo htmlspecialchars($editN['date'] ?? ''); ?>" placeholder="e.g. Recent, or Sep 2026">
      </div>
    </div>

    <label>Article Summary</label>
    <textarea name="summary" placeholder="Brief 2-3 sentence overview of this announcement..."><?php echo htmlspecialchars($editN['summary'] ?? ''); ?></textarea>

    <div class="row">
      <div>
        <label>Read More External Link (optional)</label>
        <input name="link" value="<?php echo htmlspecialchars($editN['link'] ?? ''); ?>" placeholder="https://…">
      </div>
      <div>
        <label style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px;">
          <span>Article Cover Photo</span>
          <span style="font-size:11px; font-weight:600; color:#059669; background:#ECFDF5; border:1px solid #A7F3D0; padding:2px 8px; border-radius:12px;">📐 16:9 (800 × 450 px ideal)</span>
        </label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        <div style="font-size:11px; color:#64748B; margin-top:4px;">Recommended: 16:9 ratio, 800 × 450 px (Min 600 × 338 px), JPG/PNG/WEBP under 5 MB.</div>
      </div>
    </div>

    <?php if (!empty($editN['image'])): ?>
      <div style="margin-top:12px; display:flex; align-items:center; gap:12px;">
        <?php echo ne_thumb($editN['image']); ?>
        <span style="font-size:12px; color:#64748B;">Current image: <code><?php echo htmlspecialchars($editN['image']); ?></code></span>
      </div>
    <?php endif; ?>

    <p style="margin-top:22px;">
      <button class="btn btn-primary" type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <?php echo $editN ? 'Save News Changes' : 'Publish News Item'; ?>
      </button>
      <?php if ($editN): ?><a class="btn btn-ghost" href="news.php">Cancel</a><?php endif; ?>
    </p>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
