<?php
require __DIR__ . '/includes/auth.php';
staff_require_desk('directors');
$u = staff_user();
if (($u['role'] ?? '') !== 'admin') {
  http_response_code(403);
  die('Access denied. Administrator privileges required to access the Board & Management desk.');
}

$siteRoot = dirname(staff_root());
$directorsData = staff_load_directors();
$msg = '';
$err = '';
$currentTab = ($_GET['tab'] ?? 'directors') === 'management' ? 'management' : 'directors';

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

// Reusable synchronization engine for public HTML files
function staff_sync_directors_to_html($data, $siteRoot) {
  $syncedFiles = [];
  $cacheBust = '?v=' . time();

  $rawList = $data['directors'] ?? [];
  $list = array_values(array_filter($rawList, function($d) {
    return ($d['status'] ?? 'active') === 'active';
  }));
  usort($list, function($a, $b) {
    return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
  });

  $rawMgmt = $data['management'] ?? [];
  $mgmtList = array_values(array_filter($rawMgmt, function($m) {
    return ($m['status'] ?? 'active') === 'active';
  }));
  usort($mgmtList, function($a, $b) {
    return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
  });

  // 1. Sync about-us.html (Board of Directors and Management Team)
  $aboutPath = $siteRoot . '/about-us.html';
  if (is_file($aboutPath)) {
    $html = file_get_contents($aboutPath);
    if ($html) {
      $featured = array_values(array_filter($list, function($d) { return !empty($d['photo']); }));
      $rest = array_values(array_filter($list, function($d) { return empty($d['photo']); }));

      $boardGridHtml = '';
      if (!empty($featured)) {
        $boardGridHtml = '      <div class="board-grid">' . "\n";
        foreach ($featured as $d) {
          $p = trim($d['photo'] ?? '');
          if ($p && !str_starts_with($p, 'http') && !str_contains($p, '?')) {
            $p .= $cacheBust;
          }
          $pEsc = htmlspecialchars($p);
          $nm = htmlspecialchars($d['name'] ?? '');
          $des = htmlspecialchars($d['designation'] ?? '');
          $desc = trim($d['description'] ?? '');
          $boardGridHtml .= '        <div class="board-card has-photo">' . "\n";
          $boardGridHtml .= '          <div class="board-portrait">' . "\n";
          $boardGridHtml .= '            <div class="board-photo">' . "\n";
          $boardGridHtml .= '              <img src="' . $pEsc . '" alt="' . $nm . ' - ' . $des . '">' . "\n";
          $boardGridHtml .= '            </div>' . "\n";
          $boardGridHtml .= '            <div class="board-badge">' . "\n";
          $boardGridHtml .= '              <h3>' . $nm . '</h3>' . "\n";
          $boardGridHtml .= '              <div class="role">' . $des . '</div>' . "\n";
          $boardGridHtml .= '            </div>' . "\n";
          $boardGridHtml .= '          </div>' . "\n";
          $boardGridHtml .= '          <p>' . $desc . '</p>' . "\n";
          $boardGridHtml .= '        </div>' . "\n";
        }
        $boardGridHtml .= '      </div>';
      }

      $boardRestHtml = '';
      if (!empty($rest)) {
        $boardRestHtml = '      <ul class="mgmt-list board-rest-list">' . "\n";
        foreach ($rest as $d) {
          $nm = htmlspecialchars($d['name'] ?? '');
          $des = htmlspecialchars($d['designation'] ?? '');
          $desc = trim($d['description'] ?? '');
          $boardRestHtml .= '        <li>' . "\n";
          $boardRestHtml .= '          <div><span class="mgmt-name">' . $nm . '</span><span class="mgmt-role">' . $des . '</span></div>' . "\n";
          $boardRestHtml .= '          <p>' . $desc . '</p>' . "\n";
          $boardRestHtml .= '        </li>' . "\n";
        }
        $boardRestHtml .= '      </ul>';
      }

      $boardCombined = '';
      if ($boardGridHtml && $boardRestHtml) {
        $boardCombined = $boardGridHtml . "\n\n" . $boardRestHtml;
      } elseif ($boardGridHtml) {
        $boardCombined = $boardGridHtml;
      } else {
        $boardCombined = $boardRestHtml;
      }

      // Replace Board section
      $replacedBoard = staff_replace_between_markers($html, '<!-- START: BOARD_OF_DIRECTORS -->', '<!-- END: BOARD_OF_DIRECTORS -->', $boardCombined);
      if ($replacedBoard !== false) {
        $html = $replacedBoard;
      } else {
        // Fallback regex
        $aboutRegex = '/(<section class="about-sub-section page-section" id="board-of-directors">[\s\S]*?<p>Our board brings deep technical and commercial experience in the pharmaceutical industry, guiding strategy, quality and sustainable growth.<\/p>\s*)(?:<div class="board-grid">[\s\S]*?<\/div>)?\s*(?:<ul class="mgmt-list board-rest-list">[\s\S]*?<\/ul>)?([\s\S]*?<div class="mgmt-heading-block" id="management-team">)/';
        if (preg_match($aboutRegex, $html)) {
          $html = preg_replace($aboutRegex, "${1}{$boardCombined}${2}", $html);
        }
      }

      // Sync Management Team list
      $mgmtListHtml = '      <ul class="mgmt-list">' . "\n";
      foreach ($mgmtList as $m) {
        $nm = htmlspecialchars($m['name'] ?? '');
        $des = htmlspecialchars($m['designation'] ?? '');
        $desc = trim($m['description'] ?? '');
        $photo = trim($m['photo'] ?? '');
        if ($photo && !str_starts_with($photo, 'http') && !str_contains($photo, '?')) {
          $photoUrl = htmlspecialchars($photo . $cacheBust);
        } else {
          $photoUrl = htmlspecialchars($photo);
        }

        $mgmtListHtml .= '        <li>' . "\n";
        if ($photoUrl) {
          $mgmtListHtml .= '          <div style="display:flex; gap:14px; align-items:flex-start;">' . "\n";
          $mgmtListHtml .= '            <div style="width:56px; height:56px; min-width:56px; border-radius:8px; overflow:hidden; border:1px solid var(--border); background:var(--light-gray); flex-shrink:0;">' . "\n";
          $mgmtListHtml .= '              <img src="' . $photoUrl . '" alt="' . $nm . ' - ' . $des . '" style="width:100%; height:100%; object-fit:cover; object-position:center top;">' . "\n";
          $mgmtListHtml .= '            </div>' . "\n";
          $mgmtListHtml .= '            <div><span class="mgmt-name">' . $nm . '</span><span class="mgmt-role">' . $des . '</span></div>' . "\n";
          $mgmtListHtml .= '          </div>' . "\n";
        } else {
          $mgmtListHtml .= '          <div><span class="mgmt-name">' . $nm . '</span><span class="mgmt-role">' . $des . '</span></div>' . "\n";
        }
        $mgmtListHtml .= '          <p>' . $desc . '</p>' . "\n";
        $mgmtListHtml .= '        </li>' . "\n";
      }
      $mgmtListHtml .= '      </ul>';

      // Replace Management section
      $replacedMgmt = staff_replace_between_markers($html, '<!-- START: MANAGEMENT_TEAM -->', '<!-- END: MANAGEMENT_TEAM -->', $mgmtListHtml);
      if ($replacedMgmt !== false) {
        $html = $replacedMgmt;
      } else {
        // Fallback regex
        $mgmtRegex = '/(<div class="mgmt-heading-block" id="management-team">[\s\S]*?<\/div>\s*)<ul class="mgmt-list">[\s\S]*?<\/ul>/';
        if (preg_match($mgmtRegex, $html)) {
          $html = preg_replace($mgmtRegex, "${1}{$mgmtListHtml}", $html);
        }
      }

      file_put_contents($aboutPath, $html);
      $syncedFiles[] = 'about-us.html';
    }
  }

  // 2. Sync independent-directors.html
  $idPath = $siteRoot . '/independent-directors.html';
  if (is_file($idPath)) {
    $idHtml = file_get_contents($idPath);
    if ($idHtml) {
      $indDirs = array_values(array_filter($list, function($d) {
        return !empty($d['is_independent']) || ($d['category'] ?? '') === 'independent';
      }));
      $idListHtml = '      <ul class="id-list">' . "\n";
      foreach ($indDirs as $d) {
        $nm = htmlspecialchars($d['name'] ?? '');
        $idListHtml .= '        <li>' . $nm . '</li>' . "\n";
      }
      $idListHtml .= '      </ul>';

      $replacedId = staff_replace_between_markers($idHtml, '<!-- START: INDEPENDENT_DIRECTORS_LIST -->', '<!-- END: INDEPENDENT_DIRECTORS_LIST -->', $idListHtml);
      if ($replacedId !== false) {
        $idHtml = $replacedId;
      } else {
        $idRegex = '/(The following Directors have been appointed as <strong style="color: var\(--dark\);">Independent Directors<\/strong> with effect from <strong>[^<]+<\/strong>:\s*<\/p>\s*)<ul class="id-list">[\s\S]*?<\/ul>/';
        if (preg_match($idRegex, $idHtml)) {
          $idHtml = preg_replace($idRegex, "${1}{$idListHtml}", $idHtml);
        }
      }
      file_put_contents($idPath, $idHtml);
      $syncedFiles[] = 'independent-directors.html';
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
      $csrListHtml = '        <ul style="list-style: none; padding: 0; margin: 0 0 28px;">' . "\n";
      $cnt = count($csrDirs);
      foreach ($csrDirs as $idx => $d) {
        $nm = htmlspecialchars($d['name'] ?? '');
        $role = htmlspecialchars($d['csr_role'] ?? 'Member');
        $border = ($idx < $cnt - 1) ? ' border-bottom: 1px solid #f0f0f0;' : '';
        $csrListHtml .= '          <li style="padding: 8px 0 8px 28px; position: relative; line-height: 1.55; color: #333;' . $border . '">' . "\n";
        $csrListHtml .= '            <span style="position: absolute; left: 0; color: var(--primary-red); font-weight: 700;">▸</span>' . "\n";
        $csrListHtml .= '            ' . $nm . ' &mdash; ' . $role . "\n";
        $csrListHtml .= '          </li>' . "\n";
      }
      $csrListHtml .= '        </ul>';

      $replacedCsr = staff_replace_between_markers($csrHtml, '<!-- START: CSR_COMMITTEE_LIST -->', '<!-- END: CSR_COMMITTEE_LIST -->', $csrListHtml);
      if ($replacedCsr !== false) {
        $csrHtml = $replacedCsr;
      } else {
        $csrRegex = '/(<h3 style="font-size: 18px; margin: 24px 0 12px; color: var\(--primary-dark\);">Members of the CSR Committee<\/h3>\s*)<ul style="list-style: none; padding: 0; margin: 0 0 28px;">[\s\S]*?<\/ul>/';
        if (preg_match($csrRegex, $csrHtml)) {
          $csrHtml = preg_replace($csrRegex, "${1}{$csrListHtml}", $csrHtml);
        }
      }
      file_put_contents($csrPath, $csrHtml);
      $syncedFiles[] = 'csr-policy.html';
    }
  }

  // 4. Sync posh-policy.html (Directors and Management Team tables)
  $poshPath = $siteRoot . '/posh-policy.html';
  if (is_file($poshPath)) {
    $poshHtml = file_get_contents($poshPath);
    if ($poshHtml) {
      // 4a. Sync Directors table
      $tbodyDirs = '          <tbody>' . "\n";
      $sno = 0;
      foreach ($list as $d) {
        $sno++;
        $bg = ($sno % 2 === 0) ? ' style="background:#f7fbfc;"' : '';
        $nm = htmlspecialchars($d['name'] ?? '');
        $des = htmlspecialchars($d['designation'] ?? '');
        $tbodyDirs .= '<tr' . $bg . '><td style="padding:8px 12px;border-bottom:1px solid #e8e8e8;">' . $sno . '</td><td style="padding:8px 12px;border-bottom:1px solid #e8e8e8;">' . $nm . '</td><td style="padding:8px 12px;border-bottom:1px solid #e8e8e8;">' . $des . '</td></tr>' . "\n";
      }
      $tbodyDirs .= '          </tbody>';

      $replacedPoshDirs = staff_replace_between_markers($poshHtml, '<!-- START: POSH_DIRECTORS_TABLE -->', '<!-- END: POSH_DIRECTORS_TABLE -->', $tbodyDirs);
      if ($replacedPoshDirs !== false) {
        $poshHtml = $replacedPoshDirs;
      } else {
        $poshRegex = '/(<th style="padding:10px 12px;text-align:left;">DIRECTORS NAME<\/th>\s*<th style="padding:10px 12px;text-align:left;">TITLE \/ \(DESIGNATION\)<\/th>\s*<\/tr><\/thead>\s*)<tbody>[\s\S]*?<\/tbody>/';
        if (preg_match($poshRegex, $poshHtml)) {
          $poshHtml = preg_replace($poshRegex, "${1}{$tbodyDirs}", $poshHtml);
        }
      }

      // 4b. Sync Management Team table
      $tbodyMgmt = '          <tbody>' . "\n";
      $snoMgmt = 0;
      foreach ($mgmtList as $m) {
        $snoMgmt++;
        $bg = ($snoMgmt % 2 === 0) ? ' style="background:#f7fbfc;"' : '';
        $nm = htmlspecialchars($m['name'] ?? '');
        $des = htmlspecialchars($m['designation'] ?? '');
        $tbodyMgmt .= '<tr' . $bg . '><td style="padding:8px 12px;border-bottom:1px solid #e8e8e8;">' . $snoMgmt . '</td><td style="padding:8px 12px;border-bottom:1px solid #e8e8e8;">' . $nm . '</td><td style="padding:8px 12px;border-bottom:1px solid #e8e8e8;">' . $des . '</td></tr>' . "\n";
      }
      $tbodyMgmt .= '          </tbody>';

      $replacedPoshMgmt = staff_replace_between_markers($poshHtml, '<!-- START: POSH_MANAGEMENT_TABLE -->', '<!-- END: POSH_MANAGEMENT_TABLE -->', $tbodyMgmt);
      if ($replacedPoshMgmt !== false) {
        $poshHtml = $replacedPoshMgmt;
      }

      file_put_contents($poshPath, $poshHtml);
      $syncedFiles[] = 'posh-policy.html';
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
        if (!staff_save_directors($directorsData)) {
          $err = 'Failed to persist changes to disk. Please check directory permissions.';
        } else {
          $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
          staff_log_audit('director_added', 'Added ' . $name . ' (' . $designation . ') to Board of Directors', $name, 'Corporate Governance');
          $msg = 'Successfully added "' . htmlspecialchars($name) . '" to the Board of Directors and updated ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
        }
      }
    }
  }

  // 2. EDIT DIRECTOR
  elseif ($action === 'edit_director') {
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

    if ($id === '') {
      $err = 'Invalid director ID.';
    } elseif ($name === '') {
      $err = 'Director name cannot be empty.';
    } elseif ($designation === '') {
      $err = 'Designation cannot be empty.';
    } elseif ($description === '') {
      $err = 'Description cannot be empty.';
    } else {
      $foundIdx = -1;
      foreach ($directorsData['directors'] as $i => $d) {
        if (($d['id'] ?? '') === $id) {
          $foundIdx = $i;
          break;
        }
      }

      if ($foundIdx === -1) {
        $err = 'Director record not found.';
      } else {
        $photoPath = trim($_POST['photo_existing'] ?? ($directorsData['directors'][$foundIdx]['photo'] ?? ''));
        if (!empty($_POST['remove_photo']) || !empty($_POST['delete_photo'])) {
          if (!empty($directorsData['directors'][$foundIdx]['photo']) && str_starts_with($directorsData['directors'][$foundIdx]['photo'], 'assets/directors/')) {
            $oldFile = $siteRoot . '/' . $directorsData['directors'][$foundIdx]['photo'];
            if (is_file($oldFile)) @unlink($oldFile);
          }
          $photoPath = '';
        }

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
          $directorsData['directors'][$foundIdx]['name']             = $name;
          $directorsData['directors'][$foundIdx]['designation']      = $designation;
          $directorsData['directors'][$foundIdx]['category']         = $category;
          $directorsData['directors'][$foundIdx]['category_label']   = $categoryLabel;
          $directorsData['directors'][$foundIdx]['photo']            = $photoPath;
          $directorsData['directors'][$foundIdx]['description']      = $description;
          $directorsData['directors'][$foundIdx]['is_independent']   = $isIndependent;
          $directorsData['directors'][$foundIdx]['is_csr_committee'] = $isCsr;
          $directorsData['directors'][$foundIdx]['csr_role']         = $csrRole;
          $directorsData['directors'][$foundIdx]['order']            = $order;
          $directorsData['directors'][$foundIdx]['status']           = $status;

          if (!staff_save_directors($directorsData)) {
            $err = 'Failed to persist changes to disk. Please check directory permissions.';
          } else {
            $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
            staff_log_audit('director_updated', 'Updated Board Director ' . $name . ' (' . $designation . ')', $id, 'Corporate Governance');
            $msg = 'Successfully updated "' . htmlspecialchars($name) . '" and synchronized ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
          }
        }
      }
    }
  }

  // 3. DELETE DIRECTOR
  elseif ($action === 'delete_director') {
    $id = trim($_POST['id'] ?? '');
    $deletedName = '';
    $newDirectors = [];
    foreach ($directorsData['directors'] as $d) {
      if (($d['id'] ?? '') === $id) {
        $deletedName = $d['name'] ?? 'Director';
      } else {
        $newDirectors[] = $d;
      }
    }

    if ($deletedName === '') {
      $err = 'Director not found for deletion.';
    } else {
      $directorsData['directors'] = $newDirectors;
      if (!staff_save_directors($directorsData)) {
        $err = 'Failed to persist deletion to disk.';
      } else {
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        staff_log_audit('director_deleted', 'Removed ' . $deletedName . ' from Board of Directors', $id, 'Corporate Governance');
        $msg = 'Successfully removed "' . htmlspecialchars($deletedName) . '" from the Board of Directors and re-synced ' . count($synced) . ' website pages.';
      }
    }
  }

  // 4. REORDER DIRECTORS
  elseif ($action === 'reorder_directors') {
    $dirId = trim($_POST['id'] ?? '');
    $direction = trim($_POST['direction'] ?? '');

    $list = $directorsData['directors'];
    usort($list, function($a, $b) {
      return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
    });

    $currIdx = -1;
    foreach ($list as $i => $d) {
      if (($d['id'] ?? '') === $dirId) {
        $currIdx = $i;
        break;
      }
    }

    if ($currIdx !== -1) {
      $swapIdx = ($direction === 'up') ? ($currIdx - 1) : ($currIdx + 1);
      if ($swapIdx >= 0 && $swapIdx < count($list)) {
        $tmp = $list[$currIdx];
        $list[$currIdx] = $list[$swapIdx];
        $list[$swapIdx] = $tmp;

        foreach ($list as $newOrder => &$item) {
          $item['order'] = $newOrder + 1;
        }
        unset($item);

        $directorsData['directors'] = $list;
        staff_save_directors($directorsData);
        staff_sync_directors_to_html($directorsData, $siteRoot);
        staff_log_audit('director_reordered', 'Reordered Board of Directors sequence (' . $direction . ')', $dirId, 'Corporate Governance');
        $msg = 'Board order updated and synchronized.';
      }
    }
  }

  // 4b. ARCHIVE / UNARCHIVE DIRECTOR (HIDE / SHOW ON WEBSITE)
  elseif ($action === 'toggle_archive_director' || $action === 'archive_director' || $action === 'unarchive_director') {
    $id = trim($_POST['id'] ?? '');
    $targetStatus = ($action === 'archive_director') ? 'archived' : (($action === 'unarchive_director') ? 'active' : trim($_POST['status'] ?? 'archived'));
    $dirName = '';
    $found = false;
    foreach ($directorsData['directors'] as &$d) {
      if (($d['id'] ?? '') === $id) {
        $d['status'] = $targetStatus;
        $dirName = $d['name'] ?? 'Director';
        $found = true;
        break;
      }
    }
    unset($d);

    if (!$found) {
      $err = 'Director record not found.';
    } else {
      if (!staff_save_directors($directorsData)) {
        $err = 'Failed to persist status change to disk. Please check file permissions.';
      } else {
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        $isArchived = ($targetStatus === 'archived');
        staff_log_audit(
          $isArchived ? 'director_archived' : 'director_unarchived',
          ($isArchived ? 'Archived and hid ' : 'Restored and published ') . $dirName . ' (' . ($isArchived ? 'Hidden from website' : 'Visible on website') . ')',
          $id,
          'Corporate Governance'
        );
        $msg = $isArchived
          ? 'Successfully archived "' . htmlspecialchars($dirName) . '". Their profile is now completely hidden from all public website pages (' . implode(', ', $synced) . '). Details remain safely preserved in the Staff Portal.'
          : 'Successfully restored "' . htmlspecialchars($dirName) . '". Their profile is now live and published on public website pages (' . implode(', ', $synced) . ').';
      }
    }
  }

  // 4c. DELETE DIRECTOR PHOTO (REVERT TO CLEAN TEXT / LIST VIEW)
  elseif ($action === 'delete_director_photo') {
    $id = trim($_POST['id'] ?? '');
    $dirName = '';
    $foundIdx = -1;
    foreach ($directorsData['directors'] as $i => $d) {
      if (($d['id'] ?? '') === $id) {
        $foundIdx = $i;
        $dirName = $d['name'] ?? 'Director';
        break;
      }
    }
    if ($foundIdx === -1) {
      $err = 'Director record not found.';
    } else {
      $oldPhoto = trim($directorsData['directors'][$foundIdx]['photo'] ?? '');
      if ($oldPhoto && str_starts_with($oldPhoto, 'assets/directors/')) {
        $oldFile = $siteRoot . '/' . $oldPhoto;
        if (is_file($oldFile)) @unlink($oldFile);
      }
      $directorsData['directors'][$foundIdx]['photo'] = '';
      if (!staff_save_directors($directorsData)) {
        $err = 'Failed to persist photo removal to disk.';
      } else {
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        staff_log_audit('director_photo_deleted', 'Removed portrait photo for Director ' . $dirName, $id, 'Corporate Governance');
        $msg = 'Successfully deleted portrait photo for "' . htmlspecialchars($dirName) . '". Reverted to clean text view and updated ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
      }
    }
  }

  // 5. ADD MANAGEMENT MEMBER
  elseif ($action === 'add_management') {
    $currentTab = 'management';
    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $order = intval($_POST['order'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');

    if ($name === '') {
      $err = 'Management executive name is required.';
    } elseif ($designation === '') {
      $err = 'Executive title / designation is required.';
    } elseif ($description === '') {
      $err = 'Executive biography / summary is required.';
    } else {
      $photoPath = '';
      if (!empty($_FILES['mgmt_photo_file']['tmp_name']) && is_uploaded_file($_FILES['mgmt_photo_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['mgmt_photo_file']['name'], PATHINFO_EXTENSION));
        $validExts = ['png', 'jpg', 'jpeg', 'webp'];
        $imgInfo = @getimagesize($_FILES['mgmt_photo_file']['tmp_name']);
        if (!in_array($ext, $validExts, true) || !$imgInfo) {
          $err = 'Invalid photo format. Please upload a PNG, JPG, or WEBP image.';
        } elseif ($_FILES['mgmt_photo_file']['size'] > 5 * 1024 * 1024) {
          $err = 'Photo file size must be under 5 MB.';
        } else {
          $destDir = $siteRoot . '/assets/directors';
          if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
          $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
          $slug = trim($slug, '-');
          $fileName = 'mgmt-' . $slug . '-' . time() . '.' . $ext;
          if (move_uploaded_file($_FILES['mgmt_photo_file']['tmp_name'], $destDir . '/' . $fileName)) {
            $photoPath = 'assets/directors/' . $fileName;
          } else {
            $err = 'Failed to save uploaded photo to assets/directors.';
          }
        }
      }

      if (!$err) {
        if (!isset($directorsData['management']) || !is_array($directorsData['management'])) {
          $directorsData['management'] = [];
        }
        $maxNum = 0;
        foreach ($directorsData['management'] as $m) {
          if (preg_match('/MGMT-(\d+)/', $m['id'] ?? '', $matches)) {
            $num = intval($matches[1]);
            if ($num > $maxNum) $maxNum = $num;
          }
        }
        $newId = 'MGMT-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);

        if ($order <= 0) {
          $order = count($directorsData['management']) + 1;
        }

        $newMember = [
          'id'          => $newId,
          'name'        => $name,
          'designation' => $designation,
          'photo'       => $photoPath,
          'description' => $description,
          'order'       => $order,
          'status'      => $status
        ];

        $directorsData['management'][] = $newMember;
        if (!staff_save_directors($directorsData)) {
          $err = 'Failed to persist management changes to disk.';
        } else {
          $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
          staff_log_audit('management_added', 'Added ' . $name . ' (' . $designation . ') to Senior Management Team', $name, 'Corporate Governance');
          $msg = 'Successfully added "' . htmlspecialchars($name) . '" to the Senior Management Team and updated ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
        }
      }
    }
  }

  // 6. EDIT MANAGEMENT MEMBER
  elseif ($action === 'edit_management') {
    $currentTab = 'management';
    $id = trim($_POST['id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $order = intval($_POST['order'] ?? 0);
    $status = trim($_POST['status'] ?? 'active');

    if ($id === '') {
      $err = 'Invalid management executive ID.';
    } elseif ($name === '') {
      $err = 'Executive name cannot be empty.';
    } elseif ($designation === '') {
      $err = 'Designation cannot be empty.';
    } elseif ($description === '') {
      $err = 'Description cannot be empty.';
    } else {
      $foundIdx = -1;
      if (!isset($directorsData['management']) || !is_array($directorsData['management'])) {
        $directorsData['management'] = [];
      }
      foreach ($directorsData['management'] as $i => $m) {
        if (($m['id'] ?? '') === $id) {
          $foundIdx = $i;
          break;
        }
      }

      if ($foundIdx === -1) {
        $err = 'Management team record not found.';
      } else {
        $photoPath = trim($_POST['photo_existing'] ?? ($directorsData['management'][$foundIdx]['photo'] ?? ''));
        if (!empty($_POST['remove_photo']) || !empty($_POST['delete_photo'])) {
          if (!empty($directorsData['management'][$foundIdx]['photo']) && str_starts_with($directorsData['management'][$foundIdx]['photo'], 'assets/directors/')) {
            $oldFile = $siteRoot . '/' . $directorsData['management'][$foundIdx]['photo'];
            if (is_file($oldFile)) @unlink($oldFile);
          }
          $photoPath = '';
        }

        if (!empty($_FILES['mgmt_photo_file']['tmp_name']) && is_uploaded_file($_FILES['mgmt_photo_file']['tmp_name'])) {
          $ext = strtolower(pathinfo($_FILES['mgmt_photo_file']['name'], PATHINFO_EXTENSION));
          $validExts = ['png', 'jpg', 'jpeg', 'webp'];
          $imgInfo = @getimagesize($_FILES['mgmt_photo_file']['tmp_name']);
          if (!in_array($ext, $validExts, true) || !$imgInfo) {
            $err = 'Invalid photo format. Please upload a PNG, JPG, or WEBP image.';
          } elseif ($_FILES['mgmt_photo_file']['size'] > 5 * 1024 * 1024) {
            $err = 'Photo file size must be under 5 MB.';
          } else {
            $destDir = $siteRoot . '/assets/directors';
            if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
            $slug = trim($slug, '-');
            $fileName = 'mgmt-' . $slug . '-' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['mgmt_photo_file']['tmp_name'], $destDir . '/' . $fileName)) {
              $photoPath = 'assets/directors/' . $fileName;
            } else {
              $err = 'Failed to save uploaded photo to assets/directors.';
            }
          }
        }

        if (!$err) {
          $directorsData['management'][$foundIdx]['name']        = $name;
          $directorsData['management'][$foundIdx]['designation'] = $designation;
          $directorsData['management'][$foundIdx]['photo']       = $photoPath;
          $directorsData['management'][$foundIdx]['description'] = $description;
          $directorsData['management'][$foundIdx]['order']       = $order;
          $directorsData['management'][$foundIdx]['status']      = $status;

          if (!staff_save_directors($directorsData)) {
            $err = 'Failed to persist management changes to disk.';
          } else {
            $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
            staff_log_audit('management_updated', 'Updated Senior Management executive ' . $name . ' (' . $designation . ')', $id, 'Corporate Governance');
            $msg = 'Successfully updated "' . htmlspecialchars($name) . '" and synchronized ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
          }
        }
      }
    }
  }

  // 7. DELETE MANAGEMENT MEMBER
  elseif ($action === 'delete_management') {
    $currentTab = 'management';
    $id = trim($_POST['id'] ?? '');
    $deletedName = '';
    $newMgmt = [];
    if (!isset($directorsData['management']) || !is_array($directorsData['management'])) {
      $directorsData['management'] = [];
    }
    foreach ($directorsData['management'] as $m) {
      if (($m['id'] ?? '') === $id) {
        $deletedName = $m['name'] ?? 'Executive';
      } else {
        $newMgmt[] = $m;
      }
    }

    if ($deletedName === '') {
      $err = 'Executive record not found for deletion.';
    } else {
      $directorsData['management'] = $newMgmt;
      if (!staff_save_directors($directorsData)) {
        $err = 'Failed to persist management deletion to disk.';
      } else {
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        staff_log_audit('management_deleted', 'Removed ' . $deletedName . ' from Senior Management', $id, 'Corporate Governance');
        $msg = 'Successfully removed "' . htmlspecialchars($deletedName) . '" from Senior Management and updated public pages.';
      }
    }
  }

  // 8. REORDER MANAGEMENT MEMBERS
  elseif ($action === 'reorder_management') {
    $currentTab = 'management';
    $mgmtId = trim($_POST['id'] ?? '');
    $direction = trim($_POST['direction'] ?? '');

    $list = $directorsData['management'] ?? [];
    usort($list, function($a, $b) {
      return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
    });

    $currIdx = -1;
    foreach ($list as $i => $m) {
      if (($m['id'] ?? '') === $mgmtId) {
        $currIdx = $i;
        break;
      }
    }

    if ($currIdx !== -1) {
      $swapIdx = ($direction === 'up') ? ($currIdx - 1) : ($currIdx + 1);
      if ($swapIdx >= 0 && $swapIdx < count($list)) {
        $tmp = $list[$currIdx];
        $list[$currIdx] = $list[$swapIdx];
        $list[$swapIdx] = $tmp;

        foreach ($list as $newOrder => &$item) {
          $item['order'] = $newOrder + 1;
        }
        unset($item);

        $directorsData['management'] = $list;
        staff_save_directors($directorsData);
        staff_sync_directors_to_html($directorsData, $siteRoot);
        staff_log_audit('management_reordered', 'Reordered Senior Management sequence (' . $direction . ')', $mgmtId, 'Corporate Governance');
        $msg = 'Senior Management order updated and synchronized.';
      }
    }
  }

  // 8b. ARCHIVE / UNARCHIVE MANAGEMENT MEMBER
  elseif ($action === 'toggle_archive_management' || $action === 'archive_management' || $action === 'unarchive_management') {
    $currentTab = 'management';
    $id = trim($_POST['id'] ?? '');
    $targetStatus = ($action === 'archive_management') ? 'archived' : (($action === 'unarchive_management') ? 'active' : trim($_POST['status'] ?? 'archived'));
    $mgmtName = '';
    $found = false;
    if (!isset($directorsData['management']) || !is_array($directorsData['management'])) {
      $directorsData['management'] = [];
    }
    foreach ($directorsData['management'] as &$m) {
      if (($m['id'] ?? '') === $id) {
        $m['status'] = $targetStatus;
        $mgmtName = $m['name'] ?? 'Executive';
        $found = true;
        break;
      }
    }
    unset($m);

    if (!$found) {
      $err = 'Executive record not found.';
    } else {
      if (!staff_save_directors($directorsData)) {
        $err = 'Failed to persist management changes to disk.';
      } else {
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        $isArchived = ($targetStatus === 'archived');
        staff_log_audit(
          $isArchived ? 'management_archived' : 'management_unarchived',
          ($isArchived ? 'Archived and hid ' : 'Restored and published ') . $mgmtName . ' from Senior Management',
          $id,
          'Corporate Governance'
        );
        $msg = $isArchived
          ? 'Successfully archived "' . htmlspecialchars($mgmtName) . '". Hidden from public website pages (' . implode(', ', $synced) . ').'
          : 'Successfully restored "' . htmlspecialchars($mgmtName) . '". Published and visible on public website pages (' . implode(', ', $synced) . ').';
      }
    }
  }

  // 8c. DELETE MANAGEMENT PHOTO (REVERT TO CLEAN TEXT ONLY)
  elseif ($action === 'delete_management_photo') {
    $currentTab = 'management';
    $id = trim($_POST['id'] ?? '');
    $mgmtName = '';
    $foundIdx = -1;
    if (!isset($directorsData['management']) || !is_array($directorsData['management'])) {
      $directorsData['management'] = [];
    }
    foreach ($directorsData['management'] as $i => $m) {
      if (($m['id'] ?? '') === $id) {
        $foundIdx = $i;
        $mgmtName = $m['name'] ?? 'Executive';
        break;
      }
    }
    if ($foundIdx === -1) {
      $err = 'Executive record not found.';
    } else {
      $oldPhoto = trim($directorsData['management'][$foundIdx]['photo'] ?? '');
      if ($oldPhoto && str_starts_with($oldPhoto, 'assets/directors/')) {
        $oldFile = $siteRoot . '/' . $oldPhoto;
        if (is_file($oldFile)) @unlink($oldFile);
      }
      $directorsData['management'][$foundIdx]['photo'] = '';
      if (!staff_save_directors($directorsData)) {
        $err = 'Failed to persist management changes to disk.';
      } else {
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        staff_log_audit('management_photo_deleted', 'Removed portrait photo for Executive ' . $mgmtName, $id, 'Corporate Governance');
        $msg = 'Successfully deleted portrait photo for "' . htmlspecialchars($mgmtName) . '". Reverted to clean text view and updated ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
      }
    }
  }

  // 9. MANUAL FULL SYNC
  elseif ($action === 'sync_all') {
    $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
    staff_log_audit('directors_synced_all', 'Full manual synchronization of Board & Management to public HTML pages (' . count($synced) . ' files)', 'public_html', 'Corporate Governance');
    $msg = 'Full synchronization successful! Re-rendered and saved: ' . implode(', ', $synced) . '.';
  }
}

// Reload after mutations
$directorsData = staff_load_directors();
$directors = $directorsData['directors'] ?? [];
usort($directors, function($a, $b) {
  return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
});

$management = $directorsData['management'] ?? [];
usort($management, function($a, $b) {
  return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
});

// Board statistics
$countTotal = count($directors);
$countActive = count(array_filter($directors, function($d) { return ($d['status'] ?? 'active') === 'active'; }));
$countArchived = count(array_filter($directors, function($d) { return ($d['status'] ?? 'active') === 'archived'; }));
$countExec = count(array_filter($directors, function($d) { return ($d['category'] ?? '') === 'executive'; }));
$countWhole = count(array_filter($directors, function($d) { return ($d['category'] ?? '') === 'wholetime'; }));
$countInd = count(array_filter($directors, function($d) { return !empty($d['is_independent']) || ($d['category'] ?? '') === 'independent'; }));
$countCsr = count(array_filter($directors, function($d) { return !empty($d['is_csr_committee']); }));
$countPhotos = count(array_filter($directors, function($d) { return !empty($d['photo']); }));

// Management statistics
$countMgmtTotal = count($management);
$countMgmtActive = count(array_filter($management, function($m) { return ($m['status'] ?? 'active') === 'active'; }));
$countMgmtArchived = count(array_filter($management, function($m) { return ($m['status'] ?? 'active') === 'archived'; }));
$countMgmtPhotos = count(array_filter($management, function($m) { return !empty($m['photo']); }));

$page_title = 'Board & Management Desk';
require __DIR__ . '/includes/header.php';
?>

<!-- Alert Feedback -->
<?php if ($msg): ?>
  <div class="card" style="display:flex; align-items:center; gap:10px; background:#ECFDF5; border:1px solid #10B981; color:#065F46; padding:14px 18px; border-radius:10px; margin-bottom:20px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
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
<div class="desk-hero-banner">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; position:relative; z-index:1;">
    <div>
      <div style="text-transform:uppercase; font-size:11px; letter-spacing:0.1em; opacity:0.85; font-weight:700; margin-bottom:6px;">
        Executive Governance &bull; Secretarial Desk
      </div>
      <h1 style="margin:0 0 4px; font-size:24px; color:#FFFFFF;">
        <?php echo $currentTab === 'management' ? 'Senior Management Leadership Console' : 'Board of Directors &amp; Executive Biographies'; ?>
      </h1>
      <p style="margin:0; opacity:0.9; font-size:13px; color:#E2E8F0; max-width:800px; line-height:1.5;">
        Centrally manage corporate leadership, board committees, executive designations, portrait photos, and biographies synchronized across the public website.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <form method="post" style="display:inline; margin:0;">
        <?php echo staff_csrf_field(); ?>
        <input type="hidden" name="action" value="sync_all">
        <button type="submit" class="btn btn-ghost" style="background:rgba(255,255,255,0.15); color:#FFFFFF; border:1px solid rgba(255,255,255,0.3); font-size:12.5px;" title="Force re-sync all 4 website pages">
          🔄 Force Re-Sync
        </button>
      </form>
      <?php if ($currentTab === 'management'): ?>
        <button onclick="document.getElementById('addMgmtModal').style.display='flex';" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          + Add Management Member
        </button>
      <?php else: ?>
        <button onclick="document.getElementById('addDirectorModal').style.display='flex';" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          + Add Board Member
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Desk Navigation Tabs -->
<div style="display:flex; gap:8px; margin-bottom:20px; border-bottom:2px solid #E2E8F0; padding-bottom:8px;">
  <a href="?tab=directors" class="btn" style="border-radius:8px 8px 0 0; padding:10px 20px; font-size:13.5px; font-weight:700; display:inline-flex; align-items:center; gap:8px; <?php echo $currentTab === 'directors' ? 'background:#0E8F6C; color:#FFFFFF;' : 'background:#F1F5F9; color:#475569; border:1px solid #CBD5E1; border-bottom:0;'; ?>">
    <span>🏛️ Board of Directors</span>
    <span style="background:<?php echo $currentTab === 'directors' ? 'rgba(255,255,255,0.25)' : '#E2E8F0'; ?>; color:<?php echo $currentTab === 'directors' ? '#FFFFFF' : '#0F172A'; ?>; padding:2px 8px; border-radius:12px; font-size:11.5px; font-weight:800;"><?php echo $countTotal; ?></span>
  </a>
  <a href="?tab=management" class="btn" style="border-radius:8px 8px 0 0; padding:10px 20px; font-size:13.5px; font-weight:700; display:inline-flex; align-items:center; gap:8px; <?php echo $currentTab === 'management' ? 'background:#0E8F6C; color:#FFFFFF;' : 'background:#F1F5F9; color:#475569; border:1px solid #CBD5E1; border-bottom:0;'; ?>">
    <span>💼 Senior Management Team</span>
    <span style="background:<?php echo $currentTab === 'management' ? 'rgba(255,255,255,0.25)' : '#E2E8F0'; ?>; color:<?php echo $currentTab === 'management' ? '#FFFFFF' : '#0F172A'; ?>; padding:2px 8px; border-radius:12px; font-size:11.5px; font-weight:800;"><?php echo $countMgmtTotal; ?></span>
  </a>
</div>

<?php if ($currentTab === 'directors'): ?>
  <!-- ================= BOARD OF DIRECTORS TAB ================= -->
  <!-- KPI Stats Row -->
  <div class="row-4" style="margin-bottom:22px;">
    <div class="stat-card">
      <div class="stat-label">Total Board Records</div>
      <div class="stat-num"><?php echo $countTotal; ?></div>
      <div class="stat-sub"><?php echo $countPhotos; ?> with photos in grid</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Live on Website</div>
      <div class="stat-num" style="color:#0E8F6C;"><?php echo $countActive; ?></div>
      <div class="stat-sub">✓ Published across 4 pages</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Archived / Hidden</div>
      <div class="stat-num" style="color:#D97706;"><?php echo $countArchived; ?></div>
      <div class="stat-sub">🔒 Hidden from public website</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Statutory Committees</div>
      <div class="stat-num" style="color:#7C3AED;"><?php echo $countInd; ?></div>
      <div class="stat-sub"><?php echo $countCsr; ?> on CSR Committee</div>
    </div>
  </div>

  <!-- Search, Filter & Quick Action Bar -->
  <div class="card" style="padding:16px 20px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
      <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
        <!-- Status Filter -->
        <div style="display:flex; gap:6px; align-items:center;">
          <span style="font-size:11.5px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.04em;">Status:</span>
          <button type="button" class="btn btn-ghost filter-pill-status active" onclick="filterStatus('all', this)" style="padding:4px 11px; font-size:12px; border-radius:20px;">All (<?php echo $countTotal; ?>)</button>
          <button type="button" class="btn btn-ghost filter-pill-status" onclick="filterStatus('active', this)" style="padding:4px 11px; font-size:12px; border-radius:20px; color:#15803D;">✓ Live (<?php echo $countActive; ?>)</button>
          <button type="button" class="btn btn-ghost filter-pill-status" onclick="filterStatus('archived', this)" style="padding:4px 11px; font-size:12px; border-radius:20px; color:#B45309;">🔒 Archived (<?php echo $countArchived; ?>)</button>
        </div>

        <!-- Category Filter -->
        <div style="display:flex; gap:6px; align-items:center; border-left:1px solid #CBD5E1; padding-left:14px;">
          <span style="font-size:11.5px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.04em;">Category:</span>
          <button type="button" class="btn btn-ghost filter-pill-cat active" onclick="filterCategory('all', this)" style="padding:4px 10px; font-size:12px; border-radius:20px;">All</button>
          <button type="button" class="btn btn-ghost filter-pill-cat" onclick="filterCategory('executive', this)" style="padding:4px 10px; font-size:12px; border-radius:20px;">Executive (<?php echo $countExec; ?>)</button>
          <button type="button" class="btn btn-ghost filter-pill-cat" onclick="filterCategory('wholetime', this)" style="padding:4px 10px; font-size:12px; border-radius:20px;">Whole-Time (<?php echo $countWhole; ?>)</button>
          <button type="button" class="btn btn-ghost filter-pill-cat" onclick="filterCategory('independent', this)" style="padding:4px 10px; font-size:12px; border-radius:20px;">Independent (<?php echo $countInd; ?>)</button>
        </div>
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
      <span style="font-size:12px; color:#64748B;">Reordering, editing or photo updates auto-sync all 4 website pages</span>
    </div>

    <div id="directorsContainer">
      <?php foreach ($directors as $idx => $d): 
        $id = htmlspecialchars($d['id'] ?? '');
        $name = htmlspecialchars($d['name'] ?? '');
        $des = htmlspecialchars($d['designation'] ?? '');
        $cat = htmlspecialchars($d['category'] ?? 'wholetime');
        $catLbl = htmlspecialchars($d['category_label'] ?? 'Director');
        $photo = htmlspecialchars($d['photo'] ?? '');
        $desc = htmlspecialchars($d['description'] ?? '');
        $status = htmlspecialchars($d['status'] ?? 'active');
        $isArchived = ($status === 'archived');
        $isInd = !empty($d['is_independent']) || $cat === 'independent';
        $isCsr = !empty($d['is_csr_committee']);
        $csrRole = htmlspecialchars($d['csr_role'] ?? '');
        $order = intval($d['order'] ?? ($idx + 1));
      ?>
        <div class="director-row" data-status="<?php echo $status; ?>" data-category="<?php echo $cat; ?>" data-name="<?php echo strtolower($name); ?>" data-title="<?php echo strtolower($des); ?>" style="padding:18px 24px; border-bottom:1px solid #F1F5F9; display:flex; gap:18px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; transition:background 0.15s; <?php echo $isArchived ? 'background:#FFFDF5; border-left:4px solid #F59E0B;' : ''; ?>">
          <!-- Left Identity -->
          <div style="display:flex; gap:16px; align-items:flex-start; flex:1; min-width:300px;">
            <?php if (!empty($d['photo'])): ?>
              <div style="width:52px; height:52px; border-radius:10px; overflow:hidden; border:1px solid #CBD5E1; background:#F8FAFC; flex-shrink:0;">
                <img src="../<?php echo $photo; ?>" alt="<?php echo $name; ?>" style="width:100%; height:100%; object-fit:cover; object-position:center top;">
              </div>
            <?php else: ?>
              <div style="width:52px; height:52px; border-radius:10px; overflow:hidden; background:#F1F5F9; flex-shrink:0; border:1px solid #E2E8F0; display:flex; align-items:center; justify-content:center;">
                <span style="font-weight:800; font-size:18px; color:#64748B;">
                  <?php echo strtoupper(substr($d['name'] ?? 'D', 0, 1)); ?>
                </span>
              </div>
            <?php endif; ?>

            <div style="flex:1;">
              <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span style="font-size:11px; font-weight:700; color:#64748B; background:#F1F5F9; padding:2px 7px; border-radius:4px;">#<?php echo $order; ?> &bull; <?php echo $id; ?></span>
                <h4 style="margin:0; font-size:16px; color:#0F172A; font-weight:700;"><?php echo $name; ?></h4>
                <?php if ($isArchived): ?>
                  <span class="badge" style="background:#FEF3C7; color:#92400E; padding:3px 9px; border-radius:12px; font-size:11px; font-weight:800; border:1px solid #FDE68A;">
                    🔒 Archived &bull; Hidden from Website
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:#DCFCE7; color:#15803D; padding:3px 9px; border-radius:12px; font-size:11px; font-weight:700; border:1px solid #BBF7D0;">
                    ✓ Live on Website
                  </span>
                <?php endif; ?>
                <span class="badge" style="background:#E0F2FE; color:#0369A1; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                  <?php echo $catLbl; ?>
                </span>
                <?php if (!empty($d['photo'])): ?>
                  <span class="badge" style="background:#DCFCE7; color:#15803D; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                    📷 Photo Card
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:#F1F5F9; color:#64748B; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                    📄 Text List
                  </span>
                <?php endif; ?>
                <?php if ($isInd): ?>
                  <span class="badge" style="background:#F3E8FF; color:#6B21A8; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                    Independent
                  </span>
                <?php endif; ?>
                <?php if ($isCsr): ?>
                  <span class="badge" style="background:#FEF3C7; color:#92400E; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                    CSR: <?php echo $csrRole ? $csrRole : 'Member'; ?>
                  </span>
                <?php endif; ?>
              </div>
              <div style="font-weight:600; font-size:13px; color:#0E8F6C; margin:3px 0 6px;">
                <?php echo $des; ?>
              </div>
              <div style="font-size:12.5px; color:#475569; line-height:1.5; max-width:820px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                <?php echo $desc; ?>
              </div>
              <?php if ($isArchived): ?>
                <div style="margin-top:6px; font-size:11.5px; color:#B45309; font-weight:600;">
                  <span>🔒 <strong>Archived:</strong> Excluded from all 4 public website pages (about-us.html, independent-directors.html, csr-policy.html, posh-policy.html). Saved safely in Staff Portal database.</span>
                </div>
              <?php else: ?>
                <div style="margin-top:6px; font-size:11px; color:#64748B;">
                  <span><strong>Live on:</strong> about-us.html</span>
                  <?php if ($isInd): ?> &bull; <span>independent-directors.html</span><?php endif; ?>
                  <?php if ($isCsr): ?> &bull; <span>csr-policy.html</span><?php endif; ?>
                  &bull; <span>posh-policy.html</span>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Right Actions -->
          <div style="display:flex; align-items:center; gap:8px;">
            <!-- Move Up -->
            <form method="post" style="display:inline; margin:0;">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="reorder_directors">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <input type="hidden" name="direction" value="up">
              <button type="submit" class="btn btn-ghost" style="padding:6px 9px;" title="Move Up" <?php echo ($idx === 0) ? 'disabled style="opacity:0.3;"' : ''; ?>>
                ▲
              </button>
            </form>

            <!-- Move Down -->
            <form method="post" style="display:inline; margin:0;">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="reorder_directors">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <input type="hidden" name="direction" value="down">
              <button type="submit" class="btn btn-ghost" style="padding:6px 9px;" title="Move Down" <?php echo ($idx === count($directors) - 1) ? 'disabled style="opacity:0.3;"' : ''; ?>>
                ▼
              </button>
            </form>

            <!-- Archive / Restore Quick Action Button -->
            <?php if ($isArchived): ?>
              <form method="post" style="display:inline; margin:0;">
                <?php echo staff_csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_archive_director">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="status" value="active">
                <button type="submit" class="btn" style="background:#10B981; color:#FFFFFF; font-weight:700; padding:6px 12px; font-size:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);" title="Restore this director and publish back to public website">
                  🔄 Restore to Website
                </button>
              </form>
            <?php else: ?>
              <button type="button" onclick="confirmArchive('<?php echo $id; ?>', '<?php echo addslashes($name); ?>')" class="btn btn-ghost" style="color:#B45309; background:#FEF3C7; font-weight:700; padding:6px 12px; font-size:12px; border:1px solid #FDE68A; border-radius:6px;" title="Archive this director and hide from website">
                🔒 Archive / Hide
              </button>
            <?php endif; ?>

            <!-- Delete Photo Quick Action if photo exists -->
            <?php if (!empty($d['photo'])): ?>
              <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('Remove portrait photo for &quot;<?php echo addslashes($name); ?>&quot;? This director will immediately revert to clean text list view on the public website.');">
                <?php echo staff_csrf_field(); ?>
                <input type="hidden" name="action" value="delete_director_photo">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <button type="submit" class="btn btn-ghost" style="color:#DC2626; background:#FEF2F2; border:1px solid #FECACA; font-weight:700; padding:6px 11px; font-size:12px; border-radius:6px;" title="Delete this director's photo">
                  🗑️ Delete Photo
                </button>
              </form>
            <?php endif; ?>

            <!-- Edit Button -->
            <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-ghost" style="color:var(--sp-primary); font-weight:600; padding:6px 12px; font-size:12px;">
              ✏️ Edit / Photo
            </button>

            <!-- Delete Button -->
            <button type="button" onclick="confirmDelete('<?php echo $id; ?>', '<?php echo addslashes($name); ?>')" class="btn btn-ghost" style="color:#DC2626; padding:6px 10px; font-size:12px;" title="Delete director">
              🗑️
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

<?php else: ?>
  <!-- ================= SENIOR MANAGEMENT TEAM TAB ================= -->
  <!-- KPI Stats Row for Management -->
  <div class="row-4" style="margin-bottom:22px;">
    <div class="stat-card">
      <div class="stat-label">Senior Executives</div>
      <div class="stat-num" style="color:#0E8F6C;"><?php echo $countMgmtTotal; ?></div>
      <div class="stat-sub"><?php echo $countMgmtPhotos; ?> with portrait photos</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Live on Website</div>
      <div class="stat-num" style="color:#0E8F6C;"><?php echo $countMgmtActive; ?></div>
      <div class="stat-sub">✓ Published on about-us &amp; posh-policy</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Archived / Hidden</div>
      <div class="stat-num" style="color:#D97706;"><?php echo $countMgmtArchived; ?></div>
      <div class="stat-sub">🔒 Hidden from public website</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Sync Status</div>
      <div class="stat-num" style="color:#10B981; font-size:20px; line-height:36px;">✓ 100% In Sync</div>
      <div class="stat-sub">Updates about-us &amp; posh-policy</div>
    </div>
  </div>

  <!-- Search & Quick Action Bar -->
  <div class="card" style="padding:16px 20px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <span style="font-size:11.5px; font-weight:800; color:#475569; text-transform:uppercase; letter-spacing:0.04em;">Status:</span>
        <button type="button" class="btn btn-ghost filter-pill-mgmt-status active" onclick="filterMgmtStatus('all', this)" style="padding:4px 11px; font-size:12px; border-radius:20px;">All (<?php echo $countMgmtTotal; ?>)</button>
        <button type="button" class="btn btn-ghost filter-pill-mgmt-status" onclick="filterMgmtStatus('active', this)" style="padding:4px 11px; font-size:12px; border-radius:20px; color:#15803D;">✓ Live (<?php echo $countMgmtActive; ?>)</button>
        <button type="button" class="btn btn-ghost filter-pill-mgmt-status" onclick="filterMgmtStatus('archived', this)" style="padding:4px 11px; font-size:12px; border-radius:20px; color:#B45309;">🔒 Archived (<?php echo $countMgmtArchived; ?>)</button>
      </div>
      <div style="display:flex; gap:10px; align-items:center;">
        <input type="text" id="mgmtSearch" placeholder="Search executive name, title..." onkeyup="filterMgmtSearch()" style="width:260px; padding:7px 12px; font-size:12.5px; border-radius:6px; margin:0;">
        <a href="../about-us.html#management-team" target="_blank" rel="noopener" class="btn btn-ghost" style="font-size:12px; padding:6px 12px;" title="Open live public Management Team section">
          Live Preview ↗
        </a>
      </div>
    </div>
  </div>

  <!-- Management Team Directory List -->
  <div class="card" style="padding:0; overflow:hidden;">
    <div style="padding:18px 24px; border-bottom:1px solid var(--sp-border); background:#F8FAFC; display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0; font-size:16px;">Senior Management Team Registry</h3>
      <span style="font-size:12px; color:#64748B;">Reordering, editing or photo updates auto-sync <code>about-us.html</code> and <code>posh-policy.html</code></span>
    </div>

    <div id="mgmtContainer">
      <?php foreach ($management as $idx => $m): 
        $id = htmlspecialchars($m['id'] ?? '');
        $name = htmlspecialchars($m['name'] ?? '');
        $des = htmlspecialchars($m['designation'] ?? '');
        $photo = htmlspecialchars($m['photo'] ?? '');
        $desc = htmlspecialchars($m['description'] ?? '');
        $status = htmlspecialchars($m['status'] ?? 'active');
        $isArchived = ($status === 'archived');
        $order = intval($m['order'] ?? ($idx + 1));
      ?>
        <div class="mgmt-row" data-status="<?php echo $status; ?>" data-name="<?php echo strtolower($name); ?>" data-title="<?php echo strtolower($des); ?>" style="padding:18px 24px; border-bottom:1px solid #F1F5F9; display:flex; gap:18px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; transition:background 0.15s; <?php echo $isArchived ? 'background:#FFFDF5; border-left:4px solid #F59E0B;' : ''; ?>">
          <!-- Left Identity -->
          <div style="display:flex; gap:16px; align-items:flex-start; flex:1; min-width:300px;">
            <?php if (!empty($m['photo'])): ?>
              <div style="width:52px; height:52px; border-radius:10px; overflow:hidden; border:1px solid #CBD5E1; background:#F8FAFC; flex-shrink:0;">
                <img src="../<?php echo $photo; ?>" alt="<?php echo $name; ?>" style="width:100%; height:100%; object-fit:cover; object-position:center top;">
              </div>
            <?php else: ?>
              <div style="width:52px; height:52px; border-radius:10px; overflow:hidden; background:#E0F2FE; flex-shrink:0; border:1px solid #BAE6FD; display:flex; align-items:center; justify-content:center;">
                <span style="font-weight:800; font-size:18px; color:#0369A1;">
                  <?php echo strtoupper(substr($m['name'] ?? 'M', 0, 1)); ?>
                </span>
              </div>
            <?php endif; ?>

            <div style="flex:1;">
              <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span style="font-size:11px; font-weight:700; color:#64748B; background:#F1F5F9; padding:2px 7px; border-radius:4px;">#<?php echo $order; ?> &bull; <?php echo $id; ?></span>
                <h4 style="margin:0; font-size:16px; color:#0F172A; font-weight:700;"><?php echo $name; ?></h4>
                <?php if ($isArchived): ?>
                  <span class="badge" style="background:#FEF3C7; color:#92400E; padding:3px 9px; border-radius:12px; font-size:11px; font-weight:800; border:1px solid #FDE68A;">
                    🔒 Archived &bull; Hidden from Website
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:#DCFCE7; color:#15803D; padding:3px 9px; border-radius:12px; font-size:11px; font-weight:700; border:1px solid #BBF7D0;">
                    ✓ Live on Website
                  </span>
                <?php endif; ?>
                <span class="badge" style="background:#ECFDF5; color:#065F46; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                  Senior Management
                </span>
                <?php if (!empty($m['photo'])): ?>
                  <span class="badge" style="background:#DCFCE7; color:#15803D; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                    📷 Photo Attached
                  </span>
                <?php else: ?>
                  <span class="badge" style="background:#F1F5F9; color:#64748B; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                    📄 Text Listing
                  </span>
                <?php endif; ?>
              </div>
              <div style="font-weight:600; font-size:13px; color:#0E8F6C; margin:3px 0 6px;">
                <?php echo $des; ?>
              </div>
              <div style="font-size:12.5px; color:#475569; line-height:1.5; max-width:820px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                <?php echo $desc; ?>
              </div>
              <div style="margin-top:6px; font-size:11px; color:#64748B;">
                <span><strong>Sync Targets:</strong> about-us.html (Management Team) &bull; posh-policy.html</span>
              </div>
            </div>
          </div>

          <!-- Right Actions -->
          <div style="display:flex; align-items:center; gap:8px;">
            <!-- Move Up -->
            <form method="post" style="display:inline; margin:0;">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="reorder_management">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <input type="hidden" name="direction" value="up">
              <button type="submit" class="btn btn-ghost" style="padding:6px 9px;" title="Move Up" <?php echo ($idx === 0) ? 'disabled style="opacity:0.3;"' : ''; ?>>
                ▲
              </button>
            </form>

            <!-- Move Down -->
            <form method="post" style="display:inline; margin:0;">
              <?php echo staff_csrf_field(); ?>
              <input type="hidden" name="action" value="reorder_management">
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <input type="hidden" name="direction" value="down">
              <button type="submit" class="btn btn-ghost" style="padding:6px 9px;" title="Move Down" <?php echo ($idx === count($management) - 1) ? 'disabled style="opacity:0.3;"' : ''; ?>>
                ▼
              </button>
            </form>

            <!-- Archive / Restore Quick Action Button -->
            <?php if ($isArchived): ?>
              <form method="post" style="display:inline; margin:0;">
                <?php echo staff_csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_archive_management">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="status" value="active">
                <button type="submit" class="btn" style="background:#10B981; color:#FFFFFF; font-weight:700; padding:6px 12px; font-size:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.1);" title="Restore this executive and publish back to public website">
                  🔄 Restore to Website
                </button>
              </form>
            <?php else: ?>
              <button type="button" onclick="confirmArchiveMgmt('<?php echo $id; ?>', '<?php echo addslashes($name); ?>')" class="btn btn-ghost" style="color:#B45309; background:#FEF3C7; font-weight:700; padding:6px 12px; font-size:12px; border:1px solid #FDE68A; border-radius:6px;" title="Archive this executive and hide from website">
                🔒 Archive / Hide
              </button>
            <?php endif; ?>

            <!-- Delete Photo Quick Action if photo exists -->
            <?php if (!empty($m['photo'])): ?>
              <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('Remove portrait photo for &quot;<?php echo addslashes($name); ?>&quot;? This executive will immediately revert to clean text view on the public website.');">
                <?php echo staff_csrf_field(); ?>
                <input type="hidden" name="action" value="delete_management_photo">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <button type="submit" class="btn btn-ghost" style="color:#DC2626; background:#FEF2F2; border:1px solid #FECACA; font-weight:700; padding:6px 11px; font-size:12px; border-radius:6px;" title="Delete this executive's photo">
                  🗑️ Delete Photo
                </button>
              </form>
            <?php endif; ?>

            <!-- Edit Button -->
            <button type="button" onclick="openEditMgmtModal(<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-ghost" style="color:var(--sp-primary); font-weight:600; padding:6px 12px; font-size:12px;">
              ✏️ Edit / Photo
            </button>

            <!-- Delete Button -->
            <button type="button" onclick="confirmDeleteMgmt('<?php echo $id; ?>', '<?php echo addslashes($name); ?>')" class="btn btn-ghost" style="color:#DC2626; padding:6px 10px; font-size:12px;" title="Delete executive">
              🗑️
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- ================= ADD DIRECTOR MODAL ================= -->
<div id="addDirectorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:680px; box-shadow:0 20px 40px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto; padding:28px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
      <h3 style="margin:0; font-size:18px; color:#0F172A;">Add New Board Director</h3>
      <button type="button" onclick="document.getElementById('addDirectorModal').style.display='none';" class="btn btn-ghost" style="font-size:18px; padding:4px 8px;">✕</button>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="add_director">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Full Name *</label>
          <input type="text" name="name" required placeholder="e.g., Dr. A. V. Ramana Rao" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Title / Designation *</label>
          <input type="text" name="designation" required placeholder="e.g., Whole-Time Director" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Category *</label>
          <select name="category" onchange="updateCategoryLabel(this, 'add_category_label')" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
            <option value="wholetime">Whole-time Director</option>
            <option value="executive">Executive Director (CMD / Joint MD)</option>
            <option value="independent">Independent Director</option>
            <option value="women_director">Women Director</option>
            <option value="director">Director</option>
          </select>
          <input type="hidden" id="add_category_label" name="category_label" value="Whole-time Director">
        </div>
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Display Order</label>
          <input type="number" name="order" placeholder="<?php echo $countTotal + 1; ?>" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
      </div>

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Portrait Photo (Optional for Featured Cards)</label>
        <input type="file" name="photo_file" accept="image/*" style="width:100%; padding:8px 0; font-size:12.5px;">
        <small style="color:#64748B; font-size:11.5px; display:block;">If uploaded, this director will be displayed in the executive photo grid in <code>about-us.html</code>. If omitted, they appear in the clean list section.</small>
      </div>

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Professional Biography / Description *</label>
        <textarea name="description" rows="4" required placeholder="Write a detailed description of experience, qualifications, and domain contributions..." style="width:100%; padding:10px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px; font-family:inherit;"></textarea>
      </div>

      <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px;">
        <label style="font-weight:700; font-size:12.5px; color:#0F172A; display:block; margin-bottom:4px;">Website Publication Status *</label>
        <select name="status" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF;">
          <option value="active" selected>Active &bull; Visible on Website</option>
          <option value="archived">Archived / Hidden &bull; Hidden from Website</option>
        </select>
        <small style="color:#64748B; font-size:11.5px; display:block; margin-top:4px;">
          Archived members are safely preserved in the Staff Portal database but automatically excluded from all 4 synchronized public pages.
        </small>
      </div>

      <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px;">
        <div style="font-weight:700; font-size:12.5px; color:#0F172A; margin-bottom:8px;">Statutory Committee Flags</div>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
            <input type="checkbox" name="is_independent" value="1">
            Independent Director (Auto-syncs to <code>independent-directors.html</code>)
          </label>
          <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
            <input type="checkbox" name="is_csr_committee" value="1" onchange="toggleCsrRole('add_csr_role_wrap', this.checked)">
            CSR Committee Member (Auto-syncs to <code>csr-policy.html</code>)
          </label>
        </div>
        <div id="add_csr_role_wrap" style="display:none; margin-top:10px;">
          <label style="font-size:12px; font-weight:600; color:#334155;">CSR Committee Role</label>
          <input type="text" name="csr_role" placeholder="e.g., Chairman / Member" style="width:100%; padding:7px 10px; font-size:12.5px; border-radius:6px; border:1px solid #CBD5E1;">
        </div>
      </div>

      <div style="margin-top:24px; display:flex; justify-content:flex-end; gap:12px;">
        <button type="button" onclick="document.getElementById('addDirectorModal').style.display='none';" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700;">Save &amp; Sync Site</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= EDIT DIRECTOR MODAL ================= -->
<div id="editDirectorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:680px; box-shadow:0 20px 40px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto; padding:28px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
      <h3 style="margin:0; font-size:18px; color:#0F172A;">Edit Director &bull; <span id="edit_modal_id" style="color:#0E8F6C;"></span></h3>
      <button type="button" onclick="document.getElementById('editDirectorModal').style.display='none';" class="btn btn-ghost" style="font-size:18px; padding:4px 8px;">✕</button>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="edit_director">
      <input type="hidden" id="edit_id" name="id" value="">
      <input type="hidden" id="edit_photo_existing" name="photo_existing" value="">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Full Name *</label>
          <input type="text" id="edit_name" name="name" required style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Title / Designation *</label>
          <input type="text" id="edit_designation" name="designation" required style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Category *</label>
          <select id="edit_category" name="category" onchange="updateCategoryLabel(this, 'edit_category_label')" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
            <option value="wholetime">Whole-time Director</option>
            <option value="executive">Executive Director (CMD / Joint MD)</option>
            <option value="independent">Independent Director</option>
            <option value="women_director">Women Director</option>
            <option value="director">Director</option>
          </select>
          <input type="hidden" id="edit_category_label" name="category_label" value="">
        </div>
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Display Order</label>
          <input type="number" id="edit_order" name="order" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
      </div>

      <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px;">
        <label style="font-weight:700; font-size:12.5px; color:#0F172A; display:block; margin-bottom:8px;">Portrait Photo</label>
        <div style="display:flex; gap:16px; align-items:flex-start;">
          <!-- Preview Box with Status Badge -->
          <div style="position:relative; width:64px; height:64px; border-radius:10px; overflow:hidden; background:#F1F5F9; border:2px solid #CBD5E1; display:flex; align-items:center; justify-content:center; font-size:11px; color:#64748B; flex-shrink:0;">
            <div id="edit_photo_preview" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;"></div>
            <div id="edit_photo_removed_badge" style="display:none; position:absolute; inset:0; background:rgba(220,38,38,0.9); color:#FFFFFF; font-size:9.5px; font-weight:800; text-align:center; padding:4px; align-items:center; justify-content:center; line-height:1.2;">
              PHOTO<br>DELETED
            </div>
          </div>

          <!-- Upload and Action Buttons -->
          <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
              <input type="file" id="edit_photo_input" name="photo_file" accept="image/*" style="font-size:12.5px; max-width:240px;" onchange="handlePhotoFileSelected(this, 'edit')">
              
              <!-- Red Delete Photo Toggle Button -->
              <button type="button" id="edit_btn_delete_photo" onclick="toggleDeletePhoto('edit')" class="btn" style="background:#FEE2E2; color:#DC2626; border:1px solid #FCA5A5; font-size:12px; font-weight:700; padding:6px 12px; border-radius:6px; display:inline-flex; align-items:center; gap:5px; cursor:pointer;" title="Delete this portrait photo">
                🗑️ Delete Photo
              </button>

              <!-- Instant Delete Button -->
              <button type="button" id="edit_btn_delete_photo_now" onclick="deletePhotoNow('director')" class="btn" style="background:#EF4444; color:#FFFFFF; border:none; font-size:11.5px; font-weight:700; padding:6px 10px; border-radius:6px; display:none; align-items:center; gap:4px; cursor:pointer;" title="Permanently delete photo immediately without updating other fields">
                ⚡ Delete Photo Now
              </button>
            </div>

            <input type="hidden" id="edit_remove_photo" name="remove_photo" value="0">
            
            <div id="edit_photo_hint" style="font-size:11.5px; color:#64748B; line-height:1.4;">
              Upload a JPG, PNG or WEBP portrait (&lt; 5MB), or click <strong>Delete Photo</strong> to revert to a clean text list entry on the public website.
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Professional Biography / Description *</label>
        <textarea id="edit_description" name="description" rows="5" required style="width:100%; padding:10px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px; font-family:inherit;"></textarea>
      </div>

      <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px;">
        <div style="font-weight:700; font-size:12.5px; color:#0F172A; margin-bottom:8px;">Statutory Committee Flags</div>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
          <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
            <input type="checkbox" id="edit_is_independent" name="is_independent" value="1">
            Independent Director (Syncs to <code>independent-directors.html</code>)
          </label>
          <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; cursor:pointer;">
            <input type="checkbox" id="edit_is_csr" name="is_csr_committee" value="1" onchange="toggleCsrRole('edit_csr_role_wrap', this.checked)">
            CSR Committee Member (Syncs to <code>csr-policy.html</code>)
          </label>
        </div>
        <div id="edit_csr_role_wrap" style="display:none; margin-top:10px;">
          <label style="font-size:12px; font-weight:600; color:#334155;">CSR Committee Role</label>
          <input type="text" id="edit_csr_role" name="csr_role" placeholder="e.g., Chairman / Member" style="width:100%; padding:7px 10px; font-size:12.5px; border-radius:6px; border:1px solid #CBD5E1;">
        </div>
      </div>

      <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px;">
        <label style="font-weight:700; font-size:12.5px; color:#0F172A; display:block; margin-bottom:4px;">Website Publication Status *</label>
        <select id="edit_status" name="status" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF;">
          <option value="active">Active &bull; Visible on Website</option>
          <option value="archived">Archived / Hidden &bull; Hidden from Website</option>
        </select>
        <small style="color:#64748B; font-size:11.5px; display:block; margin-top:4px;">
          Switching to "Archived / Hidden" instantly hides this member from the public website across all pages without deleting their biography or records.
        </small>
      </div>

      <div style="margin-top:24px; display:flex; justify-content:flex-end; gap:12px;">
        <button type="button" onclick="document.getElementById('editDirectorModal').style.display='none';" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700;">Update &amp; Re-Sync Site</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= DELETE DIRECTOR CONFIRMATION MODAL ================= -->
<div id="deleteDirectorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:440px; box-shadow:0 20px 40px rgba(0,0,0,0.25); padding:24px;">
    <h3 style="margin-top:0; color:#DC2626; font-size:18px;">Confirm Director Deletion</h3>
    <p style="font-size:13.5px; color:#475569; line-height:1.5;">
      Are you sure you want to delete <strong id="delete_director_name" style="color:#0F172A;"></strong> from the Board of Directors?
    </p>
    <p style="font-size:12px; color:#64748B;">
      This action will automatically remove their portrait, profile, and statutory listing permanently from <code>directors.json</code> and all public website pages (<code>about-us.html</code>, <code>independent-directors.html</code>, <code>csr-policy.html</code>, <code>posh-policy.html</code>).
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

<!-- ================= ARCHIVE DIRECTOR CONFIRMATION MODAL ================= -->
<div id="archiveDirectorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:480px; box-shadow:0 20px 40px rgba(0,0,0,0.25); padding:26px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
      <div style="width:38px; height:38px; border-radius:50%; background:#FEF3C7; display:flex; align-items:center; justify-content:center; font-size:18px; color:#B45309;">
        🔒
      </div>
      <h3 style="margin:0; color:#B45309; font-size:18px;">Archive Board Member</h3>
    </div>
    <p style="font-size:13.5px; color:#334155; line-height:1.55; margin:0 0 12px;">
      Are you sure you want to archive <strong id="archive_director_name" style="color:#0F172A;"></strong> and hide their details from the website?
    </p>
    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:12px; margin-bottom:18px; font-size:12px; color:#92400E; line-height:1.5;">
      <strong>Website Impact:</strong> Their portrait, biography, and statutory listing will be immediately removed from <code>about-us.html</code>, <code>independent-directors.html</code>, <code>csr-policy.html</code>, and <code>posh-policy.html</code>.<br><br>
      <strong>Data Safety:</strong> All details remain intact in the Staff Portal database and can be restored back to the website at any time.
    </div>
    <form method="post" style="display:flex; justify-content:flex-end; gap:10px; margin:0;">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="toggle_archive_director">
      <input type="hidden" id="archive_director_id" name="id" value="">
      <input type="hidden" name="status" value="archived">
      <button type="button" onclick="document.getElementById('archiveDirectorModal').style.display='none';" class="btn btn-ghost">Cancel</button>
      <button type="submit" class="btn" style="background:#D97706; color:#FFFFFF; font-weight:700; padding:8px 18px;">Archive &amp; Hide</button>
    </form>
  </div>
</div>

<!-- ================= ADD MANAGEMENT MODAL ================= -->
<div id="addMgmtModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:640px; box-shadow:0 20px 40px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto; padding:28px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
      <h3 style="margin:0; font-size:18px; color:#0F172A;">Add Senior Management Executive</h3>
      <button type="button" onclick="document.getElementById('addMgmtModal').style.display='none';" class="btn btn-ghost" style="font-size:18px; padding:4px 8px;">✕</button>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="add_management">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Full Name *</label>
          <input type="text" name="name" required placeholder="e.g., A. V. S. S. S. S. V. Prasad Rao" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Designation / Title *</label>
          <input type="text" name="designation" required placeholder="e.g., Senior Vice President - Operations" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:14px;">
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Display Order</label>
          <input type="number" name="order" placeholder="<?php echo $countMgmtTotal + 1; ?>" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Portrait Photo (Optional)</label>
          <input type="file" name="mgmt_photo_file" accept="image/*" style="width:100%; padding:8px 0; font-size:12.5px;">
        </div>
      </div>

      <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px;">
        <label style="font-weight:700; font-size:12.5px; color:#0F172A; display:block; margin-bottom:4px;">Website Publication Status *</label>
        <select name="status" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF;">
          <option value="active" selected>Active &bull; Visible on Website</option>
          <option value="archived">Archived / Hidden &bull; Hidden from Website</option>
        </select>
        <small style="color:#64748B; font-size:11.5px; display:block; margin-top:4px;">
          Archived executives are safely kept in the Staff Portal database but excluded from public pages.
        </small>
      </div>

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Executive Biography / Role Description *</label>
        <textarea name="description" rows="5" required placeholder="Write a summary of responsibilities, operational scope, and career background..." style="width:100%; padding:10px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px; font-family:inherit;"></textarea>
      </div>

      <div style="margin-top:24px; display:flex; justify-content:flex-end; gap:12px;">
        <button type="button" onclick="document.getElementById('addMgmtModal').style.display='none';" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700;">Save &amp; Sync Site</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= EDIT MANAGEMENT MODAL ================= -->
<div id="editMgmtModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:640px; box-shadow:0 20px 40px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto; padding:28px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
      <h3 style="margin:0; font-size:18px; color:#0F172A;">Edit Executive &bull; <span id="edit_mgmt_modal_id" style="color:#0E8F6C;"></span></h3>
      <button type="button" onclick="document.getElementById('editMgmtModal').style.display='none';" class="btn btn-ghost" style="font-size:18px; padding:4px 8px;">✕</button>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="edit_management">
      <input type="hidden" id="edit_mgmt_id" name="id" value="">
      <input type="hidden" id="edit_mgmt_photo_existing" name="photo_existing" value="">

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Full Name *</label>
          <input type="text" id="edit_mgmt_name" name="name" required style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
        <div>
          <label style="font-weight:700; font-size:12.5px; color:#334155;">Designation / Title *</label>
          <input type="text" id="edit_mgmt_designation" name="designation" required style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
        </div>
      </div>

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Display Order</label>
        <input type="number" id="edit_mgmt_order" name="order" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
      </div>

      <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px;">
        <label style="font-weight:700; font-size:12.5px; color:#0F172A; display:block; margin-bottom:8px;">Portrait Photo</label>
        <div style="display:flex; gap:16px; align-items:flex-start;">
          <!-- Preview Box with Status Badge -->
          <div style="position:relative; width:64px; height:64px; border-radius:10px; overflow:hidden; background:#F1F5F9; border:2px solid #CBD5E1; display:flex; align-items:center; justify-content:center; font-size:11px; color:#64748B; flex-shrink:0;">
            <div id="edit_mgmt_photo_preview" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;"></div>
            <div id="edit_mgmt_photo_removed_badge" style="display:none; position:absolute; inset:0; background:rgba(220,38,38,0.9); color:#FFFFFF; font-size:9.5px; font-weight:800; text-align:center; padding:4px; align-items:center; justify-content:center; line-height:1.2;">
              PHOTO<br>DELETED
            </div>
          </div>

          <!-- Upload and Action Buttons -->
          <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
              <input type="file" id="edit_mgmt_photo_input" name="mgmt_photo_file" accept="image/*" style="font-size:12.5px; max-width:240px;" onchange="handlePhotoFileSelected(this, 'edit_mgmt')">
              
              <!-- Red Delete Photo Toggle Button -->
              <button type="button" id="edit_mgmt_btn_delete_photo" onclick="toggleDeletePhoto('edit_mgmt')" class="btn" style="background:#FEE2E2; color:#DC2626; border:1px solid #FCA5A5; font-size:12px; font-weight:700; padding:6px 12px; border-radius:6px; display:inline-flex; align-items:center; gap:5px; cursor:pointer;" title="Delete this portrait photo">
                🗑️ Delete Photo
              </button>

              <!-- Instant Delete Button -->
              <button type="button" id="edit_mgmt_btn_delete_photo_now" onclick="deletePhotoNow('management')" class="btn" style="background:#EF4444; color:#FFFFFF; border:none; font-size:11.5px; font-weight:700; padding:6px 10px; border-radius:6px; display:none; align-items:center; gap:4px; cursor:pointer;" title="Permanently delete photo immediately without updating other fields">
                ⚡ Delete Photo Now
              </button>
            </div>

            <input type="hidden" id="edit_mgmt_remove_photo" name="remove_photo" value="0">
            
            <div id="edit_mgmt_photo_hint" style="font-size:11.5px; color:#64748B; line-height:1.4;">
              Upload a JPG, PNG or WEBP portrait (&lt; 5MB), or click <strong>Delete Photo</strong> to revert to a clean text-only entry on the public website.
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top:16px; background:#F8FAFC; border:1px solid #E2E8F0; padding:14px; border-radius:8px;">
        <label style="font-weight:700; font-size:12.5px; color:#0F172A; display:block; margin-bottom:4px;">Website Publication Status *</label>
        <select id="edit_mgmt_status" name="status" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF;">
          <option value="active">Active &bull; Visible on Website</option>
          <option value="archived">Archived / Hidden &bull; Hidden from Website</option>
        </select>
        <small style="color:#64748B; font-size:11.5px; display:block; margin-top:4px;">
          Switching to "Archived / Hidden" instantly hides this executive from about-us.html and posh-policy.html without deleting their record.
        </small>
      </div>

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Executive Biography / Role Description *</label>
        <textarea id="edit_mgmt_description" name="description" rows="5" required style="width:100%; padding:10px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px; font-family:inherit;"></textarea>
      </div>

      <div style="margin-top:24px; display:flex; justify-content:flex-end; gap:12px;">
        <button type="button" onclick="document.getElementById('editMgmtModal').style.display='none';" class="btn btn-ghost">Cancel</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700;">Update &amp; Re-Sync Site</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= DELETE MANAGEMENT CONFIRMATION MODAL ================= -->
<div id="deleteMgmtModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:440px; box-shadow:0 20px 40px rgba(0,0,0,0.25); padding:24px;">
    <h3 style="margin-top:0; color:#DC2626; font-size:18px;">Confirm Executive Deletion</h3>
    <p style="font-size:13.5px; color:#475569; line-height:1.5;">
      Are you sure you want to remove <strong id="delete_mgmt_name" style="color:#0F172A;"></strong> from the Senior Management Team?
    </p>
    <p style="font-size:12px; color:#64748B;">
      This action will automatically remove their listing and biography permanently from <code>directors.json</code>, <code>about-us.html#management-team</code>, and <code>posh-policy.html</code>.
    </p>
    <form method="post" style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="delete_management">
      <input type="hidden" id="delete_mgmt_id" name="id" value="">
      <button type="button" onclick="document.getElementById('deleteMgmtModal').style.display='none';" class="btn btn-ghost">Cancel</button>
      <button type="submit" class="btn" style="background:#DC2626; color:#FFFFFF; font-weight:700;">Delete Executive</button>
    </form>
  </div>
</div>

<!-- ================= ARCHIVE MANAGEMENT CONFIRMATION MODAL ================= -->
<div id="archiveMgmtModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:480px; box-shadow:0 20px 40px rgba(0,0,0,0.25); padding:26px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
      <div style="width:38px; height:38px; border-radius:50%; background:#FEF3C7; display:flex; align-items:center; justify-content:center; font-size:18px; color:#B45309;">
        🔒
      </div>
      <h3 style="margin:0; color:#B45309; font-size:18px;">Archive Senior Executive</h3>
    </div>
    <p style="font-size:13.5px; color:#334155; line-height:1.55; margin:0 0 12px;">
      Are you sure you want to archive <strong id="archive_mgmt_name" style="color:#0F172A;"></strong> and hide them from the website?
    </p>
    <div style="background:#FFFBEB; border:1px solid #FDE68A; border-radius:8px; padding:12px; margin-bottom:18px; font-size:12px; color:#92400E; line-height:1.5;">
      <strong>Website Impact:</strong> Their profile will be removed from <code>about-us.html</code> (#management-team) and <code>posh-policy.html</code>.<br><br>
      <strong>Data Safety:</strong> Records remain preserved in the Staff Portal database and can be restored at any time.
    </div>
    <form method="post" style="display:flex; justify-content:flex-end; gap:10px; margin:0;">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="toggle_archive_management">
      <input type="hidden" id="archive_mgmt_id" name="id" value="">
      <input type="hidden" name="status" value="archived">
      <button type="button" onclick="document.getElementById('archiveMgmtModal').style.display='none';" class="btn btn-ghost">Cancel</button>
      <button type="submit" class="btn" style="background:#D97706; color:#FFFFFF; font-weight:700; padding:8px 18px;">Archive &amp; Hide</button>
    </form>
  </div>
</div>

<script>
let currentStatusFilter = 'all';
let currentCategoryFilter = 'all';

function filterStatus(status, btn) {
  document.querySelectorAll('.filter-pill-status').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentStatusFilter = status;
  applyDirFilters();
}

function filterCategory(cat, btn) {
  document.querySelectorAll('.filter-pill-cat').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentCategoryFilter = cat;
  applyDirFilters();
}

function filterSearch() {
  applyDirFilters();
}

function applyDirFilters() {
  const q = document.getElementById('dirSearch').value.toLowerCase().trim();
  const rows = document.querySelectorAll('.director-row');
  rows.forEach(r => {
    const status = r.getAttribute('data-status') || 'active';
    const cat = r.getAttribute('data-category') || '';
    const nm = r.getAttribute('data-name') || '';
    const tit = r.getAttribute('data-title') || '';
    const txt = r.innerText.toLowerCase();

    const matchesStatus = (currentStatusFilter === 'all') || (status === currentStatusFilter);
    let matchesCat = (currentCategoryFilter === 'all') || (cat === currentCategoryFilter);
    if (currentCategoryFilter === 'independent') {
      matchesCat = (cat === 'independent' || r.innerText.includes('Independent'));
    }
    const matchesSearch = !q || nm.includes(q) || tit.includes(q) || txt.includes(q);

    r.style.display = (matchesStatus && matchesCat && matchesSearch) ? 'flex' : 'none';
  });
}

let currentMgmtStatusFilter = 'all';
function filterMgmtStatus(status, btn) {
  document.querySelectorAll('.filter-pill-mgmt-status').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentMgmtStatusFilter = status;
  applyMgmtFilters();
}

function filterMgmtSearch() {
  applyMgmtFilters();
}

function applyMgmtFilters() {
  const q = document.getElementById('mgmtSearch').value.toLowerCase().trim();
  const rows = document.querySelectorAll('.mgmt-row');
  rows.forEach(r => {
    const status = r.getAttribute('data-status') || 'active';
    const nm = r.getAttribute('data-name') || '';
    const tit = r.getAttribute('data-title') || '';
    const txt = r.innerText.toLowerCase();

    const matchesStatus = (currentMgmtStatusFilter === 'all') || (status === currentMgmtStatusFilter);
    const matchesSearch = !q || nm.includes(q) || tit.includes(q) || txt.includes(q);

    r.style.display = (matchesStatus && matchesSearch) ? 'flex' : 'none';
  });
}

function updateCategoryLabel(selectEl, targetInputId) {
  const text = selectEl.options[selectEl.selectedIndex].text.replace(/\s*\(.*?\)\s*/g, '');
  document.getElementById(targetInputId).value = text;
}

function toggleCsrRole(wrapId, isChecked) {
  document.getElementById(wrapId).style.display = isChecked ? 'block' : 'none';
}

function toggleDeletePhoto(prefix) {
  const removeInput = document.getElementById(prefix + '_remove_photo');
  const badge = document.getElementById(prefix + '_photo_removed_badge');
  const btn = document.getElementById(prefix + '_btn_delete_photo');
  const btnNow = document.getElementById(prefix + '_btn_delete_photo_now');
  const fileInput = document.getElementById(prefix + '_photo_input');
  const hint = document.getElementById(prefix + '_photo_hint');

  if (removeInput.value === '1') {
    // Undo
    removeInput.value = '0';
    if (badge) badge.style.display = 'none';
    if (btn) {
      btn.innerHTML = '🗑️ Delete Photo';
      btn.style.background = '#FEE2E2';
      btn.style.color = '#DC2626';
      btn.style.borderColor = '#FCA5A5';
    }
    if (btnNow) btnNow.style.display = 'inline-flex';
    if (hint) hint.innerHTML = 'Upload a JPG, PNG or WEBP portrait (&lt; 5MB), or click <strong>Delete Photo</strong> to revert to text-only.';
  } else {
    // Mark for deletion
    removeInput.value = '1';
    if (fileInput) fileInput.value = '';
    if (badge) badge.style.display = 'flex';
    if (btn) {
      btn.innerHTML = '↺ Undo Removal';
      btn.style.background = '#F1F5F9';
      btn.style.color = '#475569';
      btn.style.borderColor = '#CBD5E1';
    }
    if (btnNow) btnNow.style.display = 'inline-flex';
    if (hint) hint.innerHTML = '<span style="color:#DC2626; font-weight:700;">⚠️ Photo marked for deletion.</span> Click <strong>Update &amp; Re-Sync Site</strong> below, or click <strong>⚡ Delete Photo Now</strong> to delete immediately.';
  }
}

function handlePhotoFileSelected(input, prefix) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const prev = document.getElementById(prefix + '_photo_preview');
      const badge = document.getElementById(prefix + '_photo_removed_badge');
      const btn = document.getElementById(prefix + '_btn_delete_photo');
      const btnNow = document.getElementById(prefix + '_btn_delete_photo_now');
      const removeInput = document.getElementById(prefix + '_remove_photo');
      const hint = document.getElementById(prefix + '_photo_hint');
      
      if (prev) prev.innerHTML = '<img src="' + e.target.result + '" style="width:100%; height:100%; object-fit:cover; object-position:center top;">';
      if (badge) badge.style.display = 'none';
      if (removeInput) removeInput.value = '0';
      if (btn) {
        btn.style.display = 'inline-flex';
        btn.innerHTML = '🗑️ Clear New Photo';
        btn.style.background = '#FEE2E2';
        btn.style.color = '#DC2626';
        btn.style.borderColor = '#FCA5A5';
      }
      if (btnNow) btnNow.style.display = 'none';
      if (hint) hint.innerHTML = '<span style="color:#0E8F6C; font-weight:700;">✓ New photo chosen.</span> Save to update and re-sync website.';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function deletePhotoNow(type) {
  const isDir = (type === 'director');
  const id = isDir ? document.getElementById('edit_id').value : document.getElementById('edit_mgmt_id').value;
  const name = isDir ? document.getElementById('edit_name').value : document.getElementById('edit_mgmt_name').value;
  if (!id) return;

  if (!confirm('Are you sure you want to permanently delete the portrait photo for "' + name + '" right now? The profile will immediately revert to clean text view and re-sync all website pages.')) {
    return;
  }

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'directors.php' + (isDir ? '' : '?tab=management');
  
  const csrfInput = document.createElement('input');
  csrfInput.type = 'hidden';
  csrfInput.name = 'csrf_token';
  const existingCsrf = document.querySelector('input[name="csrf_token"]');
  csrfInput.value = existingCsrf ? existingCsrf.value : '';
  form.appendChild(csrfInput);

  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = 'action';
  actionInput.value = isDir ? 'delete_director_photo' : 'delete_management_photo';
  form.appendChild(actionInput);

  const idInput = document.createElement('input');
  idInput.type = 'hidden';
  idInput.name = 'id';
  idInput.value = id;
  form.appendChild(idInput);

  document.body.appendChild(form);
  form.submit();
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
  if (document.getElementById('edit_status')) {
    document.getElementById('edit_status').value = d.status || 'active';
  }
  document.getElementById('edit_description').value = d.description || '';
  document.getElementById('edit_is_independent').checked = !!d.is_independent;
  document.getElementById('edit_is_csr').checked = !!d.is_csr_committee;
  document.getElementById('edit_csr_role').value = d.csr_role || '';
  toggleCsrRole('edit_csr_role_wrap', !!d.is_csr_committee);

  const removeInput = document.getElementById('edit_remove_photo');
  if (removeInput) removeInput.value = '0';
  const badge = document.getElementById('edit_photo_removed_badge');
  if (badge) badge.style.display = 'none';
  const btnDel = document.getElementById('edit_btn_delete_photo');
  const btnNow = document.getElementById('edit_btn_delete_photo_now');
  const fileInput = document.getElementById('edit_photo_input');
  if (fileInput) fileInput.value = '';
  const hint = document.getElementById('edit_photo_hint');
  if (hint) hint.innerHTML = 'Upload a JPG, PNG or WEBP portrait (&lt; 5MB), or click <strong>Delete Photo</strong> to revert to a clean text list entry on the public website.';

  const prev = document.getElementById('edit_photo_preview');
  if (d.photo) {
    prev.innerHTML = '<img src="../' + d.photo + '" style="width:100%; height:100%; object-fit:cover; object-position:center top;">';
    if (btnDel) {
      btnDel.style.display = 'inline-flex';
      btnDel.innerHTML = '🗑️ Delete Photo';
      btnDel.style.background = '#FEE2E2';
      btnDel.style.color = '#DC2626';
      btnDel.style.borderColor = '#FCA5A5';
    }
    if (btnNow) btnNow.style.display = 'inline-flex';
  } else {
    prev.innerHTML = '<span style="font-weight:800; font-size:16px; color:#94A3B8;">' + (d.name ? d.name.charAt(0).toUpperCase() : 'D') + '</span>';
    if (btnDel) btnDel.style.display = 'none';
    if (btnNow) btnNow.style.display = 'none';
  }

  document.getElementById('editDirectorModal').style.display = 'flex';
}

function confirmArchive(id, name) {
  document.getElementById('archive_director_id').value = id;
  document.getElementById('archive_director_name').innerText = name;
  document.getElementById('archiveDirectorModal').style.display = 'flex';
}

function confirmArchiveMgmt(id, name) {
  document.getElementById('archive_mgmt_id').value = id;
  document.getElementById('archive_mgmt_name').innerText = name;
  document.getElementById('archiveMgmtModal').style.display = 'flex';
}

function confirmDelete(id, name) {
  document.getElementById('delete_director_id').value = id;
  document.getElementById('delete_director_name').innerText = name;
  document.getElementById('deleteDirectorModal').style.display = 'flex';
}

function openEditMgmtModal(m) {
  document.getElementById('edit_mgmt_modal_id').innerText = m.id;
  document.getElementById('edit_mgmt_id').value = m.id;
  document.getElementById('edit_mgmt_name').value = m.name || '';
  document.getElementById('edit_mgmt_designation').value = m.designation || '';
  document.getElementById('edit_mgmt_order').value = m.order || 1;
  document.getElementById('edit_mgmt_photo_existing').value = m.photo || '';
  if (document.getElementById('edit_mgmt_status')) {
    document.getElementById('edit_mgmt_status').value = m.status || 'active';
  }
  document.getElementById('edit_mgmt_description').value = m.description || '';

  const removeInput = document.getElementById('edit_mgmt_remove_photo');
  if (removeInput) removeInput.value = '0';
  const badge = document.getElementById('edit_mgmt_photo_removed_badge');
  if (badge) badge.style.display = 'none';
  const btnDel = document.getElementById('edit_mgmt_btn_delete_photo');
  const btnNow = document.getElementById('edit_mgmt_btn_delete_photo_now');
  const fileInput = document.getElementById('edit_mgmt_photo_input');
  if (fileInput) fileInput.value = '';
  const hint = document.getElementById('edit_mgmt_photo_hint');
  if (hint) hint.innerHTML = 'Upload a JPG, PNG or WEBP portrait (&lt; 5MB), or click <strong>Delete Photo</strong> to revert to a clean text-only entry on the public website.';

  const prev = document.getElementById('edit_mgmt_photo_preview');
  if (m.photo) {
    prev.innerHTML = '<img src="../' + m.photo + '" style="width:100%; height:100%; object-fit:cover; object-position:center top;">';
    if (btnDel) {
      btnDel.style.display = 'inline-flex';
      btnDel.innerHTML = '🗑️ Delete Photo';
      btnDel.style.background = '#FEE2E2';
      btnDel.style.color = '#DC2626';
      btnDel.style.borderColor = '#FCA5A5';
    }
    if (btnNow) btnNow.style.display = 'inline-flex';
  } else {
    prev.innerHTML = '<span style="font-weight:800; font-size:16px; color:#94A3B8;">' + (m.name ? m.name.charAt(0).toUpperCase() : 'M') + '</span>';
    if (btnDel) btnDel.style.display = 'none';
    if (btnNow) btnNow.style.display = 'none';
  }

  document.getElementById('editMgmtModal').style.display = 'flex';
}

function confirmDeleteMgmt(id, name) {
  document.getElementById('delete_mgmt_id').value = id;
  document.getElementById('delete_mgmt_name').innerText = name;
  document.getElementById('deleteMgmtModal').style.display = 'flex';
}
</script>

<style>
.filter-pill-status.active, .filter-pill-cat.active, .filter-pill-mgmt-status.active {
  background: #0E8F6C !important;
  color: #FFFFFF !important;
  border-color: #0E8F6C !important;
  font-weight: 700 !important;
}
.director-row:hover, .mgmt-row:hover {
  background: #F8FAFC !important;
}
.director-row[data-status="archived"]:hover, .mgmt-row[data-status="archived"]:hover {
  background: #FEF9C3 !important;
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
