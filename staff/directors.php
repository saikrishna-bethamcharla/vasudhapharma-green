<?php
require __DIR__ . '/includes/auth.php';
staff_require_desk('directors');
$u = staff_user();
if (($u['role'] ?? '') !== 'admin') {
  http_response_code(403);
  die('Access denied. Administrator privileges required to access the Board of Directors desk.');
}

$siteRoot = dirname(staff_root());
$directorsData = staff_load_directors();
$msg = '';
$err = '';

// Reusable synchronization engine for public HTML files
function staff_sync_directors_to_html($data, $siteRoot) {
  $syncedFiles = [];
  $rawList = $data['directors'] ?? [];
  $list = array_values(array_filter($rawList, function($d) {
    return ($d['status'] ?? 'active') === 'active';
  }));
  usort($list, function($a, $b) {
    return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
  });

  // 1. Sync about-us.html
  $aboutPath = $siteRoot . '/about-us.html';
  if (is_file($aboutPath)) {
    $html = file_get_contents($aboutPath);
    if ($html) {
      $featured = array_filter($list, function($d) { return !empty($d['photo']); });
      $rest = array_filter($list, function($d) { return empty($d['photo']); });

      $boardGridHtml = "      <div class=\"board-grid\">\n";
      foreach ($featured as $d) {
        $p = htmlspecialchars(trim($d['photo'] ?? ''));
        $nm = htmlspecialchars($d['name'] ?? '');
        $des = htmlspecialchars($d['designation'] ?? '');
        $desc = trim($d['description'] ?? '');
        $boardGridHtml .= "        <div class=\"board-card has-photo\">\n";
        $boardGridHtml .= "          <div class=\"board-portrait\">\n";
        $boardGridHtml .= "            <div class=\"board-photo\">\n";
        $boardGridHtml .= "              <img src=\"{$p}\" alt=\"{$nm} - {$des}\">\n";
        $boardGridHtml .= "            </div>\n";
        $boardGridHtml .= "            <div class=\"board-badge\">\n";
        $boardGridHtml .= "              <h3>{$nm}</h3>\n";
        $boardGridHtml .= "              <div class=\"role\">{$des}</div>\n";
        $boardGridHtml .= "            </div>\n";
        $boardGridHtml .= "          </div>\n";
        $boardGridHtml .= "          <p>{$desc}</p>\n";
        $boardGridHtml .= "        </div>\n";
      }
      $boardGridHtml .= "      </div>";

      $boardRestHtml = "      <ul class=\"mgmt-list board-rest-list\">\n";
      foreach ($rest as $d) {
        $nm = htmlspecialchars($d['name'] ?? '');
        $des = htmlspecialchars($d['designation'] ?? '');
        $desc = trim($d['description'] ?? '');
        $boardRestHtml .= "        <li>\n";
        $boardRestHtml .= "          <div><span class=\"mgmt-name\">{$nm}</span><span class=\"mgmt-role\">{$des}</span></div>\n";
        $boardRestHtml .= "          <p>{$desc}</p>\n";
        $boardRestHtml .= "        </li>\n";
      }
      $boardRestHtml .= "      </ul>";

      $aboutRegex = '/(<section class="about-sub-section page-section" id="board-of-directors">[\s\S]*?<p>Our board brings deep technical and commercial experience in the pharmaceutical industry, guiding strategy, quality and sustainable growth.<\/p>\s*)<div class="board-grid">[\s\S]*?<\/div>\s*<ul class="mgmt-list board-rest-list">[\s\S]*?<\/ul>([\s\S]*?<div class="mgmt-heading-block" id="management-team">)/';
      if (preg_match($aboutRegex, $html)) {
        $html = preg_replace($aboutRegex, "\${1}{$boardGridHtml}\n\n{$boardRestHtml}\${2}", $html);
        file_put_contents($aboutPath, $html);
        $syncedFiles[] = 'about-us.html';
      }
    }
  }

  // 2. Sync independent-directors.html
  $idPath = $siteRoot . '/independent-directors.html';
  if (is_file($idPath)) {
    $idHtml = file_get_contents($idPath);
    if ($idHtml) {
      $indDirs = array_filter($list, function($d) {
        return !empty($d['is_independent']) || ($d['category'] ?? '') === 'independent';
      });
      $idListHtml = "<ul class=\"id-list\">\n";
      foreach ($indDirs as $d) {
        $nm = htmlspecialchars($d['name'] ?? '');
        $idListHtml .= "        <li>{$nm}</li>\n";
      }
      $idListHtml .= "      </ul>";

      $idRegex = '/(The following Directors have been appointed as <strong style="color: var\(--dark\);">Independent Directors<\/strong> with effect from <strong>[^<]+<\/strong>:\s*<\/p>\s*)<ul class="id-list">[\s\S]*?<\/ul>/';
      if (preg_match($idRegex, $idHtml)) {
        $idHtml = preg_replace($idRegex, "\${1}{$idListHtml}", $idHtml);
        file_put_contents($idPath, $idHtml);
        $syncedFiles[] = 'independent-directors.html';
      }
    }
  }

  // 3. Sync csr-policy.html
  $csrPath = $siteRoot . '/csr-policy.html';
  if (is_file($csrPath)) {
    $csrHtml = file_get_contents($csrPath);
    if ($csrHtml) {
      $csrDirs = array_values(array_filter($list, function($d) {
        return !empty($d['is_csr_committee']);
      }));
      $csrListHtml = "<ul style=\"list-style: none; padding: 0; margin: 0 0 28px;\">\n";
      $cnt = count($csrDirs);
      foreach ($csrDirs as $idx => $d) {
        $isLast = ($idx === $cnt - 1);
        $borderStyle = $isLast ? '' : ' border-bottom: 1px solid #f0f0f0;';
        $nm = htmlspecialchars($d['name'] ?? '');
        $roleText = !empty($d['csr_role']) ? ' &mdash; ' . htmlspecialchars($d['csr_role']) : '';
        $csrListHtml .= "          <li style=\"padding: 8px 0 8px 28px; position: relative; line-height: 1.55; color: #333;{$borderStyle}\">\n";
        $csrListHtml .= "            <span style=\"position: absolute; left: 0; color: var(--primary-red); font-weight: 700;\">▸</span>\n";
        $csrListHtml .= "            {$nm}{$roleText}\n";
        $csrListHtml .= "          </li>\n";
      }
      $csrListHtml .= "        </ul>";

      $csrRegex = '/(The CSR Committee shall comprise of the following directors:<\/p>\s*)<ul style="list-style:\s*none;\s*padding:\s*0;\s*margin:\s*0\s+0\s+28px;">[\s\S]*?<\/ul>/';
      if (preg_match($csrRegex, $csrHtml)) {
        $csrHtml = preg_replace($csrRegex, "\${1}{$csrListHtml}", $csrHtml);
        file_put_contents($csrPath, $csrHtml);
        $syncedFiles[] = 'csr-policy.html';
      }
    }
  }

  // 4. Sync posh-policy.html
  $poshPath = $siteRoot . '/posh-policy.html';
  if (is_file($poshPath)) {
    $poshHtml = file_get_contents($poshPath);
    if ($poshHtml) {
      $tbodyHtml = "<tbody>\n";
      foreach ($list as $idx => $d) {
        $sno = $idx + 1;
        $bg = ($sno % 2 === 0) ? ' style="background:#f7fbfc;"' : '';
        $nm = htmlspecialchars($d['name'] ?? '');
        $des = htmlspecialchars($d['designation'] ?? '');
        $tbodyHtml .= "<tr{$bg}><td style=\"padding:8px 12px;border-bottom:1px solid #e8e8e8;\">{$sno}</td><td style=\"padding:8px 12px;border-bottom:1px solid #e8e8e8;\">{$nm}</td><td style=\"padding:8px 12px;border-bottom:1px solid #e8e8e8;\">{$des}</td></tr>\n";
      }
      $tbodyHtml .= "          </tbody>";

      $poshRegex = '/(<th style="padding:10px 12px;text-align:left;">DIRECTORS NAME<\/th>\s*<th style="padding:10px 12px;text-align:left;">TITLE \/ \(DESIGNATION\)<\/th>\s*<\/tr><\/thead>\s*)<tbody>[\s\S]*?<\/tbody>/';
      if (preg_match($poshRegex, $poshHtml)) {
        $poshHtml = preg_replace($poshRegex, "\${1}{$tbodyHtml}", $poshHtml);
        file_put_contents($poshPath, $poshHtml);
        $syncedFiles[] = 'posh-policy.html';
      }
    }
  }

  return $syncedFiles;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  staff_verify_csrf();
  $action = $_POST['action'] ?? '';

  // 1. ADD NEW DIRECTOR
  if ($action === 'add_director') {
    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $category = trim($_POST['category'] ?? 'wholetime');
    $categoryLabel = trim($_POST['category_label'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isIndependent = !empty($_POST['is_independent']);
    $isCsr = !empty($_POST['is_csr_committee']);
    $csrRole = trim($_POST['csr_role'] ?? '');
    $order = intval($_POST['order'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');

    if (!$categoryLabel) {
      $labels = [
        'executive' => 'Executive Director',
        'wholetime' => 'Whole-time Director',
        'independent' => 'Independent Director',
        'women_director' => 'Women Director',
        'director' => 'Director'
      ];
      $categoryLabel = $labels[$category] ?? 'Director';
    }

    if ($name === '') {
      $err = 'Director name is required.';
    } elseif ($designation === '') {
      $err = 'Director designation / title is required.';
    } elseif ($description === '') {
      $err = 'Director biography / professional description is required.';
    } else {
      // Handle Photo Upload
      $photoPath = trim($_POST['photo_existing'] ?? '');
      if (!empty($_FILES['photo_file']['tmp_name']) && is_uploaded_file($_FILES['photo_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
        $validExts = ['png', 'jpg', 'jpeg', 'webp'];
        $imgInfo = @getimagesize($_FILES['photo_file']['tmp_name']);
        if (!in_array($ext, $validExts, true) || !$imgInfo) {
          $err = 'Invalid photo format. Please upload a PNG, JPG, or WEBP image.';
        } elseif ($_FILES['photo_file']['size'] > 5 * 1024 * 1024) {
          $err = 'Photo file size must be under 5 MB.';
        } else {
          $destDir = $siteRoot . '/assets/directors';
          if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
          $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
          $slug = trim($slug, '-');
          $fileName = $slug . '-' . time() . '.' . $ext;
          if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $destDir . '/' . $fileName)) {
            $photoPath = 'assets/directors/' . $fileName;
          } else {
            $err = 'Failed to save uploaded photo to assets/directors.';
          }
        }
      }

      if (!$err) {
        // Next sequential ID
        $maxNum = 0;
        foreach ($directorsData['directors'] as $d) {
          if (preg_match('/DIR-(\d+)/', $d['id'] ?? '', $m)) {
            $num = intval($m[1]);
            if ($num > $maxNum) $maxNum = $num;
          }
        }
        $newId = 'DIR-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);

        if ($order <= 0) {
          $order = count($directorsData['directors']) + 1;
        }

        $newDirector = [
          'id'               => $newId,
          'name'             => $name,
          'designation'      => $designation,
          'category'         => $category,
          'category_label'   => $categoryLabel,
          'photo'            => $photoPath,
          'description'      => $description,
          'is_independent'   => $isIndependent,
          'is_csr_committee' => $isCsr,
          'csr_role'         => $csrRole,
          'order'            => $order,
          'status'           => $status
        ];

        $directorsData['directors'][] = $newDirector;
        staff_save_directors($directorsData);
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        staff_log_audit('Director Added', "Added director {$name} ({$designation})", $newId, 'Corporate Governance');
        $msg = "Director \"{$name}\" successfully added and synced across " . count($synced) . " pages (" . implode(', ', $synced) . ").";
      }
    }
  }

  // 2. EDIT DIRECTOR
  if ($action === 'edit_director') {
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $category = trim($_POST['category'] ?? 'wholetime');
    $categoryLabel = trim($_POST['category_label'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isIndependent = !empty($_POST['is_independent']);
    $isCsr = !empty($_POST['is_csr_committee']);
    $csrRole = trim($_POST['csr_role'] ?? '');
    $order = intval($_POST['order'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');

    if (!$categoryLabel) {
      $labels = [
        'executive' => 'Executive Director',
        'wholetime' => 'Whole-time Director',
        'independent' => 'Independent Director',
        'women_director' => 'Women Director',
        'director' => 'Director'
      ];
      $categoryLabel = $labels[$category] ?? 'Director';
    }

    $foundIndex = -1;
    foreach ($directorsData['directors'] as $idx => $d) {
      if (($d['id'] ?? '') === $id) {
        $foundIndex = $idx;
        break;
      }
    }

    if ($foundIndex === -1) {
      $err = 'Director not found in registry.';
    } elseif ($name === '' || $designation === '' || $description === '') {
      $err = 'Name, designation, and description are required.';
    } else {
      $photoPath = trim($_POST['photo_existing'] ?? $directorsData['directors'][$foundIndex]['photo'] ?? '');
      if (!empty($_FILES['photo_file']['tmp_name']) && is_uploaded_file($_FILES['photo_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
        $validExts = ['png', 'jpg', 'jpeg', 'webp'];
        $imgInfo = @getimagesize($_FILES['photo_file']['tmp_name']);
        if (!in_array($ext, $validExts, true) || !$imgInfo) {
          $err = 'Invalid photo format. Please upload PNG, JPG, or WEBP.';
        } elseif ($_FILES['photo_file']['size'] > 5 * 1024 * 1024) {
          $err = 'Photo file size must be under 5 MB.';
        } else {
          $destDir = $siteRoot . '/assets/directors';
          if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
          $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
          $slug = trim($slug, '-');
          $fileName = $slug . '-' . time() . '.' . $ext;
          if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $destDir . '/' . $fileName)) {
            $photoPath = 'assets/directors/' . $fileName;
          } else {
            $err = 'Failed to save uploaded photo.';
          }
        }
      }

      if (!$err) {
        $directorsData['directors'][$foundIndex]['name'] = $name;
        $directorsData['directors'][$foundIndex]['designation'] = $designation;
        $directorsData['directors'][$foundIndex]['category'] = $category;
        $directorsData['directors'][$foundIndex]['category_label'] = $categoryLabel;
        $directorsData['directors'][$foundIndex]['photo'] = $photoPath;
        $directorsData['directors'][$foundIndex]['description'] = $description;
        $directorsData['directors'][$foundIndex]['is_independent'] = $isIndependent;
        $directorsData['directors'][$foundIndex]['is_csr_committee'] = $isCsr;
        $directorsData['directors'][$foundIndex]['csr_role'] = $csrRole;
        $directorsData['directors'][$foundIndex]['order'] = $order;
        $directorsData['directors'][$foundIndex]['status'] = $status;

        staff_save_directors($directorsData);
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        staff_log_audit('Director Updated', "Updated details for director {$name} ({$id})", $id, 'Corporate Governance');
        $msg = "Director \"{$name}\" successfully updated and re-synced across " . count($synced) . " pages.";
      }
    }
  }

  // 3. DELETE DIRECTOR
  if ($action === 'delete_director') {
    $id = trim($_POST['id'] ?? '');
    $deletedName = '';
    $newDirectors = [];
    foreach ($directorsData['directors'] as $d) {
      if (($d['id'] ?? '') === $id) {
        $deletedName = $d['name'] ?? $id;
      } else {
        $newDirectors[] = $d;
      }
    }

    if ($deletedName) {
      $directorsData['directors'] = $newDirectors;
      staff_save_directors($directorsData);
      $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
      staff_log_audit('Director Deleted', "Removed director {$deletedName} ({$id})", $id, 'Corporate Governance');
      $msg = "Director \"{$deletedName}\" successfully deleted and removed from " . count($synced) . " public pages.";
    } else {
      $err = 'Director not found.';
    }
  }

  // 4. REORDER DIRECTORS
  if ($action === 'reorder') {
    $id = trim($_POST['id'] ?? '');
    $dir = trim($_POST['direction'] ?? 'up');
    usort($directorsData['directors'], function($a, $b) {
      return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
    });

    $idx = -1;
    foreach ($directorsData['directors'] as $i => $d) {
      if (($d['id'] ?? '') === $id) {
        $idx = $i;
        break;
      }
    }

    if ($idx !== -1) {
      $swapIdx = ($dir === 'up') ? $idx - 1 : $idx + 1;
      if ($swapIdx >= 0 && $swapIdx < count($directorsData['directors'])) {
        $tempOrder = $directorsData['directors'][$idx]['order'] ?? ($idx + 1);
        $directorsData['directors'][$idx]['order'] = $directorsData['directors'][$swapIdx]['order'] ?? ($swapIdx + 1);
        $directorsData['directors'][$swapIdx]['order'] = $tempOrder;
        staff_save_directors($directorsData);
        staff_sync_directors_to_html($directorsData, $siteRoot);
        $msg = "Board of Directors order updated and synchronized.";
      }
    }
  }

  // 5. MANUAL FULL SYNC
  if ($action === 'sync_all') {
    $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
    staff_log_audit('Sync Directors', "Manual trigger of Board of Directors live synchronization", 'Public Pages', 'Corporate Governance');
    $msg = "Full Board of Directors synchronization complete. " . count($synced) . " files verified (" . implode(', ', $synced) . ").";
  }
}

// Reload clean data
$directorsData = staff_load_directors();
$directors = $directorsData['directors'] ?? [];
usort($directors, function($a, $b) {
  return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
});

// Category counts
$countTotal = count($directors);
$countExec = 0;
$countWhole = 0;
$countInd = 0;
$countCsr = 0;
foreach ($directors as $d) {
  $cat = $d['category'] ?? '';
  if ($cat === 'executive') $countExec++;
  elseif ($cat === 'wholetime') $countWhole++;
  elseif ($cat === 'independent' || !empty($d['is_independent'])) $countInd++;
  if (!empty($d['is_csr_committee'])) $countCsr++;
}

$staff_title = 'Board of Directors';
require __DIR__ . '/includes/header.php';
?>

<?php if ($msg): ?>
  <div class="card ok" style="display:flex; align-items:center; gap:10px; background:#ECFDF5; border:1px solid #10B981; color:#065F46; padding:14px 18px; border-radius:10px; margin-bottom:20px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
    <div style="font-weight:600; font-size:13.5px;"><?php echo htmlspecialchars($msg); ?></div>
  </div>
<?php endif; ?>

<?php if ($err): ?>
  <div class="card" style="display:flex; align-items:center; gap:10px; background:#FEF2F2; border:1px solid #EF4444; color:#991B1B; padding:14px 18px; border-radius:10px; margin-bottom:20px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div style="font-weight:600; font-size:13.5px;"><?php echo htmlspecialchars($err); ?></div>
  </div>
<?php endif; ?>

<!-- Top Desk Hero -->
<div class="card" style="background: linear-gradient(135deg, #096B51 0%, #0E8F6C 60%, #1DB88A 100%); color:#FFFFFF; border:0; padding:24px 28px; box-shadow:0 8px 20px rgba(14,143,108,0.18); margin-bottom:22px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.12em; opacity:0.85; font-weight:700; margin-bottom:6px;">
        Admin Secretarial Desk &bull; Executive Governance
      </div>
      <h1 style="margin:0; font-size:24px; color:#FFFFFF;">Board of Directors &amp; Biographies Console</h1>
      <p style="margin:6px 0 0; opacity:0.9; font-size:13.5px; max-width:760px;">
        Centrally control all board members, executive photos, titles, and comprehensive descriptions. Edits made here automatically synchronize across <code>about-us.html</code>, <code>independent-directors.html</code>, <code>csr-policy.html</code>, and <code>posh-policy.html</code>.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <button onclick="document.getElementById('addDirectorModal').style.display='flex';" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; box-shadow:0 2px 8px rgba(0,0,0,0.15);">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        + Add New Director
      </button>
      <form method="post" style="display:inline;">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="sync_all">
        <button type="submit" class="btn" style="background:rgba(255,255,255,0.2); color:#FFFFFF; border:1px solid rgba(255,255,255,0.35); font-weight:600;" title="Rebuild HTML pages from directors.json">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
          Sync Website Now
        </button>
      </form>
    </div>
  </div>
</div>

<!-- KPI Stats Row -->
<div class="row-4" style="margin-bottom:22px;">
  <div class="stat-card">
    <div class="stat-label">Total Board Members</div>
    <div class="stat-num"><?php echo $countTotal; ?></div>
    <div class="stat-sub">Active in directors.json</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Executive / CMD</div>
    <div class="stat-num" style="color:#0088AA;"><?php echo $countExec; ?></div>
    <div class="stat-sub">Featured with photo cards</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Whole-time Directors</div>
    <div class="stat-num" style="color:#D97706;"><?php echo $countWhole; ?></div>
    <div class="stat-sub">Operational &amp; technical leadership</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Independent Directors</div>
    <div class="stat-num" style="color:#7C3AED;"><?php echo $countInd; ?></div>
    <div class="stat-sub"><?php echo $countCsr; ?> on CSR Committee</div>
  </div>
</div>

<!-- Search, Filter & Quick Action Bar -->
<div class="card" style="padding:16px 20px; margin-bottom:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <span style="font-size:12px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:0.04em;">Category:</span>
      <button type="button" class="btn btn-ghost filter-pill active" onclick="filterCategory('all', this)" style="padding:4px 12px; font-size:12px; border-radius:20px;">All (<?php echo $countTotal; ?>)</button>
      <button type="button" class="btn btn-ghost filter-pill" onclick="filterCategory('executive', this)" style="padding:4px 12px; font-size:12px; border-radius:20px;">Executive (<?php echo $countExec; ?>)</button>
      <button type="button" class="btn btn-ghost filter-pill" onclick="filterCategory('wholetime', this)" style="padding:4px 12px; font-size:12px; border-radius:20px;">Whole-Time (<?php echo $countWhole; ?>)</button>
      <button type="button" class="btn btn-ghost filter-pill" onclick="filterCategory('independent', this)" style="padding:4px 12px; font-size:12px; border-radius:20px;">Independent (<?php echo $countInd; ?>)</button>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
      <input type="text" id="dirSearch" placeholder="Search director name, title..." onkeyup="filterSearch()" style="width:260px; padding:7px 12px; font-size:12.5px; border-radius:6px; margin:0;">
      <a href="../about-us.html#board-of-directors" target="_blank" rel="noopener" class="btn btn-ghost" style="font-size:12px; padding:6px 12px;" title="Open live public Board of Directors page">
        Live Preview ↗
      </a>
    </div>
  </div>
</div>

<!-- Directors Directory List -->
<div class="card" style="padding:0; overflow:hidden;">
  <div style="padding:18px 24px; border-bottom:1px solid var(--sp-border); background:#F8FAFC; display:flex; justify-content:space-between; align-items:center;">
    <h3 style="margin:0; font-size:16px;">Board of Directors Registry</h3>
    <span style="font-size:12px; color:#64748B;">Reordering or editing auto-syncs live HTML files</span>
  </div>

  <div id="directorsContainer">
    <?php foreach ($directors as $idx => $d): 
      $id = htmlspecialchars($d['id'] ?? '');
      $name = htmlspecialchars($d['name'] ?? '');
      $des = htmlspecialchars($d['designation'] ?? '');
      $cat = htmlspecialchars($d['category'] ?? 'wholetime');
      $catLabel = htmlspecialchars($d['category_label'] ?? ucfirst($cat));
      $desc = htmlspecialchars($d['description'] ?? '');
      $photo = htmlspecialchars($d['photo'] ?? '');
      $order = intval($d['order'] ?? ($idx + 1));
      $isInd = !empty($d['is_independent']);
      $isCsr = !empty($d['is_csr_committee']);
      $csrRole = htmlspecialchars($d['csr_role'] ?? '');
    ?>
      <div class="director-row" data-category="<?php echo $cat; ?>" data-name="<?php echo strtolower($name); ?>" data-title="<?php echo strtolower($des); ?>" style="padding:18px 24px; border-bottom:1px solid #F1F5F9; display:flex; gap:18px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; transition:background 0.15s;">
        <!-- Left Portrait & Identity -->
        <div style="display:flex; gap:16px; align-items:flex-start; flex:1; min-width:300px;">
          <div style="width:58px; height:58px; border-radius:10px; overflow:hidden; background:#E2E8F0; flex-shrink:0; border:1px solid #CBD5E1; display:flex; align-items:center; justify-content:center;">
            <?php if ($photo): ?>
              <img src="../<?php echo $photo; ?>" alt="<?php echo $name; ?>" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
              <span style="font-weight:800; font-size:18px; color:#64748B;">
                <?php echo strtoupper(substr($d['name'] ?? 'D', 0, 1)); ?>
              </span>
            <?php endif; ?>
          </div>
          <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
              <span style="font-size:11px; font-weight:700; color:#64748B; background:#F1F5F9; padding:2px 7px; border-radius:4px;">#<?php echo $order; ?> &bull; <?php echo $id; ?></span>
              <h4 style="margin:0; font-size:16px; color:#0F172A; font-weight:700;"><?php echo $name; ?></h4>
              <span class="badge" style="background:<?php echo ($cat === 'executive') ? '#E0F2FE; color:#0369A1;' : (($cat === 'independent') ? '#F3E8FF; color:#7E22CE;' : '#ECFDF5; color:#065F46;'); ?> padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                <?php echo $catLabel; ?>
              </span>
              <?php if ($isInd): ?>
                <span style="background:#FEF3C7; color:#B45309; padding:2px 7px; border-radius:4px; font-size:10.5px; font-weight:700;">🛡️ Independent</span>
              <?php endif; ?>
              <?php if ($isCsr): ?>
                <span style="background:#E0E7FF; color:#3730A3; padding:2px 7px; border-radius:4px; font-size:10.5px; font-weight:700;">🤝 CSR Committee</span>
              <?php endif; ?>
            </div>
            <div style="font-weight:600; font-size:13px; color:#0E8F6C; margin:3px 0 6px;">
              <?php echo $des; ?>
            </div>
            <p style="margin:0; font-size:12.5px; color:#475569; line-height:1.55; max-width:850px;">
              <?php echo $desc; ?>
            </p>
          </div>
        </div>

        <!-- Right Action Controls -->
        <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
          <!-- Reorder Up -->
          <form method="post" style="display:inline; margin:0;">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="direction" value="up">
            <button type="submit" class="btn btn-ghost" style="padding:5px 8px; font-size:12px;" title="Move Up" <?php if ($idx === 0) echo 'disabled style="opacity:0.3; cursor:not-allowed;"'; ?>>
              ▲
            </button>
          </form>
          <!-- Reorder Down -->
          <form method="post" style="display:inline; margin:0;">
            <?php echo staff_csrf_field(); ?>
            <input type="hidden" name="action" value="reorder">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="direction" value="down">
            <button type="submit" class="btn btn-ghost" style="padding:5px 8px; font-size:12px;" title="Move Down" <?php if ($idx === count($directors) - 1) echo 'disabled style="opacity:0.3; cursor:not-allowed;"'; ?>>
              ▼
            </button>
          </form>

          <!-- Edit Button -->
          <button type="button" class="btn btn-ghost" style="padding:6px 12px; font-size:12.5px; border-color:#0E8F6C; color:#0E8F6C; font-weight:600;" 
            onclick="openEditModal(<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8'); ?>)">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>

          <!-- Delete Button -->
          <button type="button" class="btn btn-ghost" style="padding:6px 12px; font-size:12.5px; color:#DC2626; border-color:#FCA5A5;" 
            onclick="confirmDelete('<?php echo $id; ?>', '<?php echo addslashes($d['name'] ?? ''); ?>')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Delete
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ================= ADD DIRECTOR MODAL ================= -->
<div id="addDirectorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.25); padding:26px 30px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
      <h3 style="margin:0; font-size:18px; color:#0F172A;">Add New Board Director</h3>
      <button type="button" onclick="document.getElementById('addDirectorModal').style.display='none';" style="background:transparent; border:0; font-size:22px; cursor:pointer; color:#64748B;">&times;</button>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="add_director">

      <div class="row">
        <div>
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="e.g. Sri Mantena Venkata..." required>
        </div>
        <div>
          <label>Designation / Corporate Title *</label>
          <input type="text" name="designation" placeholder="e.g. Whole-time Director · Vizag" required>
        </div>
      </div>

      <div class="row" style="margin-top:10px;">
        <div>
          <label>Category *</label>
          <select name="category" onchange="updateCategoryLabel(this, 'add_category_label')">
            <option value="wholetime">Whole-time Director</option>
            <option value="executive">Executive Director (Featured Photo Grid)</option>
            <option value="independent">Independent Director</option>
            <option value="director">Director / Non-Executive</option>
            <option value="women_director">Women Director</option>
          </select>
          <input type="hidden" id="add_category_label" name="category_label" value="Whole-time Director">
        </div>
        <div>
          <label>Display Order</label>
          <input type="number" name="order" value="<?php echo count($directors) + 1; ?>" min="1">
        </div>
      </div>

      <div style="margin-top:10px;">
        <label>Upload Photo (Optional &bull; PNG, JPG, WEBP &bull; Max 5MB)</label>
        <input type="file" name="photo_file" accept=".png,.jpg,.jpeg,.webp">
        <span style="font-size:11px; color:#64748B;">Featured photo cards are displayed in the 3-column top grid on <code>about-us.html</code>.</span>
      </div>

      <div style="margin-top:14px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:12px 16px;">
        <div style="font-weight:700; font-size:12px; color:#475569; margin-bottom:8px; text-transform:uppercase;">Statutory Committees &amp; Disclosures</div>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:500; font-size:13px; margin:0;">
            <input type="checkbox" name="is_independent" value="1" style="width:auto; margin:0;">
            <span>Independent Director (Syncs to <code>independent-directors.html</code>)</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:500; font-size:13px; margin:0;">
            <input type="checkbox" name="is_csr_committee" value="1" style="width:auto; margin:0;" onchange="toggleCsrRole('add_csr_role_wrap', this.checked)">
            <span>Member of CSR Committee (Syncs to <code>csr-policy.html</code>)</span>
          </label>
        </div>
        <div id="add_csr_role_wrap" style="display:none; margin-top:10px;">
          <label style="margin-top:0;">CSR Committee Designation / Note (Optional)</label>
          <input type="text" name="csr_role" placeholder="e.g. Member or Independent Director & Member">
        </div>
      </div>

      <div style="margin-top:10px;">
        <label>Professional Biography &amp; Background Description *</label>
        <textarea name="description" rows="5" placeholder="Enter comprehensive qualifications, career tenure, industry background, degrees, and responsibilities..." required></textarea>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #E2E8F0; padding-top:16px;">
        <button type="button" onclick="document.getElementById('addDirectorModal').style.display='none';" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700;">Save &amp; Sync Website</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= EDIT DIRECTOR MODAL ================= -->
<div id="editDirectorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:680px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,0.25); padding:26px 30px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
      <h3 style="margin:0; font-size:18px; color:#0F172A;">Edit Director &bull; <span id="edit_modal_id" style="color:#0E8F6C;"></span></h3>
      <button type="button" onclick="document.getElementById('editDirectorModal').style.display='none';" style="background:transparent; border:0; font-size:22px; cursor:pointer; color:#64748B;">&times;</button>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="edit_director">
      <input type="hidden" id="edit_id" name="id" value="">
      <input type="hidden" id="edit_photo_existing" name="photo_existing" value="">

      <div class="row">
        <div>
          <label>Full Name *</label>
          <input type="text" id="edit_name" name="name" required>
        </div>
        <div>
          <label>Designation / Corporate Title *</label>
          <input type="text" id="edit_designation" name="designation" required>
        </div>
      </div>

      <div class="row" style="margin-top:10px;">
        <div>
          <label>Category *</label>
          <select id="edit_category" name="category" onchange="updateCategoryLabel(this, 'edit_category_label')">
            <option value="executive">Executive Director (Featured Photo Grid)</option>
            <option value="wholetime">Whole-time Director</option>
            <option value="independent">Independent Director</option>
            <option value="director">Director / Non-Executive</option>
            <option value="women_director">Women Director</option>
          </select>
          <input type="hidden" id="edit_category_label" name="category_label" value="">
        </div>
        <div>
          <label>Display Order</label>
          <input type="number" id="edit_order" name="order" min="1">
        </div>
      </div>

      <div style="margin-top:10px;">
        <label>Update Photo (Leave empty to keep current photo)</label>
        <div style="display:flex; gap:12px; align-items:center; margin-bottom:8px;">
          <div id="edit_photo_preview" style="width:44px; height:44px; border-radius:8px; overflow:hidden; background:#F1F5F9; border:1px solid #CBD5E1; display:flex; align-items:center; justify-content:center; font-size:11px; color:#64748B;">
            None
          </div>
          <input type="file" name="photo_file" accept=".png,.jpg,.jpeg,.webp">
        </div>
      </div>

      <div style="margin-top:14px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:12px 16px;">
        <div style="font-weight:700; font-size:12px; color:#475569; margin-bottom:8px; text-transform:uppercase;">Statutory Committees &amp; Disclosures</div>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:500; font-size:13px; margin:0;">
            <input type="checkbox" id="edit_is_independent" name="is_independent" value="1" style="width:auto; margin:0;">
            <span>Independent Director (Syncs to <code>independent-directors.html</code>)</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:500; font-size:13px; margin:0;">
            <input type="checkbox" id="edit_is_csr" name="is_csr_committee" value="1" style="width:auto; margin:0;" onchange="toggleCsrRole('edit_csr_role_wrap', this.checked)">
            <span>Member of CSR Committee (Syncs to <code>csr-policy.html</code>)</span>
          </label>
        </div>
        <div id="edit_csr_role_wrap" style="display:none; margin-top:10px;">
          <label style="margin-top:0;">CSR Committee Designation / Note</label>
          <input type="text" id="edit_csr_role" name="csr_role" placeholder="e.g. Managing Director of the company">
        </div>
      </div>

      <div style="margin-top:10px;">
        <label>Professional Biography &amp; Description *</label>
        <textarea id="edit_description" name="description" rows="5" required></textarea>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #E2E8F0; padding-top:16px;">
        <button type="button" onclick="document.getElementById('editDirectorModal').style.display='none';" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700;">Update &amp; Re-Sync Site</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= DELETE CONFIRMATION MODAL ================= -->
<div id="deleteDirectorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:440px; box-shadow:0 20px 40px rgba(0,0,0,0.25); padding:24px;">
    <h3 style="margin-top:0; color:#DC2626; font-size:18px;">Confirm Deletion</h3>
    <p style="font-size:13.5px; color:#475569; line-height:1.5;">
      Are you sure you want to delete <strong id="delete_director_name" style="color:#0F172A;"></strong> from the Board of Directors?
    </p>
    <p style="font-size:12px; color:#64748B;">
      This action will automatically remove their portrait, profile, and statutory listing from <code>about-us.html</code>, <code>independent-directors.html</code>, and policy tables.
    </p>
    <form method="post" style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="delete_director">
      <input type="hidden" id="delete_director_id" name="id" value="">
      <button type="button" onclick="document.getElementById('deleteDirectorModal').style.display='none';" class="btn btn-ghost">Cancel</button>
      <button type="submit" class="btn" style="background:#DC2626; color:#FFFFFF; font-weight:700;">Delete Director</button>
    </form>
  </div>
</div>

<script>
function filterCategory(cat, btn) {
  document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const rows = document.querySelectorAll('.director-row');
  rows.forEach(r => {
    if (cat === 'all') {
      r.style.display = 'flex';
    } else if (cat === 'independent') {
      const isInd = r.querySelector('[data-category="independent"]') || r.getAttribute('data-category') === 'independent' || r.innerText.includes('Independent');
      r.style.display = isInd ? 'flex' : 'none';
    } else {
      r.style.display = (r.getAttribute('data-category') === cat) ? 'flex' : 'none';
    }
  });
}

function filterSearch() {
  const q = document.getElementById('dirSearch').value.toLowerCase().trim();
  const rows = document.querySelectorAll('.director-row');
  rows.forEach(r => {
    const nm = r.getAttribute('data-name') || '';
    const tit = r.getAttribute('data-title') || '';
    const txt = r.innerText.toLowerCase();
    if (!q || nm.includes(q) || tit.includes(q) || txt.includes(q)) {
      r.style.display = 'flex';
    } else {
      r.style.display = 'none';
    }
  });
}

function updateCategoryLabel(selectEl, targetInputId) {
  const text = selectEl.options[selectEl.selectedIndex].text.replace(/\s*\(.*?\)\s*/g, '');
  document.getElementById(targetInputId).value = text;
}

function toggleCsrRole(wrapId, isChecked) {
  document.getElementById(wrapId).style.display = isChecked ? 'block' : 'none';
}

function openEditModal(d) {
  document.getElementById('edit_modal_id').innerText = d.id;
  document.getElementById('edit_id').value = d.id;
  document.getElementById('edit_name').value = d.name || '';
  document.getElementById('edit_designation').value = d.designation || '';
  document.getElementById('edit_category').value = d.category || 'wholetime';
  document.getElementById('edit_category_label').value = d.category_label || '';
  document.getElementById('edit_order').value = d.order || 1;
  document.getElementById('edit_photo_existing').value = d.photo || '';
  document.getElementById('edit_description').value = d.description || '';
  document.getElementById('edit_is_independent').checked = !!d.is_independent;
  document.getElementById('edit_is_csr').checked = !!d.is_csr_committee;
  document.getElementById('edit_csr_role').value = d.csr_role || '';
  toggleCsrRole('edit_csr_role_wrap', !!d.is_csr_committee);

  const prev = document.getElementById('edit_photo_preview');
  if (d.photo) {
    prev.innerHTML = `<img src="../${d.photo}" style="width:100%; height:100%; object-fit:cover;">`;
  } else {
    prev.innerHTML = 'None';
  }

  document.getElementById('editDirectorModal').style.display = 'flex';
}

function confirmDelete(id, name) {
  document.getElementById('delete_director_id').value = id;
  document.getElementById('delete_director_name').innerText = name;
  document.getElementById('deleteDirectorModal').style.display = 'flex';
}
</script>

<style>
.filter-pill.active {
  background: #0E8F6C !important;
  color: #FFFFFF !important;
  border-color: #0E8F6C !important;
  font-weight: 700 !important;
}
.director-row:hover {
  background: #F8FAFC !important;
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
