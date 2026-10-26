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

  // 1. Sync about-us.html (Board of Directors and Management Team)
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
        $html = preg_replace($aboutRegex, "${1}{$boardGridHtml}\n\n{$boardRestHtml}${2}", $html);
      }

      // Sync Management Team list
      $rawMgmt = $data['management'] ?? [];
      $mgmtList = array_values(array_filter($rawMgmt, function($m) {
        return ($m['status'] ?? 'active') === 'active';
      }));
      usort($mgmtList, function($a, $b) {
        return (intval($a['order'] ?? 999)) - (intval($b['order'] ?? 999));
      });

      $mgmtListHtml = "      <ul class=\"mgmt-list\">\n";
      foreach ($mgmtList as $m) {
        $nm = htmlspecialchars($m['name'] ?? '');
        $des = htmlspecialchars($m['designation'] ?? '');
        $desc = trim($m['description'] ?? '');
        $mgmtListHtml .= "        <li>\n";
        $mgmtListHtml .= "          <div><span class=\"mgmt-name\">{$nm}</span><span class=\"mgmt-role\">{$des}</span></div>\n";
        $mgmtListHtml .= "          <p>{$desc}</p>\n";
        $mgmtListHtml .= "        </li>\n";
      }
      $mgmtListHtml .= "      </ul>";

      $mgmtRegex = '/(<div class="mgmt-heading-block" id="management-team">[\s\S]*?<\/div>\s*)<ul class="mgmt-list">[\s\S]*?<\/ul>/';
      if (preg_match($mgmtRegex, $html)) {
        $html = preg_replace($mgmtRegex, "${1}{$mgmtListHtml}", $html);
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
        $idHtml = preg_replace($idRegex, "${1}{$idListHtml}", $idHtml);
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
        $nm = htmlspecialchars($d['name'] ?? '');
        $role = htmlspecialchars($d['csr_role'] ?? 'Member');
        $sno = $idx + 1;
        $csrListHtml .= "            <li style=\"padding: 8px 0; border-bottom: 1px solid #e8e8e8;\"><strong>{$sno}. {$nm}</strong> &ndash; {$role}</li>\n";
      }
      $csrListHtml .= "          </ul>";

      $csrRegex = '/(<h3 style="font-size: 18px; margin: 24px 0 12px; color: var\(--primary-dark\);">Members of the CSR Committee<\/h3>\s*)<ul style="list-style: none; padding: 0; margin: 0 0 28px;">[\s\S]*?<\/ul>/';
      if (preg_match($csrRegex, $csrHtml)) {
        $csrHtml = preg_replace($csrRegex, "${1}{$csrListHtml}", $csrHtml);
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
      $poshDirs = $list;
      $tbodyHtml = "<tbody>\n";
      $sno = 0;
      foreach ($poshDirs as $d) {
        $sno++;
        $bg = ($sno % 2 === 0) ? ' style="background: #fafafa;"' : '';
        $nm = htmlspecialchars($d['name'] ?? '');
        $des = htmlspecialchars($d['designation'] ?? '');
        $tbodyHtml .= "<tr{$bg}><td style=\"padding:8px 12px;border-bottom:1px solid #e8e8e8;\">{$sno}</td><td style=\"padding:8px 12px;border-bottom:1px solid #e8e8e8;\">{$nm}</td><td style=\"padding:8px 12px;border-bottom:1px solid #e8e8e8;\">{$des}</td></tr>\n";
      }
      $tbodyHtml .= "          </tbody>";

      $poshRegex = '/(<th style="padding:10px 12px;text-align:left;">DIRECTORS NAME<\/th>\s*<th style="padding:10px 12px;text-align:left;">TITLE \/ \(DESIGNATION\)<\/th>\s*<\/tr><\/thead>\s*)<tbody>[\s\S]*?<\/tbody>/';
      if (preg_match($poshRegex, $poshHtml)) {
        $poshHtml = preg_replace($poshRegex, "${1}{$tbodyHtml}", $poshHtml);
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
        staff_save_directors($directorsData);
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        $msg = 'Successfully added "' . htmlspecialchars($name) . '" to the Board of Directors and updated ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
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
        if (!empty($_POST['remove_photo'])) {
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

          staff_save_directors($directorsData);
          $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
          $msg = 'Successfully updated "' . htmlspecialchars($name) . '" and synchronized ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
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
      staff_save_directors($directorsData);
      $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
      $msg = 'Successfully removed "' . htmlspecialchars($deletedName) . '" from the Board of Directors and re-synced ' . count($synced) . ' website pages.';
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
        $msg = 'Board order updated and synchronized.';
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
        'description' => $description,
        'order'       => $order,
        'status'      => $status
      ];

      $directorsData['management'][] = $newMember;
      staff_save_directors($directorsData);
      $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
      $msg = 'Successfully added "' . htmlspecialchars($name) . '" to the Senior Management Team and updated ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
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
        $directorsData['management'][$foundIdx]['name']        = $name;
        $directorsData['management'][$foundIdx]['designation'] = $designation;
        $directorsData['management'][$foundIdx]['description'] = $description;
        $directorsData['management'][$foundIdx]['order']       = $order;
        $directorsData['management'][$foundIdx]['status']      = $status;

        staff_save_directors($directorsData);
        $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
        $msg = 'Successfully updated "' . htmlspecialchars($name) . '" and synchronized ' . count($synced) . ' website pages (' . implode(', ', $synced) . ').';
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
      staff_save_directors($directorsData);
      $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
      $msg = 'Successfully removed "' . htmlspecialchars($deletedName) . '" from Senior Management and updated public pages.';
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
        $msg = 'Senior Management order updated and synchronized.';
      }
    }
  }

  // 9. MANUAL FULL SYNC
  elseif ($action === 'sync_all') {
    $synced = staff_sync_directors_to_html($directorsData, $siteRoot);
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
$countExec = count(array_filter($directors, function($d) { return ($d['category'] ?? '') === 'executive'; }));
$countWhole = count(array_filter($directors, function($d) { return ($d['category'] ?? '') === 'wholetime'; }));
$countInd = count(array_filter($directors, function($d) { return !empty($d['is_independent']) || ($d['category'] ?? '') === 'independent'; }));
$countCsr = count(array_filter($directors, function($d) { return !empty($d['is_csr_committee']); }));

// Management statistics
$countMgmtTotal = count($management);
$countMgmtActive = count(array_filter($management, function($m) { return ($m['status'] ?? 'active') === 'active'; }));

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
        Centrally manage corporate leadership, board committees, executive designations, and biographies synchronized across the public website.
      </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
      <?php if ($currentTab === 'management'): ?>
        <button onclick="document.getElementById('addMgmtModal').style.display='flex';" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          + Add Management Member
        </button>
      <?php else: ?>
        <button onclick="document.getElementById('addModal').style.display='flex';" class="btn" style="background:#FFFFFF; color:var(--sp-primary-dark); font-weight:700; font-size:12.5px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          + Add Board Member
        </button>
      <?php endif; ?>
    </div>
  </div>
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
              <div style="font-size:12.5px; color:#475569; line-height:1.5; max-width:820px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                <?php echo $desc; ?>
              </div>
              <div style="margin-top:6px; display:flex; gap:12px; font-size:11px; color:#64748B;">
                <?php if ($isCsr): ?>
                  <span><strong>CSR Role:</strong> <?php echo $csrRole ?: 'Member'; ?></span>
                <?php endif; ?>
                <span><strong>Sync Target:</strong> about-us.html<?php echo $isInd ? ', independent-directors.html' : ''; ?><?php echo $isCsr ? ', csr-policy.html' : ''; ?>, posh-policy.html</span>
              </div>
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

            <!-- Edit Button -->
            <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-ghost" style="color:var(--sp-primary); font-weight:600; padding:6px 12px; font-size:12px;">
              ✏️ Edit
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
      <div class="stat-sub">Active in directors.json</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Public Status</div>
      <div class="stat-num" style="color:#0088AA;"><?php echo $countMgmtActive; ?></div>
      <div class="stat-sub">Live on about-us.html</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Public Anchor</div>
      <div class="stat-num" style="font-size:18px; line-height:36px; color:#475569;">#management-team</div>
      <div class="stat-sub">Direct link in About Us page</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Sync Status</div>
      <div class="stat-num" style="color:#10B981; font-size:20px; line-height:36px;">✓ 100% In Sync</div>
      <div class="stat-sub">Updates about-us.html</div>
    </div>
  </div>

  <!-- Search & Quick Action Bar -->
  <div class="card" style="padding:16px 20px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
      <div style="font-size:13.5px; color:#475569; font-weight:600;">
        Senior Management Directory &bull; Listed in executive priority order
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
      <span style="font-size:12px; color:#64748B;">Reordering or editing auto-syncs live <code>about-us.html</code></span>
    </div>

    <div id="mgmtContainer">
      <?php foreach ($management as $idx => $m): 
        $id = htmlspecialchars($m['id'] ?? '');
        $name = htmlspecialchars($m['name'] ?? '');
        $des = htmlspecialchars($m['designation'] ?? '');
        $desc = htmlspecialchars($m['description'] ?? '');
        $order = intval($m['order'] ?? ($idx + 1));
      ?>
        <div class="mgmt-row" data-name="<?php echo strtolower($name); ?>" data-title="<?php echo strtolower($des); ?>" style="padding:18px 24px; border-bottom:1px solid #F1F5F9; display:flex; gap:18px; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; transition:background 0.15s;">
          <!-- Left Identity -->
          <div style="display:flex; gap:16px; align-items:flex-start; flex:1; min-width:300px;">
            <div style="width:52px; height:52px; border-radius:10px; overflow:hidden; background:#E0F2FE; flex-shrink:0; border:1px solid #BAE6FD; display:flex; align-items:center; justify-content:center;">
              <span style="font-weight:800; font-size:18px; color:#0369A1;">
                <?php echo strtoupper(substr($m['name'] ?? 'M', 0, 1)); ?>
              </span>
            </div>
            <div style="flex:1;">
              <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span style="font-size:11px; font-weight:700; color:#64748B; background:#F1F5F9; padding:2px 7px; border-radius:4px;">#<?php echo $order; ?> &bull; <?php echo $id; ?></span>
                <h4 style="margin:0; font-size:16px; color:#0F172A; font-weight:700;"><?php echo $name; ?></h4>
                <span class="badge" style="background:#ECFDF5; color:#065F46; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                  Senior Management
                </span>
              </div>
              <div style="font-weight:600; font-size:13px; color:#0E8F6C; margin:3px 0 6px;">
                <?php echo $des; ?>
              </div>
              <div style="font-size:12.5px; color:#475569; line-height:1.5; max-width:820px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                <?php echo $desc; ?>
              </div>
              <div style="margin-top:6px; font-size:11px; color:#64748B;">
                <span><strong>Sync Target:</strong> about-us.html (Management Team List)</span>
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

            <!-- Edit Button -->
            <button type="button" onclick="openEditMgmtModal(<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8'); ?>)" class="btn btn-ghost" style="color:var(--sp-primary); font-weight:600; padding:6px 12px; font-size:12px;">
              ✏️ Edit
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
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Portrait Photo (Optional for Grid Cards)</label>
        <input type="file" name="photo_file" accept="image/*" style="width:100%; padding:8px 0; font-size:12.5px;">
        <small style="color:#64748B; font-size:11.5px; display:block;">If uploaded, this director will be displayed in the executive photo grid in <code>about-us.html</code>.</small>
      </div>

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Professional Biography / Description *</label>
        <textarea name="description" rows="4" required placeholder="Write a detailed description of experience, qualifications, and domain contributions..." style="width:100%; padding:10px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px; font-family:inherit;"></textarea>
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

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Portrait Photo</label>
        <div style="display:flex; gap:16px; align-items:center; margin-top:6px;">
          <div id="edit_photo_preview" style="width:50px; height:50px; border-radius:8px; overflow:hidden; background:#F1F5F9; border:1px solid #CBD5E1; display:flex; align-items:center; justify-content:center; font-size:11px; color:#64748B;"></div>
          <div style="flex:1;">
            <input type="file" name="photo_file" accept="image/*" style="font-size:12.5px;">
            <label style="display:block; margin-top:4px; font-size:11.5px; color:#64748B;">
              <input type="checkbox" name="remove_photo" value="1"> Remove current photo (revert to list view)
            </label>
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

<!-- ================= ADD MANAGEMENT MODAL ================= -->
<div id="addMgmtModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px; overflow-y:auto;">
  <div style="background:#FFFFFF; border-radius:14px; width:100%; max-width:640px; box-shadow:0 20px 40px rgba(0,0,0,0.25); max-height:90vh; overflow-y:auto; padding:28px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
      <h3 style="margin:0; font-size:18px; color:#0F172A;">Add Senior Management Executive</h3>
      <button type="button" onclick="document.getElementById('addMgmtModal').style.display='none';" class="btn btn-ghost" style="font-size:18px; padding:4px 8px;">✕</button>
    </div>

    <form method="post">
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

      <div style="margin-top:14px;">
        <label style="font-weight:700; font-size:12.5px; color:#334155;">Display Order</label>
        <input type="number" name="order" placeholder="<?php echo $countMgmtTotal + 1; ?>" style="width:100%; padding:9px 12px; font-size:13px; border-radius:6px; border:1px solid #CBD5E1; margin-top:4px;">
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

    <form method="post">
      <?php echo staff_csrf_field(); ?>
      <input type="hidden" name="action" value="edit_management">
      <input type="hidden" id="edit_mgmt_id" name="id" value="">

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
      This action will automatically remove their listing and biography from <code>about-us.html#management-team</code>.
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

function filterMgmtSearch() {
  const q = document.getElementById('mgmtSearch').value.toLowerCase().trim();
  const rows = document.querySelectorAll('.mgmt-row');
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
    prev.innerHTML = '<img src="../' + d.photo + '" style="width:100%; height:100%; object-fit:cover;">';
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

function openEditMgmtModal(m) {
  document.getElementById('edit_mgmt_modal_id').innerText = m.id;
  document.getElementById('edit_mgmt_id').value = m.id;
  document.getElementById('edit_mgmt_name').value = m.name || '';
  document.getElementById('edit_mgmt_designation').value = m.designation || '';
  document.getElementById('edit_mgmt_order').value = m.order || 1;
  document.getElementById('edit_mgmt_description').value = m.description || '';
  document.getElementById('editMgmtModal').style.display = 'flex';
}

function confirmDeleteMgmt(id, name) {
  document.getElementById('delete_mgmt_id').value = id;
  document.getElementById('delete_mgmt_name').innerText = name;
  document.getElementById('deleteMgmtModal').style.display = 'flex';
}
</script>

<style>
.filter-pill.active {
  background: #0E8F6C !important;
  color: #FFFFFF !important;
  border-color: #0E8F6C !important;
  font-weight: 700 !important;
}
.director-row:hover, .mgmt-row:hover {
  background: #F8FAFC !important;
}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
